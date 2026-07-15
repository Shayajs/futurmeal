<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-start gap-3">
            <h1 class="text-h2 font-semibold min-w-0">{{ $recipe->name }}</h1>
            <a href="{{ route('recipes.edit', $recipe) }}" wire:navigate class="fm-btn-primary shrink-0">Modifier</a>
        </div>
    </x-slot>
    <div class="fm-container max-w-3xl fm-panel space-y-6">
        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
            <div><dt class="text-caption text-fm-muted">Kcal</dt><dd class="mt-1 text-lg font-medium tabular-nums">{{ $nutrients['energy_kcal'] }}</dd></div>
            <div><dt class="text-caption text-fm-protein">P</dt><dd class="mt-1 text-lg font-medium tabular-nums">{{ $nutrients['protein_g'] }}g</dd></div>
            <div><dt class="text-caption text-fm-carbs">G</dt><dd class="mt-1 text-lg font-medium tabular-nums">{{ $nutrients['carbs_g'] }}g</dd></div>
            <div><dt class="text-caption text-fm-fat">L</dt><dd class="mt-1 text-lg font-medium tabular-nums">{{ $nutrients['fat_g'] }}g</dd></div>
        </dl>
        @unless ($recipe->is_macro_preset)
            <ul class="divide-y divide-fm-border">
                @foreach ($recipe->ingredients as $ingredient)
                    <li class="py-2 flex justify-between text-sm">
                        <span>{{ $ingredient->label }}</span>
                        <span class="text-fm-muted">{{ $ingredient->quantity_g }} g</span>
                    </li>
                @endforeach
            </ul>
        @endunless
    </div>
</x-app-layout>
