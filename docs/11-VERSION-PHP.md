# La version PHP — décompresser, ouvrir une page, c'est en ligne

Cette version existe pour une raison simple : sur un mutualisé, un site PHP
avec une base de données se déploie en glissant un dossier. Une application
Node.js demande un processus permanent, une compilation et 843 Mo de mémoire.

---

## Installer

1. Décompressez `wakabi-boost-php.zip` dans le dossier de votre sous-domaine.
2. Ouvrez `https://boost.wakabileguide.com/install.php`.
3. Répondez à trois questions. C'est fini.
4. **Supprimez `install.php`.**

L'installateur commence par afficher ce que propose votre hébergement — version
de PHP, extensions, droits d'écriture. S'il manque quelque chose
d'indispensable, il le dit et s'arrête, plutôt que d'échouer à mi-chemin.

### La base de données

| Choix | Quand |
|---|---|
| **SQLite** *(recommandé pour démarrer)* | Rien à créer, rien à saisir. Tout tient dans `donnees/wakabi.sqlite`. |
| **MySQL / MariaDB** | Créez d'abord la base dans cPanel, puis donnez ses identifiants. Préférable dès que le trafic monte. |

Les deux ont été vérifiés de bout en bout : **183 scénarios, 183 réussis**
sur chacun, depuis le zip livré. La montée de version d'une installation déjà en
service a été vérifiée sur les deux moteurs : colonne ajoutée à la première
requête, comptes existants intacts.

---

## Ce qui change, et ce qui ne change pas

**Ce qui ne change pas — c'est l'essentiel.** Le Studio est le *même code*.
`renderScene`, le pipeline photo, le garde-fou anti-vide et le partage viennent
de `src/core`, importés tels quels et empaquetés par `npm run php:bundle`. Un
badge produit ici et un badge produit par la version Next.js sont le même
fichier : mêmes positions, même filigrane, même QR.

La photo ne quitte toujours pas l'appareil. Elle est décodée, recadrée et
exportée dans le navigateur ; le serveur ne reçoit que le jeton du badge.

**Ce qui change :**

| | Next.js | PHP |
|---|---|---|
| Hébergement | Node permanent, 843 Mo pour construire | N'importe quel mutualisé |
| Installation | Terminal, systemd, Nginx | Une page web |
| Base | SQLite | SQLite **ou** MySQL |
| Validation du gabarit | zod | code PHP, vérifié contre zod |
| QR Code | bibliothèque `qrcode` | encodeur écrit ici, vérifié contre elle |
| Poids du JavaScript | 128 Ko | **12 Ko** |

Les 12 Ko ne sont pas une coquetterie : la contrainte C1 est un Android
d'entrée de gamme sur données mobiles. C'est le poids de ce que télécharge
un **invité** — le Studio. Le formulaire de décor (12 Ko) et l'écran de
contrôle d'entrée (4 Ko) ont leurs propres fichiers, et ne sont chargés que
par l'équipe. Le seul gros morceau du projet, le lecteur de QR de secours
(127 Ko), n'arrive que sur un navigateur sans `BarcodeDetector`, et
seulement quand on ouvre la caméra.

---

## Les deux morceaux réécrits, et comment ils sont vérifiés

Porter un contrat de données et un encodeur QR sans filet, c'est produire des
bugs invisibles : un QR qui ne se scanne pas, un gabarit auquel il manque un
champ. Les deux sont donc **comparés à l'implémentation d'origine**.

### Le QR — `npx tsx scripts/verifier-qr.ts`

Aucune bibliothèque à installer sur un mutualisé : l'encodeur est écrit dans
`app/qr.php` — mode octet, correction M, versions 1 à 10, Reed-Solomon compris.

Il est comparé **module par module** à la bibliothèque `qrcode` de Node, sur
douze charges utiles de 1 à 190 caractères. Les douze matrices sont identiques.

> Deux choses ont failli passer inaperçues. Le polynôme générateur était juste,
> mais **le placement des bits de format était en miroir** — et il écrasait au
> passage le module toujours sombre. Le QR *paraissait* correct ; rien ne
> l'aurait décodé.
>
> Et la comparaison elle-même était fausse au départ : laissée libre, la
> bibliothèque de référence découpe la charge utile en segments mixtes
> (octet + alphanumérique) pour gagner quelques modules. Comparer à ce
> résultat-là faisait échouer un encodeur correct. Le mode octet est désormais
> forcé des deux côtés.

### Le gabarit — `npx tsx scripts/verifier-gabarit.ts`

Le PHP produit un gabarit pour chaque disposition ; le script le fait valider
par **le vrai schéma zod**, puis compare champ par champ ce que zod aurait
ajouté et ce que PHP écrit.

> Sept valeurs par défaut manquaient au premier essai. L'une d'elles,
> `layers.0.mask`, faisait planter le rendu : le Studio affichait « cette image
> n'a pas pu être lue » sur toutes les photos. C'est exactement le genre de
> défaut qu'un port sans vérification laisse passer.

