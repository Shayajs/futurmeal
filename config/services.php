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

    'brightshield' => [
        'client_id' => env('BRIGHTSHIELD_CLIENT_ID'),
        'client_secret' => env('BRIGHTSHIELD_CLIENT_SECRET'),
        'redirect' => env('BRIGHTSHIELD_REDIRECT_URI'),
        /** URL publique BrightShield (navigateur) — authorize */
        'base_url' => rtrim(env('BRIGHTSHIELD_BASE_URL', 'https://shield.brightshell.fr'), '/'),
        /**
         * URL interne pour les appels PHP → token/userinfo (Docker).
         * Ex. http://host.docker.internal si shield.* résout vers 127.0.0.1 dans le conteneur.
         * Vide = même URL que base_url.
         */
        'api_base_url' => rtrim((string) env('BRIGHTSHIELD_API_BASE_URL', ''), '/'),
        /** Host HTTP forcé si api_base_url pointe vers NPM/gateway (vide = déduit de base_url) */
        'api_host_header' => env('BRIGHTSHIELD_API_HOST_HEADER'),
        /** Scopes demandés à BrightShield (openid profile email phone roles account) */
        'scopes' => array_values(array_filter(explode(' ', env('BRIGHTSHIELD_SCOPES', 'openid profile email')))),
        /**
         * Mode UX côté client BrightShield :
         * - redirect : redirection web (défaut Futurmeal)
         * - popup : fermeture de fenêtre + postMessage (autres apps)
         */
        'ux_mode' => env('BRIGHTSHIELD_UX_MODE', 'redirect'),
        /**
         * Icône affichée sur l’écran de consentement BrightShield (BrightShell × App).
         * Envoyée en query ?app_icon= sur /oauth/authorize.
         */
        'app_icon' => env('BRIGHTSHIELD_APP_ICON', rtrim((string) env('APP_URL', 'https://futurmeal.test'), '/').'/apple-touch-icon.png'),
    ],

];
