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
        'maps_key' => env('GOOGLE_MAPS_KEY'),
    ],

    'eboekhouden' => [
        'api_url' => env('EBOEKHOUDEN_API_URL', 'https://api.e-boekhouden.nl'),
        'api_key' => env('EBOEKHOUDEN_API_KEY'),
        'app_code' => env('EBOEKHOUDEN_APP_CODE', 'Flitsmoment'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-3.1-flash-image'),
    ],

    'openai_images' => [
        'api_key' => env('OPENAI_API_KEY'),
        'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-2'),
    ],

    'image_generation' => [
        'default' => env('IMAGE_GENERATION_PROVIDER', 'gemini'),
    ],

];
