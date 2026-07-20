# FuturMeal — Synthèse des APIs nutritionnelles

Document de référence pour l'intégration des sources de données externes.

## Matrice de priorité

| Source | Rôle MVP | Cache local | Usage |
|--------|----------|-------------|-------|
| **CIQUAL (ANSES)** | Ingrédients bruts | Obligatoire (import) | Primaire |
| **Open Food Facts** | Produits emballés / scan | Recommandé | Secondaire |
| **TheMealDB** | Templates recettes | Optionnel | Inspiration |
| **Edamam** | Analyse NLP recettes | Interdit (free) | Phase 2 |
| **LogSnag** | Events & notifications | Non | Observabilité |

---

## Open Food Facts (OFF)

- **Documentation** : https://openfoodfacts.github.io/openfoodfacts-server/api/
- **Base URL** : `https://world.openfoodfacts.org`
- **Version recommandée** : v3 (lecture produit), v2 (recherche structurée)

### Endpoints clés

```
GET /api/v3/product/{barcode}?fields=product_name,product_name_fr,product_name_en,nutriments,brands,image_url
GET /api/v2/search?search_terms=…&fields=code,product_name,product_name_fr,product_name_en,nutriments&countries_tags_en=france&lc=fr&cc=fr
GET /api/v2/search?categories_tags=en:beverages&page_size=20
```

### Langue des noms

FuturMeal préfère `product_name_fr`, ignore les noms majoritairement arabes / non latins, puis retombe sur `product_name` / `product_name_en`.
Les recherches biaient d’abord les produits taggés France (`countries_tags_en=france`).

### Données utiles

- `nutriments` : énergie, protéines, glucides, lipides, fibres, sel (par 100g ou portion)
- Nutri-Score, NOVA, allergènes, images

### Limites

- ~100 requêtes/minute (usage raisonnable)
- Pas de clé API requise
- User-Agent recommandé : `FuturMeal/1.0 (contact@example.com)`

### Stratégie FuturMeal (MVP)

1. Recherche locale d'abord (CIQUAL + `food_items` cache)
2. **Scan barcode live** via API v3 (`OpenFoodFactsClient::fetchByBarcode`)
3. **Recherche texte live** via API v2 (`OpenFoodFactsClient::searchByText`) si résultats locaux insuffisants
4. Cache résultats recherche (`off_search_*`, TTL configurable)
5. Rate limiting : 30 req/min/IP (`config/futurmeal.php`)
6. Stocker barcode, nom, nutriments normalisés par 100g

---

## CIQUAL (ANSES)

- **Site** : https://ciqual.anses.fr/
- **Open Data** : https://recherche.data.gouv.fr (table actualisée)
- **Pas d'API REST officielle**

### Contenu

- ~3 500 aliments représentatifs de la consommation française
- 60+ nutriments : macros, vitamines, minéraux, acides gras, sucres individuels
- Noms FR/EN, codes aliment (`alim_code`)

### Import FuturMeal

```bash
php artisan futurmeal:import-ciqual [--path=/chemin/vers/export.xml]
```

Tables : `ciqual_foods`, `ciqual_nutrients`, `ciqual_composition`

### Licence

Licence Ouverte / Open Licence — mention ANSES obligatoire dans l'UI.

---

## TheMealDB