---

## Vérifier soi-même

```bash
npm run php:serve        # http://127.0.0.1:3600
```

Ouvrez `install.php`, installez, puis :

```bash
npm run php:e2e          # 183 scénarios, dans un vrai navigateur
npm run php:verifier     # QR, gabarit, SMTP, et une sauvegarde vraiment restaurée
```

Contre une base MySQL :

```bash
BASE_URL=http://127.0.0.1:3700 npm run php:e2e
```

### Les 183 scénarios

| Groupe | Ce qui est vérifié |
|---|---|
| Vitrine | Neuf sections, l'offre de lancement, les quatre formules, les six questions, les icônes dessinées |
| La police | Déclarée, **chargée**, réellement utilisée au rendu, et servie par votre serveur |
| Le comparatif | Huit lignes, la colonne Wakabi en bandeau bleu, les deux colonnes distinctes, chaque verdict annoncé aux lecteurs d'écran |
| Sur un téléphone | **La page ne glisse pas de côté** — mesuré en tentant réellement de la déplacer, pas d'après `scrollWidth` |
| Le Studio | Le QR **visible dans l'aperçu** (mesuré sur le canevas), le jeton émis |
| Le téléchargement | Un **vrai fichier**, au format déclaré par le gabarit, sans repli intempestif |
| La page du QR | `?p=qr&jeton=…` répond, annonce le badge valide, refuse un code inventé |
| Contrôle d'entrée | Entrée validée, rescan refusé, code inconnu refusé |
| Koris | Solde crédité **au scan**, pas au téléchargement |
| Le garde-fou | Une redirection hors domaine Wakabi est refusée à un partenaire |
| Le pré-vol | Un cadre opaque ne rejoint jamais la file |
| Une soumission valide | Créée par la recette elle-même, pour qu'elle soit rejouable |
| Modération | Rapport affiché, approbation traitée, décor réellement ouvrable ensuite |
| **Gestion par l'équipe** | Ajouter, lister, filtrer, chercher, modifier, publier, supprimer — et le refus quand le titre de confirmation ne correspond pas |
| **Comptes et offres** | L'équipe crée un compte avec son rôle et son offre, une adresse déjà prise est refusée sans effacer la saisie, la personne créée se connecte et voit son quota |
| **Le quota d'une offre** | Découverte bloque la deuxième campagne active ; l'offre relevée, la campagne repart |
| **Le tableau de bord** | Cinq indicateurs avec leur variation, l'entonnoir en quatre étapes, la répartition des quatre offres |
| **Le menu du téléphone** | Replié à l'arrivée, ouvert au doigt, refermé, jamais hors de l'écran — et déplié sans clic sur grand écran |
| **La marque** | Le logo dans la barre, les portraits des témoignages |
| **Les sept gabarits** | Les sept formats proposés, l'aperçu change de forme avec le format, TikTok remonte son QR hors de la zone de la légende |
| **Le zoom libre** | Le curseur descend sous le cadrage « remplir », « Tout afficher » réduit l'image entière, le minimum est atteignable, « Remplir » revient au plein |
| **Un décor sans texte** | Accepté sans accroche ni libellé, publié, et son Studio n'affiche aucun champ à remplir |
| **La page blanche** | Son fond n'apparaît que pour elle, le format s'applique, un décor sans aucun cadre est accepté, publié, et son Studio s'ouvre — format, fond et fenêtre ronde conservés à la réouverture |
| **La fenêtre photo** | Chaque gabarit part avec l'ouverture de son cadre, un cadre 4:5 téléversé impose son format et son ouverture, « Relever sur le cadre » retrouve les mêmes valeurs après qu'on les a déréglées |
| **L'apparence** | Un décor créé sans téléverser le moindre cadre, rouvert au bon format, coin du QR et hauteur du texte conservés, retour aux réglages d'usine |
| **Le menu de l'équipe** | Sept destinations sous un seul intitulé, notifications et déconnexion hors du déroulant |
| **Le comparatif au téléphone** | Deux cartes, une par camp, huit lignes chacune, la carte Wakabi mise en avant, le tableau effacé — et l'inverse sur grand écran |
| Sécurité | Sept routes refusées à un anonyme, participant écarté de l'administration, fichiers internes non servis |
| Limitation de débit | « Trop de tentatives. Réessayez dans 15 minutes. » |
| WhatsApp | Le repli « appui long » là où le téléchargement direct est inerte |
| **Le lien partagé** | Titre, description et vignette annoncés ; la vignette se télécharge, fait 1200 × 630, est un vrai JPEG, se met en cache et ne change pas d'une demande à l'autre |
| **Le transport e-mail** | L'essai d'envoi arrive sur un vrai serveur SMTP ; l'inscription reçoit son lien ; soumettre est refusé tant que l'adresse n'est pas confirmée, accepté ensuite ; le lien ne sert qu'une fois ; l'équipe est prévenue, et la décision revient au partenaire |
| **Les sauvegardes** | L'archive s'écrit, se télécharge, est un vrai zip, n'est pas mise en cache ; un nom qui remonte l'arborescence est refusé ; le déclencheur du cron rejette une clé fausse |
| **Le QR à la caméra** | Le filtre accepte un code nu et une adresse de badge, rejette un QR étranger ; une **fausse caméra** diffuse le QR d'un badge réellement émis, l'entrée est validée, la page ne se recharge pas, le journal se met à jour |

