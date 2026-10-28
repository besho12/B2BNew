<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'b2b_portal' => [
        'base_url' => env('B2B_PORTAL_API_BASE_URL'),
        'token' => env('B2B_PORTAL_API_TOKEN'),
        'connect_timeout' => (int) env('B2B_PORTAL_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('B2B_PORTAL_TIMEOUT', 20),
        'demo_mode' => filter_var(env('B2B_PORTAL_DEMO_MODE', in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)), FILTER_VALIDATE_BOOLEAN),
        'accounts_endpoint' => env('B2B_PORTAL_ACCOUNTS_ENDPOINT', 'get_available_accounts'),
        'account_details_endpoint' => env('B2B_PORTAL_ACCOUNT_DETAILS_ENDPOINT', 'getMyAccountDetails'),
        'addresses_endpoint' => env('B2B_PORTAL_ADDRESSES_ENDPOINT', 'getMyAccountAddresses'),
        'products_endpoint' => env('B2B_PORTAL_PRODUCTS_ENDPOINT', 'get_products_list'),
        'single_product_endpoint' => env('B2B_PORTAL_SINGLE_PRODUCT_ENDPOINT', 'get_single_product'),
        'favorite_endpoint' => env('B2B_PORTAL_FAVORITE_ENDPOINT', 'addRemoveItemFavorite'),
        'orders_endpoint' => env('B2B_PORTAL_ORDERS_ENDPOINT', 'get_sales_orders'),
        'filters_endpoint' => env('B2B_PORTAL_FILTERS_ENDPOINT', 'get_filters_list'),
        'stock_analysis_endpoint' => env('B2B_PORTAL_STOCK_ANALYSIS_ENDPOINT', 'get_stock_analysis'),
    ],

    'paymob' => [
        'api_base' => env('PAYMOB_API_BASE', 'https://accept.paymob.com'),
        'checkout_base' => env('PAYMOB_CHECKOUT_BASE', 'https://accept.paymob.com/unifiedcheckout'),
        'secret_key' => env('PAYMOB_SECRET_KEY', env('PAYMOB_API_KEY')),
        'public_key' => env('PAYMOB_PUBLIC_KEY'),
        'hmac_secret' => env('PAYMOB_HMAC_SECRET', env('PAYMOB_HMAC')),
        'iframe_id' => env('PAYMOB_IFRAME_ID'),
        'payment_methods' => array_values(array_filter(array_map('intval', explode(',', (string) env('PAYMOB_PAYMENT_METHODS', env('PAYMOB_INTEGRATION_ID', '')))))),
    ],

];
