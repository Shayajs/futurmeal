<div class="fm-container space-y-8">
    <header>
        <p class="text-caption text-fm-muted">{{ $data['greeting']['date'] }}</p>
        <h1 class="text-h2 font-semibold mt-1">Bonjour, {{ $data['greeting']['name'] }}</h1>
        @if ($data['plan_context']['is_supervising'] ?? false)
            <p class="text-sm text-fm-primary mt-2">
                Tu supervises le plan de <strong>{{ $data['plan_context']['owner_name'] }}</strong> — objectifs kcal : les tiens.
            </p>
        @elseif ($data['greeting']['goal_label'])
            <p class="text-sm text-fm-muted mt-1">{{ $data['greeting']['goal_label'] }}</p>
        @endif
    </header>

    <div class="grid md:grid-cols-2 xl:grid-cols-12 gap-6">
        <div class="xl:col-span-5 fm-panel">
            <h2 class="text-sm font-medium mb-4">Corps — 30 derniers relevés</h2>
            @if (count($data['body']['chart']['labels']) > 1)
                <canvas
                    data-chart="line"
                    data-dual-axis="1"
                    data-labels='@json($data['body']['chart']['labels'])'
                    data-datasets='@json($bodyChartDatasets)'>
                </canvas>
            @else
                <p class="text-sm text-fm-muted">Ajoute des métriques dans <a href="{{ route('metrics') }}" wire:navigate class="text-fm-primary">Corps</a>.</p>
            @endif
            @if ($data['body']['target_weight'])
                <x-fm.progress-bar
                    class="mt-4"
                    label="Progression poids cible"
                    :value="$data['body']['weight_progress'] ?? 0"
                />
            @endif
        </div>

        <div class="xl:col-span-4 fm-panel">
            <h2 class="text-sm font-medium mb-4">Macros — aujourd'hui</h2>
            <canvas
                data-chart="doughnut"
                data-labels='["Protéines","Glucides","Lipides"]'
                data-datasets='@json($macroChartDatasets)'>
            </canvas>
            <dl class="grid grid-cols-3 gap-2 mt-4 text-center text-sm">
                <div><dt class="text-fm-protein text-caption">P</dt><dd class="tabular-nums">{{ round($data['today']['macros']['protein_g']) }}g</dd></div>
                <div><dt class="text-fm-carbs text-caption">G</dt><dd class="tabular-nums">{{ round($data['today']['macros']['carbs_g']) }}g</dd></div>
                <div><dt class="text-fm-fat text-caption">L</dt><dd class="tabular-nums">{{ round($data['today']['macros']['fat_g']) }}g</dd></div>
            </dl>
        </div>

        <div class="xl:col-span-3 fm-panel flex flex-col justify-center items-center text-center">
            <p class="text-caption text-fm-muted">Kcal restantes</p>
            <p class="text-4xl font-semibold tabular-nums text-fm-primary mt-2">{{ $data['today']['remaining'] }}</p>
            <p class="text-sm text-fm-muted mt-2">{{ $data['today']['consumed'] }} / {{ $data['today']['target'] }} kcal</p>
            <div class="w-full mt-4">
                <x-fm.progress-bar :value="$pctConsumed" />
            </div>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <div class="fm-panel">
            <div class="flex items-baseline justify-between mb-4">
                <h2 class="text-sm font-medium">Repas aujourd'hui</h2>
                <a
                    href="{{ route('planner.day', ['date' => today()->toDateString()]) }}"
                    wire:navigate
                    class="text-sm text-fm-primary hover:underline min-h-touch inline-flex items-center"
                >
                    Renseigner mes repas →
                </a>
            </div>
            <x-fm.meal-timeline :meals="$data['today_meals']" />
        </div>
        <div class="fm-panel">
            <h2 class="text-sm font-medium mb-4">Kcal & budget — semaine</h2>
            <canvas
                data-chart="bar"
                data-mixed="1"
                data-labels='@json($data['weekly_calories']['labels'])'
                data-datasets='@json($weeklyChartDatasets)'>
            </canvas>
            <div class="mt-4 pt-4 border-t border-fm-border">
                @if ($data['weekly_budget']['has_prices'])
                    <p class="text-sm break-words">
                        Budget estimé :
                        <strong class="tabular-nums">{{ number_format($data['weekly_budget']['spent'], 2, ',', ' ') }} €</strong>
                        <span class="text-fm-muted">({{ $data['weekly_budget']['priced_count'] }}/{{ $data['weekly_budget']['entry_count'] }} repas estimés)</span>
                    </p>
                @else
                    <p class="text-sm text-fm-muted">
                        <a href="{{ route('settings.budget') }}" wire:navigate class="text-fm-primary hover:underline">Renseigner mes prix →</a>
                        pour voir le budget semaine.
                    </p>
                @endif
            </div>
        </div>
    </div>

    <dl class="fm-panel grid grid-cols-2 md:grid-cols-4 gap-6">
        <x-fm.stat-card
            label="{{ ($data['projection']['type'] ?? 'loss') === 'gain' ? 'Surplus semaine' : 'Déficit semaine' }}"
            :value="$data['projection']['weekly_deficit_kcal']"
            unit="kcal"
        />
        <x-fm.stat-card
            label="Δ poids (30j)"
            :value="$data['body']['weight_delta'] !== null ? ($data['body']['weight_delta'] > 0 ? '+' : '').$data['body']['weight_delta'] : '—'"
            unit="kg"
        />
        <x-fm.stat-card label="IMC" :value="$data['body']['latest_bmi'] ?? '—'" />
        <x-fm.stat-card
            label="Graisse actuelle"
            :value="$data['body']['latest_body_fat'] ?? '—'"
            unit="%"
            :hint="$data['body']['target_body_fat'] ? 'Cible : '.$data['body']['target_body_fat'].' %' : null"
        />
    </dl>

    @if ($data['body']['target_body_fat'] && $data['body']['body_fat_progress'] !== null)
        <div class="fm-panel">
            <x-fm.progress-bar label="Progression graisse cible" :value="$data['body']['body_fat_progress']" />
        </div>
    @endif

    @if ($data['projection']['estimated_kg'] > 0)
        <p class="text-caption text-fm-muted">
            Projection indicative : {{ $data['projection']['label'] }} cette semaine (7700 kcal ≈ 1 kg).
        </p>
    @endif

    @if (count($data['programs']) > 0)
        <div class="space-y-4">
            <h2 class="text-sm font-medium">Programmes actifs</h2>
            @foreach ($data['programs'] as $program)
                <div class="fm-panel">
                    <div class="flex flex-wrap justify-between gap-2">
                        <div>
                            <h3 class="font-medium">{{ $program['name'] }}</h3>
                            <p class="text-caption text-fm-muted">{{ $program['member_count'] }} membres</p>
                        </div>
                        <div class="text-sm text-right">
                            @if ($program['adherence'] !== null)
                                <p>Adhérence : <strong>{{ $program['adherence'] }} %</strong></p>
                            @endif
                            @if ($program['lock_portions'])
                                <p class="text-fm-muted">Grammages verrouillés</p>
                            @endif
                            <a href="{{ route('planner', ['program' => $program['id']]) }}" wire:navigate class="text-fm-primary hover:underline">Voir le plan</a>
                        </div>
                    </div>
                    @if (count($program['shared_metrics']) > 0)
                        <div class="mt-4 flex flex-wrap gap-3">
                            @foreach ($program['shared_metrics'] as $metric)
                                <div class="px-3 py-2 rounded-lg bg-fm-bg border border-fm-border text-sm">
                                    <p class="font-medium">{{ $metric['name'] }}</p>
                                    <p class="text-fm-muted tabular-nums">
                                        {{ $metric['weight_kg'] ?? '—' }} kg · {{ $metric['body_fat_percent'] ?? '—' }} %
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
