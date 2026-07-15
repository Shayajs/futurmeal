<x-app-layout>
    <x-slot name="header">
        <h1 class="text-h2 font-semibold">Paramètres</h1>
    </x-slot>

    <div class="fm-container max-w-2xl">
        <div class="grid sm:grid-cols-2 gap-4">
            <a href="{{ route('settings.nutrition') }}" wire:navigate class="fm-panel block hover:border-fm-primary transition-colors">
                <h2 class="font-medium">Nutrition & objectifs</h2>
                <p class="text-sm text-fm-muted mt-1">Déficit kcal, maintenance, cibles poids et graisse</p>
            </a>
            <a href="{{ route('settings.budget') }}" wire:navigate class="fm-panel block hover:border-fm-primary transition-colors">
                <h2 class="font-medium">Budget alimentaire</h2>
                <p class="text-sm text-fm-muted mt-1">Prix €/kg et suivi des dépenses</p>
            </a>
            <a href="{{ route('friends') }}" wire:navigate class="fm-panel block hover:border-fm-primary transition-colors">
                <h2 class="font-medium">Amis</h2>
                <p class="text-sm text-fm-muted mt-1">Invitations, demandes, code ami</p>
            </a>
            <a href="{{ route('profile') }}" wire:navigate class="fm-panel block hover:border-fm-primary transition-colors">
                <h2 class="font-medium">Compte</h2>
                <p class="text-sm text-fm-muted mt-1">Nom, email, mot de passe, suppression</p>
            </a>
        </div>
    </div>
</x-app-layout>
