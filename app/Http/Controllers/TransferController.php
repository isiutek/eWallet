<?php

namespace App\Http\Controllers;

use App\Exceptions\WalletException;
use App\Models\AuditLog;
use App\Models\Wallet;
use App\Services\PinService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TransferController extends Controller
{
    public function create(Request $request)
    {
        return view('transfer.create', ['idempotency' => (string) Str::uuid(), 'wallet' => $request->user()->wallet]);
    }

    /** Cek nama penerima sebelum konfirmasi (AJAX). */
    public function lookup(Request $request)
    {
        $request->validate(['to' => 'required|string']);
        $wallet = $this->findRecipient($request->input('to'));

        return response()->json(['name' => $wallet?->user->name, 'number' => $wallet?->number]);
    }

    public function store(Request $request, WalletService $wallets, PinService $pins)
    {
        $data = $request->validate([
            'to' => 'required|string|max:150',
            'amount' => 'required|integer|min:1',
            'note' => 'nullable|string|max:100',
            'pin' => 'required|digits:6',
            'idempotency_key' => 'required|uuid',
        ]);
        $user = $request->user();
        $pins->verify($user, $data['pin']);

        $to = $this->findRecipient($data['to']);
        if (! $to) {
            throw new WalletException('Penerima tidak ditemukan.');
        }

        [$debit] = $wallets->transfer($user->wallet, $to, (int) $data['amount'], 'transfer', $data['note'] ?? null, [], $data['idempotency_key']);
        AuditLog::record('transfer.sent', $user->id, ['reference' => $debit->reference, 'amount' => $debit->amount, 'to' => $to->number]);

        return redirect()->route('transactions.show', $debit)->with('status', 'Transfer berhasil.');
    }

    private function findRecipient(string $to): ?Wallet
    {
        $to = trim($to);

        return Wallet::with('user')
            ->where('number', $to)
            ->orWhereHas('user', fn ($q) => $q->where('email', strtolower($to))->orWhere('phone', $to))
            ->first();
    }
}
