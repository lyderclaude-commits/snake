# Wakabi Studio — Générateur de décors

Outil web de génération de visuels personnalisés (cadres de profil, badges d'événement)
pour la plateforme **Wakabi Le Guide** — le guide des bons coins de Lomé, Cotonou et Abidjan.

> **Statut : conception.** Aucun code applicatif n'est encore écrit. Ce dépôt contient pour
> l'instant la spécification technique et les contrats de données.

## Documents

| Fichier | Contenu |
|---|---|
| [`docs/00-SPEC-TECHNIQUE.md`](docs/00-SPEC-TECHNIQUE.md) | Positionnement, contraintes, stack, architecture de rendu, inventaire des composants, grille d'analyse mougni.com |
| [`docs/01-QUESTIONS.md`](docs/01-QUESTIONS.md) | Questions ouvertes — blocs A et B largement résolus par l'archive |
| [`docs/02-CHARTE-WAKABI.md`](docs/02-CHARTE-WAKABI.md) | Audit de l'existant Wakabi : couleurs, polices, ton, économie Kori, architecture WordPress |
| [`docs/03-ANALYSE-MOUGNI.md`](docs/03-ANALYSE-MOUGNI.md) | Analyse du concurrent : modèle économique, périmètre de l'éditeur, grille de décision sur 16 points |
| [`docs/contracts/template.schema.ts`](docs/contracts/template.schema.ts) | Schéma Zod du modèle de décor — contrat back-office ↔ API ↔ éditeur |
| [`docs/contracts/template.schema.test.ts`](docs/contracts/template.schema.test.ts) | Tests des invariants du contrat (11/11 au vert, zod 3.25.76) |
| [`docs/contracts/wakabi-tokens.css`](docs/contracts/wakabi-tokens.css) | Tokens de marque — **valeurs réelles** extraites du site, seul fichier portant des couleurs |

## État des sources

Tout repose désormais sur des sources de première main :

- **Wakabi** — archive du site fournie le 25/08/2026. Charte (`#2563EB`, bleue), ton
  éditorial, économie Kori et architecture WordPress headless en sont extraits.
- **mougni.com** — page d'accueil complète fournie le 25/08/2026. Modèle économique,
  périmètre de l'éditeur et implémentation en sont extraits.

**Contexte produit :** le programme **Kori est actuellement désactivé** côté Wakabi. La
différenciation de la v1 ne repose donc pas sur lui, mais sur l'offre partenaire — voir
la spécification §1.3.
