<div class="fm-container space-y-6">
    <div>
        <h1 class="text-h2 font-semibold">Graphiques</h1>
        <p class="text-sm text-fm-muted mt-1">Compare librement tes données — même celles qui n'ont rien à voir au premier coup d'œil.</p>
    </div>

    <div class="fm-panel space-y-4">
        <div class="flex flex-wrap gap-2">
            @foreach ($periods as $key => $label)
                <button
                    type="button"
                    wire:click="$set('period', '{{ $key }}')"
                    @class([
                        'text-sm px-4 py-2.5 rounded-lg border transition-colors min-h-touch',
                        'border-fm-primary text-fm-primary bg-fm-primary/10' => $period === $key,
                        'border-fm-border text-fm-muted hover:border-fm-primary/50' => $period !== $key,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="grid sm:grid-cols-3 gap-4 pt-4 border-t border-fm-border">
            @foreach ($groups as $groupName => $items)
                <div>
                    <p class="text-caption text-fm-muted mb-2">{{ $groupName }}</p>
                    <div class="space-y-1.5">
                        @foreach ($items as $item)
                            <label class="flex items-center gap-2 text-sm cursor-pointer">
                                <input type="checkbox" wire:model.live="selected" value="{{ $item['key'] }}">
                                {{ $item['label'] }}
                                @if ($item['unit'])
                                    <span class="text-caption text-fm-muted">({{ $item['unit'] }})</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="fm-panel">
        @if ($hasData)
            <p class="text-caption text-fm-muted mb-4">
                Du {{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}
                @if ($usesRightAxis)
                    · axe gauche = première unité sélectionnée, axe droit = autres unités
                @endif
            </p>
            <canvas
                id="charts-explorer-canvas"
                data-chart="line"
                @if ($usesRightAxis) data-dual-axis="1" @endif
                data-labels='@json($chartLabels)'
                data-datasets='@json($chartDatasets)'>
            </canvas>
        @elseif (empty($selected))
            <p class="text-sm text-fm-muted py-12 text-center">Sélectionne au moins une série à afficher.</p>
        @else
            <p class="text-sm text-fm-muted py-12 text-center">
                Pas encore de données sur cette période.
                Ajoute des <a href="{{ route('metrics') }}" wire:navigate class="text-fm-primary hover:underline">métriques corps</a>
                ou <a href="{{ route('planner') }}" wire:navigate class="text-fm-primary hover:underline">planifie des repas</a>.
            </p>
        @endif
    </div>
</div>
