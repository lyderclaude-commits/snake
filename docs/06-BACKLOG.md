# Périmètre fonctionnel

Inventaire complet, organisé **par acteur** — c'est ainsi que le produit se divise
réellement. Chaque ligne porte son état et son jalon.

**Légende :** ✅ fait · 🔜 prochain · ⬜ à faire · 🔒 gelé · ✖️ écarté (décision actée)

> **État au 25/08/2026** — après la campagne « rajouter tout ce qui manque »
>
> | | |
> |---|---|
> | ✅ **67** livrées et vérifiées | recette automatisée : **22 scénarios, 22 réussis** |
> | 🔜 **7** identifiées comme prochaines | courtes, à fort effet |
> | ⬜ **23** à construire | réparties de J4 à J5 |
> | 🔒 **0** gelée | les Koris sont rallumés — le QR à l'entrée les crédite |
> | ✖️ **9** écartées volontairement | §7 — pour ne pas les reprendre par réflexe |
>
> **Ce qui a changé :** le back-office ne passe plus par WordPress (§3), le QR Code et le
> scan à l'entrée existent (§4), et le pré-vol des 7 contrôles s'exécute côté serveur —
> un décor qui échoue ne rejoint jamais la file d'attente.

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

**C'est l'argument de vente.** mougni facture 5 000 à 30 000 FCFA/mois pour ceci.
Le parcours complet est en place : inscription, cadre, gabarit, pré-vol, soumission,
suivi. Aucune ligne de code n'est nécessaire pour publier une campagne.

| # | Fonctionnalité | État | Jalon |
|---|---|:--:|:--:|
| 2.1 | Compte partenaire (rôle `partner`, inscription libre) | ✅ | J3 |
| 2.2 | Téléverser son cadre (PNG/WebP à alpha, SVG refusé) | ✅ | J3 |
| 2.3 | Gabarit : 3 dispositions prêtes (bandeau, bas, story 9:16) | ✅ | J3 |
| 2.4 | Prévisualisation avec une photo de test | ✅ | J3 |
| 2.5 | Renseigner la redirection (fiche Wakabi imposée) | ✅ | J3 |
| 2.6 | Soumettre à la relecture — **après pré-vol** | ✅ | J3 |
| 2.7 | Lire le motif de refus ou de correction | ✅ | J3 |
| 2.8 | Re-soumettre après correction demandée | ✅ | J3 |
| 2.9 | **Modifier** un décor déjà créé | 🔜 | J4 |
| 2.10 | Retirer sa soumission | ⬜ | J4 |
| 2.11 | Tableau de bord : ses campagnes, vues, téléchargements | ✅ | J4 |
| 2.12 | Quotas de téléversement (20 Mo, 30 fichiers) | ✅ | J4 |
| 2.13 | Vérification de l'adresse e-mail avant soumission | ✅ | J4 |
| 2.14 | Notifications dans l'application (cloche + compteur) | ✅ | J4 |
| 2.15 | **Envoi** réel des e-mails (SMTP) | ⬜ | J4 |
| 2.16 | Export CSV de ses propres chiffres | ⬜ | J5 |

> **2.15 — la nuance qui compte.** Le circuit de vérification est complet (jeton, expiration
> 48 h, consommation à usage unique, page `/verifier/{token}`). Il ne manque que le
> transport : sans SMTP configuré, le lien s'affiche à l'écran au lieu de partir par
> courriel. Brancher un SMTP est un changement d'une fonction.

---

## 3. L'équipe Wakabi — publier et modérer

**Plus de WordPress.** Le back-office est intégré à l'application : mêmes comptes, mêmes
rôles, mêmes données. WordPress reste la source des *articles* du guide, rien d'autre.

### Catalogue

| # | Fonctionnalité | État | Jalon |
|---|---|:--:|:--:|
| 3.1 | Table `decors` + gabarit JSON validé par le contrat | ✅ | J2 |
| 3.2 | Lecture publique du catalogue (`/decors`, `/decor/{slug}`) | ✅ | J2 |
| 3.3 | Rôles `participant` · `partner` · `admin` | ✅ | J2 |
| 3.4 | 7 statuts (`draft` → `published`, `changes_requested`, `rejected`, `expired`) | ✅ | J2 |
| 3.5 | Publication directe par l'équipe (sans relecture) | ✅ | J2 |
| 3.6 | Expiration automatique sur `expiresAt` | 🔜 | J4 |
| 3.7 | Compteurs de vues et de téléchargements | ✅ | J3 |

