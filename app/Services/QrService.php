<?php

namespace App\Services;

use App\Exceptions\WalletException;
use App\Models\PaymentRequest;
use App\Models\Wallet;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

/**
 * Payload QR: "EWPAY1.<code>.<signature>"
 * Signature HMAC-SHA256 (APP_KEY) mencegah QR palsu / dimanipulasi.
 */
class QrService
{
    private const PREFIX = 'EWPAY1';

    /** QR statis milik dompet (nominal diisi pembayar). Dibuat sekali, dipakai ulang. */
    public function staticFor(Wallet $wallet): PaymentRequest
    {
        return PaymentRequest::firstOrCreate(
            ['wallet_id' => $wallet->id, 'amount' => null, 'single_use' => false, 'expires_at' => null],
            ['code' => $this->newCode(), 'description' => 'QR statis']
        );
    }

    /** QR dinamis dengan nominal tetap, sekali pakai, berlaku terbatas. */
    public function createDynamic(Wallet $wallet, int $amount, ?string $description, int $ttlMinutes = 15): PaymentRequest
    {
        if ($amount <= 0) {
            throw new WalletException('Nominal harus lebih dari nol.');
        }

        return PaymentRequest::create([
            'code' => $this->newCode(),
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'description' => $description,
            'single_use' => true,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);
    }

    public function payload(PaymentRequest $request): string
    {
        return self::PREFIX.'.'.$request->code.'.'.$this->sign($request->code);
    }

    /** @throws WalletException bila QR tidak valid */
    public function resolve(string $payload): PaymentRequest
    {
        $parts = explode('.', trim($payload));
        if (count($parts) !== 3 || $parts[0] !== self::PREFIX || ! hash_equals($this->sign($parts[1]), $parts[2])) {
            throw new WalletException('QR Code tidak valid.');
        }
        $request = PaymentRequest::with('wallet.user')->where('code', $parts[1])->first();
        if (! $request) {
            throw new WalletException('QR Code tidak ditemukan.');
        }
        if (! $request->isUsable()) {
            throw new WalletException('QR Code sudah kedaluwarsa atau sudah dibayar.');
        }

        return $request;
    }

    public function svg(string $payload, int $size = 280): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd()));

        return $writer->writeString($payload);
    }

    private function sign(string $code): string
    {
        return substr(hash_hmac('sha256', $code, config('app.key')), 0, 24);
    }

    private function newCode(): string
    {
        return Str::lower(Str::random(20));
    }
}
