<div>
    @if ($show)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-black/60" wire:click="close"></div>

            <div class="relative min-h-full flex items-start sm:items-center justify-center p-4 sm:p-6">
                <div class="relative w-full max-w-3xl fm-panel shadow-xl space-y-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-h3 font-medium">Générer la période avec l'IA</h2>
                            <p class="text-sm text-fm-muted mt-1">
                                @if ($rangeStart && $rangeEnd)
                                    {{ \Carbon\Carbon::parse($rangeStart)->format('d/m/Y') }}
                                    → {{ \Carbon\Carbon::parse($rangeEnd)->format('d/m/Y') }}
                                    · {{ $selectedDays }} jour(s)
                                @else
                                    Choisis la plage à générer
                                @endif
                            </p>
                        </div>
                        <button type="button" wire:click="close" class="fm-btn-sm">Fermer</button>
                    </div>

                    <nav class="flex flex-wrap gap-2 text-xs">
                        @foreach ([
                            'consignes' => '1. Consignes',
                            'prompt' => '2. Prompt',
                            'response' => '3. Réponse',
                            'preview' => '4. Aperçu',
                        ] as $key => $label)
                            <span @class([
                                'px-2 py-1 rounded border',
                                'border-fm-primary text-fm-primary' => $step === $key,
                                'border-fm-border text-fm-muted' => $step !== $key,
                            ])>{{ $label }}</span>
                        @endforeach
                    </nav>

                    @if ($errorMessage)
                        <p class="text-sm text-fm-accent">{{ $errorMessage }}</p>
                    @endif

                    @if ($step === 'consignes')
                        <div class="space-y-3">
                            <div class="grid sm:grid-cols-2 gap-3">
                                <label class="block text-sm">
                                    <span class="text-caption text-fm-muted">Du</span>
                                    <input type="date" wire:model.live="rangeStart" class="fm-input mt-1 w-full">
                                </label>
                                <label class="block text-sm">
                                    <span class="text-caption text-fm-muted">Au</span>
                                    <input type="date" wire:model.live="rangeEnd" class="fm-input mt-1 w-full">
                                </label>
                            </div>
                            <p class="text-xs text-fm-muted">
                                {{ $selectedDays }} jour(s) seront générés
                                (max {{ $maxRangeDays }}). Prérempli avec la période affichée dans le planner.
                            </p>

                            <label class="block text-sm">
                                <span class="text-caption text-fm-muted">Ce que tu veux (allergies, budget, batch cooking…)</span>
                                <textarea wire:model="userInstructions" rows="5" class="fm-input mt-1 w-full" placeholder="Ex. : pas de porc, repas simples à emporter le midi, max 40 € la semaine…"></textarea>
                            </label>
                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="goToPrompt" class="fm-btn">Continuer →</button>
                            </div>
                        </div>
                    @endif

                    @if ($step === 'prompt')
                        <div class="space-y-3">
                            <p class="text-sm text-fm-muted">
                                Copie ce prompt dans ChatGPT, Gemini ou une autre IA, puis reviens coller la réponse.
                                @if ($hasApi)
                                    Ou laisse le serveur appeler ton API connectée.
                                @endif
                            </p>
                            <textarea readonly rows="12" class="fm-input w-full font-mono text-xs" x-ref="promptBox">{{ $promptFull }}</textarea>
                            <div class="flex flex-wrap gap-2 justify-between">
                                <button type="button" wire:click="goToConsignes" class="fm-btn-sm">← Consignes</button>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="fm-btn-sm"
                                        x-on:click="
                                            navigator.clipboard.writeText($refs.promptBox.value);
                                            $el.textContent = 'Copié !';
                                            setTimeout(() => $el.textContent = 'Copier le prompt', 1500);
                                        "
                                    >Copier le prompt</button>
                                    @if ($hasApi)
                                        <button
                                            type="button"
                                            wire:click="generateViaApi"
                                            wire:loading.attr="disabled"
                                            class="fm-btn"
                                        >
                                            <span wire:loading.remove wire:target="generateViaApi">Générer via mon IA</span>
                                            <span wire:loading wire:target="generateViaApi">Génération…</span>
                                        </button>
                                    @else
                                        <a href="{{ route('settings.ai') }}" wire:navigate class="fm-btn-sm text-fm-primary">Configurer une API</a>
                                    @endif
                                    <button type="button" wire:click="goToResponse" class="fm-btn">Coller une réponse →</button>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($step === 'response')
                        <div class="space-y-3">
                            <label class="block text-sm">
                                <span class="text-caption text-fm-muted">Réponse JSON de l'IA</span>
                                <textarea wire:model="rawResponse" rows="14" class="fm-input mt-1 w-full font-mono text-xs" placeholder='{"days":[...]}'></textarea>
                            </label>
                            <div class="flex flex-wrap gap-2 justify-between">
                                <button type="button" wire:click="goToPrompt" class="fm-btn-sm">← Prompt</button>
                                <button type="button" wire:click="parsePastedResponse" class="fm-btn">Valider &amp; prévisualiser</button>
                            </div>
                        </div>
                    @endif

                    @if ($step === 'preview' && $draft)
                        <div class="space-y-3">
                            <p class="text-sm">
                                <span class="text-fm-primary">{{ $draft->resolvedCount() }} résolu(s)</span>
                                @if ($draft->unresolvedCount() > 0)
                                    · <span class="text-fm-accent">{{ $draft->unresolvedCount() }} non résolu(s) (ignorés à l'application)</span>
                                @endif
                            </p>

                            <div class="space-y-3 max-h-72 overflow-y-auto border border-fm-border rounded-lg p-3">
                                @foreach ($draft->itemsByDate() as $date => $items)
                                    <div>
                                        <h3 class="text-sm font-medium mb-1">{{ \Carbon\Carbon::parse($date)->translatedFormat('l d/m') }}</h3>
                                        <ul class="space-y-1 text-sm">
                                            @foreach ($items as $item)
                                                <li @class(['text-fm-muted' => ! $item->resolved])>
                                                    <span class="text-caption text-fm-muted">{{ $slots[$item->slot] ?? $item->slot }}</span>
                                                    —
                                                    {{ $item->label }}
                                                    @if ($item->quantityG)
                                                        <span class="text-fm-muted">({{ rtrim(rtrim(number_format($item->quantityG, 1, '.', ''), '0'), '.') }} g)</span>
                                                    @endif
                                                    @if ($item->matchKind === 'recipe')
                                                        <span class="text-xs text-fm-primary">recette</span>
                                                    @elseif ($item->matchKind === 'food')
                                                        <span class="text-xs text-fm-muted">aliment</span>
                                                    @endif
                                                    @if ($item->warning)
                                                        <span class="text-xs text-fm-accent">· {{ $item->warning }}</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex flex-wrap gap-2 justify-between">
                                <button type="button" wire:click="goToResponse" class="fm-btn-sm">← Réponse</button>
                                <button
                                    type="button"
                                    wire:click="apply"
                                    wire:loading.attr="disabled"
                                    class="fm-btn"
                                    @disabled($draft->resolvedCount() === 0)
                                >
                                    Appliquer à la période
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
