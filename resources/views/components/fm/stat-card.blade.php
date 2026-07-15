@props(['label', 'value', 'unit' => null, 'hint' => null])

<div {{ $attributes->merge(['class' => '']) }}>
    <dt class="text-caption text-fm-muted">{{ $label }}</dt>
    <dd class="mt-1 text-xl font-medium tabular-nums">
        {{ $value }}
        @if ($unit)
            <span class="text-sm text-fm-muted font-normal">{{ $unit }}</span>
        @endif
    </dd>
    @if ($hint)
        <p class="text-caption text-fm-muted mt-1">{{ $hint }}</p>
    @endif
</div>
