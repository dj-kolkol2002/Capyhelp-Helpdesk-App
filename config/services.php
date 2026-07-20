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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/auth/facebook/callback'),
    ],

    'social_auth' => [
        'allowed_domains' => array_values(array_filter(array_map('trim', explode(',', env('SOCIAL_AUTH_ALLOWED_DOMAINS', ''))))),
        'allowed_emails' => array_values(array_filter(array_map('trim', explode(',', env('SOCIAL_AUTH_ALLOWED_EMAILS', ''))))),
    ],

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://ollama:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2:3b'),
        'fallback_model' => env('OLLAMA_FALLBACK_MODEL', 'llama3.2:3b'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 180),
        'temperature' => (float) env('OLLAMA_TEMPERATURE', 0.0),
        'top_p' => (float) env('OLLAMA_TOP_P', 0.2),
        'top_k' => (int) env('OLLAMA_TOP_K', 10),
        'repeat_penalty' => (float) env('OLLAMA_REPEAT_PENALTY', 1.2),
        'num_ctx' => (int) env('OLLAMA_NUM_CTX', 2048),
        'num_predict' => (int) env('OLLAMA_NUM_PREDICT', 450),
        'seed' => (int) env('OLLAMA_SEED', 42),
    ],

    'clamav' => [
        'enabled' => env('CLAMAV_ENABLED', false),
        'host' => env('CLAMAV_HOST', 'clamav'),
        'port' => env('CLAMAV_PORT', 3310),
        'timeout' => env('CLAMAV_TIMEOUT', 30),
    ],

];
