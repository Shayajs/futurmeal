<x-app-layout>
    <x-slot name="header">
        <h1 class="text-h2 font-semibold">Compte</h1>
    </x-slot>

    <div class="fm-container max-w-2xl space-y-6">
        <p class="text-caption text-fm-muted">
            <a href="{{ route('settings') }}" wire:navigate class="text-fm-primary hover:underline">← Paramètres</a>
        </p>

        <div class="fm-panel">
            <livewire:profile.update-profile-information-form />
        </div>

        <div class="fm-panel">
            <livewire:profile.update-password-form />
        </div>

        <div class="fm-panel">
            <livewire:profile.delete-user-form />
        </div>
    </div>
</x-app-layout>
