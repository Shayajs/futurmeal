<div class="fm-container grid grid-cols-1 lg:grid-cols-2 gap-6">
    @if (session('program-status'))
        <p class="lg:col-span-2 text-sm text-fm-primary">{{ session('program-status') }}</p>
    @endif
    @if (session('program-invite-link'))
        <div class="lg:col-span-2 fm-panel text-sm">
            <p class="text-fm-muted mb-2">Lien d'invitation (valide 7 jours) :</p>
            <code class="text-fm-primary break-all">{{ session('program-invite-link') }}</code>
        </div>
    @endif

    <div class="fm-panel space-y-4">
        <h2 class="text-h3 font-medium">Créer un programme</h2>
        <input wire:model="name" placeholder="Nom (ex: Semaine cut couple)" class="fm-input">
        <textarea wire:model="description" placeholder="Description" class="fm-input" rows="2"></textarea>
        <button wire:click="createProgram" type="button" class="fm-btn-primary">Créer</button>
    </div>
    <div class="fm-panel space-y-4">
        <h2 class="text-h3 font-medium">Rejoindre</h2>
        <input wire:model="invite_code" placeholder="Code invitation" class="fm-input uppercase">
        <button wire:click="joinProgram" type="button" class="fm-btn-accent">Rejoindre</button>
        <p class="text-caption text-fm-muted">Ou utilise un lien d'invitation envoyé par le propriétaire.</p>
    </div>
    <div class="lg:col-span-2 space-y-4">
        @foreach ($memberships as $item)
            @php
                $program = $item['program'];
                $membership = $item['membership'];
            @endphp
            <div class="fm-panel">
                <div class="flex flex-wrap justify-between items-start gap-4">
                    <div>
                        <h3 class="font-medium">{{ $program->name }}</h3>
                        <p class="text-sm text-fm-muted">
                            Code : <code class="text-fm-primary">{{ $program->invite_code }}</code>
                            · {{ $item['member_count'] }}/{{ $item['max_members'] }} membres
                        </p>
                        @if ($item['adherence'] !== null)
                            <p class="text-sm text-fm-muted mt-1">Ton adhérence (7j) : <strong class="text-fm-text">{{ $item['adherence'] }} %</strong></p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-4 items-center">
                        @if ($membership->role->value === 'owner')
                            <label class="text-sm flex items-center gap-2">
                                <input type="checkbox" wire:click="toggleLockPortions({{ $program->id }})" @checked($program->lock_portions)>
                                Verrouiller les grammages
                            </label>
                            <button
                                type="button"
                                wire:click="generateInviteLink({{ $program->id }})"
                                class="fm-btn-ghost text-sm"
                            >
                                Générer un lien
                            </button>
                        @endif
                        <label class="text-sm flex items-center gap-2">
                            <input type="checkbox" wire:click="toggleShareMetrics({{ $program->id }})" @checked($membership->share_metrics)>
                            Partager mes métriques
                        </label>
                        <a href="{{ route('planner', ['program' => $program->id]) }}" wire:navigate class="fm-btn-ghost text-sm">Ouvrir le plan →</a>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($program->members as $member)
                        <span class="px-3 py-1 rounded-full text-xs bg-fm-bg border border-fm-border">
                            {{ $member->user->name }}
                            @if ($member->share_metrics) · 📊 @endif
                        </span>
                    @endforeach
                </div>

                @if ($membership->role->value === 'owner' && $item['member_adherence']->isNotEmpty())
                    <div class="mt-4 overflow-x-auto">
                        <h4 class="text-caption text-fm-muted mb-2">Adhérence du groupe (7j)</h4>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-fm-muted text-left">
                                    <th class="pb-2 font-normal">Membre</th>
                                    <th class="pb-2 font-normal">Adhérence</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($item['member_adherence'] as $row)
                                    <tr class="border-t border-fm-border">
                                        <td class="py-2">{{ $row['name'] }}</td>
                                        <td class="py-2 tabular-nums">{{ $row['adherence'] ?? '—' }} %</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($item['shared_metrics']->isNotEmpty())
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-fm-muted text-left">
                                    <th class="pb-2 font-normal">Membre</th>
                                    <th class="pb-2 font-normal">Poids</th>
                                    <th class="pb-2 font-normal">Graisse</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($item['shared_metrics'] as $metric)
                                    <tr class="border-t border-fm-border">
                                        <td class="py-2">{{ $metric['name'] }}</td>
                                        <td class="py-2 tabular-nums">{{ $metric['weight_kg'] ?? '—' }} kg</td>
                                        <td class="py-2 tabular-nums">{{ $metric['body_fat_percent'] ?? '—' }} %</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
