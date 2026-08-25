# mougni.com — analyse et grille de remix

Source : page d'accueil complète fournie le 25/08/2026 (`mougni.com`, 118 Ko).
Ce document remplace l'analyse spéculative de la v0.1, qui reposait sur des résultats
de recherche.

---

## 1. Ce que mougni est vraiment

**mougni n'est pas un générateur de badges.** C'est une **plateforme SaaS de mobilisation
de communauté** pour l'Afrique francophone, qui articule trois canaux :

| Canal | Fonction |
|---|---|
| **WhatsApp** | Diffusion massive, chatbots, rappels automatiques (J-1, H-2) |
| **« J'y serai »** | Générateur de badges — *l'entonnoir d'acquisition* |
| **Web Push** | Réengagement navigateur, sans application |

S'y ajoute un quatrième produit : la **publicité native** — *« touchez notre audience
qualifiée avec des placements natifs directement dans les badges Mougni »*.

> **Le badge n'est pas le produit, c'est l'appât.** L'argent est dans l'abonnement et dans
> les crédits WhatsApp. Le badge attire les organisateurs, qui achètent ensuite du message.
> Cette lecture change tout ce qui suit.

Positionnement déclaré (JSON-LD) : `applicationCategory: BusinessApplication`,
`areaServed: Afrique francophone`, `priceCurrency: XOF`. Nom alternatif revendiqué :
**« Twibbon Mougni »** — *twibbon* est le nom générique de la catégorie (cadre de profil
de campagne).

**Ton :** vouvoiement, registre B2B — « Mobilisez votre communauté », « Ne soyez jamais
ignoré ». À l'opposé du tutoiement de Wakabi.

---

## 2. Le modèle économique

Quatre paliers mensuels, en francs CFA :

| Palier | Prix | Contenu |
|---|---:|---|
| **Découverte** | 0 | 1 campagne · 20 téléchargements/mois · **watermark sur les badges** |
| **Impact** | 5 000 | 350 téléchargements/badge · sans watermark · **redirection après téléchargement** · stats |
| **Croissance** | 10 000 | 3 campagnes · 1 000 téléchargements · personnalisation complète de la page (thèmes, couleurs, boutons) · **copies des badges** (supprimées 1 semaine après la campagne) |
| **Mouvement** | 30 000 | Illimité · **accès API REST** · rappels WhatsApp gratuits · Web Push |

**Crédits WhatsApp**, vendus à part : **2,21 FCFA par message**, de 1 000 à 100 000+,
tarif dégressif. **Paiements :** Mobile Money (MTN, Moov), cartes, virements locaux,
Bitcoin.

Trois leviers de conversion méritent d'être notés, parce qu'ils sont bien conçus :

1. **Le watermark sur le palier gratuit.** Classique, efficace : la marque voyage sur
   chaque badge partagé, et retirer le watermark est la première raison de payer.
2. **La redirection après téléchargement**, vendue dès 5 000 FCFA. Une fois son badge
   récupéré, l'utilisateur est envoyé où l'organisateur veut. C'est le seul moment où
   l'attention est totale.
3. **Les copies des badges**, à 10 000 FCFA. L'organisateur récupère tous les visuels
   générés — donc les photos des participants.

---

## 3. Ce que fait réellement l'éditeur

La FAQ est explicite :

> *« Les utilisateurs peuvent uploader leur photo, l'ajuster (**zoom, position**)
> directement sur notre interface avant de télécharger leur profil personnalisé. »*

**Zoom et déplacement. Rien d'autre.** Pas de rotation, pas de filtres, pas de calques,
pas de stickers. Et le parcours est explicitement décrit comme *« en quelques clics »*.

> **Conséquence directe pour nous :** le concurrent qui domine la catégorie tient avec
> **deux gestes**. Notre `<FilterRail>` et notre `<CropToolbar>` complète sont du
> sur-équipement pour une v1 — et ils entrent en contradiction avec l'objectif des
> 30 secondes. Voir §5.

Nuance importante : contrairement à ce que le site affirme en page d'accueil
(« sans inscription »), la FAQ dit **« Connectez-vous, choisissez un modèle ou téléversez
le vôtre »**. Le compte est requis pour **créer une campagne** — pas pour créer son badge
sur une campagne existante. Les deux rôles ne sont pas soumis à la même friction, et c'est
le bon arbitrage.

