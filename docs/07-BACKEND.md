# Back-end autonome — WordPress abandonné

**Décision du 25/08/2026 :** l'administration ne passe plus par WordPress.
Wakabi Boost a son propre back-end, ses propres comptes et sa propre base.

---

## 1. Ce que cette décision annule

Les documents 02 §6, 04 §3 et la section Stack de la spécification recommandaient de faire
vivre le catalogue dans le WordPress existant. Le raisonnement était : *ne pas imposer deux
back-offices à l'équipe*. Il tombe, puisque le WordPress sort du périmètre — il reste ce
qu'il a toujours été, **le back-end éditorial des articles**.

> Mon objection à Supabase reposait sur la même prémisse. Elle est levée aussi : voir §5
> pour le chemin de migration si vous voulez du Postgres géré.

---

## 2. Ce qui a été construit

| Couche | Choix | Fichier |
|---|---|---|
| Base | **SQLite** (`better-sqlite3`), schéma versionné au démarrage | `src/server/db.ts` |
| Sessions | Cookie `httpOnly` + table `sessions`, expiration 30 jours | `src/server/auth.ts` |
| Mots de passe | **scrypt** (bibliothèque standard Node), sel par compte, comparaison à temps constant | `src/server/auth.ts` |
| Accès données | Dépôt — **seule** frontière avec le SQL | `src/server/repo/*.ts` |
| Écritures | **Server Actions**, pas d'API REST à maintenir | `src/app/actions/*.ts` |
| Fichiers | Hors de `public/`, servis par une route | `src/app/api/frames/[name]/route.ts` |

**Trois rôles**, hiérarchisés : `user` (participe), `partner` (crée et soumet),
`admin` (publie, relit, gère les comptes).

### Le schéma

```
users(id, email, password_hash, name, role, organisation, city,
      suspended, email_verified_at, koris, created_at)
sessions(token, user_id, expires_at, created_at)
decors(id, slug, title, subtitle, city, rubrique, status, created_by, author_id,
       template_json, frame_url, published_at, expires_at,
       submitted_at, submitted_by, reviewed_at, reviewed_by, review_note, …)
events(id, decor_id, kind, created_at)          -- 'view' | 'download'
creations(id, user_id, decor_id, created_at)    -- « mes créations »

-- la boucle QR → présence → Koris
badges(token, decor_id, user_id, created_at, scanned_at, scanned_by, koris)
kori_events(id, user_id, amount, reason, badge_token, created_at)

-- l'exploitation
notifications(id, user_id, kind, title, body, href, read_at, created_at)
email_tokens(token, user_id, expires_at, created_at)
login_attempts(id, key, created_at)             -- limitation de débit
preflight(decor_id, passed, report, ran_at)     -- dernier rapport de contrôle
```

> `badges.scanned_at` est la clé de voûte : **non nul = présent**. C'est ce qui rend le
> scan idempotent (un badge ne vaut qu'une entrée) et ce qui déclenche le crédit en Koris.
> Un `UPDATE … WHERE scanned_at IS NULL` suffit : deux agents qui scannent le même badge
> en même temps ne peuvent pas créditer deux fois.

> `creations` ne stocke **que** le décor utilisé et la date. Pas la photo : elle est traitée
> dans le navigateur et ne parvient jamais au serveur. Il n'y a donc rien à protéger.

---

## 3. Les écrans

| Route | Qui | Contenu |
|---|---|---|
| `/` | tous | La vitrine Wakabi Boost |
| `/decors` | tous | Catalogue public, filtrable par ville |
| `/decor/[slug]` | tous | Le Studio — **sans compte** |
| `/connexion`, `/inscription` | tous | Deux types de compte au choix |
| `/compte` | `user` | Ses créations |
| `/partenaire` | `partner` | Ses campagnes, chiffres, statuts, retours de relecture |
| `/partenaire/nouveau` | `partner`, `admin` | Création d'un décor en 5 étapes |
| `/admin` | `admin` | Tableau de bord, file d'attente, série 14 jours |
| `/admin/moderation` | `admin` | Approuver · Corriger · Refuser |
| `/admin/comptes` | `admin` | Rôles et suspensions |
| `/scan` | `admin` | Contrôle d'entrée : valider un badge, créditer les Koris |
| `/verifier/[token]` | tous | Confirmation de l'adresse e-mail |
| `/api/frames/[name]` | tous | Sert les cadres téléversés (hors de `public/`) |

**Le Studio reste ouvert sans compte.** C'était la contrainte C4 et c'est la leçon de
mougni : le participant ne doit jamais rencontrer d'obstacle. Le compte n'ajoute qu'une
chose — retrouver ses créations.

---

## 4. Huit défauts trouvés en construisant

**a) `public/` est figé au build.** Les cadres téléversés y étaient écrits, puis servis en
404 par `next start` — et sur une image de conteneur immuable, l'écriture aurait échoué
tout court. Ils vivent désormais dans `.data/uploads/`, servis par une route qui vérifie le
nom contre `^[0-9a-f-]{36}\.(png|webp)$`. La traversée de chemin est testée.

