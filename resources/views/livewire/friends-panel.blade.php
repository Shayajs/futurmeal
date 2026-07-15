<div class="fm-container max-w-2xl space-y-6">
    <div>
        <p class="text-caption text-fm-muted">
            <a href="{{ route('settings') }}" wire:navigate class="text-fm-primary hover:underline">← Paramètres</a>
        </p>
        <h1 class="text-h2 font-semibold mt-1">Amis</h1>
        <p class="text-sm text-fm-muted mt-1">Invite tes proches pour partager menus et programmes.</p>
    </div>

    @if (session('friends-status'))
        <p class="text-sm text-fm-primary">{{ session('friends-status') }}</p>
    @endif

    {{-- Mon code + lien --}}
    <div class="fm-panel space-y-3">
        <h2 class="text-sm font-medium">Mon code ami</h2>
        <div class="flex flex-wrap items-center gap-3">
            <code class="text-lg text-fm-primary tracking-widest">{{ $friendCode }}</code>
            <button
                type="button"
                onclick="navigator.clipboard.writeText('{{ $shareLink }}'); this.textContent='Lien copié !'; setTimeout(() => this.textContent='Copier le lien de partage', 2000)"
                class="fm-btn-ghost text-sm"
            >
                Copier le lien de partage
            </button>
        </div>
        <p class="text-caption text-fm-muted">Envoie ce lien (WhatsApp, SMS…) — la personne connectée deviendra automatiquement ta demande d'ami.</p>
    </div>

    {{-- Ajouter --}}
    <div class="fm-panel space-y-4">
        <h2 class="text-sm font-medium">Ajouter un ami</h2>
        <div>
            <input
                wire:model.live.debounce.300ms="search"
                type="search"
                placeholder="Rechercher par pseudo ou email…"
                class="fm-input"
            >
            @if (count($searchResults))
                <ul class="mt-2 rounded-lg border border-fm-border divide-y divide-fm-border">
                    @foreach ($searchResults as $result)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                            <span class="text-sm min-w-0 truncate">{{ $result['name'] }} <span class="text-fm-muted text-xs">{{ $result['email'] }}</span></span>
                            <button wire:click="sendRequest({{ $result['id'] }})" type="button" class="fm-btn-action-primary shrink-0">Inviter</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <label class="flex items-center gap-2 text-sm mb-3">
            <input type="checkbox" wire:model="inviteCanEdit">
            Autoriser les modifications quand j'invite à suivre mon plan
        </label>
        <div class="flex gap-2">
            <input wire:model="inviteCode" placeholder="Ou entre un code ami" class="fm-input uppercase flex-1">
            <button wire:click="addByCode" type="button" class="fm-btn-primary text-sm">Ajouter</button>
        </div>
        @error('inviteCode') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
    </div>

    {{-- Demandes reçues --}}
    @if ($pendingReceived->isNotEmpty())
        <div class="fm-panel space-y-3">
            <h2 class="text-sm font-medium">Demandes reçues</h2>
            @foreach ($pendingReceived as $request)
                <div class="flex items-center justify-between">
                    <span class="text-sm">{{ $request->user->name }}</span>
                    <span class="flex gap-3">
                        <button wire:click="accept({{ $request->id }})" type="button" class="text-sm text-fm-primary hover:underline">Accepter</button>
                        <button wire:click="remove({{ $request->id }})" type="button" class="text-sm text-fm-accent hover:underline">Refuser</button>
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Demandes envoyées --}}
    @if ($pendingSent->isNotEmpty())
        <div class="fm-panel space-y-3">
            <h2 class="text-sm font-medium">Demandes envoyées</h2>
            @foreach ($pendingSent as $request)
                <div class="flex items-center justify-between">
                    <span class="text-sm">{{ $request->friend->name }} <span class="text-caption text-fm-muted">en attente</span></span>
                    <button wire:click="remove({{ $request->id }})" type="button" class="text-sm text-fm-muted hover:underline">Annuler</button>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Liste amis --}}
    <div class="fm-panel space-y-3">
        <h2 class="text-sm font-medium">Mes amis ({{ $friends->count() }})</h2>
        @forelse ($friends as $friend)
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-sm">{{ $friend->name }}</span>
                <span class="flex flex-wrap gap-2">
                    <button
                        wire:click="requestFollowPlan({{ $friend->id }})"
                        type="button"
                        class="text-xs text-fm-primary hover:underline"
                    >
                        Suivre son plan
                    </button>
                    <button
                        wire:click="inviteFollowPlan({{ $friend->id }})"
                        type="button"
                        class="text-xs text-fm-muted hover:underline"
                    >
                        Lui proposer le mien
                    </button>
                </span>
            </div>
        @empty
            <p class="text-sm text-fm-muted">Pas encore d'amis — partage ton code ou ton lien.</p>
        @endforelse
    </div>
</div>
