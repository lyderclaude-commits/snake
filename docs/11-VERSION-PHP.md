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

Les deux ont été vérifiés de bout en bout : **260 scénarios, 260 réussis**
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
npm run php:e2e          # 260 scénarios, dans un vrai navigateur
npm run php:verifier     # QR, gabarit, SMTP, une sauvegarde restaurée, le chiffrement push
```

Contre une base MySQL :

```bash
BASE_URL=http://127.0.0.1:3700 npm run php:e2e
```

### Les 260 scénarios

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
| **Le menu de l'équipe** | Dix destinations sous un seul intitulé, notifications et déconnexion hors du déroulant |
| **Le comparatif au téléphone** | Deux cartes, une par camp, huit lignes chacune, la carte Wakabi mise en avant, le tableau effacé — et l'inverse sur grand écran |
| Sécurité | Sept routes refusées à un anonyme, participant écarté de l'administration, fichiers internes non servis |
| Limitation de débit | « Trop de tentatives. Réessayez dans 15 minutes. » |
| WhatsApp | Le repli « appui long » là où le téléchargement direct est inerte |
| **Le lien partagé** | Titre, description et vignette annoncés ; la vignette se télécharge, fait 1200 × 630, est un vrai JPEG, se met en cache et ne change pas d'une demande à l'autre |
| **Le transport e-mail** | L'essai d'envoi arrive sur un vrai serveur SMTP ; l'inscription reçoit son lien ; soumettre est refusé tant que l'adresse n'est pas confirmée, accepté ensuite ; le lien ne sert qu'une fois ; l'équipe est prévenue, et la décision revient au partenaire |
| **Les sauvegardes** | L'archive s'écrit, se télécharge, est un vrai zip, n'est pas mise en cache ; un nom qui remonte l'arborescence est refusé ; le déclencheur du cron rejette une clé fausse |
| **Chaque offre** | Le tableau de bord annonce l'offre et ses compteurs ; ce qui n'est pas compris est montré barré avec l'offre qui l'ouvre ; les liens courts et le ciblage sont fermés en Découverte — dans le menu **et** côté serveur ; l'équipe change l'offre depuis la fiche et tout suit ; le badge est **refusé au 51ᵉ** téléchargement, l'invité sait pourquoi, l'organisateur est prévenu, et la soupape rouvre le robinet |
| **Les liens courts** | Créés, suivis en 302 vers la cible, le clic compté, un code inconnu en 404 |
| **Le QR à la caméra** | Le filtre accepte un code nu et une adresse de badge, rejette un QR étranger ; une **fausse caméra** diffuse le QR d'un badge réellement émis, l'entrée est validée, la page ne se recharge pas, le journal se met à jour |
| **L'espace profil** | Le nom et le téléphone se corrigent sans passer par l'équipe, un numéro qui n'en est pas un est refusé, le mot de passe ne change **pas** sans l'ancien, la suppression exige de recopier son adresse exactement — et le compte supprimé ne se reconnecte plus. Un compte de l'équipe n'a pas de bouton de suppression |
| **Les notifications push** | La clé publique VAPID est servie à la page, `sw.js` répond à la racine, un abonnement est enregistré, une adresse qui n'est pas en `https` est refusée, le désabonnement efface la ligne — et un organisateur **sans l'offre** trouve un écran d'explication au lieu d'un refus sec, avec l'offre qui l'ouvre |
| **Le blog** | Un brouillon renvoie **404** à un visiteur ; publié, il se lit sans compte ; intertitres, gras, listes, citation et liens sortants sont mis en forme ; **une balise saisie reste du texte et ne s'exécute pas** ; l'adresse d'un article publié n'est plus modifiable ; un organisateur n'écrit pas sur le blog |
| **Les images allégées** | Toutes les vignettes passent par le redimensionneur, proposent plusieurs tailles, annoncent leurs dimensions et se chargent en différé ; elles sont servies en **WebP**, mises en cache pour de bon ; douze décors tiennent sous 200 Ko ; quatre clés bricolées — dont `p:../../config.php` — sont refusées |

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

## Les offres, et ce qu'elles donnent vraiment

Les quatre offres de la vitrine existent dans le produit, et **chaque ligne
vendue est appliquée par le code**. Elles sont déclarées une seule fois,
dans `app/auth.php` — et la grille tarifaire de la page d'accueil en est
maintenant *déduite*, ligne à ligne. Elle était recopiée à la main : le jour
où une offre changeait dans le produit, la vitrine continuait d'en promettre
une autre.

### Trois natures de lignes, et il faut les distinguer

| Nature | Exemples | Qui l'applique |
|---|---|---|
| **Compteurs** | campagnes actives, téléchargements / mois, liens courts | Le produit. Opposables : au-delà, ça refuse. |
| **Capacités** | filigrane, Koris, redirection, statistiques, ciblage, API | Le produit, à la ligne près. |
| **Services** | diffusion à la base, Telegram et Push, article sponsorisé, account manager | **L'équipe.** Ils demandent une intervention humaine. Le produit dit qu'ils sont dus et les montre sur la fiche du compte ; prétendre qu'il les exécute serait pire que de l'écrire. |

| | Découverte | Impact | Croissance | Mouvement |
|---|:--:|:--:|:--:|:--:|
| Campagnes actives | 1 | 3 | 5 | ∞ |
| Téléchargements / mois | 50 | 500 | 2 000 | ∞ |
| Liens courts | — | 20 | 100 | ∞ |
| Badges sans filigrane | ✕ | ✓ | ✓ | ✓ |
| QR Code Koris | ✕ | ✓ | ✓ | ✓ |
| Redirection après téléchargement | ✕ | ✓ | ✓ | ✓ |
| Statistiques complètes | ✕ | ✓ | ✓ | ✓ |
| Ciblage toutes villes | ✕ | ✕ | ✓ | ✓ |
| Accès API REST | ✕ | ✕ | ✕ | ✓ |

### Où chaque ligne mord

- **Campagnes actives** — refusé à la soumission. Un brouillon ne coûte
  rien : ce qui occupe une place, c'est une campagne en ligne ou en route
  vers la relecture.
- **Téléchargements** — refusé à l'**émission du badge**, c'est-à-dire au
  geste qui coûte. Le compteur était autrefois « indicatif » : une ligne
  vendue 5 000 FCFA que rien n'appliquait. L'invité reçoit un message qui
  dit que ce n'est ni sa faute ni une panne, et l'organisateur est prévenu —
  **une seule fois par mois**, sinon le jour où sa campagne marche vraiment
  il recevrait deux cents courriels disant qu'elle marche trop bien.
- **Filigrane et redirection** — appliqués au moment de **servir** le décor,
  jamais figés à sa création. Une offre appartient au compte à l'instant
  présent : quelqu'un qui passe à Impact voit le filigrane disparaître de
  ses campagnes existantes le soir même, et celui dont l'offre retombe le
  retrouve. Un seul endroit d'application, donc : deux — un à l'écriture, un
  à la lecture — finiraient par diverger.
- **Koris** — crédités au scan seulement si l'offre de l'organisateur les
  comprend. La **présence** est comptée dans tous les cas : c'est la mesure
  qui fait la valeur du produit ; seule la récompense de l'invité se vend.
- **Ciblage** — l'option « Toutes les villes » est désactivée dans le menu,
  et **vérifiée côté serveur**. Une option désactivée est un indice visuel,
  pas une serrure.
- **Statistiques** — Découverte voit les vues et les téléchargements, ce
  qu'un générateur d'images quelconque sait déjà compter. La présence réelle
  et le taux de conversion sont ce que personne d'autre ne mesure : c'est
  donc ce qui s'achète.

### Ce que l'organisateur voit

Son tableau de bord porte **chaque ligne de son offre** : les compteurs avec
le consommé, le restant et une barre ; puis ce qui est compris ; puis ce qui
ne l'est pas, **avec l'offre qui l'ouvre**. Cacher ce qu'on n'a pas
empêcherait de le vendre ; le montrer barré, avec son prix d'entrée, est
plus honnête et plus utile.

### Ce que l'équipe voit et peut faire

**Comptes** liste désormais la consommation du mois de chaque organisateur —
le chiffre qu'on cherche quand quelqu'un écrit « mes invités ne peuvent plus
télécharger » — et signale les adresses non confirmées.

Chaque nom mène à sa **fiche** : offre, consommation face au quota, ce qui
est ouvert et ce qui est fermé, campagnes, liens courts, badges, présences,
taux de conversion, Koris. Et les leviers :

| Levier | Ce qu'il fait |
|---|---|
| **Rôle et offre** | Prend effet immédiatement, y compris sur les campagnes déjà en ligne. La personne est prévenue, et le message nomme ce qui change. |
| **Soupape de téléchargements** | S'ajoute au quota sans changer l'offre. De quoi passer un pic d'un soir sans faire monter quelqu'un d'une offre qu'il ne veut pas. |
| **Suspension** | Coupe immédiatement les sessions ouvertes. |
| **Note interne** | Invisible pour l'organisateur : « payé jusqu'en décembre », « article sponsorisé publié le 12/09 ». |

Une inscription publique démarre **toujours** en Découverte. Le bouton
« Choisir Impact » de la vitrine transmet l'offre visée : la personne le lit
sur le formulaire, et l'équipe reçoit une notification pour activer l'offre
après paiement.

---

## Les liens courts

Une adresse courte à mettre sur une affiche ou dans un message, et le nombre
de personnes qui l'ont réellement suivie. C'est une ligne d'offre : aucune
sur Découverte, 20 sur Impact, 100 sur Croissance, sans limite sur Mouvement.

Le code fait six caractères sur un alphabet qui exclut `0`/`O` et
`1`/`l`/`I` : un lien se dicte au téléphone et se recopie d'une affiche.
L'adresse est `?p=l&c=…` et non un chemin — la version PHP n'a qu'un point
d'entrée et ne suppose aucune réécriture d'URL, c'est ce qui lui permet de
se déployer en décompressant un zip. Le jour où `wkb.link` pointera ici, une
seule règle de réécriture suffira à raccourcir encore.

La cible doit être `http://` ou `https://` : sans ce filtre, un lien court
deviendrait un `javascript:` déguisé derrière un domaine de confiance — le
raccourcisseur d'URL est l'endroit exact où ce genre de chose se glisse. Le
compteur de clics est incrémenté en SQL et non lu-puis-écrit : deux
personnes qui cliquent dans la même seconde compteraient sinon pour une.

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

