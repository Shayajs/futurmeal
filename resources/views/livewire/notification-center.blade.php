<div class="fm-container max-w-2xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-h2 font-semibold">Notifications</h1>
            <p class="text-sm text-fm-muted mt-1">Demandes d'amis et partages de plans.</p>
        </div>
        @if ($unreadCount > 0)
            <button type="button" wire:click="markAllRead" class="text-sm text-fm-primary hover:underline">
                Tout marquer lu ({{ $unreadCount }})
            </button>
        @endif
    </div>

    @if ($notifications->isEmpty())
        <div class="fm-panel">
            <p class="text-sm text-fm-muted">Aucune notification.</p>
        </div>
    @else
        @if ($friendNotifs->isNotEmpty())
            <section class="space-y-3">
                <h2 class="text-sm font-medium">Amis</h2>
                <ul class="fm-panel divide-y divide-fm-border">
                    @foreach ($friendNotifs as $notification)
                        <li @class(['py-3 first:pt-0 last:pb-0', 'opacity-70' => $notification->read_at]) wire:key="n-{{ $notification->id }}">
                            <p class="text-sm">{{ $notification->data['message'] ?? '' }}</p>
                            <p class="text-caption text-fm-muted mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                            @if (($notification->data['type'] ?? '') === 'friend_request' && ! $notification->read_at)
                                <div class="flex flex-wrap gap-3 mt-2">
                                    <button
                                        wire:click="acceptFriend({{ $notification->data['friendship_id'] }})"
                                        type="button"
                                        class="fm-btn-action-primary"
                                    >
                                        Accepter
                                    </button>
                                    <button
                                        wire:click="rejectFriend({{ $notification->data['friendship_id'] }})"
                                        type="button"
                                        class="fm-btn-action-danger"
                                    >
                                        Refuser
                                    </button>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($planNotifs->isNotEmpty())
            <section class="space-y-3">
                <h2 class="text-sm font-medium">Plans partagés</h2>
                <ul class="fm-panel divide-y divide-fm-border">
                    @foreach ($planNotifs as $notification)
                        @php
                            $shareId = $notification->data['plan_share_id'] ?? null;
                            $type = $notification->data['type'] ?? '';
                            $isPending = in_array($type, ['plan_follow_request', 'plan_follow_invite'], true);
                        @endphp
                        <li @class(['py-3 first:pt-0 last:pb-0', 'opacity-70' => $notification->read_at]) wire:key="pn-{{ $notification->id }}">
                            <p class="text-sm">{{ $notification->data['message'] ?? '' }}</p>
                            @if ($type === 'plan_follow_invite' && isset($notification->data['can_edit']))
                                <p class="text-caption text-fm-muted mt-1">
                                    {{ $notification->data['can_edit'] ? 'Modifications autorisées' : 'Lecture seule' }}
                                </p>
                            @endif
                            <p class="text-caption text-fm-muted mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                            @if ($isPending && $shareId && ! $notification->read_at)
                                @if ($type === 'plan_follow_request')
                                    <label class="flex items-center gap-2 text-xs text-fm-muted mt-2">
                                        <input type="checkbox" wire:model="acceptPlanCanEdit.{{ $shareId }}">
                                        Autoriser les modifications
                                    </label>
                                @endif
                                <div class="flex flex-wrap gap-3 mt-2">
                                    <button
                                        wire:click="acceptPlanShare({{ $shareId }})"
                                        type="button"
                                        class="fm-btn-action-primary"
                                    >
                                        Accepter
                                    </button>
                                    <button
                                        wire:click="rejectPlanShare({{ $shareId }})"
                                        type="button"
                                        class="fm-btn-action-danger"
                                    >
                                        Refuser
                                    </button>
                                </div>
                            @elseif ($type === 'plan_share_accepted' && $shareId)
                                <a
                                    href="{{ route('planner', ['view' => $notification->data['from_user_id'] ?? null]) }}"
                                    wire:navigate
                                    class="text-xs text-fm-primary hover:underline mt-2 inline-block"
                                >
                                    Voir le plan →
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
</div>
