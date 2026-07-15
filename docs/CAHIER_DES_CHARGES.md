# FuturMeal — Cahier des charges MVP

## 1. Vision produit

**FuturMeal** permet de prévoir, budgéter et partager sa nutrition sportive sans réfléchir chaque jour.

| Priorité | Objectif |
|----------|----------|
| 1 | Perte de graisse (suivi % graisse, masse maigre) |
| 2 | Planification repas sur plusieurs jours/semaines |
| 3 | Budget alimentaire prévisionnel |
| 4 | Collaboration (couple, petit groupe) |

**Personas** : sportif solo, couple même menu, groupe jusqu'à 6 personnes (MVP).

---

## 2. Parcours utilisateur

### Onboarding (wizard post-inscription)

1. Objectif : perte de poids / gain de masse
2. Horizon planification par défaut : 3 / 7 / 14 jours
3. Profil : sexe, date naissance, taille (cm), poids (kg)
4. Métriques corporelles : saisie manuelle % graisse OU mesures Navy (cou, taille, hanches)
5. Activité physique + déficit/surplus kcal

### Dashboard

- Kcal restantes aujourd'hui vs objectif
- Macros du jour (P/G/L)
- Budget restant de la semaine
- Courbe poids / % graisse (30 jours)
- Prochains repas planifiés

### Planificateur

- Vue jour / semaine
- Drag-and-drop recettes sur créneaux repas
- Totaux kcal + budget par jour
- Projection perte poids (indicatif)

### Recettes

- Création manuelle : ingrédients CIQUAL/OFF/custom + grammages
- Preset macros : plat sans détail ingrédients (refaire un curry identique)
- Import template TheMealDB (phase 2 UI)

### Programmes collaboratifs

- Owner crée un programme hebdomadaire
- Invitation par code ou lien
- Membres suivent les mêmes grammages
- Partage métriques opt-in par membre

---

## 3. Modèle de données

### users (étendu)

- `onboarding_completed_at` — null tant que wizard non fini

### user_profiles

| Colonne | Type | Description |
|---------|------|-------------|
| user_id | FK | |
| gender | enum | male, female, other |
| birth_date | date | |
| height_cm | decimal | |
| activity_level | enum | sedentary → very_active |
| goal_type | enum | weight_loss, muscle_gain |
| planning_horizon_days | int | 3, 7, 14, 30 |
| daily_calorie_target | int | calculé ou override |
| calorie_adjustment | int | déficit (-500) ou surplus (+300) |

### body_metrics

| Colonne | Type | Description |
|---------|------|-------------|
| user_id | FK | |
| recorded_at | date | |
| weight_kg | decimal | |
| body_fat_percent | decimal | nullable |
| lean_mass_kg | decimal | calculé |
| bmi | decimal | informatif |
| source | enum | manual, navy, scale |
| neck_cm, waist_cm, hip_cm | decimal | Navy |

### ciqual_foods / ciqual_nutrients / ciqual_composition

Import ANSES local.

### food_items

Cache Open Food Facts + aliments custom utilisateur.

### recipes / recipe_ingredients

Recettes avec ingrédients polymorphiques (ciqual, off, custom).

### recipe_macro_presets

Macros globaux sans détail (kcal, P, G, L, portion_g).

### meal_plans / meal_plan_entries

Planification calendrier.

### programs / program_members / program_invitations

Collaboration.

### budget_entries

Prix unitaire €/kg saisi par utilisateur par référence aliment.

---

## 4. Calculs métier

### IMC (informatif)

`BMI = poids_kg / (taille_m)²`

### Navy body fat (Hodgdon-Beckett)

Mesures en **cm**, converties en log10.

**Homme** : `%BF = 86.010 × log10(waist - neck) - 70.041 × log10(height) + 36.76`

**Femme** : `%BF = 163.205 × log10(waist + hip - neck) - 97.684 × log10(height) - 78.387`

### Masse maigre

`lean_mass = weight × (1 - body_fat/100)`

### TDEE (Mifflin-St Jeor)

**Homme** : `10×poids + 6.25×taille - 5×âge + 5`

**Femme** : `10×poids + 6.25×taille - 5×âge - 161`

× facteur activité (1.2 → 1.9) + ajustement objectif.

### Nutrition recette

`nutriment_total = Σ (nutriment_per_100g × grammage / 100)`

### Projection perte poids

`kg_estimés = déficit_kcal_cumulé / 7700` — avec disclaimer « estimation indicative ».

---

## 5. Écrans & graphiques

| Écran | Composants |
|-------|------------|
| Landing | Hero sport, CTA inscription |
| Dashboard | Chart.js barres kcal, donut macros, line poids |
| Corps | Multi-courbes poids / % graisse / masse maigre |
| Planificateur | Grille semaine, totaux jour |
| Recette | Sliders grammage, jauges macros live |
| Programme | Membres, adhérence, invitation |

---

## 6. Règles collaboratives

- Métriques **privées par défaut**
- Partage explicite (`share_metrics = true`)
- Owner peut verrouiller les grammages
- Max 6 membres par programme (MVP)

---

## 7. Hors scope MVP

- App mobile native
- Scan code-barres caméra
- Balance connectée
- Edamam NLP
- Multi-tenant SaaS
- Planification > 1 mois (UI)

---

## 8. Stack technique

- Laravel 13, PHP 8.4, Livewire 3, Breeze, Tailwind 4, Chart.js
- MySQL 8.4 (dev/prod)
- Docker : `docker-compose-shaya.dev.yaml` (local), `docker-compose.yaml` (prod)
- Domaine dev : `futurmeal.test`

Voir aussi [APIS.md](./APIS.md) et [QUESTIONS.md](./QUESTIONS.md).
