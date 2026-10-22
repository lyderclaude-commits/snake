# Live Y Dream — le site de production

Le site de l'agence (livré en `dream.zip`), passé d'une maquette HTML dont le « CMS »
vivait dans le `localStorage` du navigateur à un vrai site **PHP + SQLite** : contenu
rendu par le serveur, administration réelle, articles importés de l'ancien WordPress
avec **toutes leurs images** copiées sur le serveur.

> Recette : **101 scénarios, 101 réussis, aucune erreur console** — `node dream/outils/recette.mjs`

## Ce qui a changé par rapport à la maquette

| Maquette livrée | En production |
|---|---|
| Articles, campagnes et réglages dans le `localStorage` : chaque visiteur voyait les données **de démonstration**, l'équipe modifiait seulement **son propre navigateur** | Base SQLite sur le serveur, la même pour tous |
| Mot de passe admin `lyd2024admin` écrit dans le JavaScript et affiché sur la page de connexion | Comptes hachés (`password_hash`), sessions, jeton anti-falsification, frein après 8 essais |
| Articles ouverts dans une fenêtre : aucune adresse, rien qu'un moteur puisse lire | Une page par article (`/blog/mon-article`), pagination, catégories, tags, recherche, flux RSS, plan du site, données structurées |
| Le formulaire de contact affichait « Message envoyé ! » **sans rien envoyer** | Demandes enregistrées dans *Messages* **et envoyées par e-mail à `contact@liveydream.com`** (destinataire fixe, copies possibles), champ piège anti-robots |
| Images chargées depuis `dream.loremcommunication.com`, icônes depuis icons8, polices depuis Google | Tout est servi par le site : photos recompressées (3,7 Mo → 0,7 Mo), pictogrammes dessinés en SVG, polices auto-hébergées |
| Liens `mentions-legales.html` et `confidentialite.html` en 404 | Pages présentes, texte réglable dans l'administration |
| Chiffres clés à « 0 » sans JavaScript | Chiffres écrits dans la page, animés ensuite |
| Une seule langue | **Français et anglais** (`/en/…`), sélecteur FR · EN, langue retenue |
| Services, témoignages et logos clients écrits dans le HTML | Modifiables dans l'administration, en deux langues |

La mise en page publique est **celle de la maquette** (`css/global.css` et les styles de
chaque page, repris tels quels). L'administration, elle, est **refaite dans le langage
visuel de l'administration Wakabi** (`php/public/wakabi.css`) : même barre, mêmes cartes,
tableaux, pastilles, boutons, même tableau de bord (*À faire / Les chiffres*, *Reprendre*,
*Où aller*), même éditeur. Seul le logo change.

## L'administration vient du socle commun

L'administration n'est plus propre à ce site : c'est le **socle d'administration**
(`socle-admin/` à la racine du dépôt), partagé avec les autres sites. Il est copié dans
`site/socle/` (un lien symbolique dans le dépôt, un vrai dossier dans le paquet). Live Y Dream
s'y décrit dans un seul fichier, **`site/app/definition.php`** :

- son nom, son logo (`assets/css/admin-marque.css` pour la police Bebas Neue), ses langues ;
- ses **collections** : campagnes, services, témoignages, logos clients — chacune déclarée
  champ par champ ; le socle en tire les listes, les fiches et la version anglaise ;
- ses **réglages** (L'agence, Accueil, Référencement, Pages légales ; l'onglet E-mails vient du socle) ;
- son formulaire de contact (entreprise, WhatsApp, besoin, budget) et son destinataire fixe.

À la première visite après la mise à jour, `site/app/migrations.php` range les données
existantes à la façon du socle : campagnes, services, témoignages et logos rejoignent la
table commune `elements` (les campagnes gardent leur numéro, donc les liens
`/campagnes#campagne-3` déjà partagés), les champs propres aux demandes rejoignent leur
colonne `donnees`. Les anciennes tables restent, renommées `archive_…`. Rien à faire à la main.

Pour corriger ou améliorer l'administration : modifier `socle-admin/socle/`, puis refaire les
paquets des sites qui l'utilisent. Le socle est documenté dans `socle-admin/README.md`.

## Mettre en ligne (hébergement PHP mutualisé)

Il faut PHP 8.1+ avec PDO SQLite et GD (présents chez LWS, o2switch, Hostinger…), Apache
avec `mod_rewrite`. **Aucun terminal, aucun Composer.**

