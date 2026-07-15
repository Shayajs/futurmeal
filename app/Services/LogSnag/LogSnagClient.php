<?php

namespace App\Services\LogSnag;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LogSnagClient
{
    public function log(
        string $channel,
        string $event,
        ?string $description = null,
        ?string $icon = null,
        bool $notify = false,
        array $tags = [],
        ?string $userId = null,
    ): void {
        if (! config('futurmeal.logsnag.enabled') || ! config('futurmeal.logsnag.token')) {
            return;
        }

        $payload = array_filter([
            'project' => config('futurmeal.logsnag.project'),
            'channel' => $channel,
            'event' => $event,
            'description' => $description,
            'icon' => $icon,
            'notify' => $notify,
            'tags' => $tags ?: null,
            'user_id' => $userId,
        ], fn ($v) => $v !== null);

        try {
            Http::withToken(config('futurmeal.logsnag.token'))
                ->post('https://api.logsnag.com/v1/log', $payload)
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('LogSnag error: '.$e->getMessage());
        }
    }
}
