<div class="max-w-4xl mx-auto px-4 grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="fm-card space-y-4">
        <h2 class="font-semibold">Ensemble d'aliments</h2>
        <p class="text-xs text-fm-muted">Un ensemble regroupe des aliments qui vont toujours ensemble — réutilisable dans le planificateur.</p>
        <input wire:model="name" placeholder="Nom du plat" class="fm-input">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model.live="is_macro_preset"> Preset macros (sans ingrédients)
        </label>
        @if ($is_macro_preset)
            <div class="grid grid-cols-2 gap-3">
                <x-fm.number min="0" wire:model.live="preset_energy_kcal" placeholder="Kcal" />
                <x-fm.number min="0" wire:model.live="preset_protein_g" placeholder="Protéines g" />
                <x-fm.number min="0" wire:model.live="preset_carbs_g" placeholder="Glucides g" />
                <x-fm.number min="0" wire:model.live="preset_fat_g" placeholder="Lipides g" />
            </div>
        @else
            <div>
                <input wire:model.live.debounce.300ms="search" placeholder="Rechercher un ingrédient ou code-barres…" class="fm-input">
                @if (count($searchResults))
                    <ul class="mt-2 rounded-xl border border-fm-border divide-y divide-fm-border">
                        @foreach ($searchResults as $result)
                            <li>
                                <button type="button" wire:click="addIngredient('{{ $result['type'] }}', {{ $result['id'] }}, @js($result['label']))" class="w-full text-left px-3 py-2 text-sm hover:bg-fm-surface min-h-touch">
                                    {{ $result['label'] }}
                                    @if (! empty($result['barcode']))
                                        <span class="block text-caption text-fm-muted">{{ $result['barcode'] }}</span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @elseif ($canCreateCustomFood && strlen($search) >= 2 && ! $showCustomFoodPanel)
                    <div class="mt-2 rounded-lg border border-fm-border bg-fm-surface p-3 space-y-2">
                        @if ($barcodeNotFound)
                            <p class="text-sm text-fm-muted">Code-barres inconnu dans Open Food Facts.</p>
                        @else
                            <p class="text-sm text-fm-muted">Aucun ingrédient trouvé pour « {{ $search }} ».</p>
                        @endif
                        <button type="button" wire:click="openCustomFoodPanel" class="fm-btn-secondary text-sm">Créer l'ingrédient avec macros</button>
                    </div>
                @endif
                @if ($showCustomFoodPanel)
                    <div class="mt-2 rounded-lg border border-fm-primary/40 bg-fm-surface p-3 space-y-3">
                        <p class="text-sm font-medium">Nouvel ingrédient (macros / 100 g)</p>
                        <input wire:model="customFoodName" placeholder="Nom" class="fm-input text-sm">
                        <div class="grid grid-cols-2 gap-2">
                            <input wire:model="customFoodBrand" placeholder="Marque (optionnel)" class="fm-input text-sm">
                            <input wire:model="customFoodBarcode" placeholder="Code-barres (optionnel)" class="fm-input text-sm">
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <x-fm.number wire:model="customFoodEnergy" min="0" step="1" placeholder="Kcal" class="text-sm" />
                            <x-fm.number wire:model="customFoodProtein" min="0" step="0.1" placeholder="Prot. g" class="text-sm" />
                            <x-fm.number wire:model="customFoodCarbs" min="0" step="0.1" placeholder="Gluc. g" class="text-sm" />
                            <x-fm.number wire:model="customFoodFat" min="0" step="0.1" placeholder="Lip. g" class="text-sm" />
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="shareCustomFoodWithCommunity">
                            Partager cet ingrédient avec la communauté
                        </label>
                        <div class="flex gap-2">
                            <button type="button" wire:click="createCustomFood" class="fm-btn-primary text-sm">Créer et ajouter</button>
                            <button type="button" wire:click="$set('showCustomFoodPanel', false)" class="fm-btn-ghost text-sm">Annuler</button>
                        </div>
                    </div>
                @endif
            </div>
            @foreach ($ingredients as $index => $ingredient)
                <div class="flex gap-2 items-center">
                    <span class="flex-1 text-sm">{{ $ingredient['label'] }}</span>
                    <x-fm.number wrap="w-28" wire:model.live="ingredients.{{ $index }}.quantity_g" min="1" step="5" /> g
                    <button type="button" wire:click="removeIngredient({{ $index }})" class="text-fm-accent">×</button>
                </div>
            @endforeach
        @endif
        <button wire:click="save" type="button" class="fm-btn-primary w-full">Enregistrer</button>
    </div>
    <div class="fm-card">
        <h3 class="font-semibold mb-4">Macros calculées</h3>
        <ul class="space-y-2 text-sm">
            <li class="flex justify-between"><span>Calories</span><strong class="text-fm-primary">{{ $nutrients['energy_kcal'] ?? 0 }} kcal</strong></li>
            <li class="flex justify-between"><span>Protéines</span><strong>{{ $nutrients['protein_g'] ?? 0 }} g</strong></li>
            <li class="flex justify-between"><span>Glucides</span><strong>{{ $nutrients['carbs_g'] ?? 0 }} g</strong></li>
            <li class="flex justify-between"><span>Lipides</span><strong>{{ $nutrients['fat_g'] ?? 0 }} g</strong></li>
        </ul>
        <p class="text-xs text-fm-muted mt-6">Données nutritionnelles : table CIQUAL (ANSES) et Open Food Facts.</p>
    </div>
</div>
