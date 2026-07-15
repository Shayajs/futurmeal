<?php

return [
    'off_user_agent' => env('OFF_USER_AGENT', 'FuturMeal/1.0 (dev@futurmeal.test)'),

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
