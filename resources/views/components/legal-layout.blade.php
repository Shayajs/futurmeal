@props(['title'])

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} — FuturMeal</title>
    <x-fm.favicon />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex flex-col">
    <header class="fm-container py-8">
        <x-fm.logo href="/" />
    </header>

    <main class="flex-1 fm-container max-w-3xl pb-12">
        <h1 class="text-h2 font-semibold">{{ $title }}</h1>
        <p class="text-caption text-fm-muted mt-2">Dernière mise à jour : {{ now()->format('d/m/Y') }}</p>

        <article class="fm-panel mt-8 space-y-4 text-sm text-fm-muted leading-relaxed">
            {{ $slot }}
        </article>

        <p class="mt-8">
            <a href="/" class="text-sm text-fm-primary hover:underline">← Retour à l'accueil</a>
        </p>
    </main>

    <x-fm.site-footer />
</body>
</html>
