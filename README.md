# Wakabi Studio — Générateur de décors

Outil web de génération de visuels personnalisés (cadres de profil, badges d'événement)
pour la plateforme **Wakabi Le Guide** — le guide des bons coins de Lomé, Cotonou et Abidjan.

> **Statut : J0–J1 livrés.** L'application tourne — galerie, éditeur, export haute
> définition, partage et redirection. Trois modèles de démonstration.

## Démarrer

```bash
npm install
npm run dev          # http://localhost:3000
npm run test         # 33 invariants du contrat de données
npm run frames       # régénère les cadres PNG depuis les SVG
npm run demo         # assemble public/demo/index.html (fichier unique autonome)
```

## Documents

| Fichier | Contenu |
|---|---|
| [`docs/00-SPEC-TECHNIQUE.md`](docs/00-SPEC-TECHNIQUE.md) | Positionnement, contraintes, stack, architecture de rendu, inventaire des composants, grille d'analyse mougni.com |
| [`docs/01-QUESTIONS.md`](docs/01-QUESTIONS.md) | Questions ouvertes — blocs A et B largement résolus par l'archive |
| [`docs/02-CHARTE-WAKABI.md`](docs/02-CHARTE-WAKABI.md) | Audit de l'existant Wakabi : couleurs, polices, ton, économie Kori, architecture WordPress |
| [`docs/03-ANALYSE-MOUGNI.md`](docs/03-ANALYSE-MOUGNI.md) | Analyse du concurrent : modèle économique, périmètre de l'éditeur, grille de décision sur 16 points |
| [`docs/04-MODERATION.md`](docs/04-MODERATION.md) | Création partagée équipe + partenaires : rôles, machine à états, capacités WordPress, pré-vol automatique |
| [`docs/05-IMPLEMENTATION.md`](docs/05-IMPLEMENTATION.md) | Journal d'implémentation : ce que le code a appris et qui corrige la spec |
| [`docs/06-BACKLOG.md`](docs/06-BACKLOG.md) | **Périmètre fonctionnel** — 81 fonctionnalités par acteur, état et jalon, plus les 10 écartées |
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
