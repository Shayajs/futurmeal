@props(['meals' => []])

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    @foreach ($meals as $meal)
        <div @class([
            'py-2 border-b border-fm-border last:border-0',
            'opacity-50' => $meal['empty'] ?? false,
        ])>
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-caption text-fm-muted">{{ $meal['slot'] }}</p>
                    @if ($meal['empty'] ?? false)
                        <p class="text-sm text-fm-muted italic">Non planifié</p>
                    @elseif (! empty($meal['foods']))
                        <ul class="mt-1 space-y-0.5">
                            @foreach ($meal['foods'] as $food)
                                <li class="text-sm truncate">{{ $food['label'] }} <span class="text-fm-muted tabular-nums">· {{ $food['kcal'] }} kcal</span></li>
                            @endforeach
                        </ul>
                    @else
                        <p class="font-medium text-sm truncate">{{ $meal['name'] }}</p>
                        <p class="text-caption text-fm-muted mt-0.5">
                            P {{ $meal['protein_g'] }}g · G {{ $meal['carbs_g'] }}g · L {{ $meal['fat_g'] }}g
                        </p>
                    @endif
                </div>
                <div class="text-right shrink-0">
                    @if ($meal['empty'] ?? false)
                        <a href="{{ route('planner') }}" wire:navigate class="text-xs text-fm-primary hover:underline">Planifier</a>
                    @else
                        <p class="text-fm-primary font-medium tabular-nums">{{ $meal['kcal'] }} kcal</p>
                        @if ($meal['cost'])
                            <p class="text-caption text-fm-muted">{{ number_format($meal['cost'], 2, ',', ' ') }} €</p>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
