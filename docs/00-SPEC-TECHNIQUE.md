# Wakabi Studio — Générateur de décors & visuels personnalisés
### Spécification technique & architecture — v0.5 (document de travail)

---

## 0. Périmètre de fiabilité de ce document

**Mis à jour le 25/08/2026.** L'archive du site `wakabileguide.com` a été fournie et
dépouillée : 12 pages HTML de production, `blog/assets/wakabi.css` (80 Ko) et l'archive
`Wakabi le guide v3.1.zip`.

| Sujet | Statut | Source |
|---|---|---|
| Charte graphique Wakabi | ✅ **Vérifié** | `:root` des 12 pages + `wakabi.css` |
| Ton éditorial & lexique | ✅ **Vérifié** | Contenu des pages |
| Économie Kori & niveaux | ✅ **Vérifié** | `kori.html` |
| Architecture technique | ✅ **Vérifié** | Clients API embarqués dans chaque page |
| Fonctionnalités mougni.com | ✅ **Vérifié** | Page d'accueil complète fournie (118 Ko) — voir [`docs/03-ANALYSE-MOUGNI.md`](./03-ANALYSE-MOUGNI.md) |

Le détail complet de l'audit est dans [`docs/02-CHARTE-WAKABI.md`](./02-CHARTE-WAKABI.md).

> **Deux corrections par rapport à la v0.1 de ce document.**
> **(1)** La marque Wakabi est **bleue** (`#2563EB`), pas orange. Les placeholders terre
> cuite étaient faux et ont été remplacés par les valeurs réelles dans
> `docs/contracts/wakabi-tokens.css`.
> **(2)** Wakabi tourne déjà sur un **WordPress headless** avec une API custom. La
> proposition Supabase de la v0.1 est retirée — voir §3.

**Rappel de contexte produit :** le programme **Kori est actuellement désactivé** côté
Wakabi. Cela déplace l'angle de différenciation de la v1 — voir §1.3.

Plus aucun volet du document ne repose sur des hypothèses.

---

## 1. Positionnement produit

### 1.1 Wakabi, d'après son propre code

**Positionnement.** « Le guide des bons coins » — Lomé, Cotonou, Abidjan, avec une
présence affichée sur le Togo, le Bénin, la Côte d'Ivoire, le Sénégal, le Cameroun et le
Gabon. Signature : *« Wakabi transforme chaque sortie en expérience inoubliable. Restos,
sorties, événements, hébergements — tout ce qu'il faut sur ta ville, en une seule app. »*

**Ton.** Tutoiement systématique, phrases courtes, registre direct :
« Ton prochain bon coin t'attend » · « Vis ta ville à 100% » · « En 3 étapes, c'est plié » ·
« Gagne. Dépense. Explore. » · « En direct depuis Lomé, Togo ».

**Lexique maison** à reprendre tel quel : *bon coin*, *explorateur*, *Kori*,
*Carte Wakabi*, *partenaire*.

**Économie Kori.** Symbole **₵**. 1 achat = 10 Koris minimum ; 100 Koris = −500 XOF.
Quatre niveaux : 🌱 Découvreur (0–200) · 🌟 Explorateur (201–500) · 💫 Insider (501–1 000) ·
👑 Legend (1 000+). La Carte Wakabi est un QR Code dans l'app.

**Existant technique.** Site statique interrogeant un **WordPress headless** sur
`admin.wakabileguide.com` (`wp/v2/cities`, `wp/v2/partners`, `wp/v2/posts`,
`wakabi/v1/contact`), plus une **application Android** publiée
(`com.wakabi.wakabimobile`).

> **Précision de l'équipe :** ce WordPress est le **back-end éditorial**, pas la base du
> produit — il sert à gérer les articles. Il n'expose **ni événements ni établissements** ;
> ces données vivent dans le back-end de l'app mobile. L'ancrage se fera donc par **URL**
> et non par jointure — voir §1.3.

