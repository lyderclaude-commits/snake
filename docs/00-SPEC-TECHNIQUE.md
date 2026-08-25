# Wakabi Studio — Générateur de décors & visuels personnalisés
### Spécification technique & architecture — v0.1 (document de travail)

---

## 0. Périmètre de fiabilité de ce document

**À lire en premier.** Cette session s'exécute derrière un proxy réseau restrictif. Les trois sources
que je voulais analyser directement ont été refusées :

| Source | Statut | Conséquence |
|---|---|---|
| `wakabileguide.com` | `EGRESS_BLOCKED` | **Aucun hex, aucune font, aucun logo extrait.** |
| `mougni.com` | `EGRESS_BLOCKED` | Fonctionnalités reconstituées par recherche, non vérifiées. |
| `waka.loremcommunication.com` (miroir) | `EGRESS_BLOCKED` | Idem. |

Ce qui suit sur Wakabi et mougni provient de **résultats de recherche**, pas d'une inspection du DOM.
Tout élément de charte graphique est donc traité comme une **variable**, jamais comme une constante :
la DA vit dans un fichier unique (`src/styles/wakabi-tokens.css`), et le reste du code n'écrit
jamais une couleur en dur. Le jour où vous me donnez le logo + les hex + les polices, le rebranding
complet est un diff d'un seul fichier.

> Ce qui n'est pas vérifié est marqué **`[À CONFIRMER]`** dans tout le document.

---

## 1. Positionnement produit

### 1.1 Ce que je sais de Wakabi

Wakabi Le Guide se présente comme **« le guide des bons coins »** couvrant **Lomé, Cotonou et
Abidjan** (zone UEMOA), avec un ancrage d'origine togolais. Les rubriques identifiées :

- **Gastronomie** — restaurants, maquis, bars, cocktails signature
- **Événements** — concerts, soirées, festivals, expositions
- **Hébergements** — hôtels, maisons d'hôtes
- **Culture & découverte** — traditions, culture de rue, spots cachés
- **Carte Wakabi** — réductions exclusives chez les partenaires
- **Koris** — monnaie virtuelle gagnée à chaque visite partenaire, convertible en réductions,
  accès VIP et privilèges

Ton éditorial : chaleureux, dynamique, tutoiement probable, très ancré local. **`[À CONFIRMER]`**

### 1.2 Ce que je sais de mougni.com

mougni.com est un **générateur de badges « J'y serai »** pour concerts, festivals et matchs. Ses
arguments affichés :

- **« Créez votre badge en 30 secondes »** — la promesse est la *vitesse*
- **« Gratuit et sans inscription »** — friction zéro, aucun compte
- **« +10 000 badges créés »** — preuve sociale chiffrée en page d'accueil
- **« Partagez sur WhatsApp instantanément »** — un canal de partage prioritaire, pas générique

C'est le **même genre de produit que le vôtre**. Ce n'est pas une source d'inspiration lointaine,
c'est un concurrent direct sur un marché adjacent. La question n'est donc pas « que copier »,
mais « **quel est notre avantage structurel qu'il ne peut pas répliquer** ».

### 1.3 L'angle de remix : le décor ancré

mougni produit un badge **générique et orphelin** : une fois créé, il ne mène nulle part.

Wakabi possède une chose que mougni n'a pas : **une base de lieux, d'événements, de partenaires
réels, et une économie (Koris)**. D'où la thèse produit que je propose :

> **Chaque visuel généré est rattaché à une entité réelle du catalogue Wakabi** — un lieu, un
> événement, un partenaire, une campagne — et devient un point d'entrée vers ce contenu.

Trois conséquences concrètes :

1. **Le visuel devient une affiche cliquable.** Le partage WhatsApp embarque un lien
   `wakabi.../decor/{slug}` dont l'aperçu (OG image) est le décor lui-même. Le destinataire ne voit
   pas juste une image : il voit l'événement, et peut le retrouver dans Wakabi.
