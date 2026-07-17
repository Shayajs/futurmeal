<div class="fm-container space-y-6">
    @if (session('planner-status'))
        <p class="text-sm text-fm-primary">{{ session('planner-status') }}</p>
    @endif

    <div class="fm-panel">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-h3 font-medium">
                    @if ($activeProgram)
                        Semaine du {{ \Carbon\Carbon::parse($weekStart)->format('d/m/Y') }}
                    @else
                        Plan du {{ \Carbon\Carbon::parse($weekStart)->format('d/m/Y') }}
                        <span class="text-fm-muted font-normal text-base">· {{ $horizonDays }} jours</span>
                    @endif
                </h1>
                @if ($planContext->isFriend())
                    <p class="text-sm text-fm-muted mt-1">
                        Plan de <strong class="text-fm-text">{{ $planContext->planOwner->name }}</strong>
                        · objectifs kcal : les tiens ({{ $calorieTarget }} kcal/j)
                    </p>
                @elseif ($activeProgram)
                    <p class="text-sm text-fm-muted mt-1">Programme : {{ $activeProgram->name }}</p>
                @else
                    <p class="text-sm text-fm-muted mt-1">Mon plan · horizon {{ $planningHorizon }} jours</p>
                @endif
                @if ($weekCost['entry_count'] > 0 || $weekCost['has_prices'])
                    <p class="text-sm mt-2">
                        @if ($weekCost['priced_count'] > 0)
                            Coût période :
                            <strong class="tabular-nums">≈ {{ number_format($weekCost['spent'], 2, ',', ' ') }} €</strong>
                            <span class="text-fm-muted">({{ $weekCost['priced_count'] }}/{{ $weekCost['entry_count'] }} repas estimés)</span>
                        @elseif ($weekCost['has_prices'])
                            <span class="text-fm-muted">Aucun coût estimé sur cette période —</span>
                            <a href="{{ route('settings.budget') }}" wire:navigate class="text-fm-primary hover:underline">vérifier les prix</a>
                        @else
                            <a href="{{ route('settings.budget') }}" wire:navigate class="text-fm-primary hover:underline">Renseigner mes prix</a>
                            <span class="text-fm-muted">pour voir le coût de la période.</span>
                        @endif
                    </p>
                @endif
            </div>
            <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3 w-full sm:w-auto">
                <select wire:model.live="planContextKey" class="fm-input text-sm w-full sm:min-w-[12rem] sm:w-auto">
                    <option value="self">Mon plan</option>
                    @foreach ($contexts['friends'] as $share)
                        <option value="friend:{{ $share->owner_id }}">Ami : {{ $share->owner->name }}</option>
                    @endforeach
                    @foreach ($contexts['programs'] as $prog)
                        <option value="program:{{ $prog->id }}">Programme : {{ $prog->name }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2 w-full sm:w-auto">
                    <button wire:click="previousWeek" type="button" class="fm-btn-sm flex-1 sm:flex-none">← {{ $activeProgram ? 'Semaine' : 'Période' }}</button>
                    <button wire:click="nextWeek" type="button" class="fm-btn-sm flex-1 sm:flex-none">{{ $activeProgram ? 'Semaine' : 'Période' }} →</button>
                </div>
                @if ($canEdit)
                    <button
                        type="button"
                        wire:click="$dispatch('open-ai-week-generator')"
                        class="fm-btn flex-1 sm:flex-none"
                    >
                        Générer avec l'IA
                    </button>
                    <button
                        type="button"
                        wire:click="openRangePanel('copy')"
                        class="fm-btn-sm flex-1 sm:flex-none"
                    >
                        Dupliquer
                    </button>
                    <button
                        type="button"
                        wire:click="openRangePanel('clear')"
                        class="fm-btn-sm flex-1 sm:flex-none"
                    >
                        Vider
                    </button>
                @endif
            </div>
        </div>

        @if ($canEdit && $showRangePanel)
            <div class="mb-6 p-4 rounded-lg bg-fm-bg border border-fm-border space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-medium">
                        {{ $rangeAction === 'copy' ? 'Dupliquer une journée / plage' : 'Vider une journée / plage' }}
                    </h2>
                    <div class="flex gap-2 text-xs">
                        <button
                            type="button"
                            wire:click="$set('rangeAction', 'copy')"
                            @class([
                                'px-2 py-1 rounded border',
                                'border-fm-primary text-fm-primary' => $rangeAction === 'copy',
                                'border-fm-border text-fm-muted' => $rangeAction !== 'copy',
                            ])
                        >Dupliquer</button>
                        <button
                            type="button"
                            wire:click="$set('rangeAction', 'clear')"
                            @class([
                                'px-2 py-1 rounded border',
                                'border-fm-primary text-fm-primary' => $rangeAction === 'clear',
                                'border-fm-border text-fm-muted' => $rangeAction !== 'clear',
                            ])
                        >Vider</button>
                    </div>
                </div>

                @if ($rangeError)
                    <p class="text-sm text-fm-accent">{{ $rangeError }}</p>
                @endif

                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <label class="block text-sm">
                        <span class="text-caption text-fm-muted">{{ $rangeAction === 'copy' ? 'Source — du' : 'Du' }}</span>
                        <input type="date" wire:model="rangeSourceStart" class="fm-input mt-1 w-full">
                    </label>
                    <label class="block text-sm">
                        <span class="text-caption text-fm-muted">{{ $rangeAction === 'copy' ? 'Source — au' : 'Au' }}</span>
                        <input type="date" wire:model="rangeSourceEnd" class="fm-input mt-1 w-full">
                    </label>
                    @if ($rangeAction === 'copy')
                        <label class="block text-sm">
                            <span class="text-caption text-fm-muted">Coller à partir du</span>
                            <input type="date" wire:model="rangeTargetStart" class="fm-input mt-1 w-full">
                        </label>
                    @endif
                </div>

                <p class="text-xs text-fm-muted">
                    @if ($rangeAction === 'copy')
                        Chaque jour de la source remplace le jour cible correspondant (jour 1 → date de collage, jour 2 → lendemain, etc.). Max {{ \App\Services\Plan\PlanRangeService::MAX_DAYS }} jours.
                    @else
                        Supprime toutes les entrées de la plage. Max {{ \App\Services\Plan\PlanRangeService::MAX_DAYS }} jours.
                    @endif
                </p>

                <div class="flex flex-wrap gap-2 justify-end">
                    <button type="button" wire:click="closeRangePanel" class="fm-btn-sm">Annuler</button>
                    <button
                        type="button"
                        wire:click="applyRangeAction"
                        wire:confirm="{{ $rangeAction === 'clear' ? 'Vider cette plage ? Les repas seront définitivement supprimés.' : 'Dupliquer et remplacer les jours cibles ?' }}"
                        class="fm-btn"
                    >
                        {{ $rangeAction === 'copy' ? 'Dupliquer' : 'Vider la plage' }}
                    </button>
                </div>
            </div>
        @endif

        <livewire:ai-week-generator
            :meal-plan-id="$mealPlanId"
            :week-start="$weekStart"
            :horizon-days="$horizonDays"
            :can-edit="$canEdit"
            :key="'ai-week-'.$mealPlanId.'-'.$weekStart.'-'.$horizonDays"
        />

        @if (! $canEdit)
            <p class="text-sm text-fm-accent mb-4">
                @if ($planContext->isFriend())
                    Lecture seule — tu n'as pas l'autorisation de modifier ce plan.
                @elseif ($activeProgram)
                    Grammages verrouillés par le propriétaire du programme.
                @else
                    Consultation seule.
                @endif
            </p>
        @endif

        {{-- Panneau abonnés (mon plan) --}}
        @if ($planContext->isSelf())
            <div class="mb-6 p-4 rounded-lg bg-fm-bg border border-fm-border space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-medium">Qui suit mon plan</h2>
                    <button type="button" wire:click="toggleInvitePanel" class="text-xs text-fm-primary hover:underline">
                        Inviter un ami à suivre
                    </button>
                </div>

                @if ($showInvitePanel)
                    <div class="flex flex-wrap items-end gap-3 pt-2 border-t border-fm-border">
                        <div class="flex-1 min-w-[10rem]">
                            <label class="text-caption text-fm-muted">Ami</label>
                            <select wire:model="inviteFriendId" class="fm-input text-sm mt-1">
                                <option value="">Choisir…</option>
                                @foreach ($friends as $friend)
                                    <option value="{{ $friend->id }}">{{ $friend->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="flex items-center gap-2 text-sm pb-2">
                            <input type="checkbox" wire:model="inviteCanEdit">
                            Autoriser les modifications
                        </label>
                        <button type="button" wire:click="inviteFriend" class="fm-btn-primary text-sm">Envoyer</button>
                    </div>
                @endif

                @if ($followers->isEmpty())
                    <p class="text-xs text-fm-muted">Personne ne suit ton plan pour le moment.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($followers as $share)
                            <li class="flex flex-wrap items-center justify-between gap-2 text-sm" wire:key="follower-{{ $share->id }}">
                                <span>{{ $share->viewer->name }}</span>
                                <span class="flex items-center gap-3">
                                    <label class="flex items-center gap-1.5 text-xs text-fm-muted">
                                        <input
                                            type="checkbox"
                                            @checked($share->can_edit)
                                            wire:change="toggleFollowerCanEdit({{ $share->id }}, $event.target.checked)"
                                        >
                                        Modifications
                                    </label>
                                    <span @class([
                                        'text-xs px-2 py-0.5 rounded',
                                        'bg-fm-primary/10 text-fm-primary' => $share->can_edit,
                                        'bg-fm-surface text-fm-muted' => ! $share->can_edit,
                                    ])>
                                        {{ $share->can_edit ? 'Peut modifier' : 'Lecture seule' }}
                                    </span>
                                    <button
                                        wire:click="revokeFollower({{ $share->id }})"
                                        wire:confirm="Révoquer l'accès de {{ $share->viewer->name }} ?"
                                        type="button"
                                        class="text-xs text-fm-accent hover:underline"
                                    >
                                        Révoquer
                                    </button>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        {{-- Panneau membres programme --}}
        @if ($activeProgram && $programMembers->isNotEmpty())
            <div class="mb-6 p-4 rounded-lg bg-fm-bg border border-fm-border space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-medium">Membres du programme</h2>
                    @if ($activeProgram->owner_id === auth()->id())
                        <label class="flex items-center gap-2 text-xs text-fm-muted">
                            <input
                                type="checkbox"
                                @checked($activeProgram->lock_portions)
                                wire:change="setProgramLock({{ $activeProgram->id }}, $event.target.checked)"
                            >
                            Verrouiller les grammages
                        </label>
                    @endif
                </div>
                <ul class="space-y-1.5 text-sm">
                    @foreach ($programMembers as $member)
                        <li class="flex justify-between">
                            <span>{{ $member['name'] }} <span class="text-caption text-fm-muted">({{ $member['role'] }})</span></span>
                            <span @class([
                                'text-xs',
                                'text-fm-primary' => $member['can_edit'],
                                'text-fm-muted' => ! $member['can_edit'],
                            ])>
                                {{ $member['can_edit'] ? 'Peut modifier' : 'Lecture seule' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $slotAbbreviations = [
                'morning_snack' => 'Coll. mat.',
                'breakfast' => 'Matin',
                'lunch' => 'Midi',
                'afternoon_snack' => 'Goûter',
                'dinner' => 'Soir',
                'night_snack' => 'Coll. nuit',
            ];
        @endphp

        {{-- Mobile / tablette : cards --}}
        <div @class([
            'grid gap-3 overflow-x-auto lg:hidden',
            'grid-cols-1 xs:grid-cols-2 md:grid-cols-3' => $horizonDays <= 7,
            'grid-flow-col auto-cols-[minmax(9rem,1fr)]' => $horizonDays > 7,
        ]) style="{{ $horizonDays > 7 ? 'grid-template-columns: repeat('.$horizonDays.', minmax(9rem, 1fr));' : '' }}">
            @foreach ($days as $day)
                @php
                    $key = $day->toDateString();
                    $summary = $daySummaries[$key];
                    $isToday = $day->isToday();
                @endphp
                <div @class([
                    'rounded-lg border p-3 flex flex-col',
                    'border-fm-primary/60' => $isToday,
                    'border-fm-border' => ! $isToday,
                ])>
                    <div class="flex justify-between items-start mb-3">
                        <p class="font-medium text-sm">
                            {{ $day->translatedFormat('D d/m') }}
                            @if ($isToday)
                                <span class="text-fm-primary text-xs">· auj.</span>
                            @endif
                        </p>
                        <div class="text-right text-xs">
                            <span @class([
                                'block tabular-nums',
                                'text-fm-accent' => ($summary['totals']['energy_kcal'] ?? 0) > $calorieTarget,
                                'text-fm-primary' => ($summary['totals']['energy_kcal'] ?? 0) <= $calorieTarget,
                            ])>{{ (int) ($summary['totals']['energy_kcal'] ?? 0) }} kcal</span>
                            @if ($summary['cost'] !== null)
                                <span class="text-fm-muted tabular-nums">{{ number_format($summary['cost'], 2, ',', ' ') }} €</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex-1 space-y-2 text-xs">
                        @foreach ($slots as $slotKey => $slotLabel)
                            @if (! empty($summary['by_slot'][$slotKey]))
                                <div>
                                    <p class="text-fm-muted">{{ $slotLabel }}</p>
                                    <p class="truncate">{{ implode(', ', $summary['by_slot'][$slotKey]) }}</p>
                                </div>
                            @endif
                        @endforeach
                        @if ($summary['entry_count'] === 0)
                            <p class="text-fm-muted italic">Rien de prévu</p>
                        @endif
                    </div>

                    <a
                        href="{{ route('planner.day', array_filter(['date' => $key, 'program' => $programId, 'view' => $viewUserId])) }}"
                        wire:navigate
                        @class([
                            'mt-3 text-center text-sm px-4 py-2.5 rounded-lg border transition-colors min-h-touch inline-flex items-center justify-center w-full',
                            'border-fm-primary text-fm-primary hover:bg-fm-primary/10' => $canEdit,
                            'border-fm-border text-fm-muted' => ! $canEdit,
                        ])
                    >
                        {{ $canEdit ? ($summary['entry_count'] > 0 ? 'Modifier' : 'Remplir') : 'Voir' }}
                    </a>
                </div>
            @endforeach
        </div>

        {{-- Desktop lg+ : lignes jour × colonnes créneaux --}}
        <div class="hidden lg:block overflow-x-auto -mx-1">
            <table class="w-full min-w-[64rem] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-fm-border text-left text-caption text-fm-muted">
                        <th class="sticky left-0 z-10 bg-fm-surface py-2.5 pr-3 font-medium w-28">Jour</th>
                        @foreach ($slots as $slotKey => $slotLabel)
                            <th class="px-2 py-2.5 font-medium whitespace-nowrap" title="{{ $slotLabel }}">
                                {{ $slotAbbreviations[$slotKey] ?? $slotLabel }}
                            </th>
                        @endforeach
                        <th class="px-2 py-2.5 font-medium whitespace-nowrap">Macros</th>
                        <th class="px-2 py-2.5 font-medium whitespace-nowrap">€</th>
                        <th class="pl-2 py-2.5 font-medium w-28"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($days as $day)
                        @php
                            $key = $day->toDateString();
                            $summary = $daySummaries[$key];
                            $isToday = $day->isToday();
                            $kcal = (int) ($summary['totals']['energy_kcal'] ?? 0);
                            $protein = round($summary['totals']['protein_g'] ?? 0);
                            $carbs = round($summary['totals']['carbs_g'] ?? 0);
                            $fat = round($summary['totals']['fat_g'] ?? 0);
                        @endphp
                        <tr @class([
                            'border-b border-fm-border/70 align-top hover:bg-fm-bg/60',
                            'bg-fm-primary/5' => $isToday,
                        ])>
                            <td @class([
                                'sticky left-0 z-10 py-3 pr-3',
                                'bg-fm-primary/5' => $isToday,
                                'bg-fm-surface' => ! $isToday,
                            ])>
                                <p class="font-medium whitespace-nowrap">
                                    {{ $day->translatedFormat('D d/m') }}
                                    @if ($isToday)
                                        <span class="text-fm-primary text-xs font-normal">· auj.</span>
                                    @endif
                                </p>
                            </td>
                            @foreach ($slots as $slotKey => $slotLabel)
                                @php
                                    $items = $summary['by_slot'][$slotKey] ?? [];
                                    $cellText = $items !== [] ? implode(', ', $items) : null;
                                @endphp
                                <td class="px-2 py-3 max-w-[9rem]">
                                    @if ($cellText)
                                        <p class="text-xs leading-snug line-clamp-3" title="{{ $cellText }}">{{ $cellText }}</p>
                                    @else
                                        <span class="text-fm-muted/50">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-2 py-3 whitespace-nowrap">
                                <span @class([
                                    'block tabular-nums font-medium',
                                    'text-fm-accent' => $kcal > $calorieTarget,
                                    'text-fm-primary' => $kcal <= $calorieTarget,
                                ])>{{ $kcal }} kcal</span>
                                <span class="text-xs text-fm-muted tabular-nums">
                                    <span class="text-fm-protein">P{{ $protein }}</span>
                                    · <span class="text-fm-carbs">G{{ $carbs }}</span>
                                    · <span class="text-fm-fat">L{{ $fat }}</span>
                                </span>
                            </td>
                            <td class="px-2 py-3 whitespace-nowrap text-xs text-fm-muted tabular-nums">
                                @if ($summary['cost'] !== null)
                                    {{ number_format($summary['cost'], 2, ',', ' ') }} €
                                @else
                                    —
                                @endif
                            </td>
                            <td class="pl-2 py-3 text-right">
                                <a
                                    href="{{ route('planner.day', array_filter(['date' => $key, 'program' => $programId, 'view' => $viewUserId])) }}"
                                    wire:navigate
                                    @class([
                                        'inline-flex items-center justify-center text-sm px-3 py-1.5 rounded-lg border transition-colors min-h-touch whitespace-nowrap',
                                        'border-fm-primary text-fm-primary hover:bg-fm-primary/10' => $canEdit,
                                        'border-fm-border text-fm-muted' => ! $canEdit,
                                    ])
                                >
                                    {{ $canEdit ? ($summary['entry_count'] > 0 ? 'Modifier' : 'Remplir') : 'Voir' }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 fm-panel">
            <h2 class="text-sm font-medium mb-4">Kcal par jour — période</h2>
            <canvas
                data-chart="bar"
                data-mixed="1"
                data-labels='@json($kcalChartLabels)'
                data-datasets='@json($kcalChartDatasets)'
            </canvas>
        </div>

        <p class="text-xs text-fm-muted mt-6">
            Objectif journalier (tes cibles) : {{ $calorieTarget }} kcal
            @if ($projection['kg'] > 0)
                · Projection indicative : {{ $projection['label'] }} (7700 kcal ≈ 1 kg).
            @endif
        </p>
    </div>
</div>
