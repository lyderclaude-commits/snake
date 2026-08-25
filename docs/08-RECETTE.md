# Recette — vérifier le produit soi-même

Ce document existe pour une raison : **vous n'avez pas à me croire sur parole.** Chaque
affirmation des documents 00 à 07 correspond ici à un scénario que vous pouvez rejouer.

---

## 1. Démarrer

```bash
npm install
npm run seed          # comptes et décors de démonstration
npm run dev           # http://localhost:3000
```

> `npm run seed` porte `--conditions=react-server`, et ce n'est pas décoratif : `server-only`
> lève une exception dans un Node ordinaire. Lancé sans ce drapeau, le seed échoue à l'import.

### Les comptes créés par le seed

| Rôle | Adresse | Mot de passe | Ce qu'il permet de voir |
|---|---|---|---|
| `admin` | `admin@wakabileguide.com` | `wakabi2026` | Tableau de bord, modération, comptes, scan |
| `partner` | `partenaire@chezlena.tg` | `wakabi2026` | Ses campagnes, création et soumission d'un décor |
| `user` | `kossi@exemple.tg` | `wakabi2026` | Ses créations, son solde de Koris |

---

## 2. La recette automatisée

Application lancée, dans un second terminal :

```bash
npm run e2e:full                       # suppose http://127.0.0.1:3000
BASE_URL=http://127.0.0.1:3210 npm run e2e:full   # autre port
```

Le script pilote un vrai Chromium. Il n'inspecte pas du HTML : il clique, téléverse, scanne,
et **échoue si la console du navigateur émet la moindre erreur**.

### Les 22 scénarios

| # | Scénario | Ce qu'il prouve |
|:--:|---|---|
| 1 | Vitrine chargée | Les 9 sections de la page d'accueil s'affichent |
| 2 | Offre de lancement affichée | Le bandeau −50 % est visible |
| 3 | Simulateur WhatsApp dégressif | 60 000 messages → 51 000 FCFA : le calcul est juste |
| 4 | QR visible dans l'aperçu | Le coin bas-gauche est blanc à 38 % — le QR est bien **dessiné**, pas promis |
| 5 | Jeton du badge affiché | Un code à 10 caractères est émis par badge |
| 6 | Entrée validée | Le scan du jeton ouvre l'entrée |
| 7 | Rescan refusé | Le même badge une seconde fois : refusé. **Idempotence** |
| 8 | Code inconnu refusé | Un code inventé ne passe pas |
| 9 | Koris crédités après scan | Solde 550 → 600 : la présence, pas le clic |
| 10 | Pré-vol bloque le cadre opaque | Un cadre sans transparence ne rejoint pas la file |
| 11 | Rapport de pré-vol affiché | Le relecteur voit *pourquoi* un décor est passé |
| 12 | Approbation traitée | La file d'attente diminue d'une ligne |
| 13 | **Décor approuvé réellement ouvrable** | Le décor publié répond 200, pas 404 |
| 14 | **Le Studio se charge dessus** | Approuver ne suffit pas : le gabarit doit rester valide |
| 15 | Notification reçue par le partenaire | La cloche porte un compteur non nul |
| 16 | Publication annoncée | Le décor apparaît publié |
| 17 | Accès anonyme refusé sur `/admin` | Redirection vers la connexion |
| 18 | Accès anonyme refusé sur `/admin/comptes` | Idem |
| 19 | Accès anonyme refusé sur `/partenaire` | Idem |
| 20 | Accès anonyme refusé sur `/scan` | Idem |
| 21 | Participant redirigé hors de `/admin` | Être connecté ne suffit pas : le rôle est vérifié |
| 22 | Limitation de débit active | « Trop de tentatives. Réessayez dans 15 minutes. » |

> **Les scénarios 13 et 14 sont nés d'un défaut.** Ils n'existaient pas, et c'est
> précisément pour cela qu'un décor approuvé pouvait répondre 404 sans que rien ne le
> signale. Voir `07-BACKEND.md` §4 (h).

### Dernière exécution

```
━━ Résultat : 22 réussis, 0 échoués ━━
✓ aucune erreur console
```

> **Une base fraîche est supposée.** Le scénario 12 approuve le seul décor en attente ;
> rejouer la recette sans re-semer laisse la file vide et le scénario échoue. Pour
> repartir de zéro : `rm -f .data/wakabi.sqlite*` puis `npm run seed`.

---

## 3. Les autres vérifications

| Commande | Ce qu'elle contrôle |
|---|---|
| `npm test` | Les **33 invariants** du contrat `DecorTemplate` (sans cadriciel, une seconde) |
| `npm run typecheck` | Le typage strict, sans `any` implicite |
| `npm run build` | La compilation de production |
| `npm run purge` | Les cadres orphelins sur le disque |

---

## 4. Le parcours à faire à la main

L'automatisation prouve que ça marche. Elle ne dit pas si c'est **agréable**. Ces quatre
parcours-là demandent votre œil — idéalement sur un téléphone, sur données mobiles.

### a) Le participant — l'objectif des 30 secondes

1. `/decors` → choisir un décor → importer une photo
2. Recadrer au doigt, écrire un prénom
3. Télécharger, puis partager

**Ce qu'il faut regarder :** le temps total, et si l'aperçu correspond *exactement* au
fichier reçu. C'est le seul endroit où une différence serait rédhibitoire.

### b) Le partenaire — publier sans développeur

1. S'inscrire en « organisateur », vérifier l'adresse (le lien s'affiche à l'écran)
2. `/partenaire/nouveau` : téléverser un PNG à fond transparent, choisir une disposition
3. Essayer une redirection vers un domaine **hors Wakabi** → doit être refusée
4. Soumettre → voir le pré-vol passer, ou expliquer ce qui bloque

**Le garde-fou de l'étape 3 est volontaire.** Un partenaire ne peut renvoyer que vers
`wakabileguide.com` ou `studio.wakabileguide.com`. C'est ce qui empêche un décor de devenir
une passerelle vers n'importe quoi.

### c) L'équipe — relire en connaissance de cause

1. `/admin/moderation` : la file d'attente
2. Lire le rapport de pré-vol avant de décider
3. Refuser sans motif → doit être impossible

### d) L'organisateur — l'entrée

1. Créer un badge côté participant, noter le code
2. `/scan` : saisir le code → entrée validée
3. Le ressaisir → refusé

---

## 5. Ce que la recette **ne** couvre pas

Autant l'écrire que le laisser découvrir :

| Non couvert | Pourquoi |
|---|---|
| L'envoi réel des e-mails | Aucun SMTP configuré — le lien s'affiche à l'écran |
| La lecture caméra du QR | Le code se saisit à la main pour l'instant |
| La montée en charge | SQLite mono-instance ; le chemin Postgres est décrit en `07-BACKEND.md` §5 |
| Le rendu sur un vrai Android d'entrée de gamme | Chromium de bureau n'est pas un Tecno à 40 000 FCFA |
| Les vrais cadres | Les décors de démonstration sont générés par `scripts/build-frames.ts` |

Le quatrième point est le plus important des cinq. La contrainte C1 — un Android d'entrée
de gamme sur données mobiles — est celle qui décide du produit, et c'est la seule qu'aucun
test automatisé ici ne peut trancher.
