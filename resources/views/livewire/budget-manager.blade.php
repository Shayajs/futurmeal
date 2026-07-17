<div class="fm-container max-w-2xl space-y-6">
    <div>
        <h1 class="text-h2 font-semibold">Budget alimentaire</h1>
        <p class="text-sm text-fm-muted mt-1">Renseigne tes prix au kg pour estimer le coût de tes repas planifiés.</p>
    </div>

    @if (session('status'))
        <p class="text-sm text-fm-primary">{{ session('status') }}</p>
    @endif

    <dl class="fm-panel grid grid-cols-2 gap-4">
        <div>
            <dt class="text-caption text-fm-muted">Dépenses semaine</dt>
            <dd class="mt-1 text-xl font-medium tabular-nums">
                @if ($weekly['has_prices'])
                    {{ number_format($weekly['spent'], 2, ',', ' ') }} €
                @else
                    —
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-caption text-fm-muted">Repas estimés</dt>
            <dd class="mt-1 text-xl font-medium tabular-nums">{{ $weekly['priced_count'] }}/{{ $weekly['entry_count'] }}</dd>
        </div>
        @if ($weekly['has_prices'])
            <div>
                <dt class="text-caption text-fm-muted">Projection mois</dt>
                <dd class="mt-1 text-lg font-medium tabular-nums">{{ number_format($projections['month'], 2, ',', ' ') }} €</dd>
            </div>
            <div>
                <dt class="text-caption text-fm-muted">Projection année</dt>
                <dd class="mt-1 text-lg font-medium tabular-nums">{{ number_format($projections['year'], 2, ',', ' ') }} €</dd>
            </div>
        @endif
    </dl>

    <form wire:submit="saveTarget" class="fm-panel space-y-4">
        <h2 class="text-sm font-medium">Budget cible (optionnel)</h2>
        <p class="text-caption text-fm-muted">Enveloppe hebdomadaire pour comparer tes dépenses estimées sur le Dashboard.</p>
        <x-fm.number step="0.5" min="0" wire:model="weekly_budget_target" placeholder="Ex: 80 € / semaine" />
        @error('weekly_budget_target') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
        <button type="submit" class="fm-btn">Enregistrer la cible</button>
    </form>

    <div class="fm-panel space-y-4">
        <h2 class="text-sm font-medium">Magasin Open Prices</h2>
        <p class="text-caption text-fm-muted">
            Choisis ton magasin pour préremplir les prix des produits scannés (code-barres).
            Données via <a href="https://prices.openfoodfacts.org" target="_blank" rel="noopener" class="text-fm-primary hover:underline">Open Prices</a> — licence OdBL.
        </p>

        @if ($selectedStoreLabel)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-fm-bg border border-fm-border px-4 py-3">
                <div>
                    <p class="text-sm font-medium">{{ $selectedStoreLabel }}</p>
                    <p class="text-caption text-fm-muted">ID magasin : {{ $selectedStoreId }}</p>
                </div>
                <button type="button" wire:click="clearStore" class="fm-btn-ghost text-sm text-fm-accent">Retirer</button>
            </div>
        @endif

        <div class="grid sm:grid-cols-2 gap-3">
            <input
                wire:model.live.debounce.400ms="storeSearch"
                type="search"
                placeholder="Nom du magasin (ex: Carrefour, Lidl…)"
                class="fm-input"
            >
            <input
                wire:model.live.debounce.400ms="storeCity"
                type="search"
                placeholder="Ville (optionnel)"
                class="fm-input"
            >
        </div>

        @if (count($storeResults))
            <ul class="rounded-lg border border-fm-border divide-y divide-fm-border max-h-52 overflow-y-auto">
                @foreach ($storeResults as $store)
                    <li>
                        <button
                            type="button"
                            wire:click="selectStore({{ $store['id'] }}, @js($store['name'].($store['city'] ? ' · '.$store['city'] : '')))"
                            class="w-full text-left px-3 py-2.5 text-sm hover:bg-fm-surface min-h-touch"
                        >
                            <span class="font-medium">{{ $store['name'] }}</span>
                            @if ($store['city'])
                                <span class="text-fm-muted"> · {{ $store['city'] }}</span>
                            @endif
                            @if ($store['brand'])
                                <span class="block text-caption text-fm-muted">{{ $store['brand'] }}</span>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ul>
        @elseif (strlen($storeSearch) >= 2)
            <p class="text-xs text-fm-muted">Aucun magasin trouvé.</p>
        @endif
    </div>

    <form wire:submit="save" class="fm-panel space-y-4">
        <h2 class="text-sm font-medium">Ajouter un prix manuel</h2>
        <input wire:model="label" placeholder="Ingrédient (ex: Poulet blanc)" class="fm-input">
        @error('label') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
        <x-fm.number step="0.1" min="0" wire:model="price_per_kg" placeholder="Prix €/kg" />
        @error('price_per_kg') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
        <button type="submit" class="fm-btn-primary">Enregistrer</button>
    </form>

    @if ($entries->isNotEmpty())
        <ul class="fm-panel divide-y divide-fm-border">
            @foreach ($entries as $entry)
                <li class="flex flex-wrap justify-between items-center gap-2 py-3 first:pt-0 last:pb-0">
                    <span>{{ $entry->label }}</span>
                    <span class="flex items-center gap-4">
                        <span class="tabular-nums text-fm-primary">{{ number_format($entry->price_per_kg, 2, ',', ' ') }} €/kg</span>
                        @if ($entry->price_source?->value === 'open_prices')
                            <span class="text-caption text-fm-muted">Open Prices</span>
                        @endif
                        <button wire:click="delete({{ $entry->id }})" type="button" class="text-fm-accent text-sm min-h-touch">Supprimer</button>
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($contributions->isNotEmpty())
        <div class="fm-panel space-y-3">
            <h2 class="text-sm font-medium">Mes contributions communautaires</h2>
            <p class="text-caption text-fm-muted">Prix partagés par enseigne lors de l'ajout d'aliments au planificateur.</p>
            <ul class="divide-y divide-fm-border">
                @foreach ($contributions as $contribution)
                    <li class="flex flex-wrap justify-between items-center gap-2 py-3 first:pt-0 last:pb-0">
                        <div>
                            <span class="text-sm">{{ $contribution->label }}</span>
                            <span class="block text-caption text-fm-muted">{{ $contribution->store_brand }} · {{ $contribution->observed_at->format('d/m/Y') }}</span>
                        </div>
                        <span class="flex items-center gap-4">
                            <span class="tabular-nums text-fm-primary">{{ number_format($contribution->price_per_kg, 2, ',', ' ') }} €/kg</span>
                            <button wire:click="deleteContribution({{ $contribution->id }})" type="button" class="text-fm-accent text-sm min-h-touch">Supprimer</button>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="fm-panel space-y-2">
        <h2 class="text-sm font-medium">Enseignes courantes (France)</h2>
        <p class="text-caption text-fm-muted">Suggestions lors de la saisie d'un prix par enseigne dans le planificateur.</p>
        <p class="text-sm text-fm-muted">{{ implode(' · ', $storeBrands) }}</p>
    </div>

    <p class="text-caption text-fm-muted">
        <a href="{{ route('settings') }}" wire:navigate class="text-fm-primary hover:underline">← Paramètres</a>
    </p>
</div>