## L'espace profil — `?p=profil`

**Tous les rôles**, participant compris. Un compte n'appartient pas à
l'application : la personne qui l'a créé doit pouvoir corriger son nom,
changer d'adresse, refaire son mot de passe et s'en aller. Sans ces gestes,
chaque correction passe par l'équipe — donc un humain sur le chemin d'un
changement de numéro de téléphone.

**Trois formulaires distincts**, et c'est voulu :

| Formulaire | Ce qu'il demande |
|---|---|
| **Mes informations** | Nom, adresse e-mail, structure, ville, téléphone. Rien d'autre. |
| **Mon mot de passe** | Le mot de passe **actuel**, puis le nouveau. |
| **Supprimer mon compte** | De recopier son adresse e-mail, exactement. |

Les mêler obligerait à ressaisir son mot de passe pour changer une ville.

> **Changer d'adresse redemande une confirmation.** Sinon une adresse
> confirmée servirait de laissez-passer définitif : il suffirait de la
> remplacer par n'importe quelle autre pour garder le bénéfice d'une
> vérification qui ne porte plus sur rien.

**Ce qui part, et ce qui reste**, à la suppression — l'écran le dit avant de
cliquer, pas après :

- **s'en va** : le compte, ses liens courts, ses notifications, ses Koris,
  ses abonnements push ;