> **Le mot « badge » est déjà dans le produit.** L'app affiche un « Badge *Explorateur
> Gold* ». C'est le pont le plus court vers mougni : plutôt que d'inventer une mécanique,
> le générateur peut produire **le visuel partageable d'un statut déjà gagné**.

### 1.2 mougni.com, d'après sa page d'accueil

**mougni n'est pas un générateur de badges.** C'est une **plateforme SaaS de mobilisation
de communauté** pour l'Afrique francophone, articulant trois canaux : **WhatsApp**
(diffusion, chatbots, rappels J-1 et H-2), **« J'y serai »** (le générateur de badges) et
**Web Push**. Plus un quatrième produit : la **publicité native dans les badges**.

Quatre paliers mensuels en FCFA — Découverte 0 (avec watermark), Impact 5 000, Croissance
10 000, Mouvement 30 000 — et des crédits WhatsApp vendus à part à **2,21 FCFA le message**.

> **Le badge n'est pas le produit, c'est l'appât.** L'argent est dans l'abonnement et les
> crédits WhatsApp. Le badge attire les organisateurs, qui achètent ensuite du message.

**L'éditeur se limite à « zoom, position ».** C'est écrit dans leur FAQ. Pas de rotation,
pas de filtres, pas de calques. Le leader de la catégorie tient avec **deux gestes** —
c'est un enseignement direct sur notre propre périmètre (§7).

**Deux frictions distinctes :** le participant crée son badge **sans compte** ; seul
l'**organisateur** doit se connecter pour publier une campagne. Bon arbitrage, à reprendre.

Analyse complète, grille de décision sur 16 points et recommandations de retrait :
[`docs/03-ANALYSE-MOUGNI.md`](./03-ANALYSE-MOUGNI.md).

### 1.3 L'angle de remix : le générateur comme argument de vente

mougni produit un badge **générique et orphelin** : une fois créé, il ne mène nulle part.
Wakabi possède ce que mougni n'a pas — un catalogue de lieux, d'événements et de
partenaires réels, et une audience déjà constituée.

> **Chaque visuel généré renvoie vers une page Wakabi** — la fiche d'un lieu, d'un
> événement, d'un partenaire — **au moment du téléchargement**.

**Le mécanisme est une URL, pas une jointure.** Le WordPress ne servant que le contenu
éditorial, l'ancrage passe par `share.redirectUrl` : l'équipe ou le partenaire colle le
lien de la fiche au moment de créer le décor. C'est plus simple, indépendant de toute API,
et disponible dès la v1. Le schéma va jusqu'à **refuser un décor publié sans
redirection** — sans elle, le badge ne ramène personne, et toute la stratégie tombe.

Les champs `partnerId`, `placeId` et `eventId` restent dans le schéma comme métadonnées
descriptives, prêts à devenir de vraies jointures le jour où le back-end de l'app
s'ouvrira, sans casser les décors déjà publiés.

**Le Kori étant désactivé**, la v0.1 avait tort d'en faire l'atout maître. L'analyse de
mougni en fournit un meilleur, et immédiatement actionnable :

> **Les clients de mougni sont exactement les partenaires de Wakabi.** Associations,
> organisateurs, restaurants, paroisses, marques locales. mougni leur facture **5 000 à
> 30 000 FCFA par mois** pour leurs badges.
>
> **Le générateur n'est donc pas un produit à vendre : c'est un argument de vente pour
> l'offre partenaire.** Wakabi inclut les visuels de campagne dans la Carte Partenaire —
> et y ajoute ce que mougni ne peut pas fournir : **son audience**. Un organisateur qui
> passe par mougni doit amener sa propre communauté ; celui qui passe par Wakabi est exposé
> aux explorateurs de sa ville.

Trois conséquences opérationnelles :

1. **Le générateur devient un outil de l'équipe commerciale**, démontrable en rendez-vous
   partenaire — pas seulement un gadget grand public.
2. **Le filigrane Wakabi devient évident.** L'outil est gratuit, donc le logo est
   systématique : pure visibilité, sans l'arbitrage commercial que mougni doit faire
   (chez eux, retirer le watermark est la première raison de payer).
