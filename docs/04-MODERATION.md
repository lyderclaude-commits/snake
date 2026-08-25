# Création partagée & modération

**Décision du 25/08/2026 :** les décors sont créés par **l'équipe Wakabi et par les
partenaires**, ces derniers passant par une **relecture Wakabi** avant publication.

C'est la décision qui transforme le générateur en argument de vente pour l'offre
partenaire (SPEC §1.3) — mais elle introduit une file d'attente humaine. Tout ce document
vise à ce que cette file reste **courte et rapide à traiter**.

---

## 1. Trois acteurs, trois niveaux de friction

| Acteur | Compte | Ce qu'il fait | Modération |
|---|---|---|---|
| **Visiteur** | ❌ aucun | Crée son visuel sur une campagne publiée, le télécharge, le partage | ❌ aucune |
| **Partenaire** | ✅ requis | Crée un décor, le soumet, corrige après retour | ✅ **systématique** |
| **Équipe Wakabi** | ✅ requis | Crée et publie directement, relit les soumissions | ❌ (elle *est* la modération) |

La friction est **exactement** celle de mougni, et elle est juste : le participant ne doit
jamais rencontrer d'obstacle, l'organisateur accepte volontiers d'en rencontrer un.

---

## 2. Machine à états

```
              ┌─────────┐
              │  draft  │◀──────────────────────┐
              └────┬────┘                       │
     partenaire    │        équipe              │ partenaire
     « soumettre » │        « publier »         │ « corriger »
                   ▼                            │
          ┌────────────────┐                    │
          │ pending_review │                    │
          └───┬────┬───┬───┘                    │
     approuver│    │   │demander corrections    │
              │    │   └──────▶ ┌───────────────────────┐
              │    │            │  changes_requested    │
              │    │refuser     └───────────┬───────────┘
              │    ▼                        │ re-soumettre
              │ ┌──────────┐                │
              │ │ rejected │                ▼
              │ └──────────┘        (retour pending_review)
              ▼
       ┌────────────┐   expiresAt   ┌─────────┐
       │ published  │──────────────▶│ expired │
       └─────┬──────┘               └─────────┘
             │ archiver
             ▼
       ┌──────────┐
       │ archived │
       └──────────┘
```

Les transitions autorisées **par acteur** sont codées dans
[`template.schema.ts`](./contracts/template.schema.ts) (`canTransition`) et couvertes par
les tests. Les règles qui comptent :

- Un partenaire **ne peut jamais** passer un décor à `published`.
- Un partenaire **ne peut pas** s'auto-approuver depuis `pending_review`.
- Un refus ou une demande de correction **exige un motif** — sinon le partenaire n'a rien
  à corriger.
- Un partenaire peut **retirer** sa soumission (`pending_review → draft`).

---

## 3. WordPress fait déjà 80 % du travail

C'est le principal bénéfice d'avoir gardé le WordPress existant plutôt que d'ajouter
Supabase : **le flux Contributeur → en attente → publication existe dans le cœur de
WordPress depuis toujours.** Un rôle sans la capacité `publish_*` voit automatiquement
son bouton « Publier » remplacé par « **Soumettre à la relecture** ». Il n'y a
rien à écrire pour cela.

### Déclaration du type de contenu

```php
register_post_type('decor_template', [
  'capability_type' => ['decor', 'decors'],
  'map_meta_cap'    => true,
  'show_in_rest'    => true,          // édition via l'admin
  'supports'        => ['title', 'author', 'thumbnail', 'revisions'],
]);
```

### Les deux rôles

| Capacité | `wakabi_partner` | `wakabi_editor` |
|---|:--:|:--:|
| `edit_decors` | ✅ | ✅ |
| `delete_decors` | ✅ | ✅ |
| `upload_files` | ✅ | ✅ |
| **`publish_decors`** | ❌ | ✅ |
| `edit_others_decors` | ❌ | ✅ |
| `edit_published_decors` | ❌ | ✅ |
| `read_private_decors` | ❌ | ✅ |

Retirer `publish_decors` au partenaire suffit à produire tout le comportement voulu :
bouton « Soumettre à la relecture », impossibilité de modifier un décor déjà publié,
invisibilité des décors des autres partenaires.

