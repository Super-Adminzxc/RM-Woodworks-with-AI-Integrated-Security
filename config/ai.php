<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Security Assessment AI Provider
    |--------------------------------------------------------------------------
    |
    | AI analysis is optional. API keys stay on the backend and are never
    | exposed to Blade templates or frontend JavaScript.
    |
    */

    'provider' => env('AI_PROVIDER', 'gemini'),

    'gemini' => [
        'key' => env('GEMINI_API_KEY', env('AI_API_KEY')),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY', env('AI_API_KEY')),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

];
