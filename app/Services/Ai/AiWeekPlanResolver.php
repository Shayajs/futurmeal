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
     * @param  array{label: string, quantity_g: ?float, recipe_id: ?int, recipe_hint: ?string, protein_g?: ?float, carbs_g?: ?float, fat_g?: ?float, energy_kcal?: ?float, price_eur?: ?float}  $rawItem
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
        $aiMacros = [
            'proteinG' => isset($rawItem['protein_g']) ? (float) $rawItem['protein_g'] : null,
            'carbsG' => isset($rawItem['carbs_g']) ? (float) $rawItem['carbs_g'] : null,
            'fatG' => isset($rawItem['fat_g']) ? (float) $rawItem['fat_g'] : null,
            'energyKcal' => isset($rawItem['energy_kcal']) ? (float) $rawItem['energy_kcal'] : null,
            'priceEur' => isset($rawItem['price_eur']) ? (float) $rawItem['price_eur'] : null,
        ];
        $hasAiMacros = (($aiMacros['proteinG'] ?? 0) + ($aiMacros['carbsG'] ?? 0) + ($aiMacros['fatG'] ?? 0)) > 0
            || ($aiMacros['energyKcal'] !== null && $aiMacros['energyKcal'] > 0);

        $recipe = $this->matchRecipe($recipes, $rawItem['recipe_id'] ?? null, $rawItem['recipe_hint'] ?? null, $label);
        if ($recipe) {
            // Match OK → macros catalogue / recette font foi ; on garde seulement le prix IA en secours.
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
                priceEur: $aiMacros['priceEur'],
            );
        }

        $hit = $this->matchFood($user, $label);

        if ($hit) {
            $type = FoodReferenceType::tryFrom((string) $hit['type']);
            $foodItemId = $type && in_array($type, [FoodReferenceType::OpenFoodFacts, FoodReferenceType::Custom], true)
                ? (int) $hit['id']
                : null;

            // Match OK → macros API (CIQUAL/OFF) font foi ; prix IA uniquement en secours.
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
                priceEur: $aiMacros['priceEur'],
            );
        }

        if ($hasAiMacros) {
            return new AiWeekPlanItemDraft(
                date: $date,
                slot: $slot,
                label: $label,
                quantityG: $quantityG,
                recipeId: null,
                referenceType: null,
                referenceId: null,
                foodItemId: null,
                resolved: true,
                warning: 'Estimations IA (pas de match catalogue)',
                matchKind: 'ai_estimate',
                proteinG: $aiMacros['proteinG'],
                carbsG: $aiMacros['carbsG'],
                fatG: $aiMacros['fatG'],
                energyKcal: $aiMacros['energyKcal'],
                priceEur: $aiMacros['priceEur'],
            );
        }

        $warning = "Aucun match pour « {$label} » (pas de macros IA)";
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
            priceEur: $aiMacros['priceEur'],
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
                // Score sur le libellé IA ET sur la requête dérivée (alias),
                // sinon « Whey isolat vanille » vs « Protéines de lactosérum… »
                // tombe sous le seuil alors que le bon aliment a été trouvé.
                $score = max(
                    $this->scoreLabelMatch($label, (string) $hit['label']),
                    $this->scoreLabelMatch($query, (string) $hit['label']),
                );
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
            'poulet rôti sans peau' => 'poulet',
            'blanc de dinde' => 'dinde',
            'escalope de dinde' => 'dinde',
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
            'riz basmati (cru)' => 'riz basmati',
            'riz complet cru' => 'riz complet',
            'riz thaï cru' => 'riz',
            'riz thaï (cru)' => 'riz',
            'pâtes complètes crues' => 'pâtes complètes',
            'pâtes complètes (crues)' => 'pâtes complètes',
            'nouilles asiatiques (crues)' => 'nouilles',
            'nouilles asiatiques' => 'nouilles',
            'lentilles crues' => 'lentilles',
            'lentilles (crues)' => 'lentilles',
            'quinoa cru' => 'quinoa',
            'quinoa (cru)' => 'quinoa',
            'semoule crue' => 'semoule',
            'blé cru (ebly)' => 'blé',
            'blé ebly (cru)' => 'blé',
            'blé cru' => 'blé',
            'pommes de terre' => 'pomme de terre',
            'patate douce' => 'patate douce',
            'huile d\'olive' => 'huile d\'olive',
            'fromage blanc 0%' => 'fromage blanc',
            'fromage blanc 3%' => 'fromage blanc',
            'yaourt nature' => 'yaourt',
            'yaourt grec' => 'yaourt grec',
            'yaourt au lait de brebis' => 'yaourt',
            'petit suisse' => 'petit-suisse',
            'oeufs entiers' => 'œuf',
            'œufs entiers' => 'œuf',
            'oeuf entier' => 'œuf',
            'œuf entier' => 'œuf',
            'blancs d\'oeufs' => 'blanc d\'œuf',
            'blancs d\'œufs' => 'blanc d\'œuf',
            'lait demi-écrémé' => 'lait demi-écrémé',
            'lait écrémé' => 'lait écrémé',
            'eau ou lait écrémé' => 'lait écrémé',
            'lait d\'amande sans sucre' => 'lait d\'amande',
            'chocolat noir 70%' => 'chocolat noir',
            'mousse au chocolat' => 'mousse au chocolat',
            'beurre de cacahuète' => 'beurre de cacahuète',
            'whey isolat vanille' => 'protéines de lactosérum',
            'whey isolat chocolat' => 'protéines de lactosérum',
            'whey isolat' => 'protéines de lactosérum',
            'whey' => 'protéines de lactosérum',
            'vinaigrette allégée' => 'vinaigrette',
            'sauce burger allégée' => 'sauce',
            'sauce soja salée' => 'sauce soja',
            'épices mexicaines' => 'épices',
            'dés de jambon blanc' => 'jambon',
            'jambon blanc' => 'jambon',
            'mélange de salade' => 'salade',
            'salade iceberg' => 'laitue',
            'julienne de légumes' => 'légumes',
            'courgettes poêlées' => 'courgette',
            'champignons de paris' => 'champignon',
            'mozzarella allégée' => 'mozzarella',
            'pain de mie complet' => 'pain de mie',
            'pain à burger' => 'pain burger',
            'tortilla complète' => 'tortilla',
            'compote de pomme sans sucres ajoutés' => 'compote de pomme',
            'muesli sans sucres ajoutés' => 'muesli',
            'ananas frais' => 'ananas',
            'steak haché 5%' => 'steak haché',
            'thon en boîte au naturel' => 'thon',
            'noix de cajou' => 'cajou',
            'amandes' => 'amande',
            'courgettes' => 'courgette',
            'tomates' => 'tomate',
            'fraises' => 'fraise',
            'framboises' => 'framboise',
            'myrtilles' => 'myrtille',
            'carottes' => 'carotte',
            'aubergines' => 'aubergine',
            'tomates cerises' => 'tomate cerise',
            'pain de seigle' => 'pain de seigle',
            'pain complet' => 'pain complet',
            'haricots plats' => 'haricot',
            'haricots verts' => 'haricot vert',
            'flocons d\'avoine' => 'flocons d\'avoine',
            'champignons' => 'champignon',
            'épinards' => 'épinard',
            'poireaux' => 'poireau',
            'poivrons' => 'poivron',
            'oignons' => 'oignon',
        ];

        $norm = $this->normalize($label);
        foreach ($aliases as $from => $to) {
            $fromNorm = $this->normalize($from);
            if ($norm === $fromNorm || str_contains($norm, $fromNorm)) {
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
            return $this->ciqualLikeAny([$query], $limit);
        }

        // AND sur chaque token, avec variantes œ/oe (normalize → oeuf, CIQUAL → Œuf)
        $builder = CiqualFood::query();
        foreach ($tokens as $token) {
            $variants = $this->likeVariants($token);
            $builder->where(function ($q) use ($variants): void {
                foreach ($variants as $i => $variant) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $q->{$method}('name_fr', 'like', '%'.$variant.'%');
                }
            });
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
        $orVariants = [];
        foreach (array_slice($tokens, 0, 2) as $token) {
            foreach ($this->likeVariants($token) as $variant) {
                $orVariants[] = $variant;
            }
        }

        return $this->ciqualLikeAny($orVariants, $limit);
    }

    /**
     * @param  list<string>  $needles
     * @return list<array{type: string, id: int, label: string}>
     */
    private function ciqualLikeAny(array $needles, int $limit): array
    {
        $needles = array_values(array_unique(array_filter(array_map('trim', $needles))));
        if ($needles === []) {
            return [];
        }

        $builder = CiqualFood::query();
        foreach ($needles as $i => $needle) {
            foreach ($this->likeVariants($needle) as $j => $variant) {
                $method = ($i === 0 && $j === 0) ? 'where' : 'orWhere';
                $builder->{$method}('name_fr', 'like', '%'.$variant.'%');
            }
        }

        return $builder->limit($limit)->get(['id', 'name_fr'])->map(fn (CiqualFood $f) => [
            'type' => FoodReferenceType::Ciqual->value,
            'id' => $f->id,
            'label' => $f->name_fr,
        ])->all();
    }

    /**
     * Variantes pour LIKE MySQL/SQLite : œ ↔ oe, et casse Unicode
     * (SQLite LIKE ne fold pas Œ/œ — seuls les ASCII sont insensibles à la casse).
     *
     * @return list<string>
     */
    private function likeVariants(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            return [];
        }

        $lower = mb_strtolower($token);
        $ascii = str_replace(['œ', 'æ'], ['oe', 'ae'], $lower);

        $variants = [$token, $lower, $ascii];

        if (str_contains($ascii, 'oe')) {
            $withOe = str_replace('oe', 'œ', $ascii);
            $withOE = str_replace('oe', 'Œ', $ascii);
            $variants[] = $withOe;
            $variants[] = $withOE;
            // Première lettre majuscule (libellés CIQUAL)
            $variants[] = mb_strtoupper(mb_substr($withOe, 0, 1)).mb_substr($withOe, 1);
            $variants[] = mb_strtoupper(mb_substr($ascii, 0, 1)).mb_substr($ascii, 1);
        }

        if (str_contains($ascii, 'ae')) {
            $variants[] = str_replace('ae', 'æ', $ascii);
            $variants[] = str_replace('ae', 'Æ', $ascii);
        }

        return array_values(array_unique(array_filter($variants)));
    }

    private function scoreLabelMatch(string $needle, string $haystack): float
    {
        $n = $this->normalize($needle);
        $h = $this->normalize($haystack);

        if ($n === '' || $h === '') {
            return 0.0;
        }

        if ($n === $h) {
            return 100.0;
        }

        // Contenance exacte du libellé (pas d'un seul token)
        if (str_contains($h, $n) || str_contains($n, $h)) {
            return 92.0;
        }

        similar_text($n, $h, $percent);

        $nTokens = $this->significantTokens($needle);
        $hTokens = $this->significantTokens($haystack);
        if ($nTokens === [] || $hTokens === []) {
            return (float) $percent;
        }

        $overlap = 0;
        foreach ($nTokens as $t) {
            foreach ($hTokens as $ht) {
                if ($t === $ht || str_contains($ht, $t) || str_contains($t, $ht)) {
                    $overlap++;
                    break;
                }
            }
        }

        // Pénalise les libellés longs où un seul token matche (ex. Amandes → Lait d'amande)
        $coverage = $overlap / count($nTokens);
        $specificity = count($nTokens) / max(count($hTokens), count($nTokens));
        $tokenScore = $coverage * $specificity * 100.0;

        return max((float) $percent, $tokenScore);
    }

    /**
     * @return list<string>
     */
    private function significantTokens(string $value): array
    {
        $stop = [
            'de', 'du', 'des', 'la', 'le', 'les', 'un', 'une', 'et', 'ou', 'en', 'au', 'aux',
            'sans', 'avec', 'pour', 'cru', 'crue', 'crues', 'cuit', 'cuite', 'cuites',
            'nature', 'frais', 'fraîche', 'entier', 'entiere', 'entiers', 'entières', 'entieres',
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
            $singular = $this->frenchSingular($part);
            if ($singular !== $part && ! in_array($singular, $stop, true)) {
                $tokens[] = $singular;
            }
        }

        return array_values(array_unique($tokens));
    }

    private function frenchSingular(string $word): string
    {
        if (str_ends_with($word, 'aux') && mb_strlen($word) > 3) {
            return mb_substr($word, 0, -3).'al';
        }
        if (str_ends_with($word, 'eaux') && mb_strlen($word) > 4) {
            return mb_substr($word, 0, -1);
        }
        if (str_ends_with($word, 's') && mb_strlen($word) > 3) {
            return mb_substr($word, 0, -1);
        }
        if (str_ends_with($word, 'x') && mb_strlen($word) > 3) {
            return mb_substr($word, 0, -1);
        }

        return $word;
    }

    /**
     * Matching recettes : id exact, puis fuzzy sur recipe_hint puis label.
     * Le hint IA (« Porridge express ») est prioritaire sur le libellé aliment.
     *
     * @param  \Illuminate\Support\Collection<int, Recipe>  $recipes
     */
    private function matchRecipe($recipes, ?int $recipeId, ?string $recipeHint, string $label): ?Recipe
    {
        if ($recipes->isEmpty()) {
            return null;
        }

        if ($recipeId) {
            $byId = $recipes->firstWhere('id', $recipeId);
            if ($byId) {
                return $byId;
            }
        }

        // Hint d'abord (souvent le vrai nom de recette), puis label aliment.
        $candidates = array_values(array_unique(array_filter([
            is_string($recipeHint) ? trim($recipeHint) : null,
            trim($label),
        ])));

        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $index => $needle) {
            $needleNorm = $this->normalize($needle);
            if ($needleNorm === '') {
                continue;
            }

            // Léger bonus au recipe_hint (index 0) pour départager.
            $hintBonus = $index === 0 && filled($recipeHint) ? 4.0 : 0.0;

            foreach ($recipes as $recipe) {
                $score = $this->scoreLabelMatch($needle, $recipe->name) + $hintBonus;

                if ($score >= 98.0) {
                    return $recipe;
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $recipe;
                }
            }
        }

        // Seuil un peu plus bas que similar_text pur : le score tokenisé
        // (ex. « Wrap express poulet curry » ↔ « Wrap poulet ») est plus fiable.
        return $bestScore >= 62.0 ? $best : null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        // Homogénéise apostrophes et ligatures ; on conserve les accents
        // pour que les LIKE CIQUAL (« Protéines ») restent matchables.
        $value = str_replace(['’', 'œ', 'æ'], ["'", 'oe', 'ae'], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }
}
