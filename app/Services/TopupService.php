<?php

namespace App\Services;

use App\Exceptions\WalletException;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\Topup;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TopupService
{
    public function __construct(private WalletService $wallets, private XenditClient $xendit)
    {
    }

    /** Metode yang aktif sesuai pengaturan admin. */
    public function availableMethods(): array
    {
        $mode = Setting::get('topup_mode');
        $methods = [];
        if (in_array($mode, ['manual', 'both'], true)) {
            $methods[] = 'manual';
        }
        if (in_array($mode, ['xendit', 'both'], true) && $this->xendit->enabled()) {
            $methods[] = 'xendit';
        }

        return $methods;
    }

    public function validateAmount(int $amount): void
    {
        $min = (int) Setting::get('topup_min');
        $max = (int) Setting::get('topup_max');
        if ($amount < $min || $amount > $max) {
            throw new WalletException(sprintf('Nominal isi saldo harus antara Rp %s dan Rp %s.',
                number_format($min, 0, ',', '.'), number_format($max, 0, ',', '.')));
        }
    }

    private function assertMethod(string $method): void
    {
        if (! in_array($method, $this->availableMethods(), true)) {
            throw new WalletException('Metode isi saldo ini sedang tidak tersedia.');
        }
    }

    public function createManual(User $user, int $amount, ?UploadedFile $proof, ?string $senderName): Topup
    {
        $this->assertMethod('manual');
        $this->validateAmount($amount);
        $wallet = $this->wallets->createFor($user);

        $topup = Topup::create([
            'reference' => WalletService::newReference('TOP'),
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'method' => 'manual',
            'status' => 'pending',
            'sender_name' => $senderName,
            'proof_path' => $proof?->store('topup-proofs', 'local'), // disk privat, tidak publik
        ]);
        AuditLog::record('topup.manual.created', $user->id, ['reference' => $topup->reference, 'amount' => $amount]);

        return $topup;
    }

    public function createXendit(User $user, int $amount): Topup
    {
        $this->assertMethod('xendit');
        $this->validateAmount($amount);
        $wallet = $this->wallets->createFor($user);
        $reference = WalletService::newReference('TOP');

        $invoice = $this->xendit->createInvoice([
            'external_id' => $reference,
            'amount' => $amount,
            'payer_email' => $user->email,
            'description' => 'Isi saldo dompet '.$wallet->number,
            'invoice_duration' => config('xendit.invoice_duration'),
            'currency' => 'IDR',
            'success_redirect_url' => url('/topup?status=success'),
            'failure_redirect_url' => url('/topup?status=failed'),
        ]);

        $topup = Topup::create([
            'reference' => $reference,
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'method' => 'xendit',
            'status' => 'pending',
            'xendit_invoice_id' => $invoice['id'] ?? null,
            'xendit_invoice_url' => $invoice['invoice_url'] ?? null,
            'expires_at' => isset($invoice['expiry_date']) ? \Carbon\Carbon::parse($invoice['expiry_date']) : null,
        ]);
        AuditLog::record('topup.xendit.created', $user->id, ['reference' => $reference, 'amount' => $amount]);

        return $topup;
    }

    /** Admin menyetujui top up manual. */
    public function approve(Topup $topup, User $admin, ?string $note = null): Topup
    {
        if ($topup->method !== 'manual') {
            throw new WalletException('Isi saldo otomatis dikonfirmasi oleh Xendit, bukan admin.');
        }

        return $this->markPaid($topup, ['processed_by' => $admin->id, 'admin_note' => $note], 'topup.manual.approved', $admin->id);
    }

    public function reject(Topup $topup, User $admin, ?string $note = null): Topup
    {
        return DB::transaction(function () use ($topup, $admin, $note) {
            $topup = Topup::whereKey($topup->id)->lockForUpdate()->firstOrFail();
            if ($topup->status !== 'pending') {
                throw new WalletException('Permintaan isi saldo sudah diproses.');
            }
            $topup->update(['status' => 'rejected', 'processed_by' => $admin->id, 'admin_note' => $note]);
            AuditLog::record('topup.manual.rejected', $admin->id, ['reference' => $topup->reference]);

            return $topup;
        });
    }

    /**
     * Proses webhook invoice Xendit. Idempoten: webhook yang sama boleh datang berkali-kali.
     */
    public function handleXenditInvoiceCallback(array $payload): ?Topup
    {
        $topup = Topup::where('reference', $payload['external_id'] ?? '')->where('method', 'xendit')->first();
        if (! $topup) {
            return null;
        }
        if (($payload['id'] ?? null) !== $topup->xendit_invoice_id) {
            AuditLog::record('topup.xendit.mismatch', $topup->user_id, ['reference' => $topup->reference]);

            return null;
        }

        $status = strtoupper($payload['status'] ?? '');

        // Lapisan tambahan: konfirmasi status langsung ke API Xendit, jangan percaya payload saja.
        if (config('xendit.verify_with_api')) {
            $remote = $this->xendit->getInvoice($topup->xendit_invoice_id);
            if (! $remote) {
                throw new \RuntimeException('Tidak bisa memverifikasi invoice ke Xendit.');
            }
            $status = strtoupper($remote['status'] ?? '');
            $payload = array_merge($payload, array_intersect_key($remote, array_flip(['amount', 'paid_amount', 'payment_channel'])));
        }

        if (in_array($status, ['PAID', 'SETTLED'], true)) {
            $paid = (int) ($payload['paid_amount'] ?? $payload['amount'] ?? 0);
            if ($paid < $topup->amount) {
                AuditLog::record('topup.xendit.amount_mismatch', $topup->user_id, ['reference' => $topup->reference, 'paid' => $paid]);

                return $topup;
            }

            return $this->markPaid($topup, ['payment_channel' => $payload['payment_channel'] ?? null], 'topup.xendit.paid', null);
        }

        if ($status === 'EXPIRED' && $topup->status === 'pending') {
            $topup->update(['status' => 'expired']);
        }

        return $topup;
    }

    private function markPaid(Topup $topup, array $extra, string $auditAction, ?int $actorId): Topup
    {
        return DB::transaction(function () use ($topup, $extra, $auditAction, $actorId) {
            $topup = Topup::whereKey($topup->id)->lockForUpdate()->firstOrFail();
            if ($topup->status === 'paid') {
                return $topup; // sudah diproses (webhook ganda)
            }
            if ($topup->status !== 'pending') {
                throw new WalletException('Permintaan isi saldo sudah diproses.');
            }

            $this->wallets->credit($topup->wallet, $topup->amount, 'topup',
                'Isi saldo '.($topup->method === 'xendit' ? 'via Xendit'.(! empty($extra['payment_channel']) ? ' ('.$extra['payment_channel'].')' : '') : 'manual'),
                ['topup_reference' => $topup->reference], $topup->reference, 'topup:'.$topup->reference);

            $topup->update(array_merge($extra, ['status' => 'paid', 'paid_at' => now()]));
            AuditLog::record($auditAction, $actorId ?? $topup->user_id, ['reference' => $topup->reference, 'amount' => $topup->amount]);

            return $topup;
        });
    }
}