> **La recette est rejouable.** Elle crée ses propres comptes et sa propre
> soumission à chaque exécution : pas besoin de remettre la base à zéro.

---

## Ce que la version PHP fait aussi

Tout le produit, pas un sous-ensemble :

- **vitrine complète** — héros, canaux, la boucle en trois temps, comparatif,
  témoignages, quatre formules, questions fréquentes, appel final ;
- catalogue et Studio, sans compte ;
- comptes participant, partenaire, équipe ;
- création de décor par un partenaire, en trois blocs, sans compétence graphique ;
- **gestion complète des décors par l'équipe** (§ ci-dessous) ;
- **les 7 contrôles du pré-vol**, exécutés côté serveur avec GD ;
- file de relecture, motif obligatoire au refus, notifications ;
- QR incrusté, page de badge, contrôle d'entrée, Koris ;
- **tableau de bord de pilotage** : ce qui attend une décision, cinq
  indicateurs à sept jours avec leur variation, l'entonnoir du produit,
  la répartition des offres, les décors qui portent ;
- **création de comptes par l'équipe**, avec rôle et offre, et **quota de
  campagnes** appliqué à la soumission ;
- limitation de débit, jetons anti-CSRF, sessions `httpOnly` ;
- **utilisable au doigt** : menu replié derrière un bouton, toile du Studio
  collée sous la barre, pincement pour zoomer, cibles de 44 px.

---

## La police est servie par votre serveur

**Plus Jakarta Sans** habille toute l'interface — titres compris. Une seule
famille : les titres se distinguent par la graisse et l'approche, pas par un
second caractère.

Elle est **auto-hébergée** dans `public/polices/` (128 Ko, dix fichiers WOFF2),
pas appelée chez Google. Trois raisons :

- elle s'affiche même si le CDN est lent ou bloqué ;
- elle n'ajoute pas d'aller-retour vers un tiers sur une connexion 3G ;
- aucune adresse IP de visiteur ne part chez un tiers.

> **Déclarer une police ne suffit pas.** Un `@font-face` qui pointe vers un
> fichier absent ne casse rien : le navigateur retombe en silence sur une
> police système et la page a l'air correcte. La recette vérifie donc les trois
> niveaux — déclarée sur les titres, **chargée** selon `document.fonts`, et
> **réellement utilisée au rendu** (la largeur d'un texte mesurée au canevas
> diffère de celle du repli système). Plus un quatrième contrôle : aucune
> feuille de police appelée chez un tiers.

---

## Le visiteur télécharge, il ne partage pas

Le bouton dit « Télécharger » : **il télécharge**. Ouvrir la feuille de partage
à la place est une substitution — l'invité voulait un fichier dans sa galerie,
il recevait un choix d'applications.

Le partage reste offert, en **second bouton**, et seulement quand l'appareil
sait réellement partager un fichier.

Une exception, qui n'en est pas une : dans le navigateur intégré de WhatsApp ou
d'Instagram, `<a download>` est inerte. Là, un téléchargement échouerait en
silence — l'image s'affiche avec la consigne d'appui long. La recette vérifie
les deux cas.

> **Au passage, le badge a maigri.** Le code forçait le PNG alors que le
> gabarit déclare `image/jpeg` — un défaut de mon portage, que les versions
> Next.js et démo n'avaient pas. Le format vient maintenant du gabarit :
> **921 Ko → 242 Ko**, sans toucher au rendu. Sur un forfait data facturé au
> mégaoctet, ce n'est pas un détail.

---

## L'équipe gère les décors

Un écran `?p=catalogue`, distinct de la file de relecture : celle-ci ne montre
que ce qui attend une décision, celui-là montre **tout**.

| Action | Où |
|---|---|
| **Ajouter** | Bouton « + Nouveau décor », depuis le tableau de bord ou le catalogue |
| **Lister** | Onglets par statut, recherche par titre, compteurs par onglet |
| **Modifier** | Le même formulaire que la création, pré-rempli |
| **Publier / Archiver** | Un bouton par action, selon ce que la machine à états autorise |
| **Supprimer** | Repli de confirmation, titre à retaper |

Trois décisions qui méritent d'être dites :

