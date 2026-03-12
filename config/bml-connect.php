<?php

return [
    /*
     * The environment to use: 'sandbox' or 'production'.
     */
    'mode' => env('BML_CONNECT_MODE', 'sandbox'),

    /*
     * Your BML Connect API Key.
     */
    'api_key' => env('BML_CONNECT_API_KEY'),

    /*
     * Your BML Connect Application ID.
     */
    'app_id' => env('BML_CONNECT_APP_ID'),

    /*
     * API Endpoints for BML Connect.
     */
    'endpoints' => [
        'sandbox' => 'https://api.uat.merchants.bankofmaldives.com.mv/public/',
        'production' => 'https://api.merchants.bankofmaldives.com.mv/public/',
    ],

    /*
     * HTTP Client options.
     */
    'timeout' => 30,

    'retry' => [
        'times' => 3,
        'sleep' => 100,
    ],
];
