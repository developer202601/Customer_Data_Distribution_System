<?php

return [
    'api_url' => env('OMNI_API_URL', 'https://omnitest.slt.com.lk/api/AccountOMNI/BillPaymentRequestDirect'),
    'client_key' => env('OMNI_API_CLIENT_KEY', ''),
    'timeout' => (int) env('OMNI_API_TIMEOUT', 10),
    'verify_ssl' => env('OMNI_API_VERIFY_SSL', true),
];