<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-start gap-3">
            <h1 class="text-h2 font-semibold">Recettes</h1>
            <a href="{{ route('recipes.create') }}" wire:navigate class="fm-btn-primary shrink-0">Nouvelle recette</a>
        </div>
    </x-slot>
    <div class="fm-container grid gap-3">
        @forelse ($recipes as $recipe)
            <a href="{{ route('recipes.show', $recipe) }}" wire:navigate class="fm-panel block hover:border-fm-border-strong transition-colors">
                <h3 class="font-semibold">{{ $recipe->name }}</h3>
                <p class="text-sm text-fm-muted">{{ $recipe->is_macro_preset ? 'Preset macros' : $recipe->ingredients_count.' ingrédients' }}</p>
            </a>
        @empty
            <p class="text-fm-muted">Aucune recette. <a href="{{ route('recipes.create') }}" class="text-fm-primary">Créer la première</a></p>
        @endforelse
    </div>
</x-app-layout>
