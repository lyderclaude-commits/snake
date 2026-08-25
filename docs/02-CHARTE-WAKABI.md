# Charte Wakabi — audit de l'existant

Extrait de l'archive `wakabileguide.com` (25/08/2026) : 12 pages HTML de production,
`blog/assets/wakabi.css` (80 Ko), et l'archive `Wakabi le guide v3.1.zip`.

Ce document remplace toutes les hypothèses de la v0.1 de la spécification. **La marque
Wakabi est bleue** — les placeholders orange terre cuite étaient faux et ont été supprimés.

---

## 1. Couleurs

L'échelle est celle de Tailwind (`blue-*`, `slate-*`), reprise telle quelle.

| Rôle | Token site | Hex |
|---|---|---|
| **Primaire** | `--primary` | `#2563EB` |
| Primaire foncé (survol) | `--primary-d` | `#1D4ED8` |
| Primaire clair (fonds) | `--primary-l` | `#EFF6FF` |
| Accent orange | `--orange` | `#F97316` |
| Accent teal | `--teal` | `#0D9488` |
| Or (Kori / fidélité) | `--gold` | `#D97706` |
| Succès | `--green` | `#16A34A` |
| Erreur | `--red` | `#DC2626` |
| Texte principal | `--text` | `#0F172A` |
| Texte secondaire | `--text2` | `#475569` |
| Texte tertiaire | `--text3` | `#94A3B8` |
| Fond | `--bg` / `--bg2` / `--bg3` | `#FFFFFF` / `#F8FAFF` / `#EFF6FF` |
| Surface | `--surface` / `--surface2` | `#FFFFFF` / `#F1F5F9` |
| Bordures | `--border` / `--border2` | `#E2E8F0` / `#CBD5E1` |

**Dégradés récurrents :**

```css
linear-gradient(135deg, var(--blue-800), var(--blue-600))   /* héros            */
linear-gradient(135deg, var(--blue-900), var(--blue-700))   /* sections sombres */
linear-gradient(135deg, var(--blue-50),  var(--blue-100))   /* fonds doux       */
linear-gradient(90deg,  var(--primary),  var(--blue-400))   /* texte dégradé    */
```

**Rayons :** 6 · 10 · 16 · 24 · 32 px. **Ombres :** trois niveaux, teintés `rgba(15,23,42,…)`.

---

## 2. Typographie — un bug de production à corriger

### Le constat

| Variable | Définitions | Usages |
|---|---:|---:|
| `--font-display` | **0** | **~536** |
| `--font-primary` | 1 (`'Absolut Pro', sans-serif`) | **0** |
| `--font-body` | 1 (`'Plus Jakarta Sans', sans-serif`) | 10 |

`font-family: var(--font-display)` apparaît 43 à 53 fois par page, sur les 12 pages,
et **la variable n'est jamais définie** — ni dans les `:root` inline, ni dans
`wakabi.css`, ni dans l'archive v3.1.

### La conséquence

Une `font-family` dont la variable est indéfinie est invalide au calcul&nbsp;; comme
`font-family` est héritée, elle retombe sur la valeur du parent. **Tous les titres du site
s'affichent donc en Plus Jakarta Sans**, la police de corps — jamais dans la police de
titrage prévue.

Pire, chaque page charge **Bricolage Grotesque** depuis Google Fonts…

```html
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:…&family=Bricolage+Grotesque:opsz,wght@12..96,400;…&display=swap" rel="stylesheet">
```

…et cette police n'apparaît dans **aucune règle CSS** (0 occurrence). Elle est téléchargée
sur chaque visite, puis jamais appliquée. De même, `Absolut Pro` est déclarée en
`@font-face` et assignée à `--font-primary`, qui n'est utilisée nulle part.

### Le correctif

Une ligne à ajouter dans chaque `:root` :

```css
--font-display: 'Bricolage Grotesque', 'Absolut Pro', system-ui, sans-serif;
```

L'intention de départ est lisible dans le code : **Bricolage Grotesque** en titrage (c'est
la police chargée), **Plus Jakarta Sans** en corps, **Absolut Pro** réservée au logotype —
elle n'existe qu'en graisse Bold (`AbsolutPro-BoldReduced.woff2`), donc inutilisable en
texte courant.

> C'est un correctif à porter sur le site principal, indépendamment du générateur.
> `wakabi-tokens.css` est déjà écrit dans sa version corrigée.

---

## 3. Iconographie

Les icônes viennent de **icons8**, en trois familles mélangées :

| Style icons8 | Usage observé |
|---|---|
| `ios/50/…` | Majoritaire — linéaire, fin, arrondi (`cocktail`, `map-marker`, `calendar`, `crowd`, `percentage`, `4-star-hotel`) |
| `color/48/…` | Drapeaux pays (`togo`, `benin`, `ivory-coast`, `senegal`, `cameroon`, `gabon`) |
| `fluency-systems-regular`, `dotty` | Cas isolés |

S'y ajoutent des **emoji utilisés comme icônes** dans les niveaux de fidélité
(🌱 🌟 💫 👑) et les avantages (🎁 🏆 💳 ★).

