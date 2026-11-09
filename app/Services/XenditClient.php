<?php

namespace App\Services;

use App\Exceptions\WalletException;
use Illuminate\Support\Facades\Http;

class XenditClient
{
    public function enabled(): bool
    {
        return ! empty(config('xendit.secret_key'));
    }

    public function createInvoice(array $payload): array
    {
        $response = $this->http()->post('/v2/invoices', $payload);
        if ($response->failed()) {
            report(new \RuntimeException('Xendit create invoice gagal: '.$response->body()));
            throw new WalletException('Gagal membuat tagihan pembayaran. Coba lagi nanti.');
        }

        return $response->json();
    }

    public function getInvoice(string $id): ?array
    {
        $response = $this->http()->get('/v2/invoices/'.urlencode($id));

        return $response->successful() ? $response->json() : null;
    }

    private function http()
    {
        if (! $this->enabled()) {
            throw new WalletException('Pembayaran otomatis belum dikonfigurasi.');
        }

        return Http::baseUrl(config('xendit.base_url'))
            ->withBasicAuth(config('xendit.secret_key'), '')
            ->acceptJson()
            ->timeout(20);
    }
}