1. `dream/outils/paquet.sh` produit `dream-site.zip` (1,3 Mo). Décompressez-le **à la racine
   du domaine** (ou d'un sous-domaine de test, par exemple `nouveau.liveydream.com`).
2. Vérifiez que `donnees/` et `uploads/` sont accessibles en écriture (755 ou 775).
3. Ouvrez `/admin` : créez le compte administrateur.
4. Vous arrivez sur **Import WordPress** → *Tester la connexion* → *Importer les articles*.
   Puis *Rapatrier les images* pour les photos de démonstration des campagnes.
5. Réglages → L’agence → *Les e-mails du formulaire de contact* : renseignez le serveur SMTP
   de la boîte `contact@liveydream.com` (espace client de l’hébergeur), puis *Enregistrer et
   envoyer un e-mail de test*. Voir plus bas.
6. Système → **Traduction** : collez la clé DeepL, puis *Traduire tous les articles* (voir plus bas).
7. Réglages → Référencement : indiquez `https://liveydream.com`. Réglages → Pages légales :
   complétez l'hébergeur et le responsable de publication.

### L'ordre compte : importer AVANT de basculer le domaine

Le WordPress répond aujourd'hui sur `liveydream.com`. Une fois le domaine servi par ce
nouveau site, l'ancienne API **n'existe plus**. Deux façons de faire :

- installer d'abord le nouveau site sur un sous-domaine de test, importer, vérifier, puis
  déplacer les fichiers (avec `donnees/` et `uploads/`) vers le domaine principal ;
- ou importer depuis le serveur final avant de remplacer l'ancien WordPress.

Après la bascule, les anciennes adresses des articles (`/mon-article/`,
`/2024/03/mon-article/`, `/?p=123`, `/category/…`, `/feed/`) redirigent en **301** vers
`/blog/mon-article` : les liens partagés et ce que Google connaît continuent de marcher.

### Ce que fait l'import

- Tous les articles publiés, par lots de 10 (un mutualisé coupe les scripts longs), via
  `wp-json/wp/v2/posts?_embed` : titre, texte, chapô, date, auteur, catégorie, tags.
- **Toutes les images** — image mise en avant, images du texte (la plus grande version du
  `srcset`, pas la vignette), images liées — téléchargées dans `uploads/`, dédoublonnées,
  réduites à 2000 px au plus. Une image illisible est signalée, l'article est importé quand même.
- Le HTML passe une liste blanche : ni script, ni `onclick`, ni lien `javascript:` ; les
  vidéos YouTube et Vimeo restent.
- Relancer l'import met à jour sans dupliquer, **sauf** les articles retouchés depuis
  l'administration (une case permet de forcer).

### Les e-mails des demandes de contact

Chaque demande est envoyée à **`contact@liveydream.com`**, toujours : l’adresse est fixée dans
`site/app/definition.php` (`contact.destinataire`, ou `contact_email` dans `config.php`), pas
dans un réglage qu’on pourrait vider. Les réglages permettent seulement d’ajouter des copies.
L’e-mail reprend toute la demande, avec des boutons « Répondre sur WhatsApp » et « Ouvrir
dans l’administration » ; *Répondre* dans la messagerie répond directement au client.

- **Sans réglage**, l’envoi passe par `mail()`, le serveur de l’hébergeur. Ça marche, mais ces
  messages tombent souvent dans les indésirables.
- **Avec un serveur SMTP** (recommandé) — souvent `mail.liveydream.com`, port 587 STARTTLS,
  identifiant et mot de passe de la boîte contact@ — les messages partent signés par le vrai
  serveur du domaine.

Le résultat de chaque envoi est noté sur la demande (*Messages* signale « E-mail non parti »),
et un bouton *Renvoyer par e-mail* permet de réessayer. Le visiteur, lui, voit toujours sa
demande acceptée : elle est enregistrée avant l’envoi.

## Le site en anglais

Les pages anglaises vivent sous `/en` avec leurs propres mots : `/en/services`,
`/en/campaigns`, `/en/blog`, `/en/about`, `/en/contact`, `/en/legal-notice`, `/en/privacy`.
Le sélecteur **FR · EN** de la barre (et du menu mobile) mène à la même page dans l'autre
langue, et le choix est retenu. Un premier visiteur dont le navigateur est en anglais
arrive sur `/en` ; Google, lui, voit chaque langue à son adresse, reliée à sa jumelle par
`hreflang` et dans le plan du site.

- **Les textes écrits dans les pages** (titres, paragraphes de Services, À propos, Contact…)
  sont traduits par un dictionnaire : `site/app/langues/en.php`. Une phrase qui n'y est pas
  s'affiche en français : rien ne casse, et on la complète là.
- **Les contenus de l'administration** ont leur champ anglais : articles (bloc « Version
  anglaise »), campagnes, services, témoignages, réglages (titre de l'accueil, libellés des
  chiffres, horaires, description, pages légales). Un champ anglais vide reprend le français.
- **Un article non traduit** s'affiche en français sur le site anglais, avec la mention
  « This article is only available in French », et renvoie Google à l'original.
- Les demandes envoyées depuis `/en/contact` sont marquées **EN** dans *Messages* et dans
  l'e-mail : on sait qu'il faut répondre en anglais.

## La traduction automatique (DeepL)

