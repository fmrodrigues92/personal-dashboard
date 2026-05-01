<?php

return [
    'future_invoices' => [
        'cache_store' => env('BILLING_FUTURE_INVOICES_CACHE_STORE', 'redis'),
    ],
];