**b) React 19 réinitialise un formulaire après une Server Action.** Un partenaire qui se
trompait sur un champ perdait **toute sa saisie**. L'action renvoie maintenant les valeurs
soumises, que le formulaire réapplique en `defaultValue`.

**c) Un champ fichier ne peut pas être re-rempli par le code.** Même problème, en pire : le
cadre téléversé était perdu à chaque erreur. Il est désormais **téléversé dès la
sélection**, et son URL vit dans un champ caché qui survit aux erreurs.

**d) Une série temporelle ne doit pas n'afficher que les jours actifs.** Avec un seul jour
de données, l'histogramme produisait une barre pleine largeur — lisible comme une
constante. La fenêtre de 14 jours est maintenant complétée à zéro.

**e) Deux de mes sept contrôles de pré-vol étaient faux.** `watermark-clear` refusait des
cadres corrects : il cherchait un filigrane recouvert, alors que le filigrane est **dessiné
en dernier** — il ne peut pas l'être. Et `contrast` alertait sur *tous* les décors, parce
qu'il jugeait le texte blanc sur fond clair sans regarder la photo, qui n'existe pas encore
au moment du contrôle. Les deux sont désormais utiles : le premier détecte une **collision**
(un texte placé sous le filigrane ou sous le QR), le second un **texte sombre**, seul cas
réellement indéfendable sur une photo quelconque.

> Un contrôle qui refuse ce qui est bon est pire que pas de contrôle : le relecteur apprend
> à l'ignorer, et le jour où il a raison personne ne l'écoute.

**f) Le QR n'apparaissait qu'à l'export.** Ma propre règle — un seul `renderScene` pour
l'aperçu et pour l'export — était violée par le QR, forgé au moment du téléchargement. Le
participant voyait donc un badge et en recevait un autre. Le jeton est maintenant émis au
chargement de la photo, et l'aperçu montre exactement le fichier produit.

**g) Le QR recouvrait le texte du badge.** Découvert immédiatement après (f), et invisible
tant que (f) tenait. Les trois dispositions placent désormais les textes en `x = 0,25`,
`largeur = 0,48` — dégagés du QR à gauche et du filigrane à droite — et le pré-vol refuse un
gabarit qui remettrait un texte dans l'une de ces deux zones.

**h) Un décor approuvé pouvait répondre 404.** Le plus grave des huit, et le dernier
trouvé. Le statut d'un décor vit à **deux endroits** : la colonne `decors.status` et le
champ `status` du gabarit JSON. `transition()` ne mettait à jour que la colonne. Un décor
de partenaire approuvé se retrouvait donc publié en base, listé au catalogue — et avec un
gabarit portant encore `moderation: {}`, que le contrat refuse (« un décor créé par un
partenaire ne peut pas être publié sans relecture »). La page publique appelait
`parseTemplate`, recevait `null`, et renvoyait `notFound()`.

Le contrat a fait son travail : il a bien détecté la violation. Mais **au mauvais moment** —
à la lecture, sous forme de 404, au lieu de l'écriture, sous forme de refus. `transition()`
réécrit désormais le gabarit en même temps que les colonnes et le **revalide** ; une
transition qui produirait un gabarit invalide échoue au lieu de s'écrire.

> Et ce défaut a survécu à 20 scénarios verts. La recette approuvait un décor puis vérifiait
> que la file d'attente diminuait — jamais que le décor approuvé s'ouvrait. Deux scénarios
> ont été ajoutés (13 et 14) : ils échouent sur le code d'avant.

---

## 5. Passer à Postgres

SQLite tourne sans service à provisionner, sans identifiants, sans réseau : c'est ce qui
permet de développer et de démontrer aujourd'hui. Elle tiendra largement les premiers
milliers d'utilisateurs.

Le jour où il faut plusieurs instances applicatives ou des sauvegardes gérées :

