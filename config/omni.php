<?php

return [
    'api_url' => env('OMNI_API_URL', 'https://omnitest.slt.com.lk/api/AccountOMNI/BillPaymentRequestDirect'),
    'client_key' => env('OMNI_API_CLIENT_KEY', ''),
    'timeout' => (int) env('OMNI_API_TIMEOUT', 10),
    'verify_ssl' => env('OMNI_API_VERIFY_SSL', true),

    'refresh_interval_hours' => (int) env('OMNI_REFRESH_INTERVAL_HOURS', 24),
    'chunk_size' => (int) env('OMNI_CHUNK_SIZE', 50),
    'freshness_window_seconds' => (int) env('OMNI_FRESHNESS_WINDOW_SECONDS', 25),

    'batch_stale_after_seconds' => (int) env('OMNI_BATCH_STALE_AFTER_SECONDS', 86400),
];
