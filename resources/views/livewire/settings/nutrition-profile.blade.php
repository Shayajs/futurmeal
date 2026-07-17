<div class="fm-container max-w-2xl space-y-6">
    <div>
        <p class="text-caption text-fm-muted">
            <a href="{{ route('settings') }}" wire:navigate class="text-fm-primary hover:underline">← Paramètres</a>
        </p>
        <h1 class="text-h2 font-semibold mt-1">Nutrition & objectifs</h1>
        <p class="text-sm text-fm-muted mt-1">Déficit / surplus, sport, intensité — pour simplifier la prise de masse ou la perte de poids.</p>
    </div>

    @if (session('status'))
        <p class="text-sm text-fm-primary">{{ session('status') }}</p>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="fm-panel space-y-6">
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-caption text-fm-muted">Objectif</label>
                    <select wire:model.live="goal_type" class="fm-input mt-1">
                        @foreach ($goalOptions as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-caption text-fm-muted">Niveau d'activité (hors sport listé)</label>
                    <select wire:model.live="activity_level" class="fm-input mt-1">
                        @foreach ($activityOptions as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-caption text-fm-muted">Horizon planification</label>
                    <select wire:model="planning_horizon_days" class="fm-input mt-1">
                        @foreach ([3, 7, 14, 30] as $days)
                            <option value="{{ $days }}">{{ $days }} jours</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-caption text-fm-muted">Kcal sport estimé / jour</label>
                    <x-fm.number min="0" max="2000" step="50" wire:model.live="sport_kcal_per_day" class="mt-1" />
                    <p class="text-xs text-fm-muted mt-1">Séances (musculation, cardio…) en moyenne journalière.</p>
                </div>
            </div>
        </div>

        <div class="fm-panel space-y-4">
            <h2 class="text-sm font-medium">Dépense énergétique estimée</h2>

            @if ($basal_metabolic_rate)
                <dl class="rounded-lg bg-fm-bg border border-fm-border divide-y divide-fm-border text-sm">
                    <div class="flex flex-col gap-1 px-4 py-2.5 sm:flex-row sm:items-baseline sm:justify-between">
                        <dt>
                            Métabolisme de base (MB)
                            <span class="block text-caption text-fm-muted">Mifflin-St Jeor — kcal au repos</span>
                        </dt>
                        <dd class="tabular-nums font-medium shrink-0">{{ $basal_metabolic_rate }} kcal</dd>
                    </div>
                    <div class="flex flex-col gap-1 px-4 py-2.5 sm:flex-row sm:items-baseline sm:justify-between">
                        <dt>
                            × Activité : {{ $activityLabel }}
                            <span class="block text-caption text-fm-muted">multiplicateur {{ $activityMultiplier }}</span>
                        </dt>
                        <dd class="tabular-nums font-medium text-fm-muted shrink-0">
                            {{ (int) round($basal_metabolic_rate * $activityMultiplier) }} kcal
                        </dd>
                    </div>
                    @if ($sport_kcal_per_day > 0)
                        <div class="flex flex-col gap-1 px-4 py-2.5 sm:flex-row sm:items-baseline sm:justify-between">
                            <dt>+ Sport estimé</dt>
                            <dd class="tabular-nums font-medium shrink-0">+{{ $sport_kcal_per_day }} kcal</dd>
                        </div>
                    @endif
                    <div class="flex flex-col gap-1 px-4 py-2.5 sm:flex-row sm:items-baseline sm:justify-between">
                        <dt>Maintenance</dt>
                        <dd class="tabular-nums font-medium shrink-0">{{ $maintenance_tdee }} kcal</dd>
                    </div>
                    <div class="flex flex-col gap-1 px-4 py-2.5 sm:flex-row sm:items-baseline sm:justify-between">
                        <dt>{{ $calorie_adjustment < 0 ? '− Déficit' : '+ Surplus' }}</dt>
                        <dd class="tabular-nums font-medium shrink-0 {{ $calorie_adjustment < 0 ? 'text-fm-primary' : 'text-fm-accent' }}">
                            {{ $calorie_adjustment > 0 ? '+' : '' }}{{ $calorie_adjustment }} kcal
                        </dd>
                    </div>
                </dl>
            @else
                <p class="text-sm text-fm-muted">
                    Ajoute une <a href="{{ route('metrics') }}" wire:navigate class="text-fm-primary hover:underline">mesure de poids</a> pour calculer ton métabolisme de base.
                </p>
            @endif

            <div>
                <label class="text-caption text-fm-muted">Intensité</label>
                <select wire:model.live="goal_intensity" class="fm-input mt-1">
                    @foreach ($intensityOptions as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
                @if ($intensityDisclaimer)
                    <p class="text-sm text-fm-accent mt-2">{{ $intensityDisclaimer }}</p>
                @endif
            </div>

            <div class="flex items-baseline justify-between pt-2">
                <h3 class="text-sm font-medium">Ajustement manuel (borné)</h3>
            </div>

            <div class="space-y-2">
                <input
                    type="range"
                    min="-1000"
                    max="500"
                    step="50"
                    wire:model.live="calorie_adjustment"
                >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-caption text-fm-muted shrink-0">-1000 (extrême)</span>
                    <x-fm.number
                        wrap="w-full sm:w-32"
                        min="-1000"
                        max="500"
                        step="10"
                        wire:model.live="calorie_adjustment"
                        class="text-center"
                    />
                    <span class="text-caption text-fm-muted shrink-0 sm:text-right">+500 (surplus max)</span>
                </div>
            </div>

            @if ($this->maintenanceWarning)
                <p class="text-sm text-fm-accent">
                    Attention : avec un ajustement de {{ $calorie_adjustment }} kcal tu es quasiment en maintien.
                </p>
            @else
                <p class="text-sm text-fm-muted">
                    {{ $calorie_adjustment < 0 ? 'Déficit' : 'Surplus' }} de {{ abs($calorie_adjustment) }} kcal/jour
                    ≈ <strong class="text-fm-text">{{ $this->weeklyKg }} kg/{{ $calorie_adjustment < 0 ? 'semaine de perte' : 'semaine de prise' }}</strong>
                    (indicatif, 7700 kcal ≈ 1 kg — repère ACSM ~0,5–0,9 kg/sem en perte).
                </p>
            @endif

            @if ($this->wasClamped && $floor_daily_kcal)
                <p class="text-sm text-fm-accent">
                    Objectif relevé au plancher santé ({{ $floor_daily_kcal }} kcal/j) — on ne descend pas sous le MB ni sous 1200/1500 selon le profil.
                </p>
            @endif

            <div class="pt-4 border-t border-fm-border space-y-2">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model.live="override_calories">
                    Forcer un objectif kcal manuel
                </label>
                @if ($override_calories)
                    <x-fm.number min="1000" max="6000" step="50" wire:model.live="daily_calorie_target" placeholder="kcal / jour" />
                @endif
            </div>

            <div class="rounded-lg bg-fm-bg border border-fm-border p-4 flex items-baseline justify-between">
                <span class="text-sm text-fm-muted">Objectif final</span>
                <span class="text-2xl font-semibold tabular-nums text-fm-primary">{{ $this->effectiveTarget ?? '—' }} <span class="text-sm text-fm-muted font-normal">kcal/jour</span></span>
            </div>

            <p class="text-xs text-fm-muted">
                FuturMeal est un outil indicatif, pas un avis médical. En cas de pathologie, grossesse, TCA ou traitement, consulte un professionnel de santé.
            </p>
        </div>

        <div class="fm-panel space-y-4">
            <h2 class="text-sm font-medium">Cibles corporelles</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-caption text-fm-muted">Poids cible (kg)</label>
                    <x-fm.number step="0.1" min="30" max="300" wire:model="target_weight_kg" class="mt-1" />
                    @error('target_weight_kg') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-caption text-fm-muted">Graisse cible (%)</label>
                    <x-fm.number step="0.1" min="3" max="70" wire:model="target_body_fat_percent" class="mt-1" />
                    @error('target_body_fat_percent') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <button type="submit" class="fm-btn-primary">Enregistrer</button>
    </form>
</div>
