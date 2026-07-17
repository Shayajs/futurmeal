<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Support\MealSlots;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AiPromptBuilder
{
    /**
     * @return array{system: string, user: string, full: string}
     */
    public function build(
        User $user,
        string $weekStart,
        int $horizonDays,
        string $userInstructions = '',
    ): array {
        $profile = $user->profile;
        $calorieTarget = $profile?->daily_calorie_target ?? 2000;
        $goalLabel = $profile?->goal_type?->label() ?? 'Perte de poids';

        $start = Carbon::parse($weekStart)->startOfDay();
        $dates = collect(range(0, $horizonDays - 1))
            ->map(fn (int $i) => $start->copy()->addDays($i)->toDateString())
            ->all();

        $slots = MealSlots::ordered();
        $recipes = $user->recipes()
            ->orderBy('name')
            ->get(['id', 'name', 'is_macro_preset']);

        $system = view('ai.week-plan-system-prompt', [
            'calorieTarget' => $calorieTarget,
            'goalLabel' => $goalLabel,
        ])->render();

        $userPrompt = $this->buildUserPrompt(
            $dates,
            $slots,
            $recipes,
            $calorieTarget,
            $goalLabel,
            $userInstructions,
        );

        $full = trim($system)."\n\n---\n\n".trim($userPrompt);

        return [
            'system' => trim($system),
            'user' => trim($userPrompt),
            'full' => $full,
        ];
    }

    /**
     * @param  list<string>  $dates
     * @param  array<string, string>  $slots
     * @param  Collection<int, \App\Models\Recipe>  $recipes
     */
    private function buildUserPrompt(
        array $dates,
        array $slots,
        Collection $recipes,
        int $calorieTarget,
        string $goalLabel,
        string $userInstructions,
    ): string {
        $slotLines = collect($slots)
            ->map(fn (string $label, string $key) => "- {$key} : {$label}")
            ->implode("\n");

        $dateLines = collect($dates)->map(fn (string $d) => "- {$d}")->implode("\n");

        $recipeLines = $recipes->isEmpty()
            ? '(aucune recette enregistrée — propose des aliments)'
            : $recipes->map(function ($r) {
                $tag = $r->is_macro_preset ? ' [preset macros]' : '';

                return "- id={$r->id} « {$r->name} »{$tag}";
            })->implode("\n");

        $instructions = trim($userInstructions) !== ''
            ? trim($userInstructions)
            : '(aucune consigne supplémentaire)';

        return <<<PROMPT
Période à planifier (dates obligatoires) :
{$dateLines}

Créneaux autorisés :
{$slotLines}

Objectif nutritionnel : {$calorieTarget} kcal/jour — {$goalLabel}.

Catalogue de recettes de l'utilisateur (préfère recipe_id quand pertinent) :
{$recipeLines}

Consignes libres de l'utilisateur :
{$instructions}

Génère maintenant le JSON du plan pour toutes les dates listées.
PROMPT;
    }
}
