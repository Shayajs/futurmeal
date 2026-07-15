@props(['value' => 0, 'label' => null])

<div {{ $attributes->merge(['class' => '']) }}>
    @if ($label)
        <div class="flex justify-between items-baseline text-sm mb-2">
            <span class="text-fm-muted">{{ $label }}</span>
            <span class="tabular-nums">{{ $value }} %</span>
        </div>
    @endif
    <div class="h-1.5 rounded-full bg-fm-bg overflow-hidden">
        <div class="h-full bg-fm-primary transition-all" style="width: {{ min(100, max(0, $value)) }}%"></div>
    </div>
</div>