**Le slug ne change jamais à la modification.** Il vit dans des liens déjà
partagés et dans les QR de badges déjà téléchargés. Changer l'adresse d'un
décor publié casserait les deux.

**Supprimer exige de retaper le titre.** Un décor supprimé détruit les badges
émis pour lui : leurs QR ne mènent plus nulle part, et les entrées
correspondantes ne peuvent plus être validées. Le formulaire annonce combien de
badges seront détruits, et propose **Archiver** comme voie douce.

**La suppression efface aussi ce qui en dépend** — événements, créations,
badges, rapport de pré-vol — dans une transaction, puis le fichier du cadre
s'il n'est plus référencé. Les clés étrangères ne portent pas de cascade :
MySQL et SQLite ne l'appliquent pas de la même façon selon la configuration de
l'hébergeur, et une suppression partielle laisserait des badges pointant vers
un décor disparu.

> **L'équipe n'est pas soumise au garde-fou de redirection.** Un partenaire ne
> peut renvoyer que vers un domaine Wakabi ; l'équipe peut co-brander ailleurs.
> C'est la règle que vous aviez fixée, et elle vaut toujours.

---

## Sécurité — ce qui est en place

| | |
|---|---|
| Mots de passe | `password_hash()` — Argon2 ou bcrypt selon l'hébergeur |
| Sessions | Cookie `httpOnly`, `SameSite=Lax`, `Secure` dès que le site est en HTTPS |
| Formulaires | Jeton anti-CSRF vérifié sur **chaque** POST |
| Injection SQL | Requêtes préparées partout ; `app/depot.php` est la seule frontière avec le SQL |
| XSS | `e()` sur toute valeur venue de la base ; le gabarit est encodé avec `JSON_HEX_TAG` |
| Téléversements | PNG et WebP seulement, vérifiés par `getimagesize` — pas par l'extension. Le SVG est refusé. |
| Traversée de chemin | Le nom d'un cadre doit correspondre à `^[0-9a-f-]{36}\.(png\|webp)$` |
| Dossier de données | Hors de la racine servie, plus un `.htaccess` qui interdit l'accès |
| Auto-rétrogradation | Un administrateur ne peut ni se suspendre ni se rétrograder — sinon l'installation pourrait se retrouver sans administrateur |

> **Le `.htaccess` suppose Apache**, ce qu'utilise LWS. Sur un autre serveur,
> vérifiez que `donnees/`, `app/` et `config.php` ne sont pas servis.

---

## Les offres, et ce qu'elles autorisent

Les quatre offres de la vitrine existent dans le produit. Elles sont
déclarées **une seule fois**, dans `app/auth.php` :

| Offre | Campagnes actives | Téléchargements / mois | Prix |
|---|---|---|---|
| Découverte | 1 | 50 | gratuit |
| Impact | 3 | 500 | 5 000 FCFA |
| Croissance | 5 | 2 000 | 12 000 FCFA |
| Mouvement | illimité | illimité | 30 000 FCFA |

Ce que le produit applique aujourd'hui :

- **le quota de campagnes est bloquant**, au moment de la soumission. Un
  brouillon ne coûte rien : ce qui occupe une place, c'est une campagne en
  ligne ou en route vers la relecture ;
- **le compteur de téléchargements est indicatif**. Il est affiché à
  l'organisateur, mais **aucun badge n'est refusé à un invité** : couper un
  invité au milieu de sa création parce que l'organisateur a atteint son
  quota est une décision commerciale, pas technique. Elle vous appartient.

Une inscription publique démarre **toujours** en Découverte. Le bouton
« Choisir Impact » de la vitrine transmet l'offre visée : la personne le lit
sur le formulaire, et l'équipe reçoit une notification pour activer l'offre
après paiement. C'est l'équipe qui bascule l'offre depuis **Comptes**.

---

## Les sept gabarits

Trois formats Wakabi, trois formats de réseau, une page blanche :

| Gabarit | Canevas | Ratio | Ce qu'il vise |
|---|---|---|---|
| Bandeau bas | 1080 × 1080 | 1:1 | le format d'origine, texte sur une bande |
| Coin & voile | 1080 × 1080 | 1:1 | texte en bas à gauche, sur un voile |
| Story verticale | 1080 × 1920 | 9:16 | le statut WhatsApp |
| **Post Instagram** | 1080 × 1350 | 4:5 | le format qui prend le plus de place dans le fil |
| **Post Facebook** | 1080 × 1080 | 1:1 | carré, jamais recadré par le fil |
| **TikTok & Reels** | 1080 × 1920 | 9:16 | tout est remonté au-dessus des boutons de l'appli |
| **Page blanche** | au choix | 1:1, 4:5, 9:16, 16:9 | rien d'imposé : on compose |

