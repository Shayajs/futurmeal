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
                @endif
            </div>
        </div>

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

        <div @class([
            'grid gap-3 overflow-x-auto',
            'grid-cols-1 xs:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7' => $horizonDays <= 7,
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
