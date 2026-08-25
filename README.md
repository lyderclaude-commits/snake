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
| [`docs/02-CHARTE-WAKABI.md`](docs/02-CHARTE-WAKABI.md) | Audit de l'existant : couleurs, polices, ton, économie Kori, architecture WordPress |
| [`docs/contracts/template.schema.ts`](docs/contracts/template.schema.ts) | Schéma Zod du modèle de décor — contrat back-office ↔ API ↔ éditeur |
| [`docs/contracts/template.schema.test.ts`](docs/contracts/template.schema.test.ts) | Tests des invariants du contrat (8/8 au vert, zod 3.25.76) |
| [`docs/contracts/wakabi-tokens.css`](docs/contracts/wakabi-tokens.css) | Tokens de marque — **valeurs réelles** extraites du site, seul fichier portant des couleurs |

## État des sources

La charte graphique, le ton éditorial, l'économie Kori et l'architecture technique ont été
**extraits de l'archive du site** fournie le 25/08/2026 — ce ne sont plus des hypothèses.
La marque est bleue (`#2563EB`).

Seules les fonctionnalités de **mougni.com** restent non vérifiées : le domaine est
inaccessible depuis l'environnement d'analyse, et l'analyse repose sur des résultats de
recherche.
