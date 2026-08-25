# Wakabi Studio — Générateur de décors

Outil web de génération de visuels personnalisés (cadres de profil, badges d'événement)
pour la plateforme **Wakabi Le Guide** — le guide des bons coins de Lomé, Cotonou et Abidjan.

> **Statut : conception.** Aucun code applicatif n'est encore écrit. Ce dépôt contient pour
> l'instant la spécification technique et les contrats de données.

## Documents

| Fichier | Contenu |
|---|---|
| [`docs/00-SPEC-TECHNIQUE.md`](docs/00-SPEC-TECHNIQUE.md) | Positionnement, contraintes, stack, architecture de rendu, inventaire des composants, grille d'analyse mougni.com |
| [`docs/01-QUESTIONS.md`](docs/01-QUESTIONS.md) | Questions ouvertes à trancher avant le développement |
| [`docs/contracts/template.schema.ts`](docs/contracts/template.schema.ts) | Schéma Zod du modèle de décor — contrat back-office ↔ API ↔ éditeur |
| [`docs/contracts/template.schema.test.ts`](docs/contracts/template.schema.test.ts) | Tests des invariants du contrat (8/8 au vert, zod 3.25.76) |
| [`docs/contracts/wakabi-tokens.css`](docs/contracts/wakabi-tokens.css) | Tokens de marque — **valeurs provisoires**, seul fichier portant des couleurs |

## Avertissement sur la charte graphique

Les sites `wakabileguide.com` et `mougni.com` étaient inaccessibles depuis l'environnement
d'analyse (blocage du proxy réseau). Les couleurs et polices présentes dans ce dépôt sont donc
des **placeholders**, isolés dans un fichier unique pour être remplacés d'un seul coup.
