# Questions finales FuturMeal

Réponses retenues pour le MVP — validées le 15/07/2026.

| # | Question | Défaut MVP | Statut |
|---|----------|------------|--------|
| 1 | Horizon par défaut à l'inscription | **7 jours** (choix 3/7/14/30 dans onboarding) | Implémenté |
| 2 | Devise budget | **EUR (€)** uniquement | Validé |
| 3 | Langue | **FR only** | Implémenté |
| 4 | Partage métriques groupe | **Opt-in strict** (`share_metrics = false` par défaut) | Implémenté |
| 5 | Formule TDEE | **Mifflin-St Jeor** en MVP ; Katch-McArdle si % graisse connu en phase 2 | Implémenté |
| 6 | Domaine dev | **futurmeal.test** / futurmeal.shaya | Implémenté |
| 7 | Prod — réseau NPM | **www_laravel_net** (comme brightshell) | Implémenté |
| 8 | Hébergement | **Docker local** pour l'instant | Implémenté |

## Décisions validées

| # | Question | Décision | Justification |
|---|----------|----------|---------------|
| 1 | **Domaine production** | **`futurmeal.fr`** (provisoire, modifiable avant déploiement) | Cohérent avec la marque FR ; à confirmer DNS avant mise en prod |
| 2 | **Budget** | **Optionnel** — pas obligatoire à l'inscription | Réduit la friction ; rappel contextuel sur dashboard si aucun prix saisi |
| 3 | **Verrouillage grammages** programme | **`false` par défaut** — le owner active manuellement | Couple/groupe peut ajuster au début ; verrouillage explicite quand le menu est figé |

Voir [PHASE2.md](./PHASE2.md) pour le backlog post-MVP.
