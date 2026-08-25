# Questions pour affiner le cahier des charges

**Mis à jour le 25/08/2026, après dépouillement de l'archive du site.**
Le bloc A est **entièrement résolu**, le bloc B l'est aux trois quarts. Ce qui reste
est signalé 🔴 quand c'est bloquant.

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

6. **Le logo doit-il apparaître sur chaque visuel exporté ?** Filigrane systématique, ou
   seulement quand le modèle le prévoit ? *Un filigrane systématique donne de la
   visibilité, mais réduit le partage sur les comptes soignés.*
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

12. **Qui crée les décors ?** L'équipe Wakabi seule ? Les partenaires en self-service ? Cela
    détermine s'il faut un back-office avec rôles et modération, ou un simple écran d'admin.
13. **Combien de modèles au lancement, et à quelle fréquence ensuite ?** Trois modèles fixes et
    un back-office est un surinvestissement ; deux nouveaux modèles par semaine le rend
    indispensable.
14. **Y a-t-il une campagne précise pour le lancement ?** Un festival, une date, un partenaire ?
    Avoir une première campagne réelle vaut mieux que dix modèles génériques.
15. **Jusqu'où va la v1 ?** Voir les jalons J0→J4 en SPEC §9.

---

## D. UX / parcours

16. **Format prioritaire.** Je pars du principe que le **statut WhatsApp (9:16)** est au moins
    aussi important que le carré. À confirmer — cela change la conception des cadres.
17. **Les filtres photo sont-ils vraiment souhaités en v1 ?** Ils allongent le parcours et
    entrent en tension avec l'objectif « 30 secondes ». Un unique filtre signature Wakabi,
    activable d'un geste, serait peut-être plus juste.
18. **Quels champs de texte l'utilisateur peut-il saisir ?** Prénom ? Ville ? Un message libre ?
    Le texte libre implique une **modération** dès qu'un mur public existe.
19. **Un mur public des créations est-il souhaité ?** Excellent levier social, mais il impose
    une chaîne de modération avant ouverture.
20. **Langues.** Français uniquement, ou anglais également (Abidjan / audience internationale) ?

---

## E. mougni.com

21. **Transmettez-moi ce que vous avez** : captures d'écran du parcours complet, une vidéo de
    l'écran, ou simplement la liste écrite des fonctionnalités. Je verse le tout dans la grille
    d'analyse (SPEC §8) et nous en sortons des verdicts
    `ADOPTER` / `REMIXER` / `ÉCARTER` / `DIFFÉRER`.
22. **Y a-t-il un point précis de mougni qui vous a marqué ?** Une chose que vous avez trouvée
    particulièrement réussie — ou au contraire ratée, et que vous voulez éviter ?