2. **Le visuel devient une transaction.** Créer et partager un décor de campagne peut créditer des
   **Koris**. C'est un levier d'acquisition que mougni ne peut pas offrir. **`[À CONFIRMER]`**
3. **Le visuel devient un inventaire vendable.** Un restaurant ou un festival partenaire peut
   commander son décor sponsorisé. C'est un produit publicitaire natif.

**Nom de travail : Wakabi Studio.** `[À CONFIRMER]`

---

## 2. Contraintes structurantes

Ces six contraintes dictent la quasi-totalité des choix techniques qui suivent. Elles ne sont pas
décoratives : chaque décision de la section 3 y renvoie.

| # | Contrainte | Impact technique |
|---|---|---|
| **C1** | **Android entrée de gamme** (2–4 Go RAM) majoritaire dans la zone | Le canvas d'édition doit rester ~60 fps ; interdiction de manipuler une image 12 Mpx en mémoire principale |
| **C2** | **Réseau 3G/4G instable, data payante et chère** | Payload initial minimal, offline-first, **aucun upload de photo utilisateur** |
| **C3** | **WhatsApp = canal de partage n°1** dans la zone | Web Share API niveau 2, format **statut 9:16** en first-class, OG image dynamique |
| **C4** | **Friction zéro** (leçon retenue de mougni) | Création complète **sans compte** ; l'auth n'arrive qu'au moment des Koris |
| **C5** | **L'équipe publie une campagne sans développeur** | Modèles **pilotés par la donnée**, pas par le code ; back-office |
| **C6** | **Droit à l'image & vie privée** | La photo de l'utilisateur **ne quitte jamais son appareil** |

> **C6 et C2 se renforcent.** Tout faire côté client n'est pas seulement plus respectueux : c'est
> aussi moins cher (pas de stockage, pas de bande passante sortante), plus rapide sur réseau
> dégradé, et cela supprime toute question de conservation de données personnelles. C'est le choix
> par défaut du projet, et il ne doit être remis en cause qu'avec une très bonne raison.

---

## 3. Stack technique

| Couche | Choix | Justification |
|---|---|---|
| **Framework** | **Next.js 15 (App Router) + React 19 + TypeScript strict** | SSG pour les pages campagne (SEO), **génération d'OG images dynamiques** via `next/og` (levier viral direct, cf. C3), routes API pour compteurs |
| **Moteur canvas** | **Konva + react-konva** | Scène minuscule (photo + cadre + textes) ; bindings React déclaratifs, gestion tactile/pointer solide, ~150 Ko. *Fabric.js est plus lourd et impératif ; PixiJS/WebGL est hors-sujet ici.* |
| **Rendu export** | **Canvas 2D natif + `OffscreenCanvas` en Web Worker** | Voir §4 : le rendu HD ne doit pas geler le thread principal sur un Android d'entrée de gamme (C1) |
| **Styles** | **Tailwind CSS v4 + CSS custom properties** | Les tokens de marque dans **un seul fichier** ; Tailwind ne fait que les consommer |
| **État éditeur** | **Zustand** | ~1 Ko, store sérialisable — c'est exactement le `RenderSpec` (§4) |
| **Validation** | **Zod** | Un seul schéma pour le back-office, l'API et l'éditeur (§5) |
| **Données & back-office** | **Supabase** (Postgres + Storage + Auth + RLS) | Un seul fournisseur pour les modèles, les assets, les compteurs et l'auth Koris ; coût quasi nul à ce volume |
| **PWA / offline** | **Serwist** (`@serwist/next`) | Installable, modèles en cache — répond directement à C2 |
| **Cache client** | **IndexedDB** (via `idb`) | Cadres PNG et photos récentes mis en cache : deuxième création = zéro data |
| **Analytics** | **Plausible ou Umami** + événements custom | Léger, sans cookie, sans bandeau de consentement |
| **Tests** | **Vitest** + **Playwright** (dont *golden image*) | Le renderer est une fonction pure → tests de non-régression visuelle fiables (§4) |
| **Déploiement** | **Vercel** ou **Cloudflare Pages** | Edge proche de l'Afrique de l'Ouest ; à arbitrer sur la latence réelle mesurée |

