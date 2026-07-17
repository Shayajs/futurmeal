<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiCompatibleClient
{
    /**
     * @param  array{system: string, user: string}  $messages
     */
    public function chat(User $user, array $messages): string
    {
        $apiKey = $user->ai_api_key;
        if (! filled($apiKey)) {
            throw new RuntimeException('Aucune clé API IA configurée.');
        }

        $baseUrl = rtrim(
            $user->ai_api_base_url ?: (string) config('futurmeal.ai.default_base_url'),
            '/',
        );
        $model = $user->ai_api_model ?: (string) config('futurmeal.ai.default_model');
        $timeout = (int) config('futurmeal.ai.timeout', 90);
        $maxTokens = (int) config('futurmeal.ai.max_tokens', 8000);

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $messages['system']],
                        ['role' => 'user', 'content' => $messages['user']],
                    ],
                    'temperature' => 0.4,
                    'max_tokens' => $maxTokens,
                    'response_format' => ['type' => 'json_object'],
                ])
                ->throw();
        } catch (RequestException $e) {
            $body = $e->response?->json('error.message')
                ?? $e->response?->body()
                ?? $e->getMessage();
            throw new RuntimeException('Erreur API IA : '.$body, previous: $e);
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Réponse API IA vide.');
        }

        return $content;
    }
}
