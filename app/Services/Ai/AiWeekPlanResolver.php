<?php

namespace App\Services\Ai;

use App\Data\AiWeekPlanDraft;
use App\Data\AiWeekPlanItemDraft;
use App\Enums\FoodReferenceType;
use App\Models\CiqualFood;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Nutrition\FoodSearchService;
use App\Support\MealSlots;

class AiWeekPlanResolver
{
    /** @var array<string, array{type: string, id: int, label: string}|null> */
    private array $foodCache = [];

    public function __construct(
        private FoodSearchService $foodSearch,
    ) {}

    /**
     * @param  array{days: list<array{date: string, slots: array<string, list<array{label: string, quantity_g: ?float, recipe_id: ?int, recipe_hint: ?string}>}>}  $parsed
     */
    public function resolve(User $user, array $parsed): AiWeekPlanDraft
    {
        $this->foodCache = [];
        $recipes = $user->recipes()->get(['id', 'name']);
        $defaultQty = (float) config('futurmeal.ai.default_quantity_g', 150);
        $items = [];
        $errors = [];

        foreach ($parsed['days'] as $day) {
            $date = $day['date'];
            foreach (MealSlots::keys() as $slot) {
                foreach ($day['slots'][$slot] ?? [] as $rawItem) {
                    $items[] = $this->resolveItem($user, $recipes, $date, $slot, $rawItem, $defaultQty, $errors);
                }
            }
        }

        return new AiWeekPlanDraft($items, $errors);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Recipe>  $recipes
     * @param  array{label: string, quantity_g: ?float, recipe_id: ?int, recipe_hint: ?string}  $rawItem
     * @param  list<string>  $errors
     */
    private function resolveItem(
        User $user,
        $recipes,
        string $date,
        string $slot,
        array $rawItem,
        float $defaultQty,
        array &$errors,
    ): AiWeekPlanItemDraft {
        $label = $rawItem['label'];
        $quantityG = $rawItem['quantity_g'] ?? $defaultQty;

        $recipe = $this->matchRecipe($recipes, $rawItem['recipe_id'] ?? null, $rawItem['recipe_hint'] ?? null, $label);
        if ($recipe) {
            return new AiWeekPlanItemDraft(
                date: $date,
                slot: $slot,
                label: $recipe->name,
                quantityG: null,
                recipeId: $recipe->id,
                referenceType: null,
                referenceId: null,
                foodItemId: null,
                resolved: true,
                matchKind: 'recipe',
            );
        }

        $hit = $this->matchFood($user, $label);

        if ($hit) {
            $type = FoodReferenceType::tryFrom((string) $hit['type']);
            $foodItemId = $type && in_array($type, [FoodReferenceType::OpenFoodFacts, FoodReferenceType::Custom], true)
                ? (int) $hit['id']
                : null;

            return new AiWeekPlanItemDraft(
                date: $date,
                slot: $slot,
                label: (string) $hit['label'],
                quantityG: $quantityG,
                recipeId: null,
                referenceType: $type?->value,
                referenceId: (int) $hit['id'],
                foodItemId: $foodItemId,
                resolved: true,
                matchKind: 'food',
            );
        }

        $warning = "Aucun match pour « {$label} »";
        $errors[] = "{$date} / {$slot} : {$warning}";

        return new AiWeekPlanItemDraft(
            date: $date,
            slot: $slot,
            label: $label,
            quantityG: $quantityG,
            recipeId: null,
            referenceType: null,
            referenceId: null,
            foodItemId: null,
            resolved: false,
            warning: $warning,
            matchKind: 'none',
        );
    }

    /**
     * @return array{type: string, id: int, label: string}|null
     */
    private function matchFood(User $user, string $label): ?array
    {
        $cacheKey = mb_strtolower(trim($label));
        if (array_key_exists($cacheKey, $this->foodCache)) {
            return $this->foodCache[$cacheKey];
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($this->searchQueriesFor($label) as $query) {
            foreach ($this->candidateHits($user, $query) as $hit) {
                $score = $this->scoreLabelMatch($label, (string) $hit['label']);
                // Bonus CIQUAL (plus fiable pour le plan FR)
                if (($hit['type'] ?? '') === FoodReferenceType::Ciqual->value) {
                    $score += 8.0;
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $hit;
                }
            }
        }

        // Seuil : évite les faux positifs OFF (ex. « Pomme » → fromage blanc)
        $resolved = ($best !== null && $bestScore >= 42.0) ? $best : null;
        $this->foodCache[$cacheKey] = $resolved;

        return $resolved;
    }

    /**
     * @return list<string>
     */
    private function searchQueriesFor(string $label): array
    {
        $label = trim($label);
        $queries = [$label];

        // Sans parenthèses / pourcentages
        $stripped = trim(preg_replace('/\([^)]*\)/', '', $label) ?? $label);
        $stripped = trim(preg_replace('/\d+\s*%/', '', $stripped) ?? $stripped);
        if ($stripped !== '' && $stripped !== $label) {
            $queries[] = $stripped;
        }

        // Synonymes / simplifications fréquentes IA → CIQUAL
        $aliases = [
            'skyr' => 'fromage blanc',
            'blanc de poulet' => 'poulet blanc',
            'aiguillettes de poulet' => 'poulet',
            'émincé de poulet' => 'poulet',
            'escalope de poulet' => 'poulet',
            'cuisses de poulet sans peau' => 'poulet',
            'filet de cabillaud' => 'cabillaud',
            'dos de cabillaud' => 'cabillaud',
            'pavé de saumon' => 'saumon',
            'pavé de truite' => 'truite',
            'filet de colin' => 'colin',
            'filet de merlu' => 'merlu',
            'filet de lieu noir' => 'lieu noir',
            'filet de lieu jaune' => 'lieu',
            'filet de dorade' => 'dorade',
            'filet de merlan' => 'merlan',
            'riz basmati cru' => 'riz basmati',
            'riz complet cru' => 'riz complet',
            'riz thaï cru' => 'riz',
            'pâtes complètes crues' => 'pâtes complètes',
            'lentilles crues' => 'lentilles',
            'quinoa cru' => 'quinoa',
            'semoule crue' => 'semoule',
            'blé cru (ebly)' => 'blé',
            'blé cru' => 'blé',
            'pommes de terre' => 'pomme de terre',
            'patate douce' => 'patate douce',
            'huile d\'olive' => 'huile d\'olive',
            'fromage blanc 0%' => 'fromage blanc',
            'fromage blanc 3%' => 'fromage blanc',
            'oeufs entiers' => 'oeuf',
            'blancs d\'oeufs' => 'blanc d\'oeuf',
            'lait demi-écrémé' => 'lait demi-écrémé',
            'lait d\'amande sans sucre' => 'lait d\'amande',
            'chocolat noir 70%' => 'chocolat noir',
            'beurre de cacahuète' => 'beurre de cacahuète',
            'noix de cajou' => 'cajou',
            'tomates cerises' => 'tomate cerise',
            'pain de seigle' => 'pain de seigle',
            'pain complet' => 'pain complet',
            'haricots plats' => 'haricot',
        ];

        $norm = $this->normalize($label);
        foreach ($aliases as $from => $to) {
            if ($norm === $from || str_contains($norm, $from)) {
                $queries[] = $to;
            }
        }

        // Mots significatifs seuls (du plus long au plus court)
        $tokens = $this->significantTokens($label);
        usort($tokens, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach (array_slice($tokens, 0, 3) as $token) {
            $queries[] = $token;
        }

        // Bigrammes utiles
        if (count($tokens) >= 2) {
            $queries[] = $tokens[0].' '.$tokens[1];
        }

        return array_values(array_unique(array_filter($queries)));
    }

    /**
     * @return list<array{type: string, id: int, label: string}>
     */
    private function candidateHits(User $user, string $query): array
    {
        $hits = [];

        // 1) Recherche CIQUAL tokenisée (locale, fiable)
        foreach ($this->searchCiqualTokens($query, 8) as $hit) {
            $hits[] = $hit;
        }

        // 2) Recherche standard (CIQUAL LIKE + OFF) en secours
        $search = $this->foodSearch->search($query, $user, 5);
        foreach ($search['results'] as $hit) {
            if (isset($hit['type'], $hit['id'], $hit['label'])) {
                $hits[] = [
                    'type' => (string) $hit['type'],
                    'id' => (int) $hit['id'],
                    'label' => (string) $hit['label'],
                ];
            }
        }

        return $hits;
    }

    /**
     * @return list<array{type: string, id: int, label: string}>
     */
    private function searchCiqualTokens(string $query, int $limit = 8): array
    {
        $tokens = $this->significantTokens($query);
        if ($tokens === []) {
            $like = CiqualFood::query()
                ->where('name_fr', 'like', '%'.trim($query).'%')
                ->limit($limit)
                ->get(['id', 'name_fr']);

            return $like->map(fn (CiqualFood $f) => [
                'type' => FoodReferenceType::Ciqual->value,
                'id' => $f->id,
                'label' => $f->name_fr,
            ])->all();
        }

        $builder = CiqualFood::query();
        foreach ($tokens as $token) {
            $builder->where('name_fr', 'like', '%'.$token.'%');
        }

        $strict = $builder->limit($limit)->get(['id', 'name_fr']);
        if ($strict->isNotEmpty()) {
            return $strict->map(fn (CiqualFood $f) => [
                'type' => FoodReferenceType::Ciqual->value,
                'id' => $f->id,
                'label' => $f->name_fr,
            ])->all();
        }

        // OR sur les tokens les plus longs si AND trop strict
        $or = CiqualFood::query();
        foreach (array_slice($tokens, 0, 2) as $i => $token) {
            $method = $i === 0 ? 'where' : 'orWhere';
            $or->{$method}('name_fr', 'like', '%'.$token.'%');
        }

        return $or->limit($limit)->get(['id', 'name_fr'])->map(fn (CiqualFood $f) => [
            'type' => FoodReferenceType::Ciqual->value,
            'id' => $f->id,
            'label' => $f->name_fr,
        ])->all();
    }

    private function scoreLabelMatch(string $needle, string $haystack): float
    {
        $n = $this->normalize($needle);
        $h = $this->normalize($haystack);

        if ($n === '' || $h === '') {
            return 0.0;
        }

        if ($n === $h || str_contains($h, $n) || str_contains($n, $h)) {
            return 95.0;
        }

        similar_text($n, $h, $percent);

        $nTokens = $this->significantTokens($needle);
        $hTokens = $this->significantTokens($haystack);
        $overlap = 0;
        foreach ($nTokens as $t) {
            foreach ($hTokens as $ht) {
                if ($t === $ht || str_contains($ht, $t) || str_contains($t, $ht)) {
                    $overlap++;
                    break;
                }
            }
        }
        $tokenScore = $nTokens === [] ? 0.0 : ($overlap / count($nTokens)) * 100.0;

        return max($percent, $tokenScore);
    }

    /**
     * @return list<string>
     */
    private function significantTokens(string $value): array
    {
        $stop = [
            'de', 'du', 'des', 'la', 'le', 'les', 'un', 'une', 'et', 'ou', 'en', 'au', 'aux',
            'sans', 'avec', 'pour', 'cru', 'crue', 'crues', 'cuit', 'cuite', 'cuites',
            'nature', 'frais', 'fraîche', 'entier', 'entière', 'entier',
        ];

        $value = $this->normalize($value);
        $value = str_replace(['\'', '’', '(', ')', ',', '/', '%'], ' ', $value);
        $parts = preg_split('/\s+/u', $value) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            if (mb_strlen($part) < 3) {
                continue;
            }
            if (in_array($part, $stop, true)) {
                continue;
            }
            if (preg_match('/^\d+$/', $part)) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Recipe>  $recipes
     */
    private function matchRecipe($recipes, ?int $recipeId, ?string $recipeHint, string $label): ?Recipe
    {
        if ($recipeId) {
            $byId = $recipes->firstWhere('id', $recipeId);
            if ($byId) {
                return $byId;
            }
        }

        $candidates = array_values(array_filter([$recipeHint, $label]));
        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $needle) {
            $needleNorm = $this->normalize($needle);
            if ($needleNorm === '') {
                continue;
            }

            foreach ($recipes as $recipe) {
                $hay = $this->normalize($recipe->name);
                if ($hay === $needleNorm || str_contains($hay, $needleNorm) || str_contains($needleNorm, $hay)) {
                    return $recipe;
                }

                similar_text($needleNorm, $hay, $percent);
                if ($percent > $bestScore) {
                    $bestScore = $percent;
                    $best = $recipe;
                }
            }
        }

        return $bestScore >= 72.0 ? $best : null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['’'], ["'"], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }
}