- **Documentation** : https://www.themealdb.com/api.php
- **Base URL (free)** : `https://www.themealdb.com/api/json/v1/1/`
- **Clé test** : `1` (dans l'URL)

### Endpoints utiles

```
GET /search.php?s=Arrabiata
GET /lookup.php?i=52772
GET /filter.php?i=chicken_breast
GET /random.php
GET /list.php?i=list
```

### Limitations

- ~740 recettes, ingrédients sans macros fiables
- Mesures en unités US/UK (cup, tbsp…)
- Premium : $10 lifetime pour clé dédiée + endpoints v2

### Stratégie FuturMeal

- Import comme **modèle de recette** (nom, instructions, liste ingrédients)
- Mapping manuel/auto vers CIQUAL + grammages utilisateur
- Recalcul nutrition via `RecipeCalculator`

---

## Edamam (optionnel — phase 2)

- **Documentation** : https://developer.edamam.com/
- **APIs** : Recipe Search, Nutrition Analysis

### Contraintes (free tier)

- Usage **personnel / non-profit** uniquement
- Pas de cache massif des données nutritionnelles
- Requêtes initiées par l'utilisateur humain uniquement
- Attribution Edamam obligatoire

### Plans payants

- Enterprise Basic : $9/mois (Recipe Search)
- Nutrition Analysis : $29/mois minimum

### Stratégie FuturMeal MVP

**Non utilisé en MVP.** Calcul maison via CIQUAL. Variables `.env` prévues pour activation future :

```env
EDAMAM_APP_ID=
EDAMAM_APP_KEY=
```

---

## LogSnag

- **Documentation** : https://docs.logsnag.com/
- **Endpoint** : `POST https://api.logsnag.com/v1/log`

### Authentification

```
Authorization: Bearer {LOGSNAG_TOKEN}
```

### Exemple

```json
{
  "project": "futurmeal",
  "channel": "users",
  "event": "Nouvelle inscription",
  "icon": "🥗",
  "notify": false,
  "tags": { "goal": "weight_loss" }
}
```

### Events FuturMeal

| Channel | Event | notify |
|---------|-------|--------|
| `users` | Inscription | false |
| `programs` | Programme créé | false |
| `programs` | Membre rejoint | true |
| `metrics` | Objectif kcal atteint | true |
| `budget` | Budget dépassé | true |

### Configuration

```env
LOGSNAG_TOKEN=
LOGSNAG_PROJECT=futurmeal
LOGSNAG_ENABLED=false
```

---

## Open Prices

- **Documentation** : https://prices.openfoodfacts.org/api/docs
- **Base URL** : `https://prices.openfoodfacts.org`
- **Licence** : OdBL — attribution obligatoire dans l'UI

### Endpoints utilisés par FuturMeal

```
GET /api/v1/prices?product_code={ean}&location_id={id}
GET /api/v1/prices?product_code={ean}
GET /api/v1/locations?osm_name__like={query}&osm_address_city__like={city}&osm_address_country_code=FR
```

### Conversion interne (€/kg)

FuturMeal normalise tous les prix vers **€/kg** pour alimenter `budget_entries` :

| Source API | Formule |
|------------|---------|
| `product_quantity` en grammes | `price / (qty / 1000)` |
| `product_quantity` en kg | `price / qty` |
| `price_per` avec unité kg | valeur directe |

### Stratégie FuturMeal

1. **Priorité prix perso par enseigne** : `budget_entries.store_brand` (ex. Leclerc)
2. **Prix perso global** : `budget_entries` sans enseigne (référence aliment ou label)
3. **Prix communautaires** : médiane des contributions récentes (`community_store_prices`, 90 jours)
4. **Fallback Open Prices** : si code-barres disponible (`food_items.external_id`)
5. **Magasin préféré** : `user_profiles.open_prices_location_id` (réglages budget)
6. **Sans magasin** : médiane des 5 relevés les plus récents (tous magasins)
7. **Cache Open Prices** : 24 h par `{barcode}:{location_id|all}`

### Prix communautaires (participatif)

FuturMeal agrège les prix saisis par les utilisateurs **par enseigne** (marque OSM : Carrefour, Leclerc…).

| Table | Rôle |
|-------|------|
| `community_store_prices` | Contribution utilisateur (€/kg, enseigne, date constat) |
| `budget_entries.store_brand` | Prix perso optionnel par enseigne |

- **Agrégation** : médiane des relevés des 90 derniers jours (min. 1 contribution)
- **Contribution** : lors de l'ajout d'un aliment au planificateur, case « Partager avec la communauté » (cochée par défaut)
- **UI** : badge « Communauté · {enseigne} (N relevés) » ; liste des contributions dans les réglages budget

Pas de POST vers l'API Open Prices en MVP — données stockées localement uniquement.

### Configuration

```env
OPEN_PRICES_BASE_URL=https://prices.openfoodfacts.org
OPEN_PRICES_CACHE_TTL=86400
```

---

## Normalisation interne

Toutes les sources sont converties vers un format unifié (`NutrientProfile`) :

| Champ | Unité |
|-------|-------|
| `energy_kcal` | kcal / 100g |
| `protein_g` | g / 100g |
| `carbs_g` | g / 100g |
| `fat_g` | g / 100g |
| `fiber_g` | g / 100g |
| `salt_g` | g / 100g |

Calcul recette : `nutriment × (grammage / 100)` sommé par ingrédient.
