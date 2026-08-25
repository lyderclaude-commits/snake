# Questions pour affiner le cahier des charges

**Mis à jour le 25/08/2026**, après dépouillement de l'archive du site **et** de la page
d'accueil mougni. Les blocs A, D et E sont résolus, le bloc B l'est aux trois quarts.
Ce qui reste est signalé 🔴 quand c'est bloquant.

**Rappel :** le programme **Kori est désactivé** — il passe au jalon J5, gelé.

Voir [`docs/02-CHARTE-WAKABI.md`](./02-CHARTE-WAKABI.md) pour tout ce qui a été extrait.

---

## A. Charte graphique — ✅ RÉSOLU par l'archive

| # | Question | Réponse trouvée |
|---|---|---|
| 1 | Couleurs exactes | Primaire `#2563EB`, échelle Tailwind `blue-*`, accents `#F97316` / `#0D9488` / `#D97706`, texte `#0F172A`/`#475569`/`#94A3B8` |
| 2 | Polices | **Bricolage Grotesque** (titres) + **Plus Jakarta Sans** (corps) + *Absolut Pro* (logotype, Bold uniquement) |
| 3 | Logo | `logo-w.png` — ⚠️ **une version SVG reste nécessaire** pour l'incruster proprement dans un décor haute résolution |
| 4 | Iconographie | icons8, style `ios/50` linéaire fin arrondi + emoji dans les niveaux de fidélité |
| 5 | Ton éditorial | **Tutoiement** systématique. Lexique : *bon coin*, *explorateur*, *Kori*, *Carte Wakabi* |

**Deux points restent à trancher :**

6. ✅ **Le logo sur chaque visuel exporté — tranché.** L'analyse de mougni referme la
   question : chez eux, retirer le watermark est la première raison de payer. Notre outil
   étant gratuit et servant l'offre partenaire, le filigrane Wakabi est **systématique**.
   Pure visibilité, aucun arbitrage commercial. Implémenté dans le schéma
   (`watermark.enabled` à `true` par défaut).
7. **Le pied de page vouvoie** (« Explorez. Découvrez. Connectez. ») alors que tout le
   reste tutoie. Je pars sur le **tutoiement** pour le générateur, sauf avis contraire.

> **À signaler à votre équipe technique, indépendamment de ce projet :**
> `var(--font-display)` est utilisé ~536 fois sur le site et **n'est jamais défini**.
> Tous les titres retombent donc sur Plus Jakarta Sans, et Bricolage Grotesque est
> téléchargée sur chaque page sans être appliquée. Correctif : une ligne par `:root`.
> Détail dans [`docs/02-CHARTE-WAKABI.md`](./02-CHARTE-WAKABI.md) §2.

---

## B. Écosystème existant — ✅ RÉSOLU aux trois quarts

| # | Question | Réponse trouvée |
|---|---|---|
| 8 | Qu'existe-t-il déjà ? | **WordPress headless** sur `admin.wakabileguide.com` + site statique + **app Android** `com.wakabi.wakabimobile` |
| 9 | Y a-t-il une API ? | **Oui.** `wp/v2/cities`, `wp/v2/partners`, `wp/v2/posts`, et un namespace custom `wakabi/v1` (`/contact` observé) |
| 12 | Attribution des Koris | QR Code unique dans l'app ; 1 achat = 10 Koris min ; 100 Koris = −500 XOF ; 4 niveaux |

**Ce qui reste :**

10. 🔴 **La liste complète des routes de `admin.wakabileguide.com/wp-json`.**
    Le site ne consomme que `cities`, `partners` et `posts` — mais l'app mobile expose
    manifestement des **événements** et des **établissements**. Un simple
    `GET /wp-json` renvoie l'index de toutes les routes : c'est ce qu'il me faut pour
    figer le modèle d'ancrage. Sans lui, je conçois avec une couche d'accès remplaçable,
    mais l'ancrage attend.
11. **Où vit l'application ?** Je propose `studio.wakabileguide.com`, cohérent avec
    `admin.` déjà en place.
13. **Contraintes d'hébergement ou de coût ?** Le WordPress est déjà hébergé quelque part
    (cPanel, d'après le `.ftpquota` de l'archive) — même hébergeur, ou un service edge ?

---

## C. Périmètre & organisation

12. ✅ **Qui crée les décors — tranché.** L'équipe Wakabi **et** les partenaires, avec
    modération Wakabi. Conçu dans [`docs/04-MODERATION.md`](./04-MODERATION.md) :
    trois acteurs, machine à états à 7 statuts, correspondance avec les capacités
    WordPress natives, 7 contrôles automatiques avant relecture humaine, et un garde-fou
    empêchant un partenaire de détourner la redirection vers son propre site.
13. **Combien de modèles au lancement, et à quelle fréquence ensuite ?** Trois modèles fixes et
    un back-office est un surinvestissement ; deux nouveaux modèles par semaine le rend
    indispensable.
14. **Y a-t-il une campagne précise pour le lancement ?** Un festival, une date, un partenaire ?
    Avoir une première campagne réelle vaut mieux que dix modèles génériques.
15. **Jusqu'où va la v1 ?** Voir les jalons J0→J4 en SPEC §9.

---

## D. UX / parcours — ✅ RÉSOLU par l'analyse mougni

| # | Question | Réponse |
|---|---|---|
| 17 | Les filtres en v1 ? | **Non.** mougni n'en a aucun et domine la catégorie. `<FilterRail>` retiré, ainsi que rotation et miroir. Il reste un filtre signature Wakabi, activable d'un geste. |
| 18 | Champs de texte éditables | Prénom et ville. Le message libre attend un mur public, donc une modération. |
| 19 | Mur public des créations | **Différé.** Les deux compteurs publics (⬇ / 👁) donnent la preuve sociale sans exiger de modération. |

**Reste à trancher :**

16. **Format prioritaire.** Je pars du principe que le **statut WhatsApp 9:16** est au
    moins aussi important que le carré — et qu'un modèle vaut **un format**, plutôt qu'une
    bascule multi-ratios dans l'éditeur (mougni ne propose pas de bascule). À confirmer.
20. **Langues.** Le site Wakabi est en français uniquement, mougni propose FR/EN.
    Français seul pour la v1, ou anglais dès le départ pour Abidjan ?

---

## E. mougni.com — ✅ RÉSOLU

Page d'accueil complète reçue et analysée. Voir
[`docs/03-ANALYSE-MOUGNI.md`](./03-ANALYSE-MOUGNI.md) : modèle économique, périmètre réel
de l'éditeur, implémentation, et grille de décision sur 16 points.

**Une seule question subsiste :**

21. **Y a-t-il un point de mougni que vous trouvez particulièrement réussi — ou raté ?**
    Mon analyse porte sur la page d'accueil ; vous avez peut-être utilisé le parcours de
    création lui-même, ce que je n'ai pas pu faire.