### Ce que je n'ai délibérément pas retenu

- **Application native / React Native** — le partage viral passe par un **lien** ouvert dans
  WhatsApp. Imposer un téléchargement d'app tue la boucle. Une PWA installable couvre le besoin.
- **Rendu serveur des visuels** (Sharp / Puppeteer / Cloudinary) — coûteux, impose l'upload de la
  photo (viole C6), et ajoute une latence sur réseau dégradé (C2). Le navigateur sait faire.
  *Exception : les OG images, qui elles sont générées serveur — mais sans la photo de l'utilisateur.*
- **Fabric.js** — largement surdimensionné pour une scène à trois calques.
- **Éditeur généraliste type Canva** — hors périmètre. La valeur est dans la **contrainte** :
  un décor cadré, réussi, en 30 secondes.

---

## 4. Architecture du rendu — la décision centrale

C'est le point qui conditionne à la fois la fluidité (C1) et la qualité d'export. Le principe :

> **Une seule fonction de rendu, deux échelles. Jamais deux implémentations.**

```
                    RenderSpec  (objet plat, sérialisable, = l'état Zustand)
                         │
          ┌──────────────┴───────────────┐
          ▼                              ▼
   ÉCHELLE PREVIEW                 ÉCHELLE EXPORT
   ~640 px, thread principal       2048 px, Web Worker + OffscreenCanvas
   Konva Stage (interactif)        Canvas 2D (aucun DOM)
   60 fps, tactile                 toBlob() → JPEG 0.92 / PNG
          │                              │
          └──────────► renderScene(ctx, spec, scale) ◄──────┘
                       fonction pure, zéro dépendance DOM
```

**Pourquoi c'est important :**

- **WYSIWYG garanti par construction.** L'aperçu et l'export exécutent le *même* code. Le bug
  classique du genre — « l'export ne ressemble pas à l'aperçu » — devient structurellement
  impossible.
- **Fluidité sur téléphone modeste.** L'utilisateur manipule 640 px, pas 2048 px. Le coût du HD
  n'est payé qu'une fois, à l'export, hors du thread principal.
- **Testabilité.** `renderScene` est pure → on la rend dans un canvas Node, on compare au pixel
  près avec une image de référence. Les tests de régression visuelle deviennent triviaux.

### Signatures de référence

```ts
/** Décrit intégralement un visuel. Sérialisable → partageable, restaurable, testable. */
export interface RenderSpec {
  templateId: string;
  canvas: { width: number; height: number };      // dimensions logiques (échelle 1)
  photo: {
    bitmap: ImageBitmap;
    x: number; y: number;                          // en unités logiques
    scale: number; rotation: number; flipX: boolean;
  } | null;
  filter: FilterId;                                // 'none' | 'wakabi-warm' | ...
  filterIntensity: number;                         // 0..1
  texts: Record<string, string>;                   // slotId -> contenu saisi
}

/** Rend la scène. `scale` = 1 pour l'export HD, ~0.3 pour l'aperçu. */
export function renderScene(
  ctx: CanvasRenderingContext2D | OffscreenCanvasRenderingContext2D,
  spec: RenderSpec,
  template: DecorTemplate,
  scale: number,
): void;
```

---

## 5. Le contrat de modèle (`DecorTemplate`)

C'est **l'artefact technique le plus important du projet**. Il permet à l'équipe Wakabi de publier
une campagne sans écrire de code ni redéployer (C5). Le schéma Zod complet est dans
[`docs/contracts/template.schema.ts`](./contracts/template.schema.ts).

Structure en une phrase : *un canevas, une pile de calques ordonnée, dont exactement un est
l'emplacement réservé à la photo de l'utilisateur.*

