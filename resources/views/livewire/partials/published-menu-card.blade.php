<div class="fm-panel flex flex-col" wire:key="menu-{{ $menu->id }}">
    <div class="flex-1">
        <div class="flex items-start justify-between gap-2">
            <h3 class="font-medium">{{ $menu->title }}</h3>
            <span class="text-xs text-fm-primary tabular-nums shrink-0">{{ $menu->totalKcal() }} kcal</span>
        </div>
        <p class="text-caption text-fm-muted mt-1">
            par {{ $menu->user->name }}
            @if ($menu->copies_count > 0)
                · récupéré {{ $menu->copies_count }} fois
            @endif
            @unless ($menu->is_public)
                · privé
            @endunless
        </p>
        @if ($menu->description)
            <p class="text-sm text-fm-muted mt-2">{{ $menu->description }}</p>
        @endif
        <ul class="mt-3 space-y-1 text-xs text-fm-muted">
            @foreach ($menu->day_snapshot as $slotKey => $items)
                <li>
                    <span class="text-fm-text">{{ $slots[$slotKey] ?? $slotKey }} :</span>
                    {{ collect($items)->pluck('label')->take(3)->implode(', ') }}@if (count($items) > 3)…@endif
                </li>
            @endforeach
        </ul>
    </div>
    <div class="mt-4 flex gap-2">
        <button wire:click="startApply({{ $menu->id }})" type="button" class="fm-btn-primary text-sm flex-1">Récupérer</button>
        @if ($own)
            <button
                wire:click="unpublish({{ $menu->id }})"
                wire:confirm="Dépublier ce menu ?"
                type="button"
                class="fm-btn-ghost text-sm text-fm-accent"
            >
                Dépublier
            </button>
        @endif
    </div>
</div>