Le gabarit TikTok n'est pas une story renommée. L'application pose sa légende
sur le cinquième du bas et ses boutons sur le bord droit : le bloc de texte
descend donc à 65 % de la hauteur au lieu de 83 %, et **le QR passe en haut à
gauche**, seul coin que TikTok laisse tranquille. Le filigrane, lui, reste en
bas — les trois positions permises par le contrat sont toutes en bas ; en
partage hors TikTok il est parfaitement visible.

### La fenêtre photo, et le format qui vient du cadre

Un décor disait où mettre le texte, le QR et le filigrane — mais pas **où la
photo de l'invité devait tomber**. La zone photo couvrait le canevas entier :
la photo était donc cadrée sur toute la surface alors que le cadre n'en
laisse voir qu'une bande, et le visage de l'invité tombait où il voulait.
Zoomer ou glisser ne rattrapait rien, puisque les deux gestes se
rapportaient eux aussi au canevas entier.

Deux choses viennent maintenant du cadre lui-même :

| Ce qui est relevé | Où | Comment |
|---|---|---|
| **L'ouverture** — marges, largeur, hauteur, forme | `photo_x/y/w/h`, `photo_forme` | le plus grand ensemble de pixels transparents **d'un seul tenant** |
| **Le format** | `format` | les proportions du fichier, ramenées au plus proche des quatre |

Le format compte autant que l'ouverture : le renderer étire le cadre sur tout
le canevas, donc une affiche 4:5 posée sur un décor carré **perdait un quart
de sa hauteur**, visages compris. Le pré-vol le signale désormais dans son
contrôle de format si les deux ne s'accordent pas.

Le relevé se fait à trois moments, et c'est la même mesure aux trois :

1. **À la fabrication des cadres livrés.** `npm run frames` écrit
   `public/cadres/fenetres.json` en même temps que les PNG, à partir des
   mêmes pixels : les deux ne peuvent pas se contredire. Chaque gabarit part
   donc avec la fenêtre de son cadre, sans que personne touche un curseur.
2. **Au téléversement d'un cadre**, dans le formulaire : le navigateur lit le
   canal alpha sur un canevas hors écran, pose le format, puis la fenêtre.
3. **À la demande**, avec le bouton « Relever sur le cadre » — pour un décor
   déjà enregistré, ou après avoir déplacé les curseurs.

« D'un seul tenant » n'est pas un détail : les bords adoucis d'un logo posé
dans un coin sont eux aussi transparents, et une boîte englobante de tous les
pixels percés rendrait le canevas entier — c'est-à-dire très exactement le
réglage qu'on cherche à corriger.

Une seule implémentation sert les trois moments (`src/core/photoWindow.ts`),
appelée par Node à la fabrication et par le navigateur au téléversement. Deux
mesures qui ne diraient pas la même chose vaudraient moins qu'une.

Sur l'aperçu du formulaire, **un pointillé montre la fenêtre** — deux traits,
un sombre et un clair, pour rester lisible sur un cadre noir comme sur un
cadre blanc. Il n'apparaît ni dans le Studio de l'invité, ni à l'export. Un
cadre entièrement opaque n'a pas d'ouverture à relever : le formulaire le dit
et laisse les curseurs, que le pointillé rend utilisables.

Les décors enregistrés **avant** cette version portent encore une zone photo
qui couvre tout : la migration de données (v4) pose l'ouverture sur ceux qui
sont bâtis sur un cadre fourni, dont on connaît l'ouverture au pixel près. Un
cadre téléversé n'est pas touché — personne ici ne sait ce qu'il contient, et
son auteur le relève d'un bouton.

### La page blanche

Le septième gabarit ne ressemble à rien, et c'est le but. **Aucun cadre** :
son décor tient au fond, à la forme de la fenêtre photo et au texte. Elle
ajoute quatre réglages aux autres :

| Réglage | Ce qu'il fait |
|---|---|
| **Couleur de fond** | visible partout où la photo ne va pas |

Le format et la fenêtre photo, eux, ne lui appartiennent plus en propre :
tous les gabarits les exposent, pour la raison dite plus haut. Sur la page
blanche, ils partent simplement d'un calcul plutôt que d'un cadre.

Un cadre reste possible si vous en avez un : il se pose par-dessus comme
ailleurs, et impose alors son format et son ouverture comme partout.

Les hauteurs de départ sont **calculées à partir du format**, pas écrites une
fois pour toutes : le QR est dimensionné sur la largeur, donc en paysage il
occupe un tiers de la hauteur là où il n'en prend qu'un sixième en vertical.
Changer de format remet donc la mise en page d'aplomb.

### Le cadrage de la photo

`scale` est un multiplicateur du cadrage « remplir » : 1 couvre exactement
l'emplacement, au-dessus on agrandit, **en dessous la photo devient plus
petite que son emplacement** et le fond du décor apparaît autour.

La règle était auparavant « la photo ne laisse jamais de vide », ce qui
interdisait toute échelle inférieure à 1 — une affiche panoramique ou une
capture d'écran devenaient impossibles à cadrer autrement qu'en coupant
l'essentiel. Le curseur descendait bien à 0,5, mais le cadrage refusait tout
ce qui passait sous 1 : **sa moitié gauche ne faisait rien**, et rien ne le
signalait.