- **reste** : les présences déjà scannées (c'est l'historique d'événements
  réels), et les campagnes publiées, qui restent au catalogue sans
  propriétaire. Les badges que les invités ont téléchargés portent leur QR :
  les effacer les casserait tous.

**Un compte de l'équipe ne se supprime pas lui-même** — même règle que la
suspension, et pour la même raison : une installation sans administrateur ne
se répare que par la base de données.

---

## Les notifications du navigateur

Une notification qui arrive **quand le site est fermé** : c'est la seule
façon de reparler à quelqu'un qui a téléchargé un badge il y a trois
semaines. Ni compte à créer, ni application à installer.

### Ce qui a été écrit à la main, et pourquoi

Le protocole tient en trois pièces, et il n'y a **pas de Composer** ici :

1. **VAPID** (RFC 8292) — un JWT signé ES256 qui dit au service de push
   « c'est bien ce serveur ». La paire est créée à la première demande, et
   **seule sa clé privée est stockée** : la publique en est déduite à chaque
   fois. Les garder à part ouvrait la porte à un trio incohérent — une
   publique d'une paire, une privée d'une autre — et les envois seraient
   refusés sans qu'aucun message ne dise pourquoi. **Elle ne change plus
   jamais** : la clé publique est scellée dans chaque abonnement par le
   navigateur, et une nouvelle paire les invaliderait tous d'un coup.
