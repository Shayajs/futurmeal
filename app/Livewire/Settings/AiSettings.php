<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AiSettings extends Component
{
    public string $ai_api_key = '';

    public string $ai_api_base_url = '';

    public string $ai_api_model = '';

    public bool $hasExistingKey = false;

    public function mount(): void
    {
        $user = Auth::user();
        $this->hasExistingKey = $user->hasAiApiConfigured();
        $this->ai_api_base_url = $user->ai_api_base_url
            ?? (string) config('futurmeal.ai.default_base_url');
        $this->ai_api_model = $user->ai_api_model
            ?? (string) config('futurmeal.ai.default_model');
        $this->ai_api_key = '';
    }

    public function save(): void
    {
        $this->validate([
            'ai_api_base_url' => ['nullable', 'string', 'max:255', 'url'],
            'ai_api_model' => ['nullable', 'string', 'max:120'],
            'ai_api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $user = Auth::user();
        $payload = [
            'ai_api_base_url' => trim($this->ai_api_base_url) !== ''
                ? rtrim(trim($this->ai_api_base_url), '/')
                : null,
            'ai_api_model' => trim($this->ai_api_model) !== ''
                ? trim($this->ai_api_model)
                : null,
        ];

        if (trim($this->ai_api_key) !== '') {
            $payload['ai_api_key'] = trim($this->ai_api_key);
        }

        $user->update($payload);
        $this->hasExistingKey = $user->fresh()->hasAiApiConfigured();
        $this->ai_api_key = '';

        session()->flash('status', 'Paramètres IA enregistrés.');
    }

    public function clearKey(): void
    {
        Auth::user()->update(['ai_api_key' => null]);
        $this->hasExistingKey = false;
        $this->ai_api_key = '';
        session()->flash('status', 'Clé API IA supprimée.');
    }

    public function render()
    {
        return view('livewire.settings.ai-settings', [
            'defaultBaseUrl' => (string) config('futurmeal.ai.default_base_url'),
            'defaultModel' => (string) config('futurmeal.ai.default_model'),
        ]);
    }
}
