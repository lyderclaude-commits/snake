# Wakabi Studio — Générateur de décors

Outil web de génération de visuels personnalisés (cadres de profil, badges d'événement)
pour la plateforme **Wakabi Le Guide** — le guide des bons coins de Lomé, Cotonou et Abidjan.

> **Statut : application complète, prête à déployer.** Vitrine Wakabi Boost, catalogue
> public, Studio, comptes participants et partenaires, back-end d'administration, QR et
> contrôle d'entrée. Base autonome — **plus de WordPress**.
>
> Recette : **25 scénarios, 25 réussis, aucune erreur console.**

## Démarrer

```bash
npm install
npm run seed         # base + comptes de démonstration
npm run dev          # http://localhost:3000
```

Puis, dans un second terminal, l'application lancée :

```bash
npm run e2e:full     # 25 scénarios de recette, dans un vrai navigateur
npm test             # 33 invariants du contrat de données
```

Les autres commandes : `npm run build` (production), `npm run frames` (régénère les
cadres PNG depuis les SVG), `npm run demo` (assemble une démo en fichier unique),
`npm run purge` (supprime les cadres qu'aucun décor ne référence).

## Mettre en ligne

**Lisez [`docs/09-DEPLOIEMENT.md`](docs/09-DEPLOIEMENT.md) avant tout.** Ce n'est pas un
site statique : il faut Node.js en processus permanent et un disque qui persiste. Un
hébergement mutualisé « HTML + PHP » ne suffira pas, et Vercel ne convient pas en l'état.

Un seul réglage compte vraiment :

```
WAKABI_BASE_URL=https://boost.wakabileguide.com
```

Il est incrusté dans le **QR de chaque badge**, donc dans un fichier que l'invité garde et
partage. Se tromper produit des badges faux pour toujours — l'application refuse d'en
émettre un si la variable manque en production. Voir [`.env.example`](.env.example).

## Documents

| Fichier | Contenu |
|---|---|
| [`docs/00-SPEC-TECHNIQUE.md`](docs/00-SPEC-TECHNIQUE.md) | Positionnement, contraintes, stack, architecture de rendu, inventaire des composants, grille d'analyse mougni.com |
| [`docs/01-QUESTIONS.md`](docs/01-QUESTIONS.md) | Questions ouvertes — blocs A et B largement résolus par l'archive |
| [`docs/02-CHARTE-WAKABI.md`](docs/02-CHARTE-WAKABI.md) | Audit de l'existant Wakabi : couleurs, polices, ton, économie Kori, architecture WordPress |
| [`docs/03-ANALYSE-MOUGNI.md`](docs/03-ANALYSE-MOUGNI.md) | Analyse du concurrent : modèle économique, périmètre de l'éditeur, grille de décision sur 16 points |
| [`docs/04-MODERATION.md`](docs/04-MODERATION.md) | Création partagée équipe + partenaires : rôles, machine à états, capacités WordPress, pré-vol automatique |
| [`docs/05-IMPLEMENTATION.md`](docs/05-IMPLEMENTATION.md) | Journal d'implémentation : ce que le code a appris et qui corrige la spec |
| [`docs/06-BACKLOG.md`](docs/06-BACKLOG.md) | **Périmètre fonctionnel** — 98 lignes par acteur, état et jalon, plus les 9 écartées |
| [`docs/07-BACKEND.md`](docs/07-BACKEND.md) | **Back-end autonome** : schéma, rôles, écrans, les huit défauts trouvés, et le chemin vers Postgres |
| [`docs/08-RECETTE.md`](docs/08-RECETTE.md) | **Recette** : les 25 scénarios, comment les rejouer, et ce qu'ils ne couvrent pas |
| [`docs/09-DEPLOIEMENT.md`](docs/09-DEPLOIEMENT.md) | **Mise en ligne** sur `boost.wakabileguide.com` — DNS, Node, systemd, Nginx, sauvegardes |
| [`src/core/template.schema.ts`](src/core/template.schema.ts) | Schéma Zod du modèle de décor — contrat back-office ↔ API ↔ éditeur |
| [`src/core/renderScene.ts`](src/core/renderScene.ts) | **Le cœur** : la fonction de rendu pure, une seule pour l'aperçu et l'export |
| [`src/core/template.schema.test.ts`](src/core/template.schema.test.ts) | Tests des invariants du contrat (33/33 au vert) |
| [`src/app/globals.css`](src/app/globals.css) | Tokens de marque — **valeurs réelles**, seul fichier de l'app portant des couleurs |

## État des sources

Tout repose désormais sur des sources de première main :

- **Wakabi** — archive du site fournie le 25/08/2026. Charte (`#2563EB`, bleue), ton
  éditorial, économie Kori et architecture WordPress headless en sont extraits.
- **mougni.com** — page d'accueil complète fournie le 25/08/2026. Modèle économique,
  périmètre de l'éditeur et implémentation en sont extraits.

**Contexte produit :**

- Le programme **Kori est désactivé**. La différenciation de la v1 repose sur l'offre
  partenaire, pas sur la fidélité — voir la spécification §1.3.
- Le WordPress est le **back-end éditorial** (articles), pas la base du produit : ni
  événements ni établissements. L'ancrage se fait par **URL**, pas par jointure.
- Les décors sont créés par **l'équipe Wakabi et les partenaires**, avec modération
  Wakabi — voir `docs/04-MODERATION.md`.
- **Aucun logo vectoriel n'existe.** Le filigrane prévoit une variante `text` pour ne pas
  bloquer le développement.
- **Signature officielle : « LE GUIDE DES BONS PLANS »** (constante `WAKABI_TAGLINE`).
  Sept occurrences de « bons coins » restent à corriger sur le site.

## Comptes de démonstration

| Rôle | E-mail | Mot de passe |
|---|---|---|
| Administration | `admin@wakabileguide.com` | `wakabi2026` |
| Partenaire | `partenaire@chezlena.tg` | `wakabi2026` |
| Participant | `kossi@exemple.tg` | `wakabi2026` |

> Ces mots de passe sont publics — ils sont écrits ici. **Changez celui de
> l'administration à la première connexion en ligne**, et supprimez les deux autres
> comptes depuis `/admin/comptes` une fois le vôtre créé.
