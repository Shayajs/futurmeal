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
2. Horizon planification par défaut : 3 / 7 / 14 / 30 jours
3. Profil : sexe, date naissance, taille (cm), poids (kg)
4. Métriques corporelles : saisie manuelle % graisse OU mesures Navy (cou, taille, hanches)
5. Activité physique + déficit/surplus kcal

### Dashboard

- Kcal restantes aujourd'hui vs objectif
- Macros du jour (P/G/L)
- Budget estimé de la semaine
- Courbe poids / % graisse (30 jours)
- Prochains repas planifiés
- Projection poids orientée objectif (perte ou prise)

### Planificateur

- Vue période (3/7/14/30 jours selon profil) ou semaine (programmes)
- Vue jour détaillée (`/planner/day/{date}`)
- Ajout aliments/recettes par créneau (picker Livewire, pas de drag-and-drop MVP)
- Scan code-barres Open Food Facts (saisie manuelle)
- Totaux kcal + budget par jour
- Mini-graphique kcal/jour sur la période
- Projection poids orientée objectif

### Recettes

- Création manuelle : ingrédients CIQUAL/OFF/custom + grammages
- Preset macros (`is_macro_preset` sur `recipes`) : plat sans détail ingrédients
- Import template TheMealDB (phase 2 UI)

### Programmes collaboratifs

- Owner crée un programme hebdomadaire
- Invitation par **code** ou **lien signé** (`/programs/join/{token}`)
- Membres suivent les mêmes grammages
- Verrouillage grammages optionnel (désactivé par défaut)
- Partage métriques opt-in par membre
- Adhérence personnelle + tableau adhérence groupe (owner)

### Amis / PlanShare (complément)

- Suivre le **plan personnel** d'un ami (pas les grammages d'un programme)
- Invitation ami par code ou lien `/friends/add/{code}`
- Distinct des programmes collaboratifs

### Corps (`/metrics`)

- Saisie historique poids + % graisse
- Méthode Navy récurrente (tours cou/taille/hanches)
- Courbes poids, % graisse, masse maigre

### Graphiques (`/charts`)

- Page dédiée multi-séries : poids, graisse, masse maigre, IMC, kcal, budget
- Périodes 7j / 30j / 12 mois

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
| planning_horizon_days | int | 3, 7, 14, 30 — pilote la grille du planificateur perso |
| daily_calorie_target | int | calculé ou override |
| calorie_adjustment | int | déficit (-400) ou surplus (+300) |

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

Import ANSES local (6 macros MVP, extension phase 2).

### food_items

Cache Open Food Facts + aliments custom utilisateur.

### recipes / recipe_ingredients

Recettes avec ingrédients polymorphiques (ciqual, off, custom).

### recipes.is_macro_preset

Macros globaux sans détail (kcal, P, G, L) — remplace la table `recipe_macro_presets` initialement prévue.

### meal_plans / meal_plan_entries

Planification calendrier. Plans perso (`program_id` null) ou programme partagé.

### programs / program_members / program_invitations

Collaboration groupe (max 6). Invitations par token avec expiration.

### plan_shares

Suivi de plan personnel entre amis.

### budget_entries

Prix unitaire €/kg saisi par utilisateur (optionnel).

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

### Projection poids

`kg_estimés = |déficit_ou_surplus_kcal_cumulé| / 7700` — libellé selon `goal_type`.

---

## 5. Écrans & graphiques

| Écran | Composants |
|-------|------------|
| Landing | Hero sport, CTA inscription |
| Dashboard | Chart.js barres kcal, donut macros, line poids |
| Corps | Multi-courbes poids / % graisse / masse maigre |
| Charts | Explorer multi-séries, périodes longues |
| Planificateur | Grille période, mini-chart kcal, totaux jour |
| Day editor | Édition créneaux, barcode, copie jour |
| Recette | Grammages, jauges macros live |
| Programme | Membres X/6, adhérence groupe, invitation lien |

---

## 6. Règles collaboratives

### Programmes (couple / groupe)

- Métriques **privées par défaut**
- Partage explicite (`share_metrics = true`)
- Owner peut verrouiller les grammages (**désactivé par défaut**)
- Max 6 membres par programme (MVP)
- Même plan partagé, objectifs kcal **personnels**

### Amis (PlanShare)

- Suivi du plan **personnel** d'un ami
- Permissions lecture seule ou édition par invitation

---

## 7. Hors scope MVP

- App mobile native
- Scan code-barres caméra native
- Balance connectée
- Drag-and-drop planificateur
- Edamam NLP
- Multi-tenant SaaS
- Planification > 1 mois (UI calendrier mensuel)
- Créneaux horaires (8h, 12h30…)

Voir [PHASE2.md](./PHASE2.md) pour le backlog.

---

## 8. Stack technique

- Laravel 13, PHP 8.4, Livewire 3, Breeze, Tailwind 4, Chart.js
- MySQL 8.4 (dev/prod)
- Docker : `docker-compose-shaya.dev.yaml` (local), `docker-compose.yaml` (prod)
- Domaine dev : `futurmeal.test`
- Domaine prod (provisoire) : `futurmeal.fr`

Voir aussi [APIS.md](./APIS.md), [QUESTIONS.md](./QUESTIONS.md) et [PHASE2.md](./PHASE2.md).