```
DecorTemplate
├── identité        id, slug, title, subtitle
├── rattachement    campaignId, placeId?, eventId?, city, publishedAt, expiresAt
│                   ↳ c'est ce qui rend le visuel « ancré » (§1.3)
├── canvas          { width, height, ratio, background }
├── layers[]        ordonnées du fond vers l'avant
│   ├── photoSlot   rect, fit, mask?, initialScale, minScale, maxScale   ← un seul, obligatoire
│   ├── image       src (cadre PNG/WebP à alpha), rect, opacity, blendMode?
│   └── text        rect, editable, placeholder, maxLength, font, size, color, align, uppercase?
├── filters[]       liste blanche des filtres autorisés pour ce modèle
├── export          formats[], maxPx, mimeType, quality
└── share           defaultCaption, hashtags[], utm
```

**Trois règles que le schéma fait respecter :**

1. **Exactement un `photoSlot`** par modèle — sinon le modèle est rejeté à la publication.
2. **Les coordonnées sont normalisées** (0–1 du canevas), jamais en pixels. Un même modèle se
   décline ainsi en 1:1 et 9:16 sans retouche.
3. **Le cadre est toujours au-dessus du `photoSlot`** dans l'ordre des calques, et
   `listening: false` : il ne doit jamais intercepter un geste tactile.

---

## 6. Inventaire des composants de l'éditeur

### 6.1 Arbre React

```
app/decor/[slug]/page.tsx                 (RSC — charge le DecorTemplate, génère l'OG image)
└── <DecorStudio>                         orchestrateur client ; détient le RenderSpec
    │
    ├── ÉTAPE 1 — CHOISIR
    │   ├── <TemplateGallery>             grille filtrable (campagne / ville / rubrique)
    │   │   ├── <TemplateCard>            vignette + badge « nouveau » / « expire bientôt »
    │   │   └── <TemplateFilters>         chips ville, rubrique, événement
    │   └── <CampaignHero>                titre, pitch, <CounterBadge/> (preuve sociale)
    │
    ├── ÉTAPE 2 — IMPORTER
    │   ├── <PhotoDropzone>               <input type="file" accept="image/*"> + drag & drop
    │   ├── <CameraCapture>               capture="user" — photo directe sur mobile
    │   ├── <SamplePhotoPicker>           photos Wakabi de secours (l'utilisateur n'a pas toujours
    │   │                                 une photo prête — supprime un point d'abandon majeur)
    │   └── <ImageLoadingState>           décodage + downscale (§7) ; jamais d'écran figé
    │
    ├── ÉTAPE 3 — AJUSTER
    │   ├── <CanvasStage>                 Konva Stage — aperçu ~640 px
    │   │   ├── <PhotoLayer>              image utilisateur : draggable, zoomable, rotatable
    │   │   ├── <FrameLayer>              cadre PNG à alpha — listening={false}
    │   │   ├── <TextLayer>               slots texte rendus selon le modèle
    │   │   └── <SafeAreaOverlay>         repères de zone réservée (admin / preview only)
    │   ├── <GestureController>           Pointer Events : pan, pinch-zoom, double-tap → recadrer
    │   ├── <CropToolbar>                 slider zoom, rotation ±, miroir, recentrer, réinitialiser
    │   ├── <FilterRail>                  carrousel de filtres + slider d'intensité
    │   ├── <TextPanel>                   saisie des slots éditables, compteur de caractères
    │   └── <RatioSwitcher>               1:1 (feed) · 9:16 (statut WhatsApp) · 16:9
    │
    └── ÉTAPE 4 — EXPORTER
        ├── <PreviewSheet>                aperçu plein écran avant validation
        └── <ExportBar>
            ├── <ShareButton>             Web Share API L2 — chemin **principal** sur mobile
            ├── <DownloadButton>          toBlob + <a download> — chemin de repli
            ├── <SaveHintSheet>           « appui long pour enregistrer » (webviews iOS, §7)
            ├── <CopyLinkButton>          lien de campagne + UTM
            └── <QualityPicker>           HD 2048 · Statut · Web léger
```

