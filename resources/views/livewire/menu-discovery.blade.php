<div class="fm-container space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-h2 font-semibold">Découvrir</h1>
            <p class="text-sm text-fm-muted mt-1">Récupère les menus publiés par la communauté et tes amis.</p>
        </div>
        <input
            wire:model.live.debounce.300ms="search"
            type="search"
            placeholder="Rechercher un menu…"
            class="fm-input w-full sm:w-64"
        >
    </div>

    @if (session('discover-status'))
        <p class="text-sm text-fm-primary">{{ session('discover-status') }}</p>
    @endif

    {{-- Panneau d'application --}}
    @if ($applyingMenuId)
        <div class="fm-panel border-fm-primary/40 flex flex-wrap items-end gap-4">
            <div>
                <label class="text-caption text-fm-muted">Appliquer ce menu au jour :</label>
                <input type="date" wire:model="applyDate" class="fm-input mt-1">
                @error('applyDate') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2">
                <button wire:click="apply" type="button" class="fm-btn-primary text-sm">Appliquer</button>
                <button wire:click="cancelApply" type="button" class="fm-btn-ghost text-sm">Annuler</button>
            </div>
            <p class="text-caption text-fm-muted w-full">La journée cible sera remplacée par le contenu du menu.</p>
        </div>
    @endif

    {{-- Menus des amis --}}
    @if ($friendMenus->isNotEmpty())
        <section class="space-y-3">
            <h2 class="text-sm font-medium">Menus de mes amis</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($friendMenus as $menu)
                    @include('livewire.partials.published-menu-card', ['menu' => $menu, 'own' => false])
                @endforeach
            </div>
        </section>
    @endif

    {{-- Catalogue public --}}
    <section class="space-y-3">
        <h2 class="text-sm font-medium">Catalogue public</h2>
        @if ($publicMenus->isEmpty())
            <p class="text-sm text-fm-muted">Aucun menu public{{ $search ? ' pour cette recherche' : ' pour le moment' }}.</p>
        @else
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($publicMenus as $menu)
                    @include('livewire.partials.published-menu-card', ['menu' => $menu, 'own' => false])
                @endforeach
            </div>
            {{ $publicMenus->links() }}
        @endif
    </section>

    {{-- Mes publications --}}
    <section class="space-y-3">
        <h2 class="text-sm font-medium">Mes menus publiés</h2>
        @if ($myMenus->isEmpty())
            <p class="text-sm text-fm-muted">
                Tu n'as rien publié — depuis l'<a href="{{ route('planner') }}" wire:navigate class="text-fm-primary hover:underline">éditeur d'une journée</a>, clique « Publier ce menu ».
            </p>
        @else
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($myMenus as $menu)
                    @include('livewire.partials.published-menu-card', ['menu' => $menu, 'own' => true])
                @endforeach
            </div>
        @endif
    </section>
</div>
