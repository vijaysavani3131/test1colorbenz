<?php

return [
    'baskrwado' => [
        'admin_api_key' => env('ADMIN_API_KEY'),
        'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    ],
    'whatsapp' => [
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v26.0'),
    ],
];