### Modération

| # | Fonctionnalité | État | Jalon |
|---|---|:--:|:--:|
| 3.8 | File d'attente des soumissions | ✅ | J3 |
| 3.9 | Approuver · Demander des corrections · Refuser | ✅ | J3 |
| 3.10 | Motif obligatoire au refus | ✅ | J3 |
| 3.11 | Transitions autorisées par acteur | ✅ | J3 |
| 3.12 | Garde-fou : redirection partenaire vers un domaine Wakabi | ✅ | J3 |
| 3.13 | **Pré-vol — 7 contrôles automatiques, côté serveur** | ✅ | J3 |
| 3.14 | Rapport de pré-vol affiché au relecteur | ✅ | J3 |
| 3.15 | Sécurité des téléversements (allowlist, nom de fichier contraint) | ✅ | J3 |
| 3.16 | Limitation de débit sur la connexion (8 essais / 15 min) | ✅ | J3 |
| 3.17 | Gestion des comptes : rôle, suspension | ✅ | J3 |
| 3.18 | Journal d'audit des décisions de modération | ⬜ | J4 |
| 3.19 | Engagement de relecture sous 24 h ouvrées | ✅ | *opérationnel* |

**Les 7 contrôles du pré-vol** (`src/server/preflight.ts`), tous exécutés côté serveur —
donc impossibles à contourner depuis le navigateur :

| Contrôle | Ce qu'il refuse |
|---|---|
| `schema` | Un gabarit qui viole un des 33 invariants du contrat |
| `asset-format` | Un cadre qui n'est ni PNG ni WebP, ou sans couche alpha |
| `asset-weight` | Un cadre au-delà du poids soutenable en 3G |
| `photo-visible` | Un cadre **opaque à plus de 85 %** — la photo ne se verrait pas |
| `watermark-clear` | Un texte posé sous le filigrane ou sous le QR Code |
| `text-fits` | Un texte qui déborde de sa zone à l'échelle d'export |
| `contrast` | Un texte sombre, illisible sur une photo sombre |

### Pilotage

| # | Fonctionnalité | État | Jalon |
|---|---|:--:|:--:|
| 3.20 | Tableau de bord : décors, créations, comptes | ✅ | J4 |
| 3.21 | Courbe des téléchargements sur 14 jours | ✅ | J4 |
| 3.22 | Statistiques par campagne (⬇, 👁, taux de conversion) | ✅ | J4 |
| 3.23 | Décors sponsorisés — inventaire vendable | ⬜ | J5 |
| 3.24 | Export CSV des chiffres de campagne | ⬜ | J5 |

---

## 4. L'organisateur — le QR, l'entrée, les Koris

**C'est ce qu'un générateur d'images ne peut pas copier.** Le badge ne s'arrête pas au
partage : il porte un code unique qui, scanné à l'entrée, prouve une présence *réelle*.