Trois boutons dans le Studio :

| Bouton | Ce qu'il fait |
|---|---|
| **Tout afficher** | l'image entière tient dans l'emplacement, en un geste |
| **Remplir** | le cadrage plein, celui d'origine |
| **Miroir** | retourne la photo |

Ce qui n'a pas changé : la photo ne peut pas sortir de son emplacement, plus
grande ou plus petite que lui. Les bornes viennent du gabarit (`minScale`,
`maxScale`), plus d'une valeur écrite en dur.

Les décors créés avant cette version portaient encore `minScale: 0.5` : la
migration de schéma les rattrape, sinon la moitié basse du curseur serait
restée morte sur tout ce qui existait déjà.

### L'accroche et le champ sont facultatifs

Un décor peut n'avoir **aucun texte** : le cadre porte déjà ce qu'il y a à
dire, et l'invité n'a rien à saisir. Laissez l'accroche et le libellé vides,
les calques correspondants ne sont pas écrits — plutôt qu'écrits vides, ce
qui réserverait une zone de la mise en page pour rien.

### Tout se règle, sauf l'essentiel

Le formulaire d'un décor expose maintenant l'apparence : couleur et
alignement du texte, position et largeur du bloc, taille de l'accroche et du
prénom, coin et taille du QR, coin du filigrane. **L'aperçu suit chaque
geste** : il est construit par le serveur, avec la fonction qui enregistre le
décor, et dessiné par le renderer du Studio. Ce qu'on voit est ce qui sera
enregistré.

Trois choses ne se retirent pas, quoi qu'on règle : **le QR, le filigrane et
l'emplacement photo**. Ce sont eux qui distinguent un badge Wakabi d'une
image, et `valider_gabarit()` refuse un gabarit qui s'en passerait.

En bas d'un décor, la bande réellement libre va de x = 0,24 (fin du QR et de
sa zone de silence) à x = 0,74 (début du filigrane). Un bloc de texte qui en
sort passe sous l'un des deux, et le pré-vol le refuse à la soumission.

### Les cadres livrés

`public/cadres/` contient un cadre par gabarit, plus **deux cadres de
campagne réelle** (228 Basket Playground, en 4:5 et en 9:16) qui servent
d'exemple de ce qu'un cadre d'événement peut être. Le formulaire propose
« partez d'un cadre fourni » : de quoi créer un décor Instagram ou TikTok
**sans passer par un graphiste**, le temps que les vrais visuels arrivent.
Un fichier déposé dans ce dossier apparaît dans la liste tout seul, avec son
format lu dans l'image — et son ouverture, si `fenetres.json` la connaît ;
sinon le formulaire la relève au moment où on le choisit.

Les cadres sont dessinés en SVG dans `scripts/frames-src/` puis rasterisés
par `npm run frames`, qui **embarque la police de la charte en base64** dans
la page de rendu : sans cela, Chromium reçoit le SVG par `setContent` — dont
la base est `about:blank` — et le texte du cadre sortirait dans la police
système sans que rien ne le signale.

---

## Le logo

Le fichier officiel de la marque est servi tel quel, depuis
`public/logo.png`. Rien n'est redessiné dans le code : pour changer le logo
partout — barre, pied de page, favicone — il suffit de remplacer ce fichier.
Un `public/logo.svg` est pris en compte s'il n'y a pas de PNG.

---

## Le transport e-mail

Tout le circuit existait — décisions de modération, motifs de refus,
notifications — mais **rien ne quittait le serveur** : un partenaire ne
savait qu'en revenant sur le site si son décor avait été relu.

Cela se règle dans **Administration → Réglages** : serveur, port,
chiffrement, identifiant, adresse expéditrice. Chez LWS, ces valeurs
figurent dans l'espace client, rubrique « comptes e-mail ».

**Un bouton envoie un message d'essai**, et c'est le cœur de l'écran : un
SMTP mal réglé ne se voit nulle part ailleurs. Les messages partent en
arrière-plan, personne ne les attend, et on découvrirait la panne le jour où
un partenaire dirait n'avoir jamais reçu sa décision.

| Réglage | Ce qu'il attend |
|---|---|
| **Serveur** | `mail.votredomaine.tld`. Vide = transport éteint. |
| **Port et chiffrement** | 587 avec STARTTLS dans la quasi-totalité des cas ; 465 en TLS implicite sinon. |
| **Identifiant** | Le plus souvent l'adresse e-mail elle-même. |
| **Mot de passe** | Jamais réaffiché. Laissé vide, il est gardé tel quel. |
| **Adresse expéditrice** | Doit appartenir au domaine du serveur d'envoi — une adresse gmail part en indésirables. |

