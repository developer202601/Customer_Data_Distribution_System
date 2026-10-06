<?php

return [
    'api_url' => env('OMNI_API_URL', 'https://omnitest.slt.com.lk/api/AccountOMNI/BillPaymentRequestDirect'),
    'secret_code' => env('OMNI_API_SECRET_CODE', ''),
    'timeout' => (int) env('OMNI_API_TIMEOUT', 10),
];