**Pour le générateur :** le style dominant est *linéaire fin, arrondi*. Un jeu cohérent
comme **Phosphor** (poids `regular`/`bold`) ou **Lucide** s'y substituerait proprement,
avec l'avantage d'être embarquable en SVG — donc sans requête réseau vers icons8, ce qui
compte sur data payante.

---

## 4. Ton éditorial

**Tutoiement systématique**, registre direct et familier, phrases courtes.

| Emplacement | Formulation |
|---|---|
| H1 accueil | « Ton prochain bon coin t'attend » |
| Accroche | « Vis ta ville à 100% » |
| Section | « En 3 étapes, c'est plié » |
| Section | « Une app. Un univers entier » |
| Section | « L'Afrique telle qu'on la vit » |
| Kori | « Gagne. Dépense. Explore. » |
| Kori | « Plus tu explores, plus tu gagnes. » |
| Localisation | « En direct depuis Lomé, Togo » |
| Communauté | « Rejoins des milliers d'explorateurs » |
| Pied de page | « Fait avec amour » |

**Lexique maison à réutiliser tel quel :** *bon coin* · *explorateur* · *Kori* ·
*Carte Wakabi* · *partenaire* · *vivre sa ville*.

> **Incohérence relevée :** le pied de page passe au vouvoiement
> (« Explorez. Découvrez. Connectez. ») alors que tout le reste tutoie. À trancher —
> le générateur tutoiera, comme le corps du site.

---

## 5. Économie Kori — les règles réelles

| Élément | Valeur |
|---|---|
| Symbole | **₵** |
| Gain | 1 achat chez un partenaire = **10 Koris minimum** |
| Conversion | **100 Koris = −500 XOF** de réduction |
| Support | QR Code unique dans l'app mobile |

**Quatre niveaux d'explorateur :**

| Niveau | Seuil | Avantages |
|---|---|---|
| 🌱 **Découvreur** | 0 – 200 Koris | Offres basiques, Carte Wakabi active |
| 🌟 **Explorateur** | 201 – 500 Koris | Réductions améliorées, accès prioritaire events |
| 💫 **Insider** | 501 – 1 000 Koris | Accès VIP, invitations exclusives, cashback |
| 👑 **Legend** | 1 000+ Koris | Tous les avantages + expériences uniques |

> **Le mot « badge » existe déjà chez Wakabi** — l'app affiche un
> « Badge *Explorateur Gold* ». C'est le pont naturel vers le concept mougni : le
> générateur peut produire le **visuel partageable d'un statut déjà gagné**, au lieu
> d'inventer une mécanique nouvelle.

---

## 6. Architecture technique existante

**WordPress headless.** Le site est un ensemble de pages HTML statiques qui interrogent
une API WordPress. Chaque page embarque son propre client :

```js
const WP_BASE   = 'https://admin.wakabileguide.com/wp-json/wp/v2';
const WP_CUSTOM = 'https://admin.wakabileguide.com/wp-json/wakabi/v1';
```

| Endpoint | Usage observé |
|---|---|
| `wp/v2/cities` | Villes — tri par `meta.wk_sort_order` |
| `wp/v2/partners` | Partenaires — filtrés sur `meta.wk_status !== 'suspended'` |
| `wp/v2/posts` | Blog, avec `_embed` |
| `wakabi/v1/contact` | **POST** — formulaire de contact |

**Champs meta repérés :** `wk_status`, `wk_sort_order`, `wk_taxonomies`, `wk_flag`,
`wk_country`. Sur `partners` : `title`, `excerpt`, `featured_image_url`, `meta.wk_status`,
`wk_taxonomies`.

**Application mobile Android** publiée : `com.wakabi.wakabimobile` sur Google Play.

**Autres services :** Google Tag Manager, `ipapi.co/json/` (géolocalisation par IP),
icons8 en CDN, un blog PHP autonome sous `/blog` avec son propre cache.

### Ce que cela change pour le générateur

Le catalogue de décors **n'a pas besoin d'un nouveau back-office**. La v0.1 proposait
Supabase&nbsp;; c'était une erreur d'appréciation faite sans connaître l'existant. Introduire
Supabase obligerait l'équipe à gérer **deux administrations** — ce qui contredit
frontalement la contrainte C5.

**Recommandation révisée :** faire de `decor_template` un **custom post type WordPress**
dans l'admin déjà en place, exposé via `wakabi/v1/decors`. Bénéfices immédiats :

- l'équipe publie ses décors depuis l'interface qu'elle utilise déjà&nbsp;;
- un décor référence nativement un `partner` ou une `city` — l'ancrage devient gratuit&nbsp;;
- aucun fournisseur supplémentaire, aucune authentification en double&nbsp;;
- les compteurs tiennent dans un `wakabi/v1/decors/{id}/count`.

### Ce qui reste inconnu

Le site ne consomme que `cities`, `partners` et `posts`. **Aucun endpoint `events` ou
`places` n'apparaît** — alors que l'app mobile expose manifestement des événements et des
établissements. Il faut donc obtenir la liste complète des routes de
`admin.wakabileguide.com/wp-json` avant de figer le modèle d'ancrage.