### 6.2 Modules hors React (le cœur métier)

| Module | Rôle |
|---|---|
| `core/renderScene.ts` | **Fonction de rendu pure.** Le cœur du produit (§4). |
| `core/exportWorker.ts` | Worker : `OffscreenCanvas` → rendu HD → `Blob`. |
| `core/imagePipeline.ts` | `createImageBitmap` (EXIF), downscale, garde-fou mémoire (§7). |
| `core/fitPhoto.ts` | Calcul de cadrage initial « cover » dans le `photoSlot`. |
| `core/filters.ts` | Filtres déterministes, identiques aperçu ↔ export. |
| `core/templateSchema.ts` | Schéma Zod partagé back-office / API / éditeur. |
| `store/useRenderSpec.ts` | Store Zustand = `RenderSpec` (§4). |
| `lib/share.ts` | Détection de capacité + arbre de décision de partage (§7). |

---

## 7. Pipeline image & export — les pièges à traiter dès le départ

Ces quatre points sont, d'expérience, ceux qui font échouer ce type d'outil en production. Ils sont
listés ici pour être budgétés maintenant, pas découverts en recette.

**1. Orientation EXIF.** Une photo prise au téléphone porte une rotation dans ses métadonnées. Lue
naïvement, elle s'affiche couchée. → `createImageBitmap(file, { imageOrientation: 'from-image' })`,
et vérification explicite sur iOS.

**2. Saturation mémoire (C1).** Une photo de 12 Mpx décompressée occupe ~48 Mo de RAM. Deux ou
trois manipulations et un Android d'entrée de gamme fait tomber l'onglet. → **downscale immédiat**
à ~2560 px sur le grand côté, *avant* toute mise en scène. L'original n'est jamais conservé.

**3. Le téléchargement échoue dans les webviews.** Ouvert depuis Instagram, Facebook ou parfois
WhatsApp, `<a download>` est ignoré ou silencieusement bloqué — sur iOS en particulier. Or c'est
**exactement** le chemin d'arrivée de nos utilisateurs (C3). → arbre de décision explicite :

```
navigator.canShare?.({ files: [blob] })
├─ oui  → navigator.share()                    ← chemin principal mobile (WhatsApp natif)
└─ non
   ├─ navigateur standard → <a download>        ← chemin desktop
   └─ webview détectée    → afficher l'image en plein écran
                            + <SaveHintSheet> « appui long pour enregistrer »
```

**4. Qualité d'export.** JPEG qualité 0.92 par défaut (bon rapport poids/qualité pour une photo).
PNG uniquement si le modèle exige la transparence — le poids est ~4× supérieur, ce qui coûte cher
au partage sur data payante (C2).

---

## 8. Grille d'analyse de mougni.com

Vous m'avez proposé de me transmettre les spécificités de mougni. Voici la **grille dans laquelle
je vous propose de les verser**, pour que l'analyse produise des décisions plutôt qu'une liste.

### 8.1 Les dix axes à relever

Pour chaque axe, la question est : *qu'est-ce que mougni fait, et pourquoi ?*

| # | Axe | Ce qu'il faut observer |
|---|---|---|
| 1 | **Friction d'entrée** | Combien d'écrans avant le premier résultat ? Compte obligatoire ou non ? |
| 2 | **Catalogue de modèles** | Combien ? Taxonomie ? Qui les crée ? À quelle fréquence ? |
| 3 | **Manipulations offertes** | Zoom/pan seulement, ou rotation, filtres, calques, stickers ? |
| 4 | **Personnalisation texte** | Champs libres ? Longueur max ? Choix de police ? |
| 5 | **Export & partage** | Formats, résolutions, canaux, présence d'un filigrane |
| 6 | **Preuve sociale** | Le compteur « +10 000 » — réel ou décoratif ? Mur public des créations ? |
| 7 | **Boucle virale** | Lien de campagne, aperçu OG, parrainage, UTM |
| 8 | **Monétisation** | Sponsors, marques, premium, retrait de filigrane |
| 9 | **Back-office** | Self-service pour un organisateur, ou création interne ? |
| 10 | **Données & analytics** | Que mesurent-ils ? Que gardent-ils ? |

