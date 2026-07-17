<?php

return [
    'production_domain' => env('FUTURMEAL_PRODUCTION_DOMAIN', 'futurmeal.fr'),

    'off_user_agent' => env('OFF_USER_AGENT', 'FuturMeal/1.0 (dev@futurmeal.test)'),
    'off_search_cache_ttl' => (int) env('OFF_SEARCH_CACHE_TTL', 3600),
    'off_search_rate_limit' => (int) env('OFF_SEARCH_RATE_LIMIT', 30),

    'open_prices_base_url' => env('OPEN_PRICES_BASE_URL', 'https://prices.openfoodfacts.org'),
    'open_prices_cache_ttl' => (int) env('OPEN_PRICES_CACHE_TTL', 86400),

    'store_brands' => [
        'Carrefour',
        'Leclerc',
        'Auchan',
        'Intermarché',
        'Casino',
        'Monoprix',
        'Lidl',
        'Aldi',
        'Super U',
        'Franprix',
    ],

    'logsnag' => [
        'token' => env('LOGSNAG_TOKEN'),
        'project' => env('LOGSNAG_PROJECT', 'futurmeal'),
        'enabled' => env('LOGSNAG_ENABLED', false),
    ],

    'edamam' => [
        'app_id' => env('EDAMAM_APP_ID'),
        'app_key' => env('EDAMAM_APP_KEY'),
    ],

    /*
    | Facteurs énergétiques Atwater (kcal / g) pour dériver l’énergie des macros.
    */
    'macro_energy_factors' => [
        'protein' => 4,
        'carbs' => 4,
        'fat' => 9,
    ],

    'ai' => [
        'default_base_url' => env('FUTURMEAL_AI_DEFAULT_BASE_URL', 'https://api.openai.com/v1'),
        'default_model' => env('FUTURMEAL_AI_DEFAULT_MODEL', 'gpt-4o-mini'),
        'max_tokens' => (int) env('FUTURMEAL_AI_MAX_TOKENS', 8000),
        'timeout' => (int) env('FUTURMEAL_AI_TIMEOUT', 90),
        'default_quantity_g' => 150,
        'paste_max_bytes' => 200_000,
        'api_rate_limit_per_minute' => 5,
    ],

    'max_program_members' => 6,

    'meal_slots' => [
        'morning_snack' => 'Collation matinale',
        'breakfast' => 'Matin',
        'lunch' => 'Midi',
        'afternoon_snack' => 'Goûter',
        'dinner' => 'Soir',
        'night_snack' => 'Collation nocturne',
    ],

    /** Anciens clés encore présentes en base */
    'meal_slot_aliases' => [
        'snack' => 'afternoon_snack',
    ],
];
