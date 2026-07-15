<div class="fm-container max-w-3xl space-y-6">
    @if (session('metrics-status'))
        <p class="text-sm text-fm-primary">{{ session('metrics-status') }}</p>
    @endif

    <form wire:submit="save" class="fm-panel space-y-4">
        <h2 class="text-sm font-medium">Ajouter une mesure</h2>
        <p class="text-caption text-fm-muted">Choisis une date passée pour rattraper ton historique — une mesure existante à la même date sera mise à jour.</p>

        <div>
            <label class="text-caption text-fm-muted">Source % graisse</label>
            <select wire:model.live="metric_source" class="fm-input mt-1 text-sm">
                <option value="manual">Saisie manuelle</option>
                <option value="navy">Méthode Navy (tours)</option>
                <option value="scale">Balance (saisie manuelle)</option>
            </select>
        </div>

        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <label class="text-caption text-fm-muted">Date</label>
                <input type="date" wire:model="recorded_at" max="{{ today()->toDateString() }}" class="fm-input mt-1">
                @error('recorded_at') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-caption text-fm-muted">Poids (kg)</label>
                <x-fm.number step="0.1" min="30" max="300" wire:model="weight_kg" class="mt-1" />
                @error('weight_kg') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            @if ($metric_source === 'manual' || $metric_source === 'scale')
                <div>
                    <label class="text-caption text-fm-muted">% graisse (optionnel)</label>
                    <x-fm.number step="0.1" min="3" max="70" wire:model="body_fat_percent" class="mt-1" />
                    @error('body_fat_percent') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>

        @if ($metric_source === 'navy')
            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="text-caption text-fm-muted">Tour de cou (cm)</label>
                    <x-fm.number step="0.1" wire:model="neck_cm" class="mt-1" />
                    @error('neck_cm') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-caption text-fm-muted">Tour de taille (cm)</label>
                    <x-fm.number step="0.1" wire:model="waist_cm" class="mt-1" />
                    @error('waist_cm') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                @if ($isFemale)
                    <div>
                        <label class="text-caption text-fm-muted">Tour de hanches (cm)</label>
                        <x-fm.number step="0.1" wire:model="hip_cm" class="mt-1" />
                        @error('hip_cm') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>
        @endif

        <button type="submit" class="fm-btn-primary text-sm">Enregistrer</button>
    </form>

    <div class="fm-panel">
        <h2 class="text-sm font-medium mb-4">Évolution</h2>
        @if ($labels->isEmpty())
            <p class="text-sm text-fm-muted">Aucune mesure enregistrée.</p>
        @else
            <canvas
                data-chart="line"
                data-labels='@json($labels)'
                data-datasets='@json($chartDatasets)'>
            </canvas>
        @endif
    </div>

    @if ($recentMetrics->isNotEmpty())
        <div class="fm-panel">
            <h2 class="text-sm font-medium mb-3">Dernières mesures</h2>
            <ul class="divide-y divide-fm-border">
                @foreach ($recentMetrics as $metric)
                    <li class="flex items-center justify-between py-2.5 text-sm" wire:key="metric-{{ $metric->id }}">
                        <span class="text-fm-muted">{{ $metric->recorded_at->format('d/m/Y') }}</span>
                        <span class="tabular-nums">
                            {{ $metric->weight_kg }} kg
                            @if ($metric->body_fat_percent)
                                · {{ $metric->body_fat_percent }} %
                            @endif
                            @if ($metric->source?->value === 'navy')
                                <span class="text-caption text-fm-muted">· Navy</span>
                            @endif
                        </span>
                        <button
                            wire:click="delete({{ $metric->id }})"
                            wire:confirm="Supprimer cette mesure ?"
                            type="button"
                            class="text-fm-accent text-xs hover:underline"
                        >
                            Supprimer
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="text-caption text-fm-muted">IMC informatif — le % graisse (Navy ou balance) reste la référence.</p>
</div>
