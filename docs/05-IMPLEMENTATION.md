# Journal d'implémentation — J0 / J1

Ce que le code a appris, et qui corrige la spécification écrite avant lui.

---

## 1. Konva ne sert à rien — dépendance supprimée

La spécification retenait **Konva + react-konva** pour l'éditeur interactif. À
l'implémentation, la bibliothèque n'a jamais trouvé de travail à faire.

La raison est structurelle : l'architecture à deux échelles (§4) fait de `renderScene` une
**fonction pure qui redessine toute la scène** à chaque changement d'état. Il n'y a donc
aucun graphe d'objets à tenir, aucune synchronisation, aucun cycle de vie de nœuds. Le
canevas d'aperçu se résume à :

```ts
renderScene(ctx, spec, tpl, assets, scale);
```

Un graphe de scène résout un problème que nous n'avons pas. Konva, Fabric et PixiJS sont
tous écartés. **~150 Ko de JavaScript en moins**, ce qui compte directement pour la
contrainte C2.

Même raisonnement pour **Zustand** : l'état tient dans un seul objet plat manipulé depuis
un seul écran. Un `useReducer` suffit, et le `withClamp` centralisé garantit qu'aucun
chemin de code ne peut produire un bord vide.

> Dépendances de production réelles : **next, react, react-dom, zod**. C'est tout.
> Le studio pèse **128 ko de JS au premier chargement**.

---

## 2. Google Fonts était un point de rupture silencieux

La v0.1 chargeait les polices depuis `fonts.googleapis.com`, comme le site actuel. Le
proxy réseau de l'environnement de développement a bloqué la requête — et **rien n'a
échoué visiblement**. La page s'est affichée, le canevas a rendu, aucune erreur bloquante.

Sauf que `document.fonts` était **vide** : le canevas composait avec une police système de
repli, à des métriques différentes. Toute la mise en page du décor était fausse, sans le
moindre signal.

C'est exactement le scénario de la contrainte C2 — réseau instable, data payante. Un
utilisateur à Lomé sur une 3G capricieuse aurait obtenu un visuel mal composé, sans savoir
pourquoi.

**Correctif : polices auto-hébergées** via `@fontsource/*`, qui sert les mêmes fichiers
depuis notre propre domaine, sous le **nom littéral** dont le canevas a besoin
(`'Bricolage Grotesque'`). Zéro requête externe, et le mode hors ligne devient possible.

> `next/font` n'était pas une option : il génère un nom de famille haché, que
> `ctx.font` ne sait pas résoudre.

---

## 3. Trois défauts que seul le rendu réel a révélés

**a) La signature débordait de 92 % du filigrane.**
« LE GUIDE DES BONS PLANS » fait 23 caractères. Avec l'interlettrage prévu, à l'échelle
d'export : 581 px de texte pour 302 px utiles. Invisible sur le papier, évident au premier
rendu.
→ `drawTracked` ajuste désormais à la largeur disponible : il resserre d'abord
l'interlettrage, puis réduit le corps. Dans cet ordre, parce qu'un texte serré reste plus
lisible qu'un texte minuscule.

**b) La variante `wordmark` supprimait la signature sans le dire.**
`wordmark` suppose un fichier logo. Aucun n'existant, le rendu retombait sur du texte —
mais en conservant le drapeau « pas de signature ». Résultat : une pastille contenant
« WAKABI » seul, qui ne dit pas ce qu'est Wakabi.
→ Le repli est explicite : sans asset, on bascule sur `text`, signature comprise.

**c) `String.replace` a cassé le bundle de démonstration.**
`shell.replace('//SCRIPT', js)` — avec `js` en **chaîne**, JavaScript interprète les
séquences `$&`, `` $` `` et `$'` du remplacement. Du JS minifié en contient. Le script
inliné devenait syntaxiquement invalide (`missing ) after argument list`).
→ Passer une **fonction** de remplacement désactive cette interprétation.

---

## 4. Ce qui reste à faire avant la mise en ligne

| Point | État |
|---|---|
| **Export dans un Web Worker** | L'export tourne sur le thread principal. Le renderer étant déjà pur et sans DOM, le basculement vers `OffscreenCanvas` ne touchera que `ExportBar.tsx`. À faire pour C1. |
| **Le pré-vol** | Les 7 contrôles de `docs/04-MODERATION.md` §4 sont spécifiés, pas encore codés. Ils arrivent en J3 avec l'ouverture aux partenaires. |
| **Catalogue depuis WordPress** | Les 3 modèles sont codés en dur. Le contrat `DecorTemplate` étant déjà la frontière, seul `src/core/templates.ts` changera. |
| **Cadres définitifs** | Les 3 PNG sont des placeholders aux couleurs de la charte. |
| **Logo** | Aucun vectoriel : le filigrane utilise la variante `text`. |
| **PWA / hors ligne** | Serwist pas encore branché. |

---

## 5. Vérification

Chaîne complète pilotée dans un vrai Chromium, en émulation Android d'entrée de gamme
(412 × 915, DPR 2, tactile) :

```
✓ galerie — 3 décors
✓ photo importée (EXIF appliqué, réduction à 2560 px)
✓ zoom, déplacement, teinte et texte appliqués
✓ aperçu rendu en 744×744 px
✓ export 2048×2048
✓ redirection après téléchargement affichée
✓ format 9:16 rendu
✓ aucune erreur console
```

Contrat de données : `tsc --noEmit` strict au vert, **33/33 invariants et transitions**.

**Démonstration autonome** : `public/demo/index.html` — un seul fichier, cadres incorporés
en data URI, aucune requête réseau hors polices. Elle bundle **le même cœur** que
l'application Next.js, sans une ligne réécrite. Si elle rend juste, c'est que
`renderScene` est bien pure.
