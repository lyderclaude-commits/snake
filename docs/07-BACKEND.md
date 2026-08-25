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
users(id, email, password_hash, name, role, organisation, city, suspended, created_at)
sessions(token, user_id, expires_at, created_at)
decors(id, slug, title, subtitle, city, rubrique, status, created_by, author_id,
       template_json, frame_url, published_at, expires_at,
       submitted_at, submitted_by, reviewed_at, reviewed_by, review_note, …)
events(id, decor_id, kind, created_at)        -- 'view' | 'download'
creations(id, user_id, decor_id, created_at)  -- « mes créations »
```

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

**Le Studio reste ouvert sans compte.** C'était la contrainte C4 et c'est la leçon de
mougni : le participant ne doit jamais rencontrer d'obstacle. Le compte n'ajoute qu'une
chose — retrouver ses créations.

---

## 4. Quatre défauts trouvés en construisant

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

| Point | Pourquoi |
|---|---|
| **Limitation de débit sur la connexion** | Rien n'empêche aujourd'hui d'essayer les mots de passe en boucle. |
| **Vérification de l'e-mail** | Un compte partenaire devrait prouver son adresse avant de soumettre. |
| **Notifications** | Le partenaire n'est prévenu ni de l'approbation ni du refus — seulement à sa prochaine visite. |
| **Le pré-vol** | Les 7 contrôles de `04-MODERATION.md` §4 restent à coder. La métabox affiche pour l'instant 4 vérifications simples. |
| **Quotas** | Aucun plafond de téléversement par partenaire. |
| **Sauvegardes** | Copier `.data/` ne suffira pas longtemps. |
| **Purge des cadres orphelins** | Un cadre téléversé puis abandonné reste sur le disque. |

---

## 7. Le prototype a changé trois de mes décisions

Le prototype « Wakabi Boost » que vous m'avez transmis contredit trois choix que j'avais
actés. Vos choix l'emportent, et deux d'entre eux sont meilleurs que les miens :

| Ma décision | Le prototype | Verdict |
|---|---|---|
| **Tutoiement** partout | Vouvoiement sur la vitrine | **Vous avez raison** — la vitrine s'adresse aux organisateurs, le Studio aux participants. Deux audiences, deux tons. |
| **Abonnement payant : écarté** | 4 formules, 0 à 30 000 FCFA | **Vous avez raison.** Mon raisonnement supposait l'outil offert aux partenaires. Facturer en se différenciant par l'audience et le QR est plus solide. |
| **Koris : gelés** | Le QR à l'entrée crédite des Koris | **Meilleur que mon angle.** Mesurer la *présence réelle* est un différenciateur qu'un générateur d'images ne peut pas copier. |

Le QR Code n'est pas encore implémenté — c'est le prochain gros morceau, et il touche à la
fois le renderer (incruster le code dans le décor), le back-end (un code unique par badge)
et un scanner à l'entrée.