1. `src/server/repo/*.ts` est la **seule** frontière avec le SQL. Aucun composant, aucune
   page ne connaît la base.
2. Le SQL utilisé est volontairement ordinaire — pas de spécificité SQLite hors
   `AUTOINCREMENT` et `substr(created_at,1,10)`.
3. `.data/uploads/` devient un stockage objet : seul
   `src/app/api/frames/[name]/route.ts` change.

**Candidats :** Supabase (Postgres + Storage + sauvegardes), Neon (Postgres serverless),
ou un Postgres sur le même hébergeur que le WordPress actuel.

---

## 6. Avant la mise en ligne

### Traité depuis

| Point | Ce qui a été fait |
|---|---|
| **Limitation de débit sur la connexion** | 8 essais par 15 minutes, par couple e-mail + adresse. Le compteur vit en base, il survit donc à un redémarrage. |
| **Vérification de l'e-mail** | Jeton à usage unique, 48 h. Un partenaire non vérifié **ne peut pas soumettre**. |
| **Notifications** | En base et à l'écran (cloche + compteur non lus) : soumission → l'équipe, décision → le partenaire, publication → l'auteur. |
| **Le pré-vol** | Les 7 contrôles de `04-MODERATION.md` §4 s'exécutent **côté serveur**, avec `sharp` pour analyser réellement le cadre. Un décor qui échoue ne rejoint jamais la file. |
| **Quotas** | 20 Mo et 30 fichiers par partenaire, vérifiés au téléversement. |
| **Purge des cadres orphelins** | `npx tsx scripts/purge.ts` — liste les cadres qu'aucun décor ne référence et les supprime. |

### Reste à traiter

| Point | Pourquoi |
|---|---|
| **Transport des e-mails** | Tout le circuit existe ; sans SMTP configuré le lien de vérification s'affiche à l'écran au lieu de partir. C'est une fonction à brancher, pas une fonctionnalité à écrire. |
| **Sauvegardes** | Copier `.data/` ne suffira pas longtemps. À régler *avant* d'avoir des données qu'on regretterait de perdre. |
| **Les vrais cadres** | Les décors de démonstration sont générés. Rien ne se teste sérieusement sur des placeholders. |
| **Journal d'audit** | On sait *qui* a décidé et *quand* (`reviewed_by`, `reviewed_at`), mais l'historique complet d'un décor n'est pas conservé. |
| **HTTPS et cookie `Secure`** | Le cookie de session est `httpOnly` et `sameSite=lax` ; `Secure` doit être activé au déploiement. |

---

## 7. Le prototype a changé trois de mes décisions

Le prototype « Wakabi Boost » que vous m'avez transmis contredit trois choix que j'avais
actés. Vos choix l'emportent, et deux d'entre eux sont meilleurs que les miens :

| Ma décision | Le prototype | Verdict |
|---|---|---|
| **Tutoiement** partout | Vouvoiement sur la vitrine | **Vous avez raison** — la vitrine s'adresse aux organisateurs, le Studio aux participants. Deux audiences, deux tons. |
| **Abonnement payant : écarté** | 4 formules, 0 à 30 000 FCFA | **Vous avez raison.** Mon raisonnement supposait l'outil offert aux partenaires. Facturer en se différenciant par l'audience et le QR est plus solide. |
| **Koris : gelés** | Le QR à l'entrée crédite des Koris | **Meilleur que mon angle.** Mesurer la *présence réelle* est un différenciateur qu'un générateur d'images ne peut pas copier. |

**Le QR Code est désormais implémenté**, dans les trois couches que j'annonçais :
le renderer l'incruste dans le décor (`drawQr`, visible dès l'aperçu), le back-end émet un
jeton unique par badge (`badges`), et `/scan` valide l'entrée puis crédite les Koris. Le seul
reste est la **lecture caméra** : l'agent d'entrée saisit aujourd'hui le code à la main, ce
qui fonctionne mais ne tiendra pas une file d'attente.

---

## 8. Recette — comment vérifier vous-même

```bash
npm install
npm run seed          # comptes et décors de démonstration
npm run dev           # http://localhost:3000
```

Puis, application lancée :

```bash
npm run e2e:full      # 20 scénarios, navigateur réel, sortie en français
```

Le script conduit un navigateur à travers les 25 scénarios de `docs/08-RECETTE.md` et
échoue bruyamment sur la moindre erreur de console. Dernière exécution :
**25 réussis, 0 échoués, aucune erreur console.**
