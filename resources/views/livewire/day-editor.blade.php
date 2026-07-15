<div class="fm-container max-w-3xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-caption text-fm-muted">
                <a href="{{ route('planner', array_filter(['view' => $viewUserId, 'program' => $programId])) }}" wire:navigate class="text-fm-primary hover:underline">← Retour au plan</a>
                @if ($planContext->isFriend())
                    · Plan de {{ $planContext->planOwner->name }}
                @elseif ($activeProgram)
                    · Programme : {{ $activeProgram->name }}
                @endif
            </p>
            <h1 class="text-h2 font-semibold mt-1">{{ $day->translatedFormat('l j F') }}</h1>
            <p class="text-caption text-fm-muted mt-1">
                @if ($day->isPast() && ! $day->isToday())
                    Journal — renseigne ce que tu as réellement mangé ce jour-là.
                @elseif ($day->isToday())
                    Aujourd'hui — planifie à l'avance ou renseigne au fil de la journée.
                @else
                    Planification — prépare cette journée à l'avance.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button wire:click="goToDay('{{ $day->copy()->subDay()->toDateString() }}')" type="button" class="fm-btn-sm">← Jour</button>
            <button wire:click="goToDay('{{ $day->copy()->addDay()->toDateString() }}')" type="button" class="fm-btn-sm">Jour →</button>
        </div>
    </div>

    @if (session('day-editor-status'))
        <p class="text-sm text-fm-primary">{{ session('day-editor-status') }}</p>
    @endif

    {{-- Totaux du jour --}}
    <dl class="fm-totals-bar gap-4">
        <div>
            <dt class="text-caption text-fm-muted">Kcal</dt>
            <dd @class(['mt-1 text-lg font-medium tabular-nums', 'text-fm-accent' => $dayTotal['energy_kcal'] > $calorieTarget, 'text-fm-primary' => $dayTotal['energy_kcal'] <= $calorieTarget])>
                {{ (int) $dayTotal['energy_kcal'] }} <span class="text-xs text-fm-muted font-normal">/ {{ $calorieTarget }}</span>
            </dd>
        </div>
        <div>
            <dt class="text-caption text-fm-protein">Protéines</dt>
            <dd class="mt-1 text-lg font-medium tabular-nums">{{ round($dayTotal['protein_g']) }} g</dd>
        </div>
        <div>
            <dt class="text-caption text-fm-carbs">Glucides</dt>
            <dd class="mt-1 text-lg font-medium tabular-nums">{{ round($dayTotal['carbs_g']) }} g</dd>
        </div>
        <div>
            <dt class="text-caption text-fm-fat">Lipides</dt>
            <dd class="mt-1 text-lg font-medium tabular-nums">{{ round($dayTotal['fat_g']) }} g</dd>
        </div>
        <div>
            <dt class="text-caption text-fm-muted">Budget</dt>
            <dd class="mt-1 text-lg font-medium tabular-nums">{{ $dayCost !== null ? number_format($dayCost, 2, ',', ' ').' €' : '—' }}</dd>
        </div>
    </dl>

    @if (! $canEdit)
        <p class="text-sm text-fm-accent">Les grammages sont verrouillés par le propriétaire du programme. Consultation seule.</p>
    @endif

    {{-- Suggestions rapides --}}
    @if ($canEdit && count($recentFoods) && $activeSlot)
        <div class="fm-panel">
            <p class="text-caption text-fm-muted mb-2">Récemment utilisés — clic pour ajouter à « {{ $slots[$activeSlot] }} »</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($recentFoods as $food)
                    <button
                        type="button"
                        wire:click="addSuggestion('{{ $activeSlot }}', @js($food['type'] ?? ''), {{ $food['id'] ?? 'null' }}, @js($food['label']), {{ $food['quantity_g'] }})"
                        class="text-sm px-3 py-2 rounded-full bg-fm-surface border border-fm-border hover:border-fm-primary transition-colors min-h-touch"
                    >
                        {{ $food['label'] }} <span class="text-fm-muted">· {{ round($food['quantity_g']) }} g</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Créneaux --}}
    <div class="space-y-4">
        @foreach ($slots as $slotKey => $slotLabel)
            @php $slotEntries = $entriesBySlot->get($slotKey, collect()); @endphp
            <div class="fm-panel" wire:key="slot-{{ $slotKey }}">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-medium">{{ $slotLabel }}</h2>
                    @php
                        $slotKcal = 0;
                        foreach ($slotEntries as $e) { $slotKcal += $entryCalculator->calculate($e)->energyKcal; }
                    @endphp
                    @if ($slotEntries->isNotEmpty())
                        <span class="text-xs text-fm-primary tabular-nums">{{ (int) $slotKcal }} kcal</span>
                    @endif
                </div>

                <div class="space-y-1">
                    @foreach ($slotEntries as $entry)
                        @php $nutrients = $entryCalculator->calculate($entry); @endphp
                        <div class="text-sm bg-fm-surface rounded-lg p-2.5 flex flex-wrap items-center gap-x-3 gap-y-2" wire:key="entry-{{ $entry->id }}">
                            <span class="min-w-0 flex-1 basis-[10rem]">
                                <strong class="block truncate text-sm">{{ $entryCalculator->label($entry) }}</strong>
                                <span class="text-xs text-fm-primary tabular-nums">{{ (int) $nutrients->energyKcal }} kcal</span>
                                @if ($entry->estimated_cost)
                                    <span class="text-xs text-fm-muted tabular-nums"> · {{ number_format($entry->estimated_cost, 2, ',', ' ') }} €</span>
                                @endif
                            </span>
                            @if ($canEdit && $entry->quantity_g)
                                <label class="flex items-center gap-1 text-xs text-fm-muted shrink-0">
                                    <x-fm.number
                                        wrap="w-20"
                                        step="1" min="1" max="5000"
                                        value="{{ $entry->quantity_g }}"
                                        class="py-0.5 px-1 text-xs"
                                        wire:change="updateQuantity({{ $entry->id }}, parseFloat($event.target.value))"
                                    />
                                    g
                                </label>
                            @elseif ($canEdit && $entry->recipe_id && ! $entry->quantity_g)
                                <label class="flex items-center gap-1 text-xs text-fm-muted shrink-0">
                                    ×
                                    <x-fm.number
                                        wrap="w-[4.5rem]"
                                        step="0.25" min="0.25" max="10"
                                        value="{{ $entry->portions ?? 1 }}"
                                        class="py-0.5 px-1 text-xs"
                                        wire:change="updatePortions({{ $entry->id }}, parseFloat($event.target.value))"
                                    />
                                </label>
                            @endif
                            @if ($canEdit)
                                <button wire:click="removeEntry({{ $entry->id }})" type="button" class="inline-flex min-h-touch min-w-touch items-center justify-center text-fm-accent shrink-0 rounded-lg hover:bg-fm-accent/10" aria-label="Supprimer">×</button>
                            @endif
                        </div>
                    @endforeach

                    @if ($slotEntries->isEmpty() && ! $canEdit)
                        <p class="text-xs text-fm-muted italic">—</p>
                    @endif
                </div>

                @if ($canEdit)
                    @if ($activeSlot === $slotKey)
                        <div class="mt-3 p-3 rounded-lg border border-fm-primary/40 bg-fm-bg space-y-2">
                            <div class="flex flex-wrap gap-2">
                                <input
                                    wire:model.live.debounce.300ms="foodSearch"
                                    type="search"
                                    placeholder="Chercher un aliment, code-barres…"
                                    class="fm-input text-sm flex-1 min-w-[10rem]"
                                    autofocus
                                >
                                <div class="flex items-center gap-1">
                                    <x-fm.number wrap="w-24" wire:model="quantityG" min="1" max="5000" step="5" class="text-sm" title="Grammes" />
                                    <span class="text-sm text-fm-muted">g</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <x-fm.number wrap="w-24" wire:model="pricePerKg" min="0" max="999" step="0.5" class="text-sm" placeholder="Prix" title="Prix €/kg (facultatif)" />
                                    <span class="text-sm text-fm-muted whitespace-nowrap">€/kg</span>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2 items-end">
                                <div class="flex-1 min-w-[10rem]">
                                    <label class="text-caption text-fm-muted">Enseigne</label>
                                    <input wire:model.live="storeBrand" list="store-brands-list" class="fm-input text-sm mt-1" placeholder="Ex. Carrefour">
                                    <datalist id="store-brands-list">
                                        @foreach ($storeBrands as $brand)
                                            <option value="{{ $brand }}"></option>
                                        @endforeach
                                    </datalist>
                                </div>
                                <div>
                                    <label class="text-caption text-fm-muted">Constaté le</label>
                                    <input type="date" wire:model="priceObservedAt" max="{{ today()->toDateString() }}" class="fm-input text-sm mt-1">
                                </div>
                            </div>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="sharePriceWithCommunity">
                                Partager ce prix avec la communauté
                            </label>
                            @if ($priceSourceLabel)
                                <p class="text-caption text-fm-primary">{{ $priceSourceLabel }}</p>
                            @endif
                            @if ($selectedFoodBarcode)
                                <p class="text-caption text-fm-muted">Code-barres : {{ $selectedFoodBarcode }}</p>
                            @endif
                            <p class="text-caption text-fm-muted">
                                Prix facultatif pour le budget.
                                <a href="{{ route('settings.budget') }}" wire:navigate class="text-fm-primary hover:underline">Magasin par défaut</a>
                            </p>
                            @if ($selectedFoodLabel)
                                <div class="flex flex-wrap items-center gap-2 rounded-lg border border-fm-border bg-fm-surface px-3 py-2">
                                    <span class="text-sm flex-1 min-w-0 truncate">{{ $selectedFoodLabel }}</span>
                                    <button type="button" wire:click="addFood" class="fm-btn-primary text-sm">Ajouter</button>
                                    <button type="button" wire:click="clearSelectedFood" class="fm-btn-ghost text-sm">Annuler</button>
                                </div>
                            @endif
                            @if (count($foodSearchResults))
                                <ul class="rounded-lg border border-fm-border divide-y divide-fm-border max-h-44 overflow-y-auto">
                                    @foreach ($foodSearchResults as $result)
                                        <li>
                                            <button
                                                type="button"
                                                wire:click="selectFoodForAdd('{{ $result['type'] }}', {{ $result['id'] }}, @js($result['label']), @js($result['barcode'] ?? null))"
                                                @class([
                                                    'w-full text-left px-3 py-2.5 text-sm hover:bg-fm-surface min-h-touch',
                                                    'bg-fm-primary/10' => $selectedFoodId === $result['id'] && $selectedFoodType === $result['type'],
                                                ])
                                            >
                                                {{ $result['label'] }}
                                                @if (! empty($result['barcode']))
                                                    <span class="block text-caption text-fm-muted">{{ $result['barcode'] }}</span>
                                                @endif
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @elseif ($canCreateCustomFood && strlen($foodSearch) >= 2 && ! $showCustomFoodPanel)
                                <div class="rounded-lg border border-fm-border bg-fm-surface p-3 space-y-2">
                                    @if ($barcodeNotFound)
                                        <p class="text-sm text-fm-muted">Code-barres inconnu dans Open Food Facts.</p>
                                    @else
                                        <p class="text-sm text-fm-muted">Aucun aliment trouvé pour « {{ $foodSearch }} ».</p>
                                    @endif
                                    <button type="button" wire:click="openCustomFoodPanel" class="fm-btn-secondary text-sm">
                                        Créer l'aliment avec macros
                                    </button>
                                </div>
                            @elseif (strlen($foodSearch) >= 2 && ! $showCustomFoodPanel)
                                <p class="text-xs text-fm-muted">Aucun aliment trouvé.</p>
                            @endif
                            @if ($showCustomFoodPanel)
                                <div class="rounded-lg border border-fm-primary/40 bg-fm-surface p-3 space-y-3">
                                    <p class="text-sm font-medium">Nouvel aliment (macros / 100 g)</p>
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
                                        Partager cet aliment avec la communauté
                                    </label>
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="createCustomFood" class="fm-btn-primary text-sm">Créer et sélectionner</button>
                                        <button type="button" wire:click="$set('showCustomFoodPanel', false)" class="fm-btn-ghost text-sm">Annuler</button>
                                    </div>
                                </div>
                            @endif
                            <button type="button" wire:click="closeSlot" class="text-xs text-fm-muted hover:text-fm-text">Fermer</button>
                        </div>
                    @else
                        <div class="flex flex-wrap gap-2 mt-3">
                            <button
                                type="button"
                                wire:click="openSlot('{{ $slotKey }}')"
                                class="text-sm px-4 py-2 rounded-lg border border-fm-border hover:border-fm-primary transition-colors min-h-touch"
                            >
                                + Aliment
                            </button>
                            @if ($recipes->isNotEmpty())
                                <select
                                    class="fm-input text-xs max-w-[10rem] py-1"
                                    onchange="if(this.value){ $wire.addRecipeBundle('{{ $slotKey }}', parseInt(this.value)); this.value=''; }"
                                >
                                    <option value="">+ Ensemble</option>
                                    @foreach ($recipes as $recipe)
                                        <option value="{{ $recipe->id }}">{{ $recipe->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    @endif
                @endif
            </div>
        @endforeach
    </div>

    {{-- Actions du jour --}}
    @if ($canEdit)
        <div class="fm-panel space-y-4">
            <div class="flex flex-wrap gap-3">
                <button type="button" wire:click="toggleCopyPanel" class="fm-btn-primary text-sm">
                    Copier ce jour vers…
                </button>
                <button type="button" wire:click="togglePublishPanel" class="fm-btn-ghost text-sm">
                    Publier ce menu
                </button>
                <button
                    type="button"
                    wire:click="clearDay"
                    wire:confirm="Vider tous les repas de cette journée ?"
                    class="fm-btn-ghost text-sm text-fm-accent"
                >
                    Vider la journée
                </button>
            </div>

            @if ($showPublishPanel)
                <div class="space-y-3 pt-3 border-t border-fm-border">
                    <input wire:model="publishTitle" placeholder="Titre du menu (ex: Journée cut 1800 kcal)" class="fm-input">
                    @error('publishTitle') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                    <textarea wire:model="publishDescription" placeholder="Description (optionnel)" class="fm-input" rows="2"></textarea>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="publishPublic">
                        Visible par tout le monde (sinon amis uniquement)
                    </label>
                    <div class="flex gap-2">
                        <button type="button" wire:click="publishDay" class="fm-btn-primary text-sm">Publier</button>
                        <button type="button" wire:click="togglePublishPanel" class="fm-btn-ghost text-sm">Annuler</button>
                    </div>
                </div>
            @endif

            @if ($showCopyPanel)
                <div class="space-y-3 pt-3 border-t border-fm-border">
                    <div class="flex flex-wrap items-center gap-3">
                        <select wire:model.live="copyWeekOffset" class="fm-input text-sm w-auto">
                            <option value="0">Cette semaine</option>
                            <option value="1">Semaine prochaine</option>
                            <option value="2">Dans 2 semaines</option>
                            <option value="3">Dans 3 semaines</option>
                        </select>
                        <button type="button" wire:click="selectWholeWeek" class="text-xs text-fm-primary hover:underline">Toute la semaine</button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($copyDays as $copyDay)
                            @php $dateStr = $copyDay->toDateString(); @endphp
                            <label @class([
                                'text-xs px-3 py-1.5 rounded-lg border cursor-pointer transition-colors',
                                'border-fm-primary bg-fm-primary/10' => in_array($dateStr, $copyTargets),
                                'border-fm-border' => ! in_array($dateStr, $copyTargets),
                                'opacity-40 pointer-events-none' => $dateStr === $date,
                            ])>
                                <input type="checkbox" wire:model.live="copyTargets" value="{{ $dateStr }}" class="sr-only" @disabled($dateStr === $date)>
                                {{ $copyDay->translatedFormat('D d/m') }}
                            </label>
                        @endforeach
                    </div>
                    <p class="text-xs text-fm-muted">Les jours cibles seront remplacés par le contenu de cette journée.</p>
                    <button type="button" wire:click="copyDay" class="fm-btn-primary text-sm" @disabled(empty($copyTargets))>
                        Copier vers {{ count($copyTargets) }} jour(s)
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>