### Correspondance des états

| État du schéma | WordPress |
|---|---|
| `draft` | `draft` *(natif)* |
| `pending_review` | `pending` *(natif)* |
| `published` | `publish` *(natif)* |
| `archived` | `trash` *(natif)* ou statut dédié |
| `changes_requested` | `register_post_status()` |
| `rejected` | `register_post_status()` |
| `expired` | `register_post_status()` + tâche WP-Cron quotidienne sur `expiresAt` |

> **Piège connu :** les statuts déclarés par `register_post_status()` n'apparaissent pas
> d'eux-mêmes dans le sélecteur de l'éditeur de blocs. Il faut les exposer explicitement,
> ou — plus simple et plus lisible pour l'équipe — piloter ces trois transitions par des
> **boutons dédiés** dans une métabox « Relecture », plutôt que par le sélecteur natif.

### Exposition publique

Le point d'entrée public doit être **le seul** à servir des décors, et ne servir que ceux
qui sont publiés et non expirés :

```
GET wakabi/v1/decors            → published uniquement, expiresAt non dépassé
GET wakabi/v1/decors/{slug}     → idem
POST wakabi/v1/decors/{id}/count → incrémente ⬇ ou 👁
```

⚠️ Ne pas exposer `wp/v2/decor_template` publiquement : la route native laisserait fuiter
les brouillons et les soumissions en attente aux utilisateurs authentifiés.

---

## 4. Le contrôle automatique avant relecture humaine

**L'objectif : que le relecteur Wakabi n'ait à juger que ce qu'une machine ne peut pas
juger.** Tout le reste tourne à la soumission, et un décor qui échoue ne rejoint jamais la
file d'attente — il revient directement au partenaire avec la liste de ce qui cloche.

Sept contrôles, définis dans `PreflightReport` :

