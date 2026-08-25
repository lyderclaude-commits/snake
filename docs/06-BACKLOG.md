# Périmètre fonctionnel

Inventaire complet, organisé **par acteur** — c'est ainsi que le produit se divise
réellement. Chaque ligne porte son état et son jalon.

**Légende :** ✅ fait · 🔜 prochain · ⬜ à faire · 🔒 gelé · ✖️ écarté (décision actée)

> **État au 25/08/2026**
>
> | | |
> |---|---|
> | ✅ **28** livrées et vérifiées | l'application tourne de bout en bout |
> | ✅ **3** garanties par le contrat de données | la règle existe, l'écran qui l'expose non |
> | 🔜 **4** identifiées comme prochaines | courtes, à fort effet |
> | ⬜ **42** à construire | réparties de J2 à J4 |
> | 🔒 **1** gelée | les Koris, en attente du programme de fidélité |
> | ✖️ **10** écartées volontairement | §6 — pour ne pas les reprendre par réflexe |

---

## 1. Le visiteur — créer et partager un visuel

**C'est le cœur.** Contrainte permanente : le parcours complet doit tenir en
**30 secondes**, sans compte. Chaque ajout ici se paie sur ce budget.

### Découverte

| # | Fonctionnalité | État | Jalon |
|---|---|:--:|:--:|
| 1.1 | Galerie des décors publiés | ✅ | J0 |
| 1.2 | Page décor à slug plat `/decor/{slug}` | ✅ | J0 |
| 1.3 | Filtres : ville, rubrique, événement | ⬜ | J2 |
| 1.4 | Recherche dans le catalogue | ⬜ | J2 |
| 1.5 | Badges « nouveau » / « expire bientôt » | ⬜ | J2 |
| 1.6 | Compteurs publics ⬇ téléchargements · 👁 vues | ⬜ | J3 |

### Import de la photo

| # | Fonctionnalité | État | Jalon |
|---|---|:--:|:--:|
| 1.7 | Import depuis la galerie du téléphone | ✅ | J1 |
| 1.8 | Rotation EXIF appliquée automatiquement | ✅ | J1 |
| 1.9 | Réduction mémoire à 2560 px (anti-plantage) | ✅ | J1 |
| 1.10 | Traitement 100 % local, aucun téléversement | ✅ | J1 |
| 1.11 | **Prise de photo directe** (caméra) | 🔜 | J1+ |
| 1.12 | **Photos d'exemple Wakabi** — supprime un abandon majeur | 🔜 | J1+ |
| 1.13 | Glisser-déposer (desktop) | ⬜ | J2 |

### Recadrage

| # | Fonctionnalité | État | Jalon |
|---|---|:--:|:--:|
| 1.14 | Zoom : curseur, molette, pincement | ✅ | J1 |
| 1.15 | Déplacement au doigt | ✅ | J1 |
| 1.16 | Garde-fou anti-vide : la photo couvre toujours | ✅ | J1 |
| 1.17 | Recentrer / réinitialiser | ✅ | J1 |
| 1.18 | Miroir horizontal | ✅ | J1 |
| 1.19 | Teinte signature Wakabi (intensité réglable) | ✅ | J1 |
| 1.20 | Double-tap pour recadrer | ⬜ | J2 |

### Texte

| # | Fonctionnalité | État | Jalon |
|---|---|:--:|:--:|
| 1.21 | Champs éditables définis par le modèle | ✅ | J1 |
| 1.22 | Compteur de caractères, longueur plafonnée | ✅ | J1 |
| 1.23 | Auto-réduction du texte qui déborde | ✅ | J1 |
| 1.24 | Ombre portée pour lisibilité sur photo claire ou sombre | ✅ | J1 |

### Export & partage

| # | Fonctionnalité | État | Jalon |
|---|---|:--:|:--:|
| 1.25 | Aperçu temps réel, identique à l'export | ✅ | J0 |
| 1.26 | Export haute définition 2048 px | ✅ | J1 |
| 1.27 | Partage natif WhatsApp (Web Share API L2) | ✅ | J1 |
| 1.28 | Téléchargement direct (repli desktop) | ✅ | J1 |
| 1.29 | Repli webview « appui long pour enregistrer » | ✅ | J1 |
| 1.30 | Filigrane Wakabi systématique | ✅ | J1 |
| 1.31 | **Redirection après téléchargement** — la brique d'ancrage | ✅ | J1 |
| 1.32 | Copier le lien de campagne (+ UTM) | ⬜ | J3 |
| 1.33 | Choix de qualité : HD · statut · web léger | ⬜ | J2 |
| 1.34 | Aperçu plein écran avant validation | ⬜ | J2 |

