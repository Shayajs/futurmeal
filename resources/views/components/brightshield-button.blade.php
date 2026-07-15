@props([
    'label' => 'Se connecter avec BrightShell',
])

@if (filled(config('services.brightshield.client_id')))
    <a href="{{ route('brightshield.redirect') }}"
       class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-fm-border bg-fm-bg px-4 py-2.5 text-sm font-semibold text-fm-text transition hover:border-fm-primary/40 hover:bg-fm-primary/5">
        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-indigo-500/20 text-[10px] font-bold text-indigo-300">B</span>
        <span>{{ $label }}</span>
    </a>
@endif
