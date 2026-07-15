<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'FuturMeal') }}</title>
        <x-fm.favicon />
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body>
        <div class="min-h-screen flex flex-col">
            <div class="fm-container pt-10 pb-6">
                <x-fm.logo href="/" wire:navigate />
            </div>
            <main class="flex-1 flex items-center justify-center px-6 pb-16">
                <div class="w-full max-w-md">
                    {{ $slot }}
                </div>
            </main>
            <x-fm.site-footer />
        </div>
        @livewireScripts
    </body>
</html>