3. **La redirection après téléchargement est la brique centrale**, pas une option. mougni
   la facture dès 5 000 FCFA ; chez nous elle est gratuite et structurante.

Le Kori redevient ce qu'il aurait dû rester : **un jalon ultérieur**, à activer le jour où
le programme de fidélité sera rallumé.

## 2. Contraintes structurantes

Ces six contraintes dictent la quasi-totalité des choix techniques qui suivent. Elles ne sont pas
décoratives : chaque décision de la section 3 y renvoie.

| # | Contrainte | Impact technique |
|---|---|---|
| **C1** | **Android entrée de gamme** (2–4 Go RAM) majoritaire dans la zone | Le canvas d'édition doit rester ~60 fps ; interdiction de manipuler une image 12 Mpx en mémoire principale |
| **C2** | **Réseau 3G/4G instable, data payante et chère** | Payload initial minimal, offline-first, **aucun upload de photo utilisateur** |
| **C3** | **WhatsApp = canal de partage n°1** dans la zone | Web Share API niveau 2, format **statut 9:16** en first-class, OG image dynamique |
| **C4** | **Friction zéro** (leçon retenue de mougni) | Création complète **sans compte** ; l'auth n'arrive qu'au moment des Koris, via le compte Wakabi existant |
| **C5** | **L'équipe publie une campagne sans développeur** | Modèles **pilotés par la donnée**, pas par le code — et dans le **WordPress qu'elle utilise déjà** |
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
| **Styles** | **Tailwind CSS v4 + CSS custom properties** | Les tokens réels de Wakabi dans **un seul fichier** (`wakabi-tokens.css`) ; Tailwind ne fait que les consommer |
| **Typographie** | **Bricolage Grotesque** (titres) + **Plus Jakarta Sans** (corps) | Les deux polices déjà chargées par le site. *Absolut Pro* est réservée au logotype — elle n'existe qu'en Bold |
| **Icônes** | **Phosphor** ou Lucide, embarquées en SVG | Remplace les appels CDN vers icons8, dans le même style linéaire fin ; zéro requête réseau **C2** |
| **État éditeur** | **Zustand** | ~1 Ko, store sérialisable — c'est exactement le `RenderSpec` (§4) |
| **Validation** | **Zod** | Un seul schéma pour le back-office, l'API et l'éditeur (§5) |
| **Données & back-office** | **Le WordPress existant** — `decor_template` en custom post type, exposé via `wakabi/v1/decors` | L'équipe publie depuis l'admin qu'elle utilise déjà **C5**, et le circuit de modération est natif (§6.3) ; aucun fournisseur ni compte supplémentaires |
| **Ancrage** | **Une URL** (`share.redirectUrl`), pas une jointure d'API | Le WordPress ne sert que l'éditorial : ni événements ni établissements. L'URL fonctionne dès la v1 et ne dépend de rien |
| **PWA / offline** | **Serwist** (`@serwist/next`) | Installable, modèles en cache — répond directement à C2 |
| **Cache client** | **IndexedDB** (via `idb`) | Cadres PNG et photos récentes mis en cache : deuxième création = zéro data |
| **Analytics** | **Plausible ou Umami** + événements custom | Léger, sans cookie, sans bandeau de consentement |
| **Tests** | **Vitest** + **Playwright** (dont *golden image*) | Le renderer est une fonction pure → tests de non-régression visuelle fiables (§4) |
| **Déploiement** | **Vercel** ou **Cloudflare Pages** | Edge proche de l'Afrique de l'Ouest ; à arbitrer sur la latence réelle mesurée |

