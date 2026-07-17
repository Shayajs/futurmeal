<?php

namespace App\Services\Ai;

use App\Support\MacroEnergy;
use App\Support\MealSlots;
use Carbon\Carbon;
use InvalidArgumentException;

class AiWeekPlanParser
{
    /**
     * @param  list<string>  $expectedDates
     * @return array{days: list<array{date: string, slots: array<string, list<array{label: string, quantity_g: ?float, recipe_id: ?int, recipe_hint: ?string, protein_g: ?float, carbs_g: ?float, fat_g: ?float, energy_kcal: ?float, price_eur: ?float}>}>}
     */
    public function parse(string $raw, array $expectedDates = []): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new InvalidArgumentException('La réponse IA est vide.');
        }

        $maxBytes = (int) config('futurmeal.ai.paste_max_bytes', 200_000);
        if (strlen($raw) > $maxBytes) {
            throw new InvalidArgumentException('La réponse IA est trop volumineuse.');
        }

        $json = $this->extractJson($raw);
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('JSON invalide : '.json_last_error_msg());
        }

        if (! isset($decoded['days']) || ! is_array($decoded['days'])) {
            throw new InvalidArgumentException('Le JSON doit contenir une clé "days" (tableau).');
        }

        $normalizedDays = [];

        foreach ($decoded['days'] as $index => $day) {
            if (! is_array($day)) {
                throw new InvalidArgumentException("Jour #{$index} invalide.");
            }

            $date = $this->normalizeDate($day['date'] ?? null, $index);

            $slotsRaw = $day['slots'] ?? [];
            if (! is_array($slotsRaw)) {
                throw new InvalidArgumentException("Créneaux invalides pour {$date}.");
            }

            $slots = [];
            foreach (MealSlots::keys() as $slotKey) {
                $items = $slotsRaw[$slotKey] ?? [];
                if (! is_array($items)) {
                    throw new InvalidArgumentException("Créneau « {$slotKey} » invalide pour {$date}.");
                }
                $slots[$slotKey] = array_values(array_map(
                    fn ($item) => $this->normalizeItem($item, $date, $slotKey),
                    $items,
                ));
            }

            $normalizedDays[] = [
                'date' => $date,
                'slots' => $slots,
            ];
        }

        if ($expectedDates !== []) {
            $got = array_column($normalizedDays, 'date');
            $missing = array_diff($expectedDates, $got);
            if ($missing !== []) {
                throw new InvalidArgumentException(
                    'Dates manquantes dans la réponse : '.implode(', ', $missing).'.'
                );
            }
        }

        return ['days' => $normalizedDays];
    }

    private function extractJson(string $raw): string
    {
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $raw, $m)) {
            return trim($m[1]);
        }

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false || $end < $start) {
            throw new InvalidArgumentException('Aucun objet JSON trouvé dans la réponse.');
        }

        return substr($raw, $start, $end - $start + 1);
    }

    private function normalizeDate(mixed $date, int $index): string
    {
        if (! is_string($date) || trim($date) === '') {
            throw new InvalidArgumentException("Date manquante pour le jour #{$index}.");
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            throw new InvalidArgumentException("Date invalide « {$date} ».");
        }
    }

    /**
     * @return array{label: string, quantity_g: ?float, recipe_id: ?int, recipe_hint: ?string, protein_g: ?float, carbs_g: ?float, fat_g: ?float, energy_kcal: ?float, price_eur: ?float}
     */
    private function normalizeItem(mixed $item, string $date, string $slot): array
    {
        if (! is_array($item)) {
            throw new InvalidArgumentException("Item invalide ({$date} / {$slot}).");
        }

        $label = trim((string) ($item['label'] ?? $item['recipe_hint'] ?? ''));
        $recipeHint = isset($item['recipe_hint']) ? trim((string) $item['recipe_hint']) : null;
        if ($recipeHint === '') {
            $recipeHint = null;
        }

        $recipeId = null;
        if (isset($item['recipe_id']) && $item['recipe_id'] !== null && $item['recipe_id'] !== '') {
            $recipeId = (int) $item['recipe_id'];
            if ($recipeId <= 0) {
                $recipeId = null;
            }
        }

        if ($label === '' && $recipeHint === null && $recipeId === null) {
            throw new InvalidArgumentException("Item sans label ({$date} / {$slot}).");
        }

        if ($label === '') {
            $label = $recipeHint ?? "Recette #{$recipeId}";
        }

        $quantityG = null;
        if (isset($item['quantity_g']) && $item['quantity_g'] !== null && $item['quantity_g'] !== '') {
            $quantityG = max(1.0, min(5000.0, (float) $item['quantity_g']));
        }

        $proteinG = $this->optionalFloat($item, ['protein_g', 'proteines_g', 'protein', 'protéines']);
        $carbsG = $this->optionalFloat($item, ['carbs_g', 'glucides_g', 'carbs', 'glucides', 'carbohydrates_g']);
        $fatG = $this->optionalFloat($item, ['fat_g', 'lipides_g', 'fat', 'lipides']);
        $energyKcal = $this->optionalFloat($item, ['energy_kcal', 'kcal', 'calories', 'energie_kcal', 'énergie_kcal']);
        $priceEur = $this->optionalFloat($item, ['price_eur', 'prix_eur', 'price', 'prix']);

        if ($energyKcal === null && (($proteinG ?? 0) + ($carbsG ?? 0) + ($fatG ?? 0)) > 0) {
            $energyKcal = round(MacroEnergy::kcalFromMacros(
                (float) ($proteinG ?? 0),
                (float) ($carbsG ?? 0),
                (float) ($fatG ?? 0),
            ), 1);
        }

        if ($priceEur !== null) {
            $priceEur = max(0.0, min(500.0, $priceEur));
        }

        return [
            'label' => $label,
            'quantity_g' => $quantityG,
            'recipe_id' => $recipeId,
            'recipe_hint' => $recipeHint,
            'protein_g' => $proteinG,
            'carbs_g' => $carbsG,
            'fat_g' => $fatG,
            'energy_kcal' => $energyKcal,
            'price_eur' => $priceEur,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $keys
     */
    private function optionalFloat(array $item, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $item) || $item[$key] === null || $item[$key] === '') {
                continue;
            }

            return max(0.0, min(5000.0, (float) $item[$key]));
        }

        return null;
    }
}
