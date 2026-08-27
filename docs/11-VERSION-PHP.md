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

Les deux ont été vérifiés de bout en bout : **117 scénarios, 117 réussis**
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
| Poids du JavaScript | 128 Ko | **11 Ko** |

Les 11 Ko ne sont pas une coquetterie : la contrainte C1 est un Android
d'entrée de gamme sur données mobiles.

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
npm run php:e2e          # 117 scénarios, dans un vrai navigateur
npm run php:verifier     # QR et gabarit contre les implémentations d'origine
```

Contre une base MySQL :

```bash
BASE_URL=http://127.0.0.1:3700 npm run php:e2e
```

### Les 117 scénarios

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
| **La page blanche** | Ses réglages n'apparaissent que pour elle, le format s'applique, un décor sans aucun cadre est accepté, publié, et son Studio s'ouvre — format, fond et fenêtre ronde conservés à la réouverture |
| **L'apparence** | Un décor créé sans téléverser le moindre cadre, rouvert au bon format, coin du QR et hauteur du texte conservés, retour aux réglages d'usine |
| **Le menu de l'équipe** | Cinq destinations sous un seul intitulé, notifications et déconnexion hors du déroulant |
| **Le comparatif au téléphone** | Deux cartes, une par camp, huit lignes chacune, la carte Wakabi mise en avant, le tableau effacé — et l'inverse sur grand écran |
| Sécurité | Sept routes refusées à un anonyme, participant écarté de l'administration, fichiers internes non servis |
| Limitation de débit | « Trop de tentatives. Réessayez dans 15 minutes. » |
| WhatsApp | Le repli « appui long » là où le téléchargement direct est inerte |

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

### La page blanche

Le septième gabarit ne ressemble à rien, et c'est le but. **Aucun cadre** :
son décor tient au fond, à la forme de la fenêtre photo et au texte. Elle
ajoute quatre réglages aux autres :

| Réglage | Ce qu'il fait |
|---|---|
| **Format** | 1:1, 4:5, 9:16 ou 16:9 — les gabarits nommés imposent le leur, celle-ci le demande |
| **Couleur de fond** | visible partout où la photo ne va pas |
| **Forme de la fenêtre photo** | rectangle, coins arrondis ou cercle |
| **Fenêtre photo** | marges, largeur et hauteur |

Un cadre reste possible si vous en avez un : il se pose par-dessus comme
ailleurs.

Les hauteurs de départ sont **calculées à partir du format**, pas écrites une
fois pour toutes : le QR est dimensionné sur la largeur, donc en paysage il
occupe un tiers de la hauteur là où il n'en prend qu'un sixième en vertical.
Changer de format remet donc la mise en page d'aplomb.

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

### Six cadres livrés

`public/cadres/` contient un cadre par gabarit. Le formulaire propose
« partez d'un cadre fourni » : de quoi créer un décor Instagram ou TikTok
**sans passer par un graphiste**, le temps que les vrais visuels arrivent.
Un fichier déposé dans ce dossier apparaît dans la liste tout seul, avec son
format lu dans l'image.

---

## Le logo

Le fichier officiel de la marque est servi tel quel, depuis
`public/logo.png`. Rien n'est redessiné dans le code : pour changer le logo
partout — barre, pied de page, favicone — il suffit de remplacer ce fichier.
Un `public/logo.svg` est pris en compte s'il n'y a pas de PNG.

---

## Mettre à jour

```bash
npm run package:php
```

Téléversez le nouveau zip **par-dessus**, sans toucher à `config.php` ni au
dossier `donnees/`. Vos comptes, campagnes et badges restent.

**Le schéma se met à jour tout seul.** Une installation déjà en service ne
repasse jamais par `install.php` : une colonne ajoutée après coup n'existerait
donc que chez les nouveaux. Le numéro de version du schéma est gardé dans
`donnees/version-schema.txt`, et la première requête après la mise à jour
ajoute ce qui manque. Rien à lancer à la main, rien à exécuter dans
phpMyAdmin.

---

## Ce qui reste

| Point | Sans quoi |
|---|---|
| **SMTP** | Aucun e-mail n'est envoyé. Les notifications sont dans l'application, pas dans la boîte de réception. |
| **Les vrais cadres** | Les trois décors installés sont des placeholders. |
| **Lecture caméra du QR** | L'agent d'entrée saisit le code. Ça marche, ça ne tient pas une file d'attente. |
| **Sauvegardes** | Sauvegardez `donnees/` (ou exportez la base MySQL depuis cPanel), et sortez la copie du serveur. |
