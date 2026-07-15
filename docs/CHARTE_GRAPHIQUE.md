# FuturMeal — Charte graphique v2.0

**Référence officielle :** [`docs/charte/00-reference-officielle.png`](charte/00-reference-officielle.png)

Document de référence unique pour toute l'application (web, emails, futures extensions).

---

## 1. Identité

| Élément | Valeur |
|---------|--------|
| **Nom** | FuturMeal |
| **Logo** | Mark « F » (deux barres inclinées vert néon) + wordmark blanc/vert |
| **Slogan** | Nutrition aujourd'hui, **performance demain.** |
| **Baseline produit** | Prévoir. Budgéter. Partager. |
| **Ton** | Sportif, performance, précis, motivant |
| **Police** | [Outfit](https://fonts.google.com/specimen/Outfit) — 400, 500, 600, 700 |

### Icônes catégories (outline)

| Catégorie | Symbole | Usage |
|-----------|---------|-------|
| Énergie | ⚡ | Kcal, activité |
| Protéines | 💪 | Macro P |
| Hydratation | 💧 | Eau, boissons |
| Récupération | 🕐 | Repos, sommeil |

---

## 2. Palette de couleurs (v2)

### Fonds & surfaces

| Token | Hex | Usage |
|-------|-----|-------|
| `fm-bg` | `#0B0F19` | Fond principal |
| `fm-surface` | `#12182A` | Cards, nav |
| `fm-surface-elevated` | `#1A2238` | Hover, dropdowns |
| `fm-border` | `#1E2A3D` | Bordures |
| `fm-border-strong` | `#2A3850` | Dividers actifs |

### Couleurs d'action

| Token | Hex | Usage |
|-------|-----|-------|
| `fm-primary` | `#00FF88` | Electric Green — CTA, display, logo, succès |
| `fm-primary-hover` | `#00E67A` | Hover boutons |
| `fm-primary-muted` | `rgba(0,255,136,0.12)` | Tints, badges |
| `fm-accent` | `#FF6D00` | Alertes, glucides, urgence |
| `fm-accent-hover` | `#E65100` | Hover accent |

### Texte

| Token | Hex | Usage |
|-------|-----|-------|
| `fm-text` | `#FFFFFF` | Titres H1–H3 |
| `fm-text-body` | `#C8D0DC` | Corps de texte |
| `fm-muted` | `#8B95A5` | Labels, captions |
| `fm-disabled` | `#5A6474` | Désactivé |

### Sémantique nutrition

| Token | Hex | Macro |
|-------|-----|-------|
| `fm-protein` | `#00FF88` | Protéines |
| `fm-carbs` | `#FF6D00` | Glucides |
| `fm-fat` | `#8B95A5` | Lipides |
| `fm-kcal` | `#00BFA5` | Énergie |

---

## 3. Typographie — échelle officielle

| Niveau | Taille | Leading | Poids | Couleur | Exemple |
|--------|--------|---------|-------|---------|---------|
| **DISPLAY** | 60px | 110% | Bold 700 | Primary `#00FF88` | Prévois tes repas |
| **H1** | 36px | 120% | Bold 700 | White | Optimise ta nutrition. |
| **H2** | 24px | 130% | SemiBold 600 | White | Planifie. Prépare. Performance. |
| **H3** | 18px | 140% | SemiBold 600 | White | Des repas adaptés à ton sport. |
| **BODY** | 16px | 150% | Regular 400 | `#C8D0DC` | Paragraphes |
| **CAPTION** | 12px | 140% | Regular 400 | `#8B95A5` | Labels UI |

Classes Tailwind : `text-display`, `text-h1`, `text-h2`, `text-h3`, `text-body`, `text-caption`

---

## 4. Logo

Composant Blade : `<x-fm.logo />` et `<x-fm.logo-mark />`

- Mark : deux barres parallèles inclinées, couleur `fm-primary`
- Wordmark : « Futur » blanc + « Meal » vert
- Tailles : `sm`, `default`, `lg`

---

## 5. Composants UI

Préfixe obligatoire : **`fm-`**

| Classe | Usage |
|--------|-------|
| `.fm-btn-primary` | CTA principal (texte foncé sur vert) |
| `.fm-btn-secondary` | Outline vert |
| `.fm-btn-accent` | Orange urgence |
| `.fm-btn-ghost` | Actions tertiaires |
| `.fm-card` | Conteneur glass |
| `.fm-input` | Champs formulaire |
| `.fm-badge-primary` | Badge succès/objectif |
| `.fm-slogan` | Slogan uppercase tracking wide |

Fichiers : `resources/css/design-tokens.css`, `resources/css/app.css`

---

## 6. Graphiques (Chart.js)

```javascript
primary: '#00FF88'
accent:  '#FF6D00'
muted:   '#8B95A5'
grid:    'rgba(255,255,255,0.05)'
```

---

## 7. Planches visuelles

| Fichier | Contenu |
|---------|---------|
| **00-reference-officielle.png** | **Référence validée v2** |
| 01-palette.png … 10-homepage-hero.png | Planches détaillées v1 (à recaler sur v2) |

---

## 8. Do / Don't

**Do** — Display en vert primary, titres en blanc, corps en gris clair, logo mark partout.

**Don't** — Ancien vert `#00E676`, fond blanc en app, plus de 2 accents simultanés.
