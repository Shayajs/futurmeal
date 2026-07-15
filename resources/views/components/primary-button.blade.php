<button {{ $attributes->merge(['type' => 'submit', 'class' => 'fm-btn-primary']) }}>
    {{ $slot }}
</button>
