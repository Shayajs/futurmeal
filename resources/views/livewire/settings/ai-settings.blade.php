<div class="fm-container max-w-2xl space-y-6">
    <div>
        <p class="text-caption text-fm-muted">
            <a href="{{ route('settings') }}" wire:navigate class="text-fm-primary hover:underline">← Paramètres</a>
        </p>
        <h1 class="text-h2 font-semibold mt-1">Intelligence artificielle</h1>
        <p class="text-sm text-fm-muted mt-1">
            Optionnel : branche ta propre clé API (OpenAI, OpenRouter, Groq…). Sinon, copie le prompt FuturMeal dans ChatGPT / Gemini et colle la réponse.
        </p>
    </div>

    @if (session('status'))
        <p class="text-sm text-fm-primary">{{ session('status') }}</p>
    @endif

    <form wire:submit="save" class="fm-panel space-y-4">
        <div>
            <label class="text-caption text-fm-muted">Clé API</label>
            <input
                type="password"
                wire:model="ai_api_key"
                class="fm-input mt-1 w-full"
                autocomplete="off"
                placeholder="{{ $hasExistingKey ? '•••••••• (laisser vide pour conserver)' : 'sk-…' }}"
            >
            @error('ai_api_key') <p class="text-sm text-fm-accent mt-1">{{ $message }}</p> @enderror
            @if ($hasExistingKey)
                <button type="button" wire:click="clearKey" class="text-xs text-fm-accent mt-2 hover:underline">
                    Supprimer la clé enregistrée
                </button>
            @endif
        </div>

        <div>
            <label class="text-caption text-fm-muted">URL de base (compatible OpenAI)</label>
            <input type="url" wire:model="ai_api_base_url" class="fm-input mt-1 w-full" placeholder="{{ $defaultBaseUrl }}">
            @error('ai_api_base_url') <p class="text-sm text-fm-accent mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="text-caption text-fm-muted">Modèle</label>
            <input type="text" wire:model="ai_api_model" class="fm-input mt-1 w-full" placeholder="{{ $defaultModel }}">
            @error('ai_api_model') <p class="text-sm text-fm-accent mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end">
            <button type="submit" class="fm-btn">Enregistrer</button>
        </div>
    </form>
</div>