> **Révision par rapport à la v0.1.** Ce document recommandait Supabase, faute de
> connaître l'existant. L'archive montre un WordPress headless déjà en place, avec un
> namespace REST custom et des custom post types. Ajouter Supabase imposerait à l'équipe
> **deux back-offices à tenir** — exactement ce que la contrainte C5 cherche à éviter.
> Le catalogue de décors doit donc vivre dans le WordPress existant.

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
    │   └── <CampaignHero>                titre, pitch, <CampaignStats/>
    │       └── <CampaignStats>           ⬇ téléchargements · 👁 vues — deux compteurs
    │                                     publics par campagne, comme mougni. Le
    │                                     rapport des deux dit le taux de conversion.
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
        ├── <ExportBar>
        │   ├── <ShareButton>         Web Share API L2 — chemin **principal** sur mobile
        │   ├── <DownloadButton>      toBlob + <a download> — chemin de repli
        │   ├── <SaveHintSheet>       « appui long pour enregistrer » (webviews iOS, §7)
        │   └── <CopyLinkButton>      lien de campagne + UTM
        └── <RedirectAfterDownload>   ← LA BRIQUE D'ANCRAGE. Renvoi vers la fiche
                                      Wakabi du partenaire ou de l'événement. mougni
                                      la facture dès 5 000 FCFA/mois ; chez nous elle
                                      est gratuite et structurante. C'est le seul
                                      moment où l'attention est totale.