---

## 4. Le reste de l'implémentation

| Élément | Constat |
|---|---|
| **Stack** | SPA **Vite** (`index-*.js`, `vendor-libs-*.js`, modules ES) — pas de rendu serveur |
| **PWA** | `manifest.webmanifest`, bouton « Installer Mougni » |
| **Icônes** | **Lucide** — `check`, `arrow-right`, `download`, `eye`, `megaphone`, `bell` |
| **Design** | Néo-brutalisme : jaune `#FFEA28`, vert `#00A651`, bordures noires 2 px, ombres dures `4px 4px 0 #000`, `font-black` |
| **Analytics** | **PostHog** (avec enregistrement de session, dead-clicks, sondages) + **Microsoft Clarity** + gtag |
| **Routes** | Slugs à plat à la racine : `/camp-scout`, `/tous-a-savalou-2026` · catalogue `/badges?q=` · `/templates`, `/jy-serai`, `/whatsapp`, `/webpush`, `/advertise`, `/designers` |
| **Compteurs** | Deux par campagne, publics : ⬇ **téléchargements** et 👁 **vues** (ex. 20 / 37, 460 / 778) |

Les campagnes visibles sont locales et concrètes : *Camp scout*, *Journées socio-culturelles
d'Abradine*, *Tous à Savalou 2026*, *Journée du jeune chercheur d'emploi*, *Chapelet
Marathon*, *Akpan-Party*. Associations, paroisses, événements jeunesse — au Bénin et au Togo.
C'est exactement le tissu que Wakabi référence déjà.

> **Un point à ne pas copier.** Le JSON-LD déclare un `aggregateRating` de **4,8 sur
> 150 avis** alors qu'aucun avis n'est visible sur la page. Un balisage de notation non
> adossé à des avis réels est contraire aux règles de Google et expose à une pénalité
> manuelle. À éviter.

---

## 5. Grille de décision

Verdicts : `ADOPTER` · `REMIXER` · `ÉCARTER` · `DIFFÉRER`.

| # | Fonctionnalité mougni | Valeur | Viral | Cohérence | Coût | Verdict |
|---|---|:--:|:--:|:--:|:--:|---|
| 1 | Badge sans compte pour le **participant** | ★★★ | ★★★ | ★★★ | S | **ADOPTER** |
| 2 | Compte requis pour **créer une campagne** | ★★★ | ★★☆ | ★★★ | M | **ADOPTER** — deux rôles, deux frictions |
| 3 | Éditeur limité à **zoom + position** | ★★★ | ★★☆ | ★★★ | S | **ADOPTER** — et donc *retirer* filtres et rotation de la v1 |
| 4 | Parcours « en quelques clics » | ★★★ | ★★☆ | ★★★ | M | **ADOPTER** comme budget temps mesuré |
| 5 | Partage WhatsApp direct | ★★★ | ★★★ | ★★★ | S | **ADOPTER** — Web Share API L2 |
| 6 | **Redirection après téléchargement** | ★★★ | ★★★ | ★★★ | S | **REMIXER** — chez mougni c'est payant ; chez nous c'est le **cœur de l'ancrage**, et gratuit : on renvoie vers la fiche Wakabi du lieu ou de l'événement |
| 7 | **Watermark** sur le palier gratuit | ★★☆ | ★★★ | ★★★ | S | **REMIXER** — l'outil étant gratuit, le logo Wakabi est systématique : pure visibilité, aucun arbitrage commercial |
| 8 | Deux compteurs publics (⬇ / 👁) | ★★☆ | ★★★ | ★★★ | S | **ADOPTER** — honnêtes, par campagne, et le rapport des deux dit le taux de conversion |
| 9 | Slugs à plat `/nom-campagne` | ★★☆ | ★★★ | ★★★ | S | **ADOPTER** — lisible, partageable |
| 10 | Publicité native dans les badges | ★★☆ | ★★☆ | ★★★ | M | **DIFFÉRER** — c'est notre « décor sponsorisé », adossé aux partenaires déjà en base |
| 11 | Rappels WhatsApp automatiques (J-1, H-2) | ★★★ | ★★☆ | ★★☆ | L | **DIFFÉRER** — produit à part entière, hors périmètre du générateur |
| 12 | Web Push | ★★☆ | ★★☆ | ★★☆ | M | **DIFFÉRER** |
| 13 | Abonnement 5–30 k FCFA aux organisateurs | ★☆☆ | ★☆☆ | ★☆☆ | — | **ÉCARTER** — voir §6, c'est précisément là qu'on les prend à revers |
| 14 | **Copies des badges** livrées à l'organisateur | ★☆☆ | ★☆☆ | ★☆☆ | M | **ÉCARTER** — suppose l'upload des photos côté serveur, ce qui viole C6 |
| 15 | `aggregateRating` sans avis réels | — | — | ★☆☆ | — | **ÉCARTER** — risque de pénalité Google |
| 16 | Néo-brutalisme jaune/noir | — | — | ★☆☆ | — | **ÉCARTER** — incompatible avec la charte bleue |

