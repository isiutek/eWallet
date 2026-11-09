<?php

return [
    'secret_key' => env('XENDIT_SECRET_KEY'),
    // Token verifikasi webhook dari dashboard Xendit (Settings > Webhooks)
    'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
    'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
    'invoice_duration' => (int) env('XENDIT_INVOICE_DURATION', 86400),
    // Konfirmasi ulang status invoice ke API Xendit sebelum saldo ditambah
    'verify_with_api' => (bool) env('XENDIT_VERIFY_WITH_API', true),
];
