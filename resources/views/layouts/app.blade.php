<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'FuturMeal') }}</title>
        <x-fm.favicon />
        <x-fm.pwa-meta />
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body>
        <div class="min-h-screen flex flex-col">
            <livewire:layout.navigation />
            @if (isset($header))
                <header class="fm-divider">
                    <div class="fm-container py-4 sm:py-6 lg:py-8">
                        {{ $header }}
                    </div>
                </header>
            @endif
            <main class="flex-1 py-4 sm:py-6 lg:py-10">
                {{ $slot }}
            </main>
            <x-fm.site-footer show-pwa-install />
        </div>
        @livewireScripts
    </body>
</html>
