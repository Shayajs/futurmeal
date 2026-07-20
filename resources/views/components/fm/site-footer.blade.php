@props(['showPwaInstall' => false])

<footer {{ $attributes->merge(['class' => 'fm-divider py-8 mt-auto']) }}>
    <div class="fm-container flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-3">
            <x-fm.logo size="sm" href="/" wire:navigate />
            <p class="text-caption text-fm-muted max-w-sm">
                Planification de repas, macros et suivi corporel.
            </p>
        </div>

        <nav aria-label="Informations légales" class="flex flex-col sm:items-end gap-2 text-caption">
            <a href="{{ route('legal.privacy') }}" wire:navigate class="text-fm-muted hover:text-fm-primary transition-colors min-h-touch inline-flex items-center">
                Politique de confidentialité
            </a>
            <a href="{{ route('legal.cookies') }}" wire:navigate class="text-fm-muted hover:text-fm-primary transition-colors min-h-touch inline-flex items-center">
                Cookies
            </a>
            <a href="{{ route('legal.notice') }}" wire:navigate class="text-fm-muted hover:text-fm-primary transition-colors min-h-touch inline-flex items-center">
                Mentions légales
            </a>
            <a
                href="https://github.com/Shayajs/futurmeal"
                target="_blank"
                rel="noopener noreferrer"
                class="text-fm-muted hover:text-fm-primary transition-colors min-h-touch inline-flex items-center gap-1.5"
            >
                GitHub
                <span aria-hidden="true">↗</span>
            </a>
        </nav>
    </div>

    <div class="fm-container mt-6 pt-6 border-t border-fm-border">
        <p class="text-caption text-fm-muted">
            Composition nutritionnelle · Table CIQUAL © ANSES
        </p>

        @if ($showPwaInstall)
            <div class="mt-3" data-pwa-install hidden>
                <button
                    type="button"
                    class="inline-flex min-h-touch items-center text-caption text-fm-muted/70 hover:text-fm-primary transition-colors"
                >
                    Installer l'application sur cet appareil
                </button>
            </div>
        @endif
    </div>
</footer>
