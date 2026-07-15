<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="FuturMeal — Planification de repas, macros et programmes partagés.">
    <title>FuturMeal</title>
    <x-fm.favicon />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body>
    <header class="fm-container py-8 flex justify-between items-center">
        <x-fm.logo href="/" />
        <div class="flex items-center gap-6">
            @auth
                <a href="{{ route('dashboard') }}" class="fm-btn-primary">Ouvrir l'app</a>
            @else
                <a href="{{ route('login') }}" class="fm-btn-ghost">Connexion</a>
                <a href="{{ route('register') }}" class="fm-btn-primary">Créer un compte</a>
            @endauth
        </div>
    </header>

    <main>
        <section class="fm-container pb-20 lg:pb-28">
            <div class="grid lg:grid-cols-2 gap-16 lg:gap-20 items-start">
                <div class="max-w-lg pt-4 lg:pt-12">
                    <p class="fm-kicker">Nutrition · planification · partage</p>
                    <h1 class="mt-4 text-display font-bold text-fm-primary leading-[1.1]">
                        Prévois tes repas
                    </h1>
                    <p class="mt-5 text-h2 font-semibold text-fm-text leading-snug">
                        Optimise ta nutrition.<br>Atteins tes objectifs.
                    </p>
                    <p class="mt-6 text-body text-fm-muted leading-relaxed">
                        Compose tes recettes avec de vrais grammages, planifie la semaine,
                        et partage le même programme — sans tableur ni recalcul le soir.
                    </p>
                    <div class="mt-10 flex items-center gap-6">
                        <a href="{{ route('register') }}" class="fm-btn-primary-lg">Commencer</a>
                        <a href="{{ route('login') }}" class="fm-btn-link">Déjà inscrit</a>
                    </div>
                </div>

                <div class="fm-panel lg:mt-8">
                    <div class="flex justify-between items-baseline mb-6">
                        <div>
                            <p class="text-caption text-fm-muted">Aujourd'hui</p>
                            <p class="text-h3 mt-1 font-medium">Semaine 12</p>
                        </div>
                        <p class="text-sm tabular-nums"><span class="text-fm-muted">reste</span> <span class="text-fm-text font-medium">1 840 kcal</span></p>
                    </div>

                    <dl class="grid grid-cols-3 gap-4 pb-6 mb-6 border-b border-fm-border text-center">
                        <div>
                            <dt class="text-caption text-fm-muted">Objectif</dt>
                            <dd class="mt-1 text-lg font-medium tabular-nums">2 100</dd>
                        </div>
                        <div>
                            <dt class="text-caption text-fm-muted">Consommé</dt>
                            <dd class="mt-1 text-lg font-medium tabular-nums">260</dd>
                        </div>
                        <div>
                            <dt class="text-caption text-fm-muted">Budget</dt>
                            <dd class="mt-1 text-lg font-medium tabular-nums">38 €</dd>
                        </div>
                    </dl>

                    <div class="space-y-0">
                        <div class="fm-list-row">
                            <span class="text-fm-muted">12h30</span>
                            <span class="text-fm-text">Poulet coco, riz</span>
                            <span class="tabular-nums text-fm-muted">620 kcal</span>
                        </div>
                        <div class="fm-list-row">
                            <span class="text-fm-muted">16h00</span>
                            <span class="text-fm-text">Collation skyr</span>
                            <span class="tabular-nums text-fm-muted">180 kcal</span>
                        </div>
                        <div class="fm-list-row">
                            <span class="text-fm-muted">19h30</span>
                            <span class="text-fm-text">Saumon, brocoli</span>
                            <span class="tabular-nums text-fm-muted">540 kcal</span>
                        </div>
                    </div>

                    <p class="mt-6 pt-4 border-t border-fm-border text-caption text-fm-muted">
                        142 g protéines · 165 g glucides · 52 g lipides
                    </p>
                </div>
            </div>
        </section>

        <section class="fm-divider">
            <div class="fm-container fm-section">
                <div class="grid lg:grid-cols-2 gap-12 lg:gap-20">
                    <div>
                        <h2 class="text-h2 font-semibold">Ce que l'app fait</h2>
                        <p class="mt-3 text-fm-muted text-body leading-relaxed">
                            Pas de promesses marketing — des outils concrets pour manger mieux sur la durée.
                        </p>
                    </div>
                    <ul class="space-y-0 divide-y divide-fm-border">
                        @foreach ([
                            ['Planification', 'Organise tes repas sur plusieurs jours avec kcal et budget par jour.'],
                            ['Recettes CIQUAL', 'Saisis des grammages réels — les macros viennent de la base ANSES.'],
                            ['Suivi corps', 'Poids, graisse (Navy ou manuel), courbes sur 30 jours.'],
                            ['Programmes', 'Partage un menu hebdo avec ton partenaire ou ton groupe.'],
                        ] as [$title, $desc])
                            <li class="py-5 first:pt-0">
                                <p class="font-medium text-fm-text">{{ $title }}</p>
                                <p class="mt-1 text-sm text-fm-muted leading-relaxed">{{ $desc }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>

        <section class="fm-container pb-24">
            <div class="fm-panel flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                <div>
                    <p class="text-h3 font-medium">Prêt à planifier la semaine ?</p>
                    <p class="mt-1 text-sm text-fm-muted">Gratuit · Données CIQUAL · Sans carte bancaire</p>
                </div>
                <a href="{{ route('register') }}" class="fm-btn-primary shrink-0">Créer un compte</a>
            </div>
        </section>
    </main>

    <footer class="fm-divider py-8">
        <div class="fm-container flex flex-col sm:flex-row justify-between gap-4 text-caption text-fm-muted">
            <x-fm.logo size="sm" />
            <p>Composition nutritionnelle · Table CIQUAL © ANSES</p>
        </div>
    </footer>
</body>
</html>
