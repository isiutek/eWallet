<?php

namespace App\Http\Controllers;

use App\Exceptions\WalletException;
use App\Models\AuditLog;
use App\Models\PaymentRequest;
use App\Services\PinService;
use App\Services\QrService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QrController extends Controller
{
    /** Halaman "Terima": QR statis + form QR dinamis bernominal. */
    public function receive(Request $request, QrService $qr)
    {
        $wallet = $request->user()->wallet;
        $static = $qr->staticFor($wallet);
        $dynamic = null;
        if ($code = $request->query('code')) {
            $dynamic = PaymentRequest::where('wallet_id', $wallet->id)->where('code', $code)->first();
        }

        return view('qr.receive', [
            'staticSvg' => $qr->svg($qr->payload($static)),
            'staticPayload' => $qr->payload($static),
            'dynamic' => $dynamic,
            'dynamicSvg' => $dynamic ? $qr->svg($qr->payload($dynamic)) : null,
            'dynamicPayload' => $dynamic ? $qr->payload($dynamic) : null,
        ]);
    }

    public function createDynamic(Request $request, QrService $qr)
    {
        $data = $request->validate(['amount' => 'required|integer|min:1000', 'description' => 'nullable|string|max:100']);
        $pr = $qr->createDynamic($request->user()->wallet, (int) $data['amount'], $data['description'] ?? null);

        return redirect()->route('qr.receive', ['code' => $pr->code]);
    }

    /** Status QR dinamis (dipoll halaman Terima untuk tahu sudah dibayar). */
    public function status(Request $request, string $code)
    {
        $pr = PaymentRequest::where('wallet_id', $request->user()->wallet->id)->where('code', $code)->firstOrFail();

        return response()->json(['paid' => (bool) $pr->paid_at, 'reference' => $pr->paid_reference]);
    }

    public function scan()
    {
        return view('qr.scan');
    }

    /** Setelah QR dipindai: tampilkan konfirmasi penerima & nominal. */
    public function confirm(Request $request, QrService $qr)
    {
        $request->validate(['payload' => 'required|string|max:200']);
        try {
            $pr = $qr->resolve($request->input('payload'));
            if ($pr->wallet_id === $request->user()->wallet->id) {
                throw new WalletException('Tidak bisa membayar QR milik sendiri.');
            }
        } catch (WalletException $e) {
            return redirect()->route('qr.scan')->withErrors(['payload' => $e->getMessage()]);
        }

        return view('qr.confirm', ['pr' => $pr, 'payload' => $request->input('payload'), 'idempotency' => (string) Str::uuid()]);
    }

    public function pay(Request $request, QrService $qr, WalletService $wallets, PinService $pins)
    {
        $data = $request->validate([
            'payload' => 'required|string|max:200',
            'amount' => 'nullable|integer|min:1',
            'pin' => 'required|digits:6',
            'idempotency_key' => 'required|uuid',
        ]);
        $user = $request->user();
        $pins->verify($user, $data['pin']);

        $debit = DB::transaction(function () use ($qr, $wallets, $data, $user) {
            $pr = $qr->resolve($data['payload']);
            $pr = PaymentRequest::whereKey($pr->id)->lockForUpdate()->first(); // cegah QR sekali pakai dibayar dua kali
            if (! $pr->isUsable()) {
                throw new WalletException('QR Code sudah kedaluwarsa atau sudah dibayar.');
            }
            $amount = $pr->amount ?? (int) ($data['amount'] ?? 0);
            if ($amount <= 0) {
                throw new WalletException('Masukkan nominal pembayaran.');
            }

            [$debit] = $wallets->transfer($user->wallet, $pr->wallet, $amount, 'qr_payment', $pr->description,
                ['qr_code' => $pr->code], $data['idempotency_key']);

            if ($pr->single_use) {
                $pr->update(['paid_at' => now(), 'paid_reference' => $debit->reference]);
            }

            return $debit;
        });
        AuditLog::record('qr.paid', $user->id, ['reference' => $debit->reference, 'amount' => $debit->amount]);

        return redirect()->route('transactions.show', $debit)->with('status', 'Pembayaran QR berhasil.');
    }
}