| Contrôle | Ce qu'il vérifie | Échec = |
|---|---|---|
| **`schema`** | Le modèle valide le schéma Zod, invariants compris | `fail` |
| **`photo-visible`** | Le cadre est rendu sur un canevas transparent, puis le canal alpha est échantillonné **à l'intérieur du `photoSlot`**. Au-delà de ~85 % de couverture opaque, la photo de l'utilisateur serait invisible. | `fail` |
| **`text-fits`** | Chaque calque texte est mesuré avec sa police et sa taille réelles. S'il déborde de son `rect` et que `autoShrink` est désactivé | `fail` |
| **`watermark-clear`** | La zone du filigrane Wakabi n'est pas recouverte par une région opaque du cadre | `fail` |
| **`asset-format`** | PNG ou WebP, **canal alpha présent**, dimensions ≥ celles du canevas. **SVG refusé** (vecteur d'injection dans la médiathèque WordPress) | `fail` |
| **`asset-weight`** | Le cadre pèse moins de ~400 Ko — la data est chère (C2) | `warn` |
| **`contrast`** | Chaque texte est composité sur une photo test **blanche** *et* une photo test **noire** ; le rapport de contraste est calculé sur les deux | `warn` sous 4,5:1 |

Ces contrôles réutilisent `renderScene` : c'est le bénéfice direct d'avoir fait du
renderer une **fonction pure** (SPEC §4). Le pré-vol tourne le même code que l'éditeur et
que l'export, donc il teste ce que l'utilisateur verra réellement.

> `photo-visible` est le contrôle le plus rentable. L'erreur la plus fréquente d'un
> non-designer qui fabrique un cadre est d'aplatir son visuel en un PNG opaque — le cadre
> est alors magnifique, et la photo n'apparaît jamais. Le schéma vérifie déjà l'ordre des
> calques ; ce contrôle vérifie le résultat rendu.

---

## 5. Ce qui reste à l'humain

Six points, qu'aucun contrôle automatique ne peut trancher :

1. **Les droits sur les visuels.** Le partenaire a-t-il le droit d'utiliser cette image ?
   C'est le risque juridique n°1, et il est porté par Wakabi qui héberge.
2. **La cohérence éditoriale.** Ton, orthographe, lexique maison — le tutoiement, « bon
   coin », « explorateur ».
3. **Le caractère approprié du contenu.** Pas de contenu politique, choquant ou trompeur.
4. **La réalité de l'événement.** La date d'expiration correspond-elle à un événement qui
   existe ? Un décor daté qui traîne pollue le catalogue.
5. **L'activité du partenaire.** `meta.wk_status` du partenaire vaut-il toujours autre
   chose que `suspended` ? Un partenaire suspendu ne doit plus publier.
6. **La destination de la redirection.** Le schéma impose déjà un domaine Wakabi pour les
   décors de partenaires *(voir §6)* — le relecteur vérifie que la fiche pointée est bien
   la bonne.

**Format de la relecture :** une métabox « Relecture » avec trois boutons — *Approuver*,
*Demander des corrections*, *Refuser* — et un champ motif obligatoire pour les deux
derniers. Rien de plus.

---

## 6. Un garde-fou que la création partagée rend nécessaire

Sans contrainte, un partenaire pourrait faire pointer la redirection après téléchargement
vers **son propre site**. Il récupérerait alors tout le trafic généré par le badge, sans
que Wakabi n'en garde rien — ce qui viderait de sa substance l'ancrage décrit en
SPEC §1.3, et transformerait le générateur en régie publicitaire gratuite.

Le schéma impose donc que la redirection d'un décor **créé par un partenaire** pointe vers
un domaine Wakabi :

```ts
export const WAKABI_REDIRECT_HOSTS = ['wakabileguide.com', 'studio.wakabileguide.com'];
```

L'équipe Wakabi n'est pas contrainte — une campagne co-brandée peut légitimement renvoyer
ailleurs. Invariant couvert par les tests.

---

## 7. Notifications

| Événement | Destinataire | Canal |
|---|---|---|
| Décor soumis | Équipe Wakabi | E-mail + file d'attente dans l'admin |
| Approuvé | Partenaire | E-mail, avec le lien public |
| Corrections demandées | Partenaire | E-mail, **avec le motif** |
| Refusé | Partenaire | E-mail, **avec le motif** |
| Expiration dans 3 jours | Partenaire | E-mail |

L'e-mail suffit en v1. WhatsApp serait plus efficace dans le contexte local — c'est
d'ailleurs tout le fonds de commerce de mougni — mais cela suppose un compte WhatsApp
Business API et un budget par message. À rouvrir plus tard.

**Engagement de délai :** viser une relecture sous **24 h ouvrées**. Au-delà, l'outil perd
son intérêt commercial — un partenaire qui prépare une campagne pour le week-end ne peut
pas attendre. C'est un engagement opérationnel, pas technique, mais il conditionne
l'argument de vente.

---

## 8. Sécurité des téléversements

Les partenaires téléversent des fichiers dans la médiathèque WordPress. Quatre règles :

1. **SVG refusé.** WordPress le bloque par défaut, et il faut le laisser bloqué : un SVG
   est un document XML qui peut porter du JavaScript.
2. **Types autorisés en liste blanche** — PNG et WebP uniquement, vérifiés sur le contenu
   réel du fichier et pas sur son extension.
3. **Quota par partenaire** — un plafond de fichiers et de poids, pour éviter qu'un compte
   compromis ne remplisse le disque.
4. **Pas d'exécution PHP** dans `wp-content/uploads` — règle serveur classique, à vérifier
   sur l'hébergement actuel.

---

## 9. Effet sur les jalons

La création partagée n'attend pas J4 : c'est elle qui donne sa valeur commerciale au
produit. Découpage révisé :

| Jalon | Ce que la décision y ajoute |
|---|---|
| **J2 — Catalogue** | Le CPT `decor_template`, les deux rôles, les statuts personnalisés. L'équipe publie. |
| **J3 — Ancrage** | Le **pré-vol** et la métabox de relecture. Les partenaires soumettent, l'équipe relit. |
| **J4 — Partenaires** | Notifications, quotas, tableau de bord partenaire, décors sponsorisés. |

Le pré-vol arrive **en même temps** que l'ouverture aux partenaires, pas après : sans lui,
la file de relecture se remplit de décors cassés et l'engagement des 24 h ne tient pas.
