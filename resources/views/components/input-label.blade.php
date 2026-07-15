@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm text-fm-muted mb-1']) }}>
    {{ $value ?? $slot }}
</label>
