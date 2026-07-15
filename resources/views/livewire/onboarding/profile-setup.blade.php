<div class="fm-container max-w-lg space-y-8">
    <div>
        <p class="text-caption text-fm-muted">Étape {{ $step }} / 5</p>
        <h1 class="text-h2 font-semibold mt-2">{{ $this->stepTitle }}</h1>
    </div>

    @if ($step === 1)
        <div wire:key="onboarding-step-1" class="space-y-4">
            <div>
                <label class="block text-sm text-fm-muted mb-1">Objectif principal</label>
                <select wire:model.live="goal_type" wire:key="goal-type-select" class="fm-input">
                    <option value="weight_loss">Perte de poids</option>
                    <option value="muscle_gain">Gain de masse</option>
                </select>
            </div>
            <div>
                <label class="block text-sm text-fm-muted mb-1">Horizon de planification par défaut</label>
                <select wire:model="planning_horizon_days" class="fm-input">
                    <option value="3">3 jours</option>
                    <option value="7">7 jours</option>
                    <option value="14">14 jours</option>
                    <option value="30">30 jours</option>
                </select>
            </div>
        </div>
    @elseif ($step === 2)
        <div wire:key="onboarding-step-2" class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-sm text-fm-muted mb-1">Sexe</label>
                <select wire:model="gender" class="fm-input">
                    <option value="male">Homme</option>
                    <option value="female">Femme</option>
                    <option value="other">Autre</option>
                </select>
            </div>
            <div>
                <label class="block text-sm text-fm-muted mb-1">Date de naissance</label>
                <input type="date" wire:model="birth_date" class="fm-input">
            </div>
            <div>
                <label class="block text-sm text-fm-muted mb-1">Taille (cm)</label>
                <x-fm.number min="100" max="250" wire:model="height_cm" />
            </div>
            <div>
                <label class="block text-sm text-fm-muted mb-1">Poids actuel (kg)</label>
                <x-fm.number step="0.1" min="30" max="300" wire:model="weight_kg" />
            </div>
            <div>
                <label class="block text-sm text-fm-muted mb-1">Activité</label>
                <select wire:model="activity_level" class="fm-input">
                    @foreach (\App\Enums\ActivityLevel::cases() as $level)
                        <option value="{{ $level->value }}">{{ $level->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @elseif ($step === 3)
        <div wire:key="onboarding-step-3" class="space-y-4">
            <p class="text-sm text-fm-muted">Point de départ pour mesurer ta progression.</p>
            <div>
                <label class="block text-sm text-fm-muted mb-1">Source % graisse</label>
                <select wire:model.live="metric_source" wire:key="metric-source-select" class="fm-input">
                    <option value="manual">Saisie manuelle</option>
                    <option value="navy">Méthode Navy (tours)</option>
                </select>
            </div>
            @if ($metric_source === 'manual')
                <div>
                    <label class="block text-sm text-fm-muted mb-1">% graisse actuel</label>
                    <x-fm.number step="0.1" min="3" max="70" wire:model="body_fat_percent" placeholder="Ex. 18" />
                </div>
            @else
                <div class="grid grid-cols-1 xs:grid-cols-3 gap-4">
                    <div><label class="text-sm text-fm-muted">Cou (cm)</label><x-fm.number min="20" max="80" wire:model="neck_cm" /></div>
                    <div><label class="text-sm text-fm-muted">Taille (cm)</label><x-fm.number min="40" max="200" wire:model="waist_cm" /></div>
                    @if ($gender === 'female')
                        <div><label class="text-sm text-fm-muted">Hanches (cm)</label><x-fm.number min="40" max="200" wire:model="hip_cm" /></div>
                    @endif
                </div>
            @endif
        </div>
    @elseif ($step === 4)
        <div wire:key="onboarding-step-4" class="space-y-4">
            <p class="text-sm text-fm-muted">
                @if ($goal_type === 'weight_loss')
                    Où tu veux arriver — poids et masse grasse cibles.
                @else
                    Masse visée — poids et composition cibles.
                @endif
            </p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-fm-muted mb-1">Poids cible (kg)</label>
                    <x-fm.number step="0.1" min="30" max="300" wire:model="target_weight_kg" />
                    @error('target_weight_kg') <p class="text-caption text-fm-accent mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm text-fm-muted mb-1">% graisse cible</label>
                    <x-fm.number step="0.1" min="3" max="70" wire:model="target_body_fat_percent" />
                    @error('target_body_fat_percent') <p class="text-caption text-fm-accent mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            @if ($weight_kg)
                <p class="text-caption text-fm-muted">
                    Actuel : {{ $weight_kg }} kg
                    @if ($this->currentBodyFat !== null)
                        · {{ $this->currentBodyFat }} % graisse
                    @endif
                </p>
            @endif
        </div>
    @else
        <div wire:key="onboarding-step-5" class="fm-panel space-y-3 text-sm">
            <p class="text-caption text-fm-muted uppercase tracking-wide">Récapitulatif</p>
            <p>Objectif : <strong>{{ $this->goalLabel }}</strong></p>
            <p>Planification : <strong>{{ $planning_horizon_days }} jours</strong></p>
            <p>Poids : <strong>{{ $weight_kg }} kg</strong> → <strong>{{ $target_weight_kg }} kg</strong></p>
            <p>Graisse : <strong>{{ $this->currentBodyFat ?? '—' }} %</strong> → <strong>{{ $target_body_fat_percent }} %</strong></p>
        </div>
    @endif

    <div class="flex justify-between pt-4">
        @if ($step > 1)
            <button wire:click="previousStep" type="button" class="text-fm-muted hover:text-fm-text">Retour</button>
        @else
            <span></span>
        @endif
        @if ($step < 5)
            <button wire:click="nextStep" type="button" class="fm-btn-primary">Continuer</button>
        @else
            <button wire:click="complete" type="button" class="fm-btn-primary">C'est parti !</button>
        @endif
    </div>
</div>
