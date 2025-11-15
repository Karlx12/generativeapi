<?php

return [
    'google_api_key' => env('GOOGLE_API_KEY', ''),
    // Use the generative language API by default for v1beta endpoints
    'google_base_url' => env('GOOGLE_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    // Gemini model name (can be overridden per-request via options)
    'google_model' => env('GEMINI_MODEL', ''),
];
