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

    @if ($errors->has('brightshield'))
        <div class="rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-200" role="alert">
            {{ $errors->first('brightshield') }}
        </div>
    @endif

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

    @if (filled(config('services.brightshield.client_id')))
        <div class="relative py-2">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-fm-border"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase tracking-wide">
                <span class="bg-fm-panel px-2 text-fm-muted">ou</span>
            </div>
        </div>

        <x-brightshield-button />
    @endif
</div>
