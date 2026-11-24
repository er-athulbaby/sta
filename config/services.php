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

    // "mrz" = passport-trained model (BSD-3, DoubangoTelecom/tesseractMRZ) shipped in resources/tessdata, as in the visa app.
    'tesseract' => ['binary' => env('TESSERACT_PATH', 'tesseract'), 'pdftoppm' => env('PDFTOPPM_PATH', 'pdftoppm'),
        'tessdata' => env('TESSDATA_DIR', resource_path('tessdata')), 'lang' => env('TESSERACT_LANG', 'mrz')],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