Le client SMTP est **écrit à la main**, sans Composer ni `vendor/` : la
version PHP se déploie en décompressant un zip sur un mutualisé, et une
dépendance à installer en ligne de commande annulerait cette promesse. Il
parle STARTTLS et TLS implicite, s'authentifie en LOGIN ou en PLAIN, encode
les sujets accentués, et protège les points en début de ligne — sans quoi un
texte contenant une ligne réduite à « . » couperait le message en deux.

`npx tsx scripts/verifier-courriel.ts` le met à l'épreuve d'un vrai serveur
SMTP ouvert pour l'occasion : ordre des commandes, authentification en deux
temps, en-têtes, sujet accentué décodé, point protégé. Vingt-quatre
vérifications qu'aucune recette de navigateur ne pourrait faire — rien de
tout cela n'est visible à l'écran.

### La confirmation d'adresse

Une inscription reçoit un lien valable **48 heures, à usage unique**. Tant
qu'elle n'a pas confirmé, une personne partenaire **ne peut pas soumettre**
de décor à la relecture : la décision part par courriel, et l'envoyer à une
adresse que personne n'a vérifiée reviendrait à travailler pour rien.

**Cette exigence ne s'applique que si le transport est réglé.** Réclamer une
confirmation qu'on est incapable d'envoyer ne serait pas une sécurité, mais
une porte fermée à clé sur une application qui marchait la veille. Sans
SMTP, le lien s'affiche à l'écran à l'inscription, et la soumission reste
ouverte.

Un serveur qui ne répond plus ne fait perdre qu'**une** attente par requête,
de huit secondes : le premier échec conclut pour les envois suivants de la
même page. Sans cela, approuver un décor qui prévient l'auteur et l'équipe
aurait gelé l'écran presque une minute.

---

## Le lien partagé dans WhatsApp

Un lien de décor collé dans une conversation arrivait **nu** : un titre gris,
rien d'autre. Or c'est par ce lien que tout circule — c'est la boucle qui
remplit la salle, et aucune autre optimisation ne rattrape les ouvertures
qu'un lien sans image ne provoque pas.

Chaque page porte désormais ses balises `og:` et `twitter:`, et chaque décor
a **sa vignette 1200 × 630**, composée par le serveur :

1. le décor agrandi et flouté remplit le fond — les grandes marges d'un
   badge carré dans un rectangle sont ainsi habitées par le décor lui-même,
   pas par un aplat ;
2. le badge net par-dessus, à la taille que la hauteur permet, avec la photo
   d'exemple dans **la fenêtre déclarée par le gabarit** et le masque
   qu'elle porte — ronde si elle est ronde ;
3. le QR, à la place exacte que le gabarit lui donne, et qui mène vraiment à
   la page du décor ;
4. le logo, discret, en bas à droite.

L'image est composée avec **GD**, la même extension que le pré-vol : rien à
installer. Aucun texte n'y est dessiné — GD écrirait avec FreeType, qui
réclame une police TrueType, et le projet n'embarque que des WOFF2 pour le
navigateur. Le titre voyage de toute façon dans `og:title`, et s'affiche à
côté de la vignette.

La vignette est **calculée une fois puis relue** dans `donnees/og/`. Son
adresse porte une empreinte de la date de modification du décor : changer le
cadre change l'adresse, donc l'image que WhatsApp affiche. Sans cela, son
cache garderait l'ancienne pendant des semaines.

---

## Les sauvegardes

Ce qui disparaît si le serveur brûle : les comptes, les campagnes, les
badges émis, les présences scannées — et les **cadres**, qui sont des
fichiers et que personne ne pense à sauver avec la base.

**Administration → Sauvegardes.** Une archive contient les deux, et sous la
forme qui sert vraiment à restaurer :

| Fichier | Ce qu'on en fait |
|---|---|
| `base.sql` *(MySQL)* | Se réimporte dans phpMyAdmin, dans une base vide. |
| `wakabi.sqlite` *(SQLite)* | Remplace le fichier du même nom dans `donnees/`. |
| `cadres/` | Se recopie dans `donnees/cadres/`. |
| `LISEZ-MOI.txt` | Les gestes exacts, dans l'archive. Une sauvegarde qu'on ne sait pas remettre en place n'est qu'un fichier de plus. |

`config.php` n'y est **pas** : il décrit votre serveur — identifiants de base
de données, chemins — et n'a rien à faire dans une archive qui circule.
Gardez-en une copie à part.

En SQLite, la copie passe par `VACUUM INTO` : un instantané cohérent même si
quelqu'un écrit pendant ce temps, là où un simple `copy()` peut attraper une
base à moitié écrite. En MySQL, le dump est écrit **en PHP** et non par
`mysqldump` : `exec()` est désactivée sur la plupart des mutualisés, et une
sauvegarde qui dépend d'un binaire absent ne se découvre qu'au moment de
restaurer.