---

## 2. Le partenaire — créer sa campagne

**C'est l'argument de vente.** mougni facture 5 000 à 30 000 FCFA/mois pour ceci ;
Wakabi l'inclut dans la Carte Partenaire. Rien n'est encore construit.

| # | Fonctionnalité | État | Jalon |
|---|---|:--:|:--:|
| 2.1 | Compte partenaire (rôle WordPress `wakabi_partner`) | ⬜ | J3 |
| 2.2 | Téléverser son cadre (PNG/WebP à alpha) | ⬜ | J3 |
| 2.3 | Éditeur de gabarit : placer la zone photo et les textes | ⬜ | J3 |
| 2.4 | Prévisualisation avec une photo de test | ⬜ | J3 |
| 2.5 | Renseigner la redirection (fiche Wakabi imposée) | ⬜ | J3 |
| 2.6 | Soumettre à la relecture | ⬜ | J3 |
| 2.7 | Lire le motif de refus ou de correction | ⬜ | J3 |
| 2.8 | Corriger et re-soumettre | ⬜ | J3 |
| 2.9 | Retirer sa soumission | ⬜ | J3 |
| 2.10 | Tableau de bord : ses campagnes et leurs chiffres | ⬜ | J4 |
| 2.11 | Quotas de téléversement (fichiers, poids) | ⬜ | J4 |
| 2.12 | Notifications e-mail à chaque étape | ⬜ | J4 |

---

## 3. L'équipe Wakabi — publier et modérer

Tout vit dans le **WordPress existant** : c'est lui qui fournit gratuitement le circuit
Contributeur → en attente → publication.

### Catalogue

| # | Fonctionnalité | État | Jalon |
|---|---|:--:|:--:|
| 3.1 | Custom post type `decor_template` | ⬜ | J2 |
| 3.2 | Endpoints `wakabi/v1/decors` (lecture publique) | ⬜ | J2 |
| 3.3 | Rôles `wakabi_partner` et `wakabi_editor` | ⬜ | J2 |
| 3.4 | Statuts personnalisés (`changes_requested`, `rejected`, `expired`) | ⬜ | J2 |
| 3.5 | Publication directe par l'équipe | ⬜ | J2 |
| 3.6 | Expiration automatique (tâche WP-Cron sur `expiresAt`) | ⬜ | J3 |
| 3.7 | Endpoint compteur `POST /decors/{id}/count` | ⬜ | J3 |

### Modération

| # | Fonctionnalité | État | Jalon |
|---|---|:--:|:--:|
| 3.8 | File d'attente des soumissions | ⬜ | J3 |
| 3.9 | Métabox : Approuver · Demander des corrections · Refuser | ⬜ | J3 |
| 3.10 | Motif obligatoire au refus | ✅ *(contrat)* | J3 |
| 3.11 | Transitions autorisées par acteur | ✅ *(contrat)* | J3 |
| 3.12 | Garde-fou : redirection partenaire vers un domaine Wakabi | ✅ *(contrat)* | J3 |
| 3.13 | **Pré-vol — 7 contrôles automatiques** | ⬜ | J3 |
| 3.14 | Sécurité des téléversements (SVG refusé, allowlist réelle) | ⬜ | J3 |
| 3.15 | Engagement de relecture sous 24 h ouvrées | ⬜ | *opérationnel* |

Le pré-vol (3.13) détaille : validation du schéma · **zone photo réellement visible** ·
textes qui tiennent · filigrane non recouvert · format et alpha du cadre · poids du
fichier · contraste sur photo claire et sombre.

### Pilotage

| # | Fonctionnalité | État | Jalon |
|---|---|:--:|:--:|
| 3.16 | Statistiques par campagne (⬇, 👁, taux de conversion) | ⬜ | J4 |
| 3.17 | Décors sponsorisés — inventaire vendable | ⬜ | J4 |
| 3.18 | Export CSV des chiffres de campagne | ⬜ | J4 |