### 8.2 La grille de décision

Chaque fonctionnalité relevée passe ensuite par ce tableau, et **doit** en ressortir avec un verdict :

| Fonctionnalité | Valeur user | Effet viral | Cohérence Wakabi | Coût (S/M/L) | Backend requis | Verdict |
|---|---|---|---|---|---|---|

**Verdicts possibles :** `ADOPTER` (tel quel) · `REMIXER` (l'idée, exécutée à la sauce Wakabi) ·
`ÉCARTER` (contraire à notre positionnement) · `DIFFÉRER` (bon, mais pas en v1).

### 8.3 Premier passage — à partir de ce que j'ai pu vérifier

| Fonctionnalité mougni | Valeur | Viral | Cohérence | Coût | Backend | Verdict proposé |
|---|---|---|---|---|---|---|
| Sans inscription | ★★★ | ★★★ | ★★★ | S | non | **ADOPTER** — l'auth n'apparaît qu'aux Koris |
| « En 30 secondes » | ★★★ | ★★☆ | ★★★ | M | non | **ADOPTER** comme *contrainte de conception* : c'est un budget temps, pas un slogan |
| Partage WhatsApp direct | ★★★ | ★★★ | ★★★ | S | non | **ADOPTER** — Web Share API L2 (§7) |
| Compteur « +10 000 créés » | ★★☆ | ★★★ | ★★☆ | S | oui | **REMIXER** — compteur **par campagne**, honnête, temps réel |
| Badge « J'y serai » | ★★★ | ★★★ | ★★☆ | S | non | **REMIXER** — « J'y serai » → décor **rattaché à un événement réel du catalogue** (§1.3) |
| Badge générique orphelin | — | — | ★☆☆ | — | — | **ÉCARTER** — c'est précisément notre différence |
| Mur public des créations | ★★☆ | ★★☆ | ★★★ | M | oui + modération | **DIFFÉRER** — fort, mais exige une modération avant ouverture |
| Décors sponsorisés partenaires | ★★☆ | ★★☆ | ★★★ | M | oui | **DIFFÉRER v2** — modèle de revenu natif, adossé aux partenaires existants |
| Récompense en Koris | ★★★ | ★★★ | ★★★ | L | oui + auth | **REMIXER — l'atout maître.** Aucune contrepartie possible chez mougni |

> **La ligne à retenir :** les trois premières lignes sont ce qui rend le produit *utilisé*. La
> dernière est ce qui le rend *défendable*. Les deux doivent être dans la feuille de route, mais
> pas dans le même jalon.

---

## 9. Découpage proposé

| Jalon | Contenu | Sortie |
|---|---|---|
| **J0 — Socle** | Tokens de marque, `renderScene`, schéma `DecorTemplate`, 1 modèle codé en dur | Un visuel exportable de bout en bout |
| **J1 — Éditeur** | Upload, gestes, recadrage, export HD, partage | Produit utilisable, mono-campagne |
| **J2 — Catalogue** | Supabase, back-office modèles, galerie, compteurs, OG images | L'équipe publie sans développeur |
| **J3 — Ancrage** | Rattachement lieux/événements, UTM, analytics, PWA offline | La boucle virale est fermée |
| **J4 — Koris** | Auth, attribution des Koris, décors sponsorisés | Différenciation & revenu |

---

## 10. Questions ouvertes

Voir [`docs/01-QUESTIONS.md`](./01-QUESTIONS.md).