| # | Fonctionnalité | État | Jalon |
|---|---|:--:|:--:|
| 4.1 | QR Code incrusté dans le décor (position et taille réglables) | ✅ | J3 |
| 4.2 | QR visible **dès l'aperçu**, pas seulement à l'export | ✅ | J3 |
| 4.3 | Un jeton unique par badge émis | ✅ | J3 |
| 4.4 | Écran de scan à l'entrée (`/scan`) | ✅ | J3 |
| 4.5 | Scan idempotent : un badge ne vaut qu'une entrée | ✅ | J3 |
| 4.6 | Code inconnu refusé explicitement | ✅ | J3 |
| 4.7 | **Koris crédités à l'entrée**, pas au téléchargement | ✅ | J3 |
| 4.8 | Lecture caméra du QR (aujourd'hui : saisie du code) | 🔜 | J4 |
| 4.9 | Liste des présents en direct pour l'organisateur | ⬜ | J4 |
| 4.10 | Export de la liste des présents | ⬜ | J5 |

> **4.7 — pourquoi c'est le bon endroit.** Créditer au téléchargement récompense un clic.
> Créditer au scan récompense une venue. C'est la seule métrique qu'un organisateur paie
> volontiers, et la seule que la concurrence ne mesure pas.

---

## 5. Socle technique

| # | Fonctionnalité | État | Jalon |
|---|---|:--:|:--:|
| 5.1 | `renderScene` : fonction pure, deux échelles | ✅ | J0 |
| 5.2 | Contrat `DecorTemplate` — 33 invariants vérifiés | ✅ | J0 |
| 5.3 | Machine à états et transitions par acteur | ✅ | J0 |
| 5.4 | Tokens de charte dans un fichier unique | ✅ | J0 |
| 5.5 | Polices auto-hébergées (pas de CDN externe) | ✅ | J1 |
| 5.6 | **Export dans un Web Worker** (`OffscreenCanvas`) | 🔜 | J2 |
| 5.7 | Tests de régression visuelle (golden image) | ⬜ | J2 |
| 5.8 | OG images dynamiques par campagne | ⬜ | J3 |
| 5.9 | Cache IndexedDB des cadres | ⬜ | J3 |
| 5.10 | PWA installable, mode hors ligne (Serwist) | ⬜ | J3 |
| 5.11 | Analytics sans cookie (Plausible ou Umami) | ⬜ | J3 |
| 5.12 | Internationalisation FR / EN | ⬜ | J4 |
| 5.13 | Accessibilité : navigation clavier, contrastes, `prefers-reduced-motion` | 🔜 | J2 |

---

## 6. Différé

| # | Fonctionnalité | Pourquoi plus tard |
|---|---|---|
| 6.1 | Mur public des créations | Fort levier social, mais exige une chaîne de modération avant ouverture |
| 6.2 | Solde de Koris consultable par le participant | L'attribution existe (§4.7) ; l'écran de solde attend le programme de fidélité |
| 6.3 | Publicité native dans les décors | Revenu natif, adossé aux partenaires en base — après J4 |
| 6.4 | Parrainage / lien de campagne personnel | Après les compteurs |

---

## 7. Écarté — décisions actées

Ces lignes existent pour qu'on ne les reprenne pas par réflexe. Chacune a une raison.

> **Une ligne a quitté ce tableau.** J'y avais écrit « abonnement payant aux organisateurs :
> écarté ». Le prototype Wakabi Boost tranche autrement — 4 formules, 0 à 30 000 FCFA — et il
> a raison : la différenciation ne se joue pas sur la gratuité mais sur l'audience du guide et
> sur le QR. Voir `07-BACKEND.md` §7.

| Fonctionnalité | Pourquoi non |
|---|---|
| **Filtres photo multiples** | mougni n'en a aucun et domine la catégorie. Ils allongent le parcours et contredisent le budget des 30 s. Une seule teinte signature suffit. |
| **Rotation de la photo** | Idem — chaque geste supplémentaire se paie. |
| **Bascule multi-formats dans l'éditeur** | Un modèle = un format. Le 9:16 est un décor distinct, pas une option. |
| **Copies des badges livrées à l'organisateur** | Suppose que les photos transitent par nos serveurs. Contredit frontalement C6. |
| **`aggregateRating` sans avis réels** | Balisage trompeur, expose à une pénalité manuelle Google. |
| **Rappels WhatsApp automatiques** | Produit à part entière (crédits, API Business), hors périmètre du générateur. |
| **Web Push** | Idem. |
| **Éditeur généraliste type Canva** | La valeur est dans la contrainte : un décor cadré, réussi, en 30 secondes. |
| **Application native** | Le partage viral passe par un lien ouvert dans WhatsApp. Imposer un téléchargement tue la boucle. |

---

## 8. Ce que je ferais ensuite

Dans cet ordre, et voici pourquoi :

1. **Les vrais cadres** (1.x). Rien ne se teste sérieusement sur des placeholders — ni la
   lisibilité, ni l'envie de partager. C'est le seul point qui bloque une mise en ligne.
2. **La lecture caméra du QR** (4.8). Aujourd'hui l'agent d'entrée saisit le code à la main :
   ça marche, mais ça ne tient pas une file d'attente. `BarcodeDetector` couvre Android récent,
   avec repli sur une bibliothèque.
3. **Caméra directe et photos d'exemple** (1.11, 1.12). Deux ajouts courts qui ferment le
   principal point d'abandon : arriver sans photo prête.
4. **L'export en Web Worker** (5.6). Le seul point qui peut faire tomber un Android d'entrée
   de gamme. Ne touche qu'un fichier.
5. **Le SMTP** (2.15). Le circuit de vérification et les notifications existent ; sans
   transport, le partenaire doit revenir de lui-même. Une fonction à brancher.
6. **Modifier un décor** (2.9). Le partenaire peut re-soumettre mais pas corriger — c'est le
   manque le plus visible du parcours partenaire.
7. **Sauvegardes et migration Postgres** (`07-BACKEND.md` §5). À faire *avant* d'avoir des
   données qu'on regretterait de perdre, pas après.
