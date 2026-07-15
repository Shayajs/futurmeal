<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="fm-panel space-y-6">
    <div>
        <h1 class="text-h2 font-semibold">Connexion</h1>
    </div>

    <x-auth-session-status :status="session('status')" />

    <form wire:submit="login" class="space-y-4">
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input wire:model="form.email" id="email" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Mot de passe" />
            <x-text-input wire:model="form.password" id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <label for="remember" class="flex items-center gap-2 text-sm text-fm-muted">
            <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-fm-border bg-fm-bg text-fm-primary focus:ring-fm-primary/30" name="remember">
            Se souvenir de moi
        </label>

        <div class="flex items-center justify-between pt-2">
            @if (Route::has('password.request'))
                <a class="fm-btn-link" href="{{ route('password.request') }}" wire:navigate>Mot de passe oublié</a>
            @else
                <span></span>
            @endif
            <x-primary-button>Se connecter</x-primary-button>
        </div>
    </form>
</div>
