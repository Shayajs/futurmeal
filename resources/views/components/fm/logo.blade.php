@props(['href' => null, 'size' => 'default', 'variant' => 'wordmark'])

@php
    $markSizes = [
        'sm' => 'h-6 w-6',
        'default' => 'h-7 w-7',
        'lg' => 'h-8 w-8',
    ];
    $markClass = $markSizes[$size] ?? $markSizes['default'];
@endphp

@if ($variant === 'icon')
    @if ($href)
        <a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex']) }}>
            <x-fm.logo-mark class="{{ $markClass }}" />
        </a>
    @else
        <div {{ $attributes->merge(['class' => 'inline-flex']) }}>
            <x-fm.logo-mark class="{{ $markClass }}" />
        </div>
    @endif
@else
    @if ($href)
        <a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex']) }}>
            <x-fm.logo-wordmark :size="$size" class="text-fm-text" />
        </a>
    @else
        <div {{ $attributes->merge(['class' => 'inline-flex']) }}>
            <x-fm.logo-wordmark :size="$size" class="text-fm-text" />
        </div>
    @endif
@endif
