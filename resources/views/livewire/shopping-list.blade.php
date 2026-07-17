<div class="fm-container space-y-4 lg:space-y-6 pb-28 lg:pb-8">
    {{-- Mobile sticky progress --}}
    <div class="lg:hidden sticky top-[var(--fm-nav-height,3.5rem)] z-30 -mx-4 px-4 py-2 bg-fm-bg/95 backdrop-blur border-b border-fm-border">
        <div class="flex items-center justify-between gap-3 mb-1.5">
            <p class="text-sm font-medium truncate">Courses</p>
            <p class="text-xs tabular-nums text-fm-muted shrink-0">{{ $checkedCount }}/{{ $totalCount }}</p>
        </div>
        <div class="h-1.5 rounded-full bg-fm-border overflow-hidden">
            <div
                class="h-full rounded-full bg-fm-primary transition-all"
                style="width: {{ $totalCount > 0 ? round(($checkedCount / $totalCount) * 100) : 0 }}%"
            ></div>
        </div>
    </div>

    <div class="grid lg:grid-cols-[minmax(16rem,20rem)_1fr] xl:grid-cols-[22rem_1fr] gap-6 lg:gap-8 items-start">
        {{-- Colonne contextuelle : sticky desktop --}}
        <aside class="space-y-4 lg:sticky lg:top-24">
            <div>
                <h1 class="text-h2 font-semibold hidden lg:block">Faire mes courses</h1>
                <h1 class="text-h3 font-semibold lg:hidden">Faire mes courses</h1>
                <p class="text-sm text-fm-muted mt-1">
                    {{ \Carbon\Carbon::parse($rangeStart)->translatedFormat('d M') }}
                    → {{ \Carbon\Carbon::parse($rangeEnd)->translatedFormat('d M Y') }}
                    <span class="text-fm-muted">· {{ $horizonDays }} j</span>
                </p>
            </div>

            @if ($statusMessage)
                <p class="text-sm text-fm-primary">{{ $statusMessage }}</p>
            @endif

            {{-- Période : pleine largeur mobile --}}
            <div class="grid grid-cols-2 gap-2">
                <button type="button" wire:click="previousPeriod" class="fm-btn-sm min-h-touch w-full justify-center">← Période</button>
                <button type="button" wire:click="nextPeriod" class="fm-btn-sm min-h-touch w-full justify-center">Période →</button>
            </div>

            {{-- Stats + actions : panel desktop, compact mobile --}}
            <div class="fm-panel space-y-3 hidden lg:block">
                <div class="flex items-baseline justify-between gap-2">
                    <p class="text-sm text-fm-muted">Progression</p>
                    <p class="text-lg font-semibold tabular-nums">
                        {{ $checkedCount }}<span class="text-fm-muted font-normal text-sm"> / {{ $totalCount }}</span>
                    </p>
                </div>
                <div class="h-2 rounded-full bg-fm-border overflow-hidden">
                    <div
                        class="h-full rounded-full bg-fm-primary transition-all"
                        style="width: {{ $totalCount > 0 ? round(($checkedCount / $totalCount) * 100) : 0 }}%"
                    ></div>
                </div>
                <div class="flex flex-col gap-2 pt-1">
                    <button type="button" wire:click="resync" class="fm-btn-sm w-full justify-center">Resynchroniser le plan</button>
                    @if ($checkedCount > 0)
                        <button type="button" wire:click="uncheckAll" class="fm-btn-sm w-full justify-center">Tout décocher</button>
                    @endif
                    <a href="{{ route('planner') }}" wire:navigate class="text-center text-sm text-fm-primary hover:underline min-h-touch inline-flex items-center justify-center">
                        Voir le plan →
                    </a>
                </div>
            </div>

            <div class="flex gap-2 lg:hidden">
                <button type="button" wire:click="resync" class="fm-btn-sm flex-1 min-h-touch justify-center">Resync</button>
                @if ($checkedCount > 0)
                    <button type="button" wire:click="uncheckAll" class="fm-btn-sm flex-1 min-h-touch justify-center">Décocher</button>
                @endif
            </div>

            {{-- Ajout : sidebar desktop --}}
            <form wire:submit="addCustom" class="fm-panel space-y-3 hidden lg:block">
                <h2 class="text-sm font-medium">Ajouter un article</h2>
                <label class="block text-sm">
                    <span class="text-caption text-fm-muted">Nom</span>
                    <input type="text" wire:model="customLabel" class="fm-input mt-1 w-full" placeholder="Ex. Éponge, sel…">
                </label>
                <label class="block text-sm">
                    <span class="text-caption text-fm-muted">Quantité (g, optionnel)</span>
                    <x-fm.number min="0" step="1" wire:model="customQuantityG" class="mt-1" placeholder="opt." />
                </label>
                @error('customLabel') <p class="text-xs text-red-400">{{ $message }}</p> @enderror
                <button type="submit" class="fm-btn w-full justify-center">Ajouter</button>
            </form>
        </aside>

        {{-- Liste principale --}}
        <div class="min-w-0 space-y-6">
            @if ($totalCount === 0)
                <div class="fm-panel text-sm text-fm-muted space-y-2">
                    <p>Aucun aliment sur cette période.</p>
                    <p>
                        Remplis ton
                        <a href="{{ route('planner') }}" wire:navigate class="text-fm-primary hover:underline">plan</a>
                        puis resynchronise, ou ajoute des articles à la main.
                    </p>
                </div>
            @else
                @if ($pendingItems->isNotEmpty())
                    <section class="space-y-3">
                        <div class="flex items-baseline justify-between gap-2">
                            <h2 class="text-sm font-medium">À acheter</h2>
                            <span class="text-caption text-fm-muted tabular-nums">{{ $pendingItems->count() }}</span>
                        </div>
                        <ul class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2 lg:gap-3">
                            @foreach ($pendingItems as $item)
                                <li
                                    wire:key="shop-{{ $item->id }}"
                                    class="flex items-stretch gap-3 rounded-lg border border-fm-border bg-fm-surface px-3 py-2.5 lg:px-4 lg:py-3 min-h-touch"
                                >
                                    <label class="flex items-center gap-3 min-w-0 flex-1 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            class="size-6 lg:size-5 shrink-0 accent-[var(--fm-primary)]"
                                            @checked($item->is_checked)
                                            wire:change="toggleItem({{ $item->id }}, $event.target.checked)"
                                        >
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-medium leading-snug break-words">{{ $item->label }}</span>
                                            <span class="block text-caption text-fm-muted mt-0.5 tabular-nums">
                                                @if ($item->quantity_g !== null)
                                                    {{ \App\Livewire\ShoppingList::formatQuantity($item->quantity_g) }}
                                                @else
                                                    —
                                                @endif
                                                @if ($item->source === $customSource)
                                                    · ajouté
                                                @endif
                                            </span>
                                        </span>
                                    </label>
                                    @if ($item->source === $customSource)
                                        <button
                                            type="button"
                                            wire:click="deleteItem({{ $item->id }})"
                                            class="text-xs text-fm-accent hover:underline shrink-0 self-center px-1 min-h-touch"
                                            aria-label="Retirer {{ $item->label }}"
                                        >Retirer</button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($doneItems->isNotEmpty())
                    <section class="space-y-3">
                        <div class="flex items-baseline justify-between gap-2">
                            <h2 class="text-sm font-medium text-fm-muted">Déjà achetés</h2>
                            <span class="text-caption text-fm-muted tabular-nums">{{ $doneItems->count() }}</span>
                        </div>
                        <ul class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2 lg:gap-3 opacity-75">
                            @foreach ($doneItems as $item)
                                <li
                                    wire:key="shop-done-{{ $item->id }}"
                                    class="flex items-stretch gap-3 rounded-lg border border-fm-border/60 bg-fm-bg px-3 py-2.5 lg:px-4 lg:py-3 min-h-touch"
                                >
                                    <label class="flex items-center gap-3 min-w-0 flex-1 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            class="size-6 lg:size-5 shrink-0 accent-[var(--fm-primary)]"
                                            checked
                                            wire:change="toggleItem({{ $item->id }}, $event.target.checked)"
                                        >
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-medium leading-snug break-words line-through text-fm-muted">{{ $item->label }}</span>
                                            <span class="block text-caption text-fm-muted mt-0.5 tabular-nums">
                                                @if ($item->quantity_g !== null)
                                                    {{ \App\Livewire\ShoppingList::formatQuantity($item->quantity_g) }}
                                                @endif
                                            </span>
                                        </span>
                                    </label>
                                    @if ($item->source === $customSource)
                                        <button
                                            type="button"
                                            wire:click="deleteItem({{ $item->id }})"
                                            class="text-xs text-fm-accent hover:underline shrink-0 self-center px-1 min-h-touch"
                                        >Retirer</button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endif
        </div>
    </div>

    {{-- Mobile : barre d’ajout collée en bas --}}
    <form
        wire:submit="addCustom"
        class="lg:hidden fixed bottom-0 inset-x-0 z-40 border-t border-fm-border bg-fm-bg/95 backdrop-blur px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
    >
        <div class="fm-container !px-0 flex gap-2 items-end">
            <label class="block text-sm flex-1 min-w-0">
                <span class="sr-only">Nom</span>
                <input type="text" wire:model="customLabel" class="fm-input w-full min-h-touch" placeholder="Ajouter…">
            </label>
            <label class="block text-sm w-20 shrink-0">
                <span class="sr-only">Quantité g</span>
                <x-fm.number min="0" step="1" wire:model="customQuantityG" class="min-h-touch" placeholder="g" />
            </label>
            <button type="submit" class="fm-btn min-h-touch shrink-0 px-4">+</button>
        </div>
        @error('customLabel') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
    </form>
</div>
