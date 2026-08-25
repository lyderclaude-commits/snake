# Questions pour affiner le cahier des charges

Classées par **impact sur l'architecture**. Les questions marquées 🔴 bloquent des décisions
structurantes ; les autres peuvent être tranchées en cours de route.

---

## A. Charte graphique — 🔴 bloquant pour la DA

Le proxy réseau de ma session m'interdit l'accès à `wakabileguide.com`. Je n'ai donc **aucune**
donnée de première main sur votre identité visuelle. Tout ce que je produirai en DA sera une
proposition à valider tant que je n'ai pas :

1. **Les couleurs exactes** — hex de la primaire, secondaire, accent(s), fonds, textes.
   Une charte PDF, un fichier Figma, ou même trois captures d'écran suffisent.
2. **Les polices** — titres et corps de texte. Google Fonts ? Fonte achetée ? Fallbacks ?
3. **Le logo** en SVG (versions couleur, monochrome, fond sombre).
4. **Le style d'iconographie** — linéaire ou plein ? Anguleux ou arrondi ? Une source
   (Lucide, Phosphor, jeu d'icônes maison) ?
5. **Le ton éditorial** — tutoiement ou vouvoiement ? Y a-t-il un lexique maison
   (« bons coins », « Koris »…) à réutiliser tel quel dans l'interface ?
6. **Le logo doit-il apparaître sur chaque visuel exporté ?** Filigrane systématique, ou
   uniquement quand le modèle le prévoit ? *(Cela change la stratégie virale : un filigrane
   systématique donne de la visibilité, mais réduit le partage sur les comptes soignés.)*

> En attendant, la charte est isolée dans `docs/contracts/wakabi-tokens.css`. Aucune couleur
> n'est écrite en dur ailleurs dans le code : le rebranding sera un diff d'un seul fichier.

---

## B. Écosystème existant — 🔴 bloquant pour l'architecture

7. **Qu'existe-t-il déjà techniquement ?** Le site est-il un WordPress, un site sur mesure,
   autre chose ? Y a-t-il une **application mobile** Wakabi (les Koris le suggèrent) ?
8. **Existe-t-il une base / une API des lieux, événements et partenaires ?**
   C'est la question la plus importante du document. Si oui, le générateur s'y branche et
   les décors sont **ancrés** (SPEC §1.3). Si non, la v1 fonctionne en autonomie avec des
   modèles saisis à la main, et l'ancrage devient un jalon ultérieur.
9. **Où vit l'application ?** Sous-domaine dédié (`studio.wakabileguide.com`), sous-répertoire
   du site actuel, ou intégration en iframe dans une page existante ?
10. **Contraintes d'hébergement ou de coût ?** Un fournisseur imposé, un budget mensuel cible,
    une obligation d'hébergement dans une zone donnée ?
11. **Les Koris : comment sont-ils attribués aujourd'hui ?** Manuellement, via une app, via un
    QR code chez le partenaire ? Existe-t-il un compte utilisateur Wakabi sur lequel se brancher ?

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