```

### 6.2 Modules hors React (le cœur métier)

| Module | Rôle |
|---|---|
| `core/renderScene.ts` | **Fonction de rendu pure.** Le cœur du produit (§4). |
| `core/exportWorker.ts` | Worker : `OffscreenCanvas` → rendu HD → `Blob`. |
| `core/imagePipeline.ts` | `createImageBitmap` (EXIF), downscale, garde-fou mémoire (§7). |
| `core/fitPhoto.ts` | Calcul de cadrage initial « cover » dans le `photoSlot`. |
| `core/filters.ts` | Filtre signature unique, déterministe, identique aperçu ↔ export. |
| `core/watermark.ts` | Filigrane Wakabi, incrusté dans `renderScene`. Systématique : l'outil est gratuit, le logo voyage. |
| `core/templateSchema.ts` | Schéma Zod partagé back-office / API / éditeur. |
| `store/useRenderSpec.ts` | Store Zustand = `RenderSpec` (§4). |
| `lib/share.ts` | Détection de capacité + arbre de décision de partage (§7). |
| `core/preflight.ts` | Les 7 contrôles automatiques exécutés à la soumission d'un décor de partenaire. Réutilise `renderScene` — bénéfice direct de la fonction pure. |
| `core/transitions.ts` | `canTransition(from, to, actor)` — qui a le droit de faire quoi (§6.3). |

---

### 6.3 Côté back-office — création partagée

Les décors sont créés par **l'équipe Wakabi et par les partenaires**, ces derniers passant
par une relecture. L'essentiel vit dans le WordPress existant, pas dans le front Next.js :

| Écran | Où | Contenu |
|---|---|---|
| Édition d'un décor | Admin WP | Le CPT `decor_template`, avec ses calques |
| Soumission | Admin WP | Bouton natif « Soumettre à la relecture » — obtenu en retirant `publish_decors` au rôle partenaire |
| **Métabox Relecture** | Admin WP | *Approuver* · *Demander des corrections* · *Refuser*, motif obligatoire pour les deux derniers |
| **Rapport de pré-vol** | Admin WP | Les 7 contrôles automatiques, affichés au relecteur |
| File d'attente | Admin WP | Filtre natif sur le statut `pending` |

> **WordPress fait déjà 80 % du travail.** Le flux Contributeur → en attente → publication
> est natif : un rôle sans `publish_decors` voit son bouton « Publier » remplacé par
> « Soumettre à la relecture », ne peut pas modifier un décor déjà publié, et ne voit pas
> ceux des autres. Il n'y a rien à écrire pour cela. C'est le bénéfice concret d'avoir
> gardé le WordPress existant plutôt que d'ajouter Supabase.

Détail complet — machine à états, capacités WordPress, les 7 contrôles de pré-vol, la
grille de relecture humaine et la sécurité des téléversements — dans
[`docs/04-MODERATION.md`](./04-MODERATION.md).

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

## 8. Analyse de mougni.com

L'analyse complète — modèle économique, périmètre réel de l'éditeur, implémentation, et
une **grille de décision sur 16 points** (`ADOPTER` / `REMIXER` / `ÉCARTER` / `DIFFÉRER`)
— est dans [`docs/03-ANALYSE-MOUGNI.md`](./03-ANALYSE-MOUGNI.md).

Les quatre conclusions qui portent :

| Constat | Conséquence pour nous |
|---|---|
| **Le badge est l'appât, pas le produit.** L'argent est dans les crédits WhatsApp (2,21 FCFA/message) et l'abonnement. | Nous n'avons pas à monétiser l'outil : il finance la Carte Partenaire (§1.3). |
| **L'éditeur se limite à zoom + position.** Pas de rotation, pas de filtres. | Retirer `<FilterRail>`, la rotation et le miroir de la v1 (§7 du doc dédié). |
| **La redirection après téléchargement est vendue dès 5 000 FCFA.** | C'est notre brique d'ancrage, et elle est gratuite. |
| **Le watermark est ce qu'on paie pour retirer.** | L'outil étant gratuit, le filigrane Wakabi est systématique. Question A2 refermée. |

Deux choses à ne pas reprendre : les **copies des badges** livrées à l'organisateur —
elles supposent l'upload des photos côté serveur, ce qui viole C6 — et un
`aggregateRating` JSON-LD de 4,8/150 sans aucun avis réel sur la page, qui expose à une
pénalité manuelle Google.

## 9. Découpage proposé

| Jalon | Contenu | Sortie |
|---|---|---|
| **J0 — Socle** | Tokens de marque, `renderScene`, schéma `DecorTemplate`, 1 modèle codé en dur | Un visuel exportable de bout en bout |
| **J1 — Éditeur** | Upload, zoom, déplacement, export HD, partage, redirection | Produit utilisable, mono-campagne |
| | ↑ *le pré-vol arrive en J3, en même temps que l'ouverture aux partenaires — pas après. Sans lui, la file de relecture se remplit de décors cassés.* | |
| **J2 — Catalogue** | CPT `decor_template`, les deux rôles, statuts personnalisés, endpoints `wakabi/v1/decors`, galerie, OG images | **L'équipe** publie depuis son WordPress |
| **J3 — Ancrage & relecture** | Rattachement aux `partners`/`cities`, **pré-vol + métabox de relecture**, compteurs publics, UTM, PWA offline | **Les partenaires** soumettent, l'équipe relit |
| **J4 — Partenaires** | Notifications, quotas de téléversement, tableau de bord partenaire, décors sponsorisés | L'offre partenaire a son argument complet |
| **J5 — Koris** *(gelé)* | Attribution des Koris sur création et partage | **À activer quand le programme de fidélité sera rallumé** |

---

## 10. Questions ouvertes

L'archive a répondu à tout le bloc A (charte) et à l'essentiel du bloc B (écosystème).
Ce qui reste est dans [`docs/01-QUESTIONS.md`](./01-QUESTIONS.md). Les trois points
bloquants restants :

1. **Le logo dans une définition exploitable** — source vectorielle, ou à défaut un PNG à
   alpha d'au moins 2000 px de large. Le filigrane est incrusté dans chaque export 2048 px.
   *Non bloquant* : la variante `text` du filigrane permet de développer J0 et J1 sans
   attendre (voir [`02-CHARTE-WAKABI.md`](./02-CHARTE-WAKABI.md) §2 bis).
2. **« Bons coins » ou « bons plans » ?** Le logo dit « BONS PLANS », le site écrit
   « bons coins » 13 fois — dont le `<title>` de l'accueil. À trancher avant d'incruster
   la formule sur des milliers de visuels partagés.
3. **Le périmètre de la v1** (§9)  — combien de modèles au lancement, et avec quelle
   première campagne réelle.

*Tranchés : la charte (§2 du doc dédié), l'analyse mougni (§8), et la création partagée
équipe + partenaires avec modération Wakabi (§6.3).*