Système → **Traduction**. Il faut une clé « DeepL API Free » (deepl.com/pro-api, gratuite
jusqu'à 500 000 caractères par mois, soit 60 à 100 articles) ; elle est gardée dans les
réglages, jamais réaffichée.

- **Traduire tous les articles** : les articles en ligne sans version anglaise, trois par
  trois. Les versions anglaises existantes ne sont pas touchées. Quota épuisé ou clé
  refusée : la traduction s'arrête proprement et reprend où elle s'était arrêtée.
- **Dans l'éditeur**, le bouton *Traduire automatiquement* (bloc « Version anglaise »)
  traduit le titre, le chapô et le texte tels qu'ils sont dans le formulaire ; rien n'est
  enregistré avant *Enregistrer*.
- **À la publication** (case à cocher) : chaque article publié sans anglais, depuis
  l'éditeur ou l'import WordPress, est traduit aussitôt. Une panne de DeepL ne bloque
  jamais la publication : l'article paraît en français et le lot le rattrapera.
- DeepL traduit le **HTML** : titres, listes, gras, images et liens ressortent intacts. Les
  très longs articles sont découpés entre deux blocs puis recollés.
- Une traduction automatique reste marquée **« Auto · à relire »** (liste des articles,
  tableau de bord) jusqu'à ce que quelqu'un coche *J'ai relu la traduction anglaise*.
  Elle est publiée dès le départ : la mention sert à savoir lesquelles relire.
- Anglais britannique par défaut ; américain au choix.

## L'accueil réglable

*Contenus* → **Services**, **Témoignages**, **Logos clients** :

- un **service** alimente sa carte de l'accueil, l'introduction et la section détaillée de
  la page Services (intro, livrables, prix, image) et le pied de page ;
- un **témoignage** : texte, fonction, entreprise (son initiale fait la pastille), étoiles ;
- un **logo client** se choisit dans la médiathèque ; sans logo, le nom s'affiche ; un lien
  vers le site du client est possible.

Tout se range par ordre, se masque sans se supprimer. L'accueil montre **5 campagnes** du
portfolio (les campagnes « à la une » d'abord).

## Les lectures d'articles

Le compteur *Lectures* (liste des articles, tableau de bord) est alimenté par la page
elle-même, une seconde après son affichage : une lecture par onglet et par article. Les
robots et les aperçus de liens (Google, Facebook, WhatsApp) ne comptent pas ; l'équipe
connectée, si — c'était la raison pour laquelle le compteur semblait figé.

## Les notifications push

Chaque membre de l'équipe active les notifications **sur chacun de ses appareils** :
*Mon profil* → *Notifications* → *Activer sur cet appareil*. À chaque nouvelle demande de
contact, une notification « Nouvelle demande : … » arrive, même site fermé, et ouvre la
demande dans l'administration. Un bouton envoie une notification de test.

- Il faut le site **en HTTPS** (c'est le cas en production avec le certificat).
- Sur **iPhone/iPad** : ajouter d'abord l'administration à l'écran d'accueil (Partager →
  *Sur l'écran d'accueil*), puis activer depuis cette icône (iOS 16.4+).
- Le protocole (VAPID + chiffrement aes128gcm) est celui de l'administration Wakabi, en PHP
  pur avec OpenSSL ; la recette déchiffre réellement chaque notification envoyée.

## Développer, tester

```bash
php -S 127.0.0.1:8080 -t dream/site dream/outils/routeur-dev.php   # le site
php -S 127.0.0.1:8090 dream/outils/faux-wordpress/index.php          # un faux WordPress (23 articles)
node dream/outils/recette.mjs                                        # la recette, sur une copie isolée
```

`routeur-dev.php` rejoue le `.htaccess` pour le serveur intégré de PHP. Le faux WordPress
reproduit l'API (pagination, en-têtes `X-WP-Total`, `_embed`, contenu Gutenberg avec
`srcset`, une vidéo, une image morte et quelques pièges XSS).

## Organisation

```
site/                 ← ce qui part chez l'hébergeur
  index.php           routeur des pages publiques (FR/EN, redirections WordPress, sitemap, robots)
  socle/              le socle d'administration (lien vers ../../socle-admin/socle)
  app/                code (interdit au web) : definition (le site pour le socle), migrations,
                      site, langue + langues/en.php, contenu (campagnes), accueil, vues/site
  assets/             css (site.css = global.css d'origine, admin-marque.css), js, polices, images
  uploads/            images téléversées et importées (PHP n'y s'exécute jamais)
  donnees/            base SQLite et sessions (interdit au web)
outils/               routeur de développement, faux WordPress, faux sendmail, recette, paquet
                      (la recette monte aussi un faux SMTP, un faux service de push et un faux DeepL)
```

Sauvegarde : Réglages → *Télécharger une sauvegarde* (la base). Pensez aussi à garder une
copie de `uploads/`.
