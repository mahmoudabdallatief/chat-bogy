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

    'voice' => [
        'provider' => env('VOICE_PROVIDER', 'openai'),
        'openai_key' => env('OPENAI_API_KEY'),
        'openai_base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'transcription_model' => env('VOICE_TRANSCRIPTION_MODEL', 'gpt-4o-mini-transcribe'),
        'speech_model' => env('VOICE_SPEECH_MODEL', 'gpt-4o-mini-tts'),
        'default_wake_word' => env('VOICE_WAKE_WORD', 'hey boogy'),
    ],

];