2. **Le chiffrement** (RFC 8291, `aes128gcm`) — le contenu est chiffré **pour
   le navigateur destinataire**. Ni Google ni Mozilla ne peuvent le lire : ils
   ne transportent qu'une enveloppe close.
3. **L'envoi** — un POST vers l'adresse que le navigateur a donnée.

> **`npx tsx scripts/verifier-push.ts`** compare le chiffrement de
> `php/app/push.php`, **octet pour octet**, à une implémentation Node
> indépendante du même RFC, sur des clés et un sel fixés. Un chiffrement
> écrit à la main qu'on n'a pas vérifié est un chiffrement dont on ne sait
> rien : le service accepte le POST, répond 201, et la notification
> n'apparaît jamais — aucune erreur nulle part. 17 vérifications, dont la
> signature VAPID en `r‖s` (le piège du DER d'OpenSSL) et la stabilité de la
> paire de clés.

### S'abonner

Le bouton est dans **Mon profil**, et **sous le badge** que l'invité vient de
télécharger — c'est là qu'on accepte le plus : la personne a un lien avec
l'événement à cet instant précis. La demande de permission part d'un **clic**,
jamais du chargement de la page : les navigateurs pénalisent durablement un
site qui demande sans geste, et un visiteur surpris refuse.

Un abonnement appartient à un **navigateur**, pas à une personne : le même
invité sur son téléphone et sur son poste en a deux, et un poste sans compte
peut en porter un. C'est précisément ce qui permet de reparler à un visiteur
qui n'a jamais créé de compte.

### Envoyer — `?p=diffusion`

| Qui | Ce qu'il peut viser |
|---|---|
| **L'équipe** | Tout le monde, les organisateurs, ou une ville. |
| **Un organisateur** (offre Croissance et au-delà) | **Uniquement les invités de ses propres campagnes.** |

La distinction n'est pas de la politesse : la base d'abonnés est celle du
guide, elle s'est constituée sur la promesse de nouvelles du guide, et la
louer à qui paie une offre la brûlerait en trois messages.

- Le **lien** d'une notification d'organisateur passe le même garde-fou que
  la redirection d'un décor : un domaine Wakabi. Une notification signée
  Wakabi qui ouvre un site tiers, c'est notre nom qui sert de caution.
- Un **compteur d'envois** : un bouton de diffusion de masse sans compteur
  est une arme.
- Les abonnements **périmés** — navigateur vidé, notifications refusées — sont
  effacés automatiquement à l'envoi suivant, sur les réponses 404 et 410.

> **Il faut HTTPS.** Sans certificat, `navigator.serviceWorker` n'existe pas
> et le navigateur refuse tout abonnement. L'écran le dit plutôt que de
> laisser cliquer un bouton mort.

`sw.js` est servi **à la racine** de l'application, pas depuis `public/` : la
portée d'un service worker est celle de son dossier. Il ne fait qu'une chose,
afficher la notification et ouvrir le bon onglet — **pas de cache**, parce
qu'un service worker qui met en cache sert un jour une vieille page à
quelqu'un qui vient de mettre à jour.

---

## Le blog — `?p=blog`

Lisible par **tout le monde**, sans compte, et repris sur la page d'accueil.
Il sert deux choses à la fois : il donne au guide de quoi **se faire trouver**
par un moteur de recherche — un site dont toutes les pages sont des
formulaires ne se référence pas — et il rend concret l'**article sponsorisé**
vendu avec l'offre Mouvement.

**L'écriture est réservée à l'équipe** (`?p=blog-admin`), y compris pour un
compte Mouvement : l'article y est vendu comme un service, rédigé et publié
par la rédaction. Un champ de saisie libre sur une page publique confié à des
comptes clients, c'est le début d'une modération qu'on n'a pas les moyens de
tenir.

### Le corps est du TEXTE, et c'est une décision de sécurité

Un champ où l'on collerait du HTML serait une faille béante : quiconque
publie pourrait poser un `<script>` sur une page lue par tout le monde. Un
éditeur riche règle cela avec une liste blanche de balises — c'est-à-dire
avec un analyseur HTML complet, qu'il faudrait écrire ici, sans
bibliothèque, et maintenir.

On prend l'autre chemin : le corps est **échappé d'abord, entièrement**, et
seulement ensuite quelques marques d'écriture sont reconnues pour poser les
balises. **Aucune balise ne peut venir de la saisie** — elles sont toutes
écrites par `php/app/texte.php`.

```
## Un intertitre
### Un sous-titre
Un paragraphe. Une ligne vide en sépare deux.
- un point de liste
> une citation
**gras**, *italique*, `code`
[le texte du lien](https://wakabileguide.com)
```

C'est la syntaxe que les gens connaissent de WhatsApp. Un lien n'accepte que
`http` et `https` — sans quoi un `[cliquez](javascript:…)` deviendrait un
lien exécutable. Les liens sortants portent `rel="noopener nofollow"`.

### Le reste

- **Un brouillon n'est visible que de l'équipe**, par son adresse : une
  relecture avant publication doit être possible sans mettre l'article en
  ligne. Un visiteur reçoit un 404.
- **L'adresse est figée** une fois l'article publié. Elle a pu être partagée,
  mise en favori, indexée : la recalculer parce qu'on a corrigé une faute
  dans le titre casserait tous ces liens en silence.
- **La date de publication ne se réécrit pas** à chaque retouche : un article
  corrigé six mois plus tard remonterait en tête de liste comme s'il était
  neuf.
- Les **lectures** sont comptées une fois par visiteur et par article.

---

## Les images allégées

Le catalogue affichait une douzaine de décors dans des vignettes de 300 px —
en téléchargeant à chaque fois le **cadre entier**, un PNG de 1080 px et
140 Ko, pour l'afficher en timbre-poste. Une page coûtait près d'un
mégaoctet, sur des connexions où le mégaoctet se paie et se compte.

Trois gestes, dans cet ordre d'importance :

| Geste | Gain | Où |
|---|---|---|
| **Redimensionner** | de loin le plus gros | `?p=vignette` — 320, 640, 960 px |
| **Changer de format** | 2 à 4× | WebP, transparence comprise |
| **Recompresser à l'arrivée** | variable, parfois énorme | au téléversement, une fois |

Chaque `<img>` du catalogue et du blog porte un `srcset` : le navigateur
choisit la taille qui convient à son écran. Les dimensions sont écrites dans
la balise — **la page ne saute plus** au fur et à mesure que les images
arrivent, et quelqu'un qui lisait la deuxième ligne ne se retrouve plus à la
cinquième.

Les vignettes sont fabriquées **à la demande** puis gardées sur disque : la
première visite paie, les suivantes non. La **date de modification** de la
source entre dans la clé du cache — un cadre remplacé produit une vignette
refaite, sans qu'on ait à vider quoi que ce soit à la main.

> **`?p=vignette&f=…` ne prend jamais un chemin.** Elle prend une clé —
> `c:` un cadre téléversé, `p:` un cadre livré, `m:` une couverture
> d'article — retraduite par un motif strict. Un nom de fichier venu de la
> requête ne désigne jamais un chemin, ici pas plus qu'ailleurs.

### Ce qui est déjà en ligne

Les vignettes règlent le catalogue, mais pas le **Studio** : là, c'est le
cadre entier qui est chargé, parce que c'est lui qu'on dessine. Un décor mis
en ligne avant cette version porte donc encore son fichier d'origine.

**Administration → Réglages → Les images → « Alléger les cadres déjà en
ligne »** s'en occupe, **par lots d'une douzaine** — un mutualisé coupe un
script au bout de trente secondes, et traiter cent cadres d'un coup finirait
en page blanche à mi-parcours, avec la moitié du travail faite et aucun moyen
de savoir laquelle. L'écran dit où il en est et propose de continuer.

L'opération ne garde le résultat **que s'il est plus léger** : sur un cadre
très plat — deux aplats et un trait — le PNG gagne parfois, et garder le plus
lourd « parce que c'est le format moderne » serait absurde. En cas de pépin,
l'original est laissé intact : une image un peu lourde vaut infiniment mieux
qu'un cadre perdu.

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
| **Un domaine `wkb.link`** | Il s'achète et se fait pointer ici : le logiciel ne peut pas l'inventer. La marche à suivre est dans Administration → Réglages. |

Trois lignes ont quitté ce tableau : le **SMTP** se règle depuis
Administration → Réglages, les **sauvegardes** depuis Administration →
Sauvegardes, et le QR se **lit à la caméra** sur l'écran de contrôle
d'entrée. Les trois ont leur section plus bas.