### Le point 14 mérite d'être explicité

mougni peut livrer les copies des badges parce que **les photos transitent par ses
serveurs**. Notre architecture 100 % client (C6) rend cette fonctionnalité *impossible* —
et c'est un choix, pas une limite subie : sur data payante, ne rien téléverser est plus
rapide et moins cher, et rien à conserver signifie rien à protéger. Si l'équipe commerciale
réclame un jour ces copies, c'est toute l'architecture qu'il faudra rouvrir. Autant le
savoir maintenant.

---

## 6. La conséquence stratégique : le Kori étant coupé, l'angle change

La v0.1 désignait la **récompense en Koris** comme l'atout maître face à mougni. Le Kori
étant désactivé, cet angle tombe pour la v1. Il faut donc en trouver un autre — et
l'analyse de mougni en offre un meilleur, plus immédiat.

**Les clients de mougni sont exactement les partenaires de Wakabi.** Associations,
organisateurs d'événements, restaurants, paroisses, marques locales — le tissu que Wakabi
référence déjà. mougni leur facture **5 000 à 30 000 FCFA par mois** pour leurs badges.

> **Le générateur n'est pas un produit à vendre. C'est un argument de vente pour l'offre
> partenaire.**
>
> Là où mougni facture 10 000 FCFA par mois à un organisateur pour ses visuels de campagne,
> Wakabi les **inclut dans la Carte Partenaire** — et y ajoute quelque chose que mougni ne
> peut pas fournir : **son audience**. Un organisateur qui passe par mougni doit amener sa
> propre communauté ; celui qui passe par Wakabi est exposé aux explorateurs de sa ville.

Trois conséquences opérationnelles :

1. **Le générateur devient un outil de l'équipe commerciale**, pas seulement un gadget
   grand public. Il se démontre en rendez-vous partenaire.
2. **Le watermark Wakabi devient évident** — l'outil est gratuit, donc le logo est
   systématique. La question A2 se referme d'elle-même.
3. **La redirection après téléchargement est la brique centrale**, pas une option : chaque
   badge téléchargé renvoie vers la fiche Wakabi du partenaire. C'est la boucle qui relie
   le générateur au catalogue.

Le Kori, lui, redevient ce qu'il aurait dû rester : **un jalon ultérieur**, à activer le
jour où le programme de fidélité sera rallumé.

---

## 7. Ce que je recommande de retirer de la v1

À la lumière de ce que fait réellement le concurrent qui domine la catégorie :

| Retiré | Motif |
|---|---|
| `<FilterRail>` et les filtres photo | mougni n'en a aucun. Ils allongent le parcours et contredisent le budget des 30 secondes. |
| Rotation et miroir dans `<CropToolbar>` | Idem. On garde zoom, déplacement, recentrer, réinitialiser. |
| `<RatioSwitcher>` multi-formats en v1 | Un modèle = un format. Le 9:16 devient un modèle distinct, pas une bascule. |
| Supabase | Déjà retiré au profit du WordPress existant. |

**Ajouté en échange**, parce que ce sont les vrais leviers :

| Ajouté | Motif |
|---|---|
| `<RedirectAfterDownload>` | La brique d'ancrage — renvoi vers la fiche Wakabi du partenaire. |
| `<CampaignStats>` — ⬇ et 👁 publics | Preuve sociale honnête, et mesure du taux de conversion. |
| Watermark Wakabi systématique dans `renderScene` | Visibilité de marque sur chaque partage. |
| Rôle « organisateur » dans le schéma | Compte requis pour créer une campagne, pas pour créer un badge. |
