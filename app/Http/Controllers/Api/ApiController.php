<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WalletException;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PaymentRequest;
use App\Models\Topup;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\AuthService;
use App\Services\PinService;
use App\Services\QrService;
use App\Services\ReportService;
use App\Services\TopupService;
use App\Services\TwoFactorService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** REST API untuk aplikasi mobile / integrasi. Autentikasi: Bearer token Sanctum. */
class ApiController extends Controller
{
    public function login(Request $request, AuthService $auth, TwoFactorService $tfa)
    {
        $data = $request->validate([
            'email' => 'required|email', 'password' => 'required|string',
            'otp' => 'nullable|string', 'device_name' => 'required|string|max:100',
        ]);
        $user = $auth->attempt($data['email'], $data['password']);

        if ($user->hasTwoFactor() && ! $tfa->verify($user->two_factor_secret, $data['otp'] ?? null)) {
            AuditLog::record('api.2fa.failed', $user->id);
            throw ValidationException::withMessages(['otp' => 'Kode 2FA wajib diisi dan harus benar.']);
        }
        AuditLog::record('api.login', $user->id, ['device' => $data['device_name']]);

        return ['token' => $user->createToken($data['device_name'], ['*'], now()->addDays(30))->plainTextToken];
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return ['ok' => true];
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('wallet');

        return [
            'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone,
            'has_pin' => $user->hasPin(), 'two_factor' => $user->hasTwoFactor(),
            'wallet' => ['number' => $user->wallet->number, 'balance' => $user->wallet->balance, 'currency' => 'IDR'],
        ];
    }

    public function transactions(Request $request, ReportService $reports)
    {
        $filters = array_filter($request->validate([
            'from' => 'nullable|date', 'to' => 'nullable|date',
            'type' => 'nullable|in:topup,transfer,qr_payment,adjustment', 'direction' => 'nullable|in:credit,debit',
        ]));
        $query = $reports->query($request->user()->wallet->id, $filters);

        return [
            'summary' => $reports->summary($query),
            'data' => $query->paginate(50)->through(fn (Transaction $t) => $this->trx($t)),
        ];
    }

    public function transfer(Request $request, WalletService $wallets, PinService $pins)
    {
        $data = $request->validate([
            'to' => 'required|string', 'amount' => 'required|integer|min:1',
            'note' => 'nullable|string|max:100', 'pin' => 'required|digits:6',
        ]);
        $key = $this->idempotencyKey($request);
        $user = $request->user();
        $pins->verify($user, $data['pin']);

        $to = Wallet::where('number', $data['to'])->first();
        if (! $to) {
            throw new WalletException('Nomor dompet penerima tidak ditemukan.');
        }
        [$debit] = $wallets->transfer($user->wallet, $to, (int) $data['amount'], 'transfer', $data['note'] ?? null, [], $key);
        AuditLog::record('api.transfer.sent', $user->id, ['reference' => $debit->reference]);

        return response()->json($this->trx($debit->load('counterparty.user')), 201);
    }

    public function topups(Request $request)
    {
        return Topup::where('user_id', $request->user()->id)->latest('id')
            ->paginate(20, ['reference', 'amount', 'method', 'status', 'xendit_invoice_url', 'paid_at', 'created_at']);
    }

    public function createTopup(Request $request, TopupService $topups)
    {
        $data = $request->validate([
            'method' => 'required|in:manual,xendit', 'amount' => 'required|integer|min:1',
            'sender_name' => 'required_if:method,manual|nullable|string|max:100',
            'proof' => 'required_if:method,manual|nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);
        $topup = $data['method'] === 'xendit'
            ? $topups->createXendit($request->user(), (int) $data['amount'])
            : $topups->createManual($request->user(), (int) $data['amount'], $request->file('proof'), $data['sender_name'] ?? null);

        return response()->json($topup->only(['reference', 'amount', 'method', 'status', 'xendit_invoice_url', 'expires_at']), 201);
    }

    public function qrStatic(Request $request, QrService $qr)
    {
        return ['payload' => $qr->payload($qr->staticFor($request->user()->wallet))];
    }

    public function qrDynamic(Request $request, QrService $qr)
    {
        $data = $request->validate(['amount' => 'required|integer|min:1000', 'description' => 'nullable|string|max:100']);
        $pr = $qr->createDynamic($request->user()->wallet, (int) $data['amount'], $data['description'] ?? null);

        return response()->json(['payload' => $qr->payload($pr), 'code' => $pr->code, 'amount' => $pr->amount, 'expires_at' => $pr->expires_at], 201);
    }

    public function qrResolve(Request $request, QrService $qr)
    {
        $pr = $qr->resolve($request->validate(['payload' => 'required|string|max:200'])['payload']);

        return [
            'merchant_name' => $pr->wallet->user->name, 'wallet_number' => $pr->wallet->number,
            'amount' => $pr->amount, 'description' => $pr->description, 'expires_at' => $pr->expires_at,
        ];
    }

    public function qrPay(Request $request, QrService $qr, WalletService $wallets, PinService $pins)
    {
        $data = $request->validate(['payload' => 'required|string|max:200', 'amount' => 'nullable|integer|min:1', 'pin' => 'required|digits:6']);
        $key = $this->idempotencyKey($request);
        $user = $request->user();
        $pins->verify($user, $data['pin']);

        $debit = DB::transaction(function () use ($qr, $wallets, $data, $user, $key) {
            $pr = PaymentRequest::whereKey($qr->resolve($data['payload'])->id)->lockForUpdate()->first();
            if (! $pr->isUsable()) {
                throw new WalletException('QR Code sudah kedaluwarsa atau sudah dibayar.');
            }
            if ($pr->wallet_id === $user->wallet->id) {
                throw new WalletException('Tidak bisa membayar QR milik sendiri.');
            }
            $amount = $pr->amount ?? (int) ($data['amount'] ?? 0);
            [$debit] = $wallets->transfer($user->wallet, $pr->wallet, $amount, 'qr_payment', $pr->description, ['qr_code' => $pr->code], $key);
            if ($pr->single_use) {
                $pr->update(['paid_at' => now(), 'paid_reference' => $debit->reference]);
            }

            return $debit;
        });
        AuditLog::record('api.qr.paid', $user->id, ['reference' => $debit->reference]);

        return response()->json($this->trx($debit->load('counterparty.user')), 201);
    }

    private function idempotencyKey(Request $request): string
    {
        $key = (string) $request->header('Idempotency-Key');
        if (! preg_match('/^[A-Za-z0-9\-_]{8,100}$/', $key)) {
            throw ValidationException::withMessages(['Idempotency-Key' => 'Header Idempotency-Key wajib (8-100 karakter).']);
        }

        return $key;
    }

    private function trx(Transaction $t): array
    {
        return [
            'reference' => $t->reference, 'type' => $t->type, 'direction' => $t->direction,
            'amount' => $t->amount, 'balance_after' => $t->balance_after, 'description' => $t->description,
            'counterparty' => $t->counterparty ? ['name' => $t->counterparty->user->name, 'wallet_number' => $t->counterparty->number] : null,
            'status' => $t->status, 'created_at' => $t->created_at,
        ];
    }
}