---

## 4. Socle technique

| # | Fonctionnalité | État | Jalon |
|---|---|:--:|:--:|
| 4.1 | `renderScene` : fonction pure, deux échelles | ✅ | J0 |
| 4.2 | Contrat `DecorTemplate` — 33 invariants vérifiés | ✅ | J0 |
| 4.3 | Machine à états et transitions par acteur | ✅ | J0 |
| 4.4 | Tokens de charte dans un fichier unique | ✅ | J0 |
| 4.5 | Polices auto-hébergées (pas de CDN externe) | ✅ | J1 |
| 4.6 | **Export dans un Web Worker** (`OffscreenCanvas`) | 🔜 | J2 |
| 4.7 | Tests de régression visuelle (golden image) | ⬜ | J2 |
| 4.8 | OG images dynamiques par campagne | ⬜ | J3 |
| 4.9 | Cache IndexedDB des cadres | ⬜ | J3 |
| 4.10 | PWA installable, mode hors ligne (Serwist) | ⬜ | J3 |
| 4.11 | Analytics sans cookie (Plausible ou Umami) | ⬜ | J3 |
| 4.12 | Internationalisation FR / EN | ⬜ | J4 |
| 4.13 | Accessibilité : navigation clavier, contrastes, `prefers-reduced-motion` | 🔜 | J2 |

---

## 5. Différé

| # | Fonctionnalité | Pourquoi plus tard |
|---|---|---|
| 5.1 | Mur public des créations | Fort levier social, mais exige une chaîne de modération avant ouverture |
| 5.2 | **Récompense en Koris** 🔒 | Le programme de fidélité est désactivé. À rallumer avec lui (J5) |
| 5.3 | Publicité native dans les décors | Revenu natif, adossé aux partenaires en base — après J4 |
| 5.4 | Parrainage / lien de campagne personnel | Après les compteurs |

---

## 6. Écarté — décisions actées

Ces lignes existent pour qu'on ne les reprenne pas par réflexe. Chacune a une raison.

| Fonctionnalité | Pourquoi non |
|---|---|
| **Filtres photo multiples** | mougni n'en a aucun et domine la catégorie. Ils allongent le parcours et contredisent le budget des 30 s. Une seule teinte signature suffit. |
| **Rotation de la photo** | Idem — chaque geste supplémentaire se paie. |
| **Bascule multi-formats dans l'éditeur** | Un modèle = un format. Le 9:16 est un décor distinct, pas une option. |
| **Copies des badges livrées à l'organisateur** | Suppose que les photos transitent par nos serveurs. Contredit frontalement C6. |
| **Abonnement payant aux organisateurs** | C'est exactement là qu'on prend mougni à revers : chez nous c'est inclus. |
| **`aggregateRating` sans avis réels** | Balisage trompeur, expose à une pénalité manuelle Google. |
| **Rappels WhatsApp automatiques** | Produit à part entière (crédits, API Business), hors périmètre du générateur. |
| **Web Push** | Idem. |
| **Éditeur généraliste type Canva** | La valeur est dans la contrainte : un décor cadré, réussi, en 30 secondes. |
| **Application native** | Le partage viral passe par un lien ouvert dans WhatsApp. Imposer un téléchargement tue la boucle. |

---

## 7. Ce que je ferais ensuite

Dans cet ordre, et voici pourquoi :

1. **Les vrais cadres** (1.x). Rien ne se teste sérieusement sur des placeholders — ni la
   lisibilité, ni l'envie de partager.
2. **Caméra directe et photos d'exemple** (1.11, 1.12). Deux ajouts courts qui ferment le
   principal point d'abandon : arriver sans photo prête.
3. **L'export en Web Worker** (4.6). Le seul point qui peut faire tomber un Android
   d'entrée de gamme. Ne touche qu'un fichier.
4. **Le catalogue WordPress** (3.1 → 3.5). À partir de là, l'équipe publie seule.
5. **Pré-vol et modération** (3.8 → 3.14). Ouvre aux partenaires — et c'est là que l'outil
   devient un argument commercial.

Les compteurs publics (1.6) sont peu coûteux et donnent la preuve sociale : bons à glisser
dès que le catalogue existe.
