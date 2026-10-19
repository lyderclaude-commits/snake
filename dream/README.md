# Live Y Dream — le site de production

Le site de l'agence (livré en `dream.zip`), passé d'une maquette HTML dont le « CMS »
vivait dans le `localStorage` du navigateur à un vrai site **PHP + SQLite** : contenu
rendu par le serveur, administration réelle, articles importés de l'ancien WordPress
avec **toutes leurs images** copiées sur le serveur.

> Recette : **62 scénarios, 62 réussis, aucune erreur console** — `node dream/outils/recette.mjs`

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

La mise en page publique est **celle de la maquette** (`css/global.css` et les styles de
chaque page, repris tels quels). L'administration, elle, est **refaite dans le langage
visuel de l'administration Wakabi** (`php/public/wakabi.css`) : même barre, mêmes cartes,
tableaux, pastilles, boutons, même tableau de bord (*À faire / Les chiffres*, *Reprendre*,
*Où aller*), même éditeur. Seul le logo change ; l'accent se règle dans `--primary` en tête
de `site/assets/css/admin.css`.

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
6. Réglages → Référencement : indiquez `https://liveydream.com`. Réglages → Pages légales :
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
`site/app/courriel.php` (constante `CONTACT_EMAIL`, ou `contact_email` dans `config.php`), pas
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
  index.php           routeur unique (site, redirections WordPress, sitemap, robots)
  app/                code (interdit au web) : bootstrap, html (tamis), medias, contenu,
                      wordpress (import), site, admin, vues/site, vues/admin
  assets/             css (site.css = global.css d'origine, admin.css), js, polices, images
  uploads/            images téléversées et importées (PHP n'y s'exécute jamais)
  donnees/            base SQLite et sessions (interdit au web)
outils/               routeur de développement, faux WordPress, faux sendmail, recette, paquet
```

Sauvegarde : Réglages → *Télécharger une sauvegarde* (la base). Pensez aussi à garder une
copie de `uploads/`.
