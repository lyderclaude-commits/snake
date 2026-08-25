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
| [`docs/04-MODERATION.md`](docs/04-MODERATION.md) | Création partagée équipe + partenaires : rôles, machine à états, capacités WordPress, pré-vol automatique |
| [`docs/contracts/template.schema.ts`](docs/contracts/template.schema.ts) | Schéma Zod du modèle de décor — contrat back-office ↔ API ↔ éditeur |
| [`docs/contracts/template.schema.test.ts`](docs/contracts/template.schema.test.ts) | Tests des invariants du contrat (33/33 au vert, zod 3.25.76) |
| [`docs/contracts/wakabi-tokens.css`](docs/contracts/wakabi-tokens.css) | Tokens de marque — **valeurs réelles** extraites du site, seul fichier portant des couleurs |

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