L'archive est un ZIP écrit à la main, pour la même raison que le reste :
`ZipArchive` n'est pas toujours compilée sur un mutualisé. Elle s'écrit au
fil de l'eau dans un fichier, jamais assemblée en mémoire — une base de dix
mille badges avec ses cadres dépasserait la limite d'un hébergement partagé,
et tomber en panne de RAM le jour où l'on sauvegarde serait une plaisanterie.

### Tous les jours, sans y penser

L'écran donne la ligne à coller dans **Tâches Cron** de cPanel :

```
curl -s "https://votre-site/index.php?p=sauvegarde-auto&cle=…"
```

La clé remplace le mot de passe — la comparaison se fait en temps constant,
parce qu'un `===` répond d'autant plus tard que le préfixe est juste, et que
cette différence suffit à deviner une clé caractère par caractère. Les sept
dernières archives sont gardées, les plus anciennes s'effacent : sans
rotation, un disque plein empêcherait d'écrire la sauvegarde suivante,
c'est-à-dire exactement au moment où l'on en aurait besoin.

> **Une sauvegarde gardée sur le serveur qu'elle sauvegarde ne sauvegarde
> rien.** Téléchargez-la, et gardez-en une copie ailleurs.

### Elle est éprouvée en la RESTAURANT

`npx tsx scripts/verifier-sauvegarde.ts` ne se contente pas d'écrire une
archive : il la décompresse avec le vrai `unzip`, la recharge dans une base
**vide**, et compare table par table ce qu'il retrouve à ce qu'il avait mis
— accents, apostrophes, guillemets et antislashs compris. Les deux moteurs
y passent. Une archive qu'on n'a jamais remise en place n'est pas une
sauvegarde.

---

## Le QR lu à la caméra

Saisir dix caractères par personne marche — mais pas devant une file. Le
geste devient : approcher le téléphone du badge, entendre le bip, passer au
suivant. **La page ne se recharge pas** : la caméra reste ouverte et seule la
réponse change, sinon chaque invité coûterait une mise au point.

Deux décodeurs, dans cet ordre :

1. **`BarcodeDetector`**, présent depuis Chrome 83 — donc sur la quasi-
   totalité des téléphones Android. Rien à télécharger, décodage confié au
   système, et c'est le cas courant à une porte de Lomé ou d'Abidjan.
2. **jsQR**, chargé *seulement* si le premier manque — Safari,
   essentiellement. 127 Ko qu'on ne fait pas payer à ceux qui n'en ont pas
   besoin. Licence Apache-2.0, texte complet dans `public/jsqr.LICENSE.txt`.

Le formulaire de saisie reste là et fonctionne sans une ligne de
JavaScript : la caméra est un raccourci, pas une dépendance. Une douchette,
qui se comporte comme un clavier, marche toujours aussi.

Trois détails qui font la différence à une porte :

- **Un badge n'est envoyé qu'une fois** tant qu'on ne voit pas un autre code.
  La caméra lit douze fois par seconde ; sans ce garde, la deuxième réponse
  serait « déjà scanné » — un refus affiché sur un badge qu'on vient de
  valider.
- **Un bip**, synthétisé et non téléchargé : grave pour un refus. L'agent
  regarde le badge et la file, pas l'écran.
- **Un QR étranger ne part pas au serveur.** Seuls les dix caractères d'un
  badge, ou l'adresse `?p=qr&jeton=…` qu'un badge encode, sont reconnus.

> **La caméra exige HTTPS.** `getUserMedia` n'existe qu'en contexte sûr : sur
> un site en `http://`, le bouton le dit au lieu de rester muet.

La recette éprouve tout le chemin avec une **fausse caméra** : Chromium
diffuse une vidéo qui montre le QR d'un badge réellement émis, et la
validation doit apparaître sans que la page se recharge.

---

## Mettre à jour

```bash
npm run package:php
```

Téléversez le nouveau zip **par-dessus**, sans toucher à `config.php` ni au
dossier `donnees/`. Vos comptes, campagnes et badges restent.

**Le schéma ET les données se mettent à jour tout seuls.** Une installation déjà en service ne
repasse jamais par `install.php` : une colonne ajoutée après coup n'existerait
donc que chez les nouveaux. Le numéro de version du schéma est gardé dans
`donnees/version-schema.txt`, et la première requête après la mise à jour
ajoute ce qui manque. Rien à lancer à la main, rien à exécuter dans
phpMyAdmin.

---

## Ce qui reste

| Point | Sans quoi |
|---|---|
| **Les vrais cadres** | Les décors installés sont des placeholders — les vôtres se téléversent depuis le formulaire. |

Trois lignes ont quitté ce tableau : le **SMTP** se règle depuis
Administration → Réglages, les **sauvegardes** depuis Administration →
Sauvegardes, et le QR se **lit à la caméra** sur l'écran de contrôle
d'entrée. Les trois ont leur section plus bas.
