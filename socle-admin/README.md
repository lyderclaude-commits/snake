# Le socle d'administration

L'administration de Live Y Dream, rendue réutilisable : le même tableau de bord, les mêmes écrans et le même style que l'administration Wakabi, pour n'importe quel site PHP.

Un site se branche dessus avec **un seul fichier de configuration**, `app/definition.php`. Ce fichier fixe le nom, la couleur, les langues, les types de contenus et les réglages. L'administration se dessine d'après lui : il n'y a ni formulaire, ni écran, ni requête SQL à écrire.

## Ce que le socle fournit

- **Comptes** : premier compte administrateur à l'installation, deux rôles (administrateur, éditeur), connexion freinée contre les essais en série, profil.
- **Tableau de bord** : ce qui attend une action, ce qui a été modifié en dernier, les chiffres et une recherche dans tout le site.
- **Collections** : les contenus propres au site (services, réalisations, témoignages, logos, équipe, FAQ…).
  - Elles sont déclarées dans la définition, et le socle en tire la liste ordonnable, la fiche d'édition et la version anglaise.
  - Il en tire aussi la recherche, le rapatriement des images extérieures et le contrôle avant suppression d'une image.
- **Blog** (module `articles`) :
  - éditeur de texte avec images, brouillons, articles programmés ;
  - compteur de lectures qui ne compte pas les robots ;
  - version anglaise, et traduction automatique par **DeepL** (module `traduction`) ;
  - **import WordPress** avec rapatriement des images et redirections 301 des anciennes adresses (module `wordpress`).
- **Messages** (module `messages`) : le formulaire de contact du site, avec des champs déclarés librement.
  - Chaque demande est rangée dans l'administration et envoyée par e-mail (par `mail()` ou par un serveur SMTP réglable).
  - Elle déclenche aussi une **notification push** sur les téléphones de l'équipe (module `push`).
- **Médiathèque** : les images téléversées, vérifiées par leur contenu et réduites à 2000 px.
- **Réglages** : des onglets déclarés par le site, plus l'onglet « E-mails » du socle, et une sauvegarde de la base en un clic.

Il tourne sur un hébergement mutualisé ordinaire : PHP 8.1 ou plus récent, avec PDO SQLite, GD, cURL et OpenSSL. Il n'a besoin ni de Composer, ni de terminal, ni de serveur de base de données.

## Contenu du paquet

```
socle/        le socle, à copier tel quel dans chaque site
exemple/      un petit site complet (accueil, blog, contact) qui l'utilise
outils/       la recette automatisée et le serveur de développement
README.md     ce document
```

## Démarrer un nouveau site

1. **Copier le site exemple**, qui contient déjà un dossier `socle/`.
2. **Écrire la définition** : ouvrir `app/definition.php` et changer le nom, la couleur, le destinataire des demandes, les collections et les réglages.
3. **Écrire les pages publiques** dans `index.php` et `app/vues/`. Elles lisent les contenus avec les aides du socle (voir plus bas).
4. **Téléverser** le dossier chez l'hébergeur, puis ouvrir `https://votre-site/admin` : la première visite crée le compte administrateur.

Pour essayer sur sa machine :

```
php -S 127.0.0.1:8080 -t exemple outils/routeur-dev.php
```

Pour **brancher un site qui existe déjà** :

- ajoutez le dossier `socle/` à sa racine ;
- en tête de son `index.php`, ajoutez :

  ```php
  require __DIR__ . '/socle/demarrer.php';
  socle_router();   // sert /admin/… et /sw.js, puis rend la main à vos pages
  ```

- écrivez son `app/definition.php`.

### Organisation d'un site

```
index.php            vos pages publiques (après socle_router())
.htaccess            réécriture vers index.php ; interdit app/, donnees/, socle/ (sauf socle/assets/)
config.php           facultatif, jamais versionné : adresse publique, dossier des données
app/definition.php   la définition du site
app/vues/admin/      facultatif : une vue du même nom remplace celle du socle
donnees/             la base SQLite et les sessions (créé tout seul, interdit au web)
uploads/             les images téléversées
socle/               le socle
```

Le `.htaccess` de l'exemple est le bon point de départ. La ligne qui protège le socle est indispensable :

```
RewriteRule ^socle/(?!assets/) - [F,L]
```

Le dossier `socle/` porte aussi son propre `.htaccess` (`Require all denied`), au cas où.

## La définition du site

`app/definition.php` renvoie un tableau. Toutes les clés sont facultatives ; voici les plus utiles.

```php
return [
    'nom' => 'Mon Site',                 // nom par défaut (modifiable dans Réglages)
    'base' => 'site.sqlite',             // fichier de la base, dans donnees/
    'session' => 'monsite_session',      // nom du cookie de session
    'fuseau' => 'Africa/Lome',

    'marque' => [
        'couleur' => '#0D9488',          // remplace le bleu des boutons, liens, onglets
        'logo' => '<img src="/assets/logo.svg" alt="Mon Site">',   // sinon : le nom du site
        'courriel_logo' => 'MON SITE',   // en tête des e-mails (HTML simple)
        'icone' => 'assets/icone-192.png',   // notifications, écran d'accueil du téléphone
        'favicon' => 'assets/favicon.svg',
        'css' => 'assets/css/admin-marque.css',  // une feuille de style en plus (police du logo…)
        'nom_court' => 'MS Admin',       // sous l'icône du téléphone
    ],

    'langues' => ['fr' => 'Français'],  // ou ['fr' => …, 'en' => 'English'] pour un site bilingue
    'routes_en' => ['' => '', 'services' => 'services', 'a-propos' => 'about'],  // adresses anglaises
    'traduire' => fn(string $s) => t($s),  // le dictionnaire du site, pour les messages publics

    'modules' => ['articles' => true, 'messages' => true, 'wordpress' => false, 'traduction' => false, 'push' => true],
    'articles' => ['chemin' => 'blog', 'par_page' => 9, 'image_defaut' => 'assets/blog.jpg', 'auteur_defaut' => 'L’équipe'],
    'wordpress' => 'https://ancien-site.com/wp-json/wp/v2',   // adresse proposée à l'import

    'contact' => [ … ],        // voir « Le formulaire de contact »
    'collections' => [ … ],    // voir « Les collections »
    'reglages' => [ … ],       // voir « Les réglages »

    'fichiers' => ['app/site.php'],      // votre code, chargé juste après le socle
    'installer' => 'installer_contenus', // appelé à chaque requête : posez vos contenus de départ une fois
    'migrations' => ['monsite-1' => 'migration_monsite_1'],   // jouées une fois chacune, dans l'ordre
    'admin' => [
        'pages' => [ … ],                // des écrans à vous (voir plus bas)
        'aide_reglages' => 'Coordonnées, SEO, e-mails',
    ],
];
```

### Les collections

Une collection, c'est une liste d'éléments de même forme. Sa déclaration :

```php
'collections' => [
    'realisations' => [
        'titre' => 'Réalisations',            // dans le menu et en tête de liste
        'element' => 'réalisation', 'feminin' => true,   // « Nouvelle réalisation », « Réalisation enregistrée. »
        'aide_menu' => 'Le portfolio',
        'description' => 'Texte d’aide en tête de la liste.',
        'groupe' => 'Contenus',                // le menu déroulant où elle apparaît
        'titre_champ' => 'titre',              // le champ qui nomme un élément (par défaut : le premier)
        'tri' => [['vedette', 'desc'], ['ordre', 'asc']],   // l'ordre sur le site et dans la liste
        'visible' => ['libelle' => 'Visible sur le site', 'oui' => 'En ligne', 'non' => 'Masquée'],
        // Facultatifs, pour enrichir la liste :
        'sous_titre' => fn($e) => $e['client'],
        'apercu' => fn($e) => '<img …>',       // la vignette (sinon : le premier champ image)
        'colonnes' => ['Année' => fn($e) => e((string) $e['annee'])],
        'pastilles' => fn($e) => $e['vedette'] ? '<span class="pastille vedette">À la une</span>' : '',
        'valider' => fn(array $fr, array $en, ?int $id) => null,   // ou un message d'erreur
        'champs' => [
            'titre'    => ['libelle' => 'Titre', 'requis' => true, 'traduit' => true],
            'client'   => ['libelle' => 'Client'],
            'annee'    => ['libelle' => 'Année', 'type' => 'nombre'],
            'resume'   => ['libelle' => 'Résumé', 'type' => 'texte_long', 'max' => 400, 'traduit' => true],
            'chiffres' => ['libelle' => 'Chiffres', 'type' => 'repetition', 'lignes_max' => 4, 'section' => 'Résultats',
                           'champs' => ['valeur' => ['libelle' => 'Chiffre'], 'libelle' => ['libelle' => 'Libellé', 'traduit' => true]]],
            'vedette'  => ['libelle' => 'À la une', 'type' => 'case'],
            'image'    => ['libelle' => 'L’image', 'type' => 'image'],
        ],
    ],
],
```

Une collection apparaît dans l'administration à l'adresse `/admin/<nom>`. Le nom ne peut pas être celui d'un écran du socle (`articles`, `medias`, `messages`, `reglages`…).

**Les types de champs**

| Type | Saisie | Valeur rangée |
|---|---|---|
| `texte` (par défaut) | une ligne | texte, coupé à `max` (200) |
| `texte_long` | une zone de texte (`lignes`) | texte, coupé à `max` (2000) |
| `html` | une zone de texte | HTML filtré (balises de texte, liens, images ; ni script ni style) |
| `nombre` | un nombre (`min`) | entier |
| `case` | une case à cocher | 0 ou 1 |
| `choix` | une liste (`options` : `valeur => libellé`) | la valeur choisie |
| `note` | de 1 à 5 étoiles | entier |
| `image` | le choix dans la médiathèque (ou un téléversement) | `uploads/…`, `assets/…` ou une adresse https |
| `lien` | une adresse | une adresse `http(s)://`, sinon rien |
| `email` | une adresse e-mail | l'adresse si elle est valide |
| `etiquettes` | « a, b, c » | une liste |
| `slug` | un identifiant d'adresse (`source` : le champ dont il se déduit) | `mon-identifiant`, unique dans la collection |
| `repetition` | des lignes de sous-champs (`champs`, `lignes_max`) | une liste de lignes |

**Les options de champ**

- `libelle`, `aide` : l'aide accepte du HTML simple.
- `placeholder`, `defaut`.
- `requis` : un champ obligatoire.
- `unique` : la valeur ne peut pas se répéter dans la collection.
- `section` : le champ va dans une carte à ce titre.
- `cote` : le champ va dans la colonne de droite. C'est automatique pour les cases et les images.
- `traduit` : le champ a une version anglaise (voir plus bas).

**La version anglaise.** Si le site parle anglais, chaque champ `traduit` reçoit un jumeau dans le bloc « Version anglaise » de la fiche. Sur le site, un champ anglais vide reprend le français. Dans une répétition, seuls les sous-champs traduits changent : un chiffre comme « +340 % » reste celui du français, seul son libellé est traduit.

**Sur le site**

```php
foreach (elements('realisations') as $r) {          // visibles, triées, dans la langue de la page
    echo e($r['titre']);                              // chaque champ, à plat
    foreach ($r['chiffres'] as $c) echo e($c['valeur'] . ' ' . $c['libelle']);
}
elements('realisations', ['limite' => 3]);           // les trois premières
elements('realisations', ['tous' => true]);          // masquées comprises
element(12);                                         // une seule, par son numéro (non traduite)
elements_compter('realisations');
element_creer('realisations', ['titre' => '…'], ['titre' => '… (en)']);   // dans un installateur
```

### Les réglages

Des onglets, faits de sections, faites de champs. Les types de champs sont les mêmes que pour les collections, sauf `repetition`.

```php
'reglages' => [
    'site' => [
        'titre' => 'Le site',
        'description' => 'Texte d’aide en tête de l’onglet.',
        'sections' => [
            ['titre' => 'Coordonnées', 'aide' => '…', 'champs' => [
                'nom_site'  => ['libelle' => 'Nom du site'],
                'telephone' => ['libelle' => 'Téléphone', 'defaut' => '+228 00 00 00 00'],
                'horaires'  => ['libelle' => 'Horaires', 'traduit' => true, 'defaut' => '8h — 18h', 'defaut_en' => '8 am — 6 pm'],
                'site_url'  => ['libelle' => 'Adresse publique', 'type' => 'lien'],
            ]],
        ],
    ],
],
```

Sur le site :

- `reglage('telephone')` lit un réglage ;
- `reglage_l('horaires')` lit sa version dans la langue de la page, et retombe sur le français si l'anglais est vide.

Quelques clés ont un rôle dans le socle :

- `nom_site` : le nom affiché dans l'administration et les e-mails ;
- `site_url` : l'adresse utilisée pour les liens absolus et les e-mails.

Le socle ajoute de lui-même l'onglet **E-mails**. On y règle les copies des demandes, l'adresse d'expédition et le serveur SMTP, avec un bouton d'essai.

### Le formulaire de contact

```php
'contact' => [
    'destinataire' => 'contact@mon-site.com',   // TOUJOURS destinataire ; les réglages ajoutent des copies
    'champs' => [
        'nom'      => ['libelle' => 'Nom', 'requis' => true],
        'email'    => ['libelle' => 'E-mail', 'type' => 'email', 'requis' => true],
        'whatsapp' => ['libelle' => 'WhatsApp', 'type' => 'whatsapp'],   // ajoute « Répondre sur WhatsApp »
        'besoin'   => ['libelle' => 'Besoin', 'sujet' => true],          // résume la demande (objet de l'e-mail, notification)
        'budget'   => ['libelle' => 'Budget', 'liste' => true],          // une colonne dans la liste des messages
        'message'  => ['libelle' => 'Message', 'type' => 'texte_long'],
    ],
    'erreur_requis' => 'Indiquez votre nom et votre numéro WhatsApp.',   // sinon : « Indiquez : Nom, … »
    'limite_heure' => 8,                         // demandes par heure et par adresse IP
],
```

Les types possibles sont `texte`, `email`, `tel`, `whatsapp` et `texte_long`. `nom`, `email` et `message` ont leur propre colonne dans la base. Les autres champs sont rangés ensemble : en ajouter un ne demande aucune migration.

Dans la page Contact, une ligne suffit :

```php
['erreur' => $erreur, 'envoye' => $envoye, 'v' => $v] = contact_traiter('contact');
```

Le formulaire envoie les champs déclarés sous leur nom (`<input name="whatsapp">`), avec `csrf_champ()` et un champ piège caché `site_web`. Les robots le remplissent, et leur demande est alors ignorée en silence. Le visiteur est redirigé vers `contact?envoye=1` ; l'e-mail et la notification partent ensuite, sans le faire attendre.

### Les écrans à vous

Pour un besoin qu'aucune collection ne couvre, déclarez un écran :

```php
'admin' => ['pages' => [
    'statistiques' => [
        'titre' => 'Statistiques', 'aide' => 'Les visites', 'groupe' => 'Système', 'admin' => true,
        'action' => function (string $r1, string $r2, bool $post, array $me): never {
            vue_admin('statistiques', ['chiffres' => …]);   // app/vues/admin/statistiques.php
        },
    ],
]],
```

Toute vue du socle peut aussi être remplacée par un fichier du même nom dans `app/vues/admin/`.

### Les migrations

Le socle crée ses tables à la première visite. Chaque migration (celles du socle, puis celles de la définition) est jouée **une seule fois**, notée par son nom dans la table `migrations`, dans une transaction. Une migration reçoit l'objet PDO :

```php
function migration_monsite_1(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS …');
    colonne_ajouter($pdo, 'articles', 'sous_titre', "TEXT NOT NULL DEFAULT ''");
}
```

## Les aides pour les pages publiques

| Aide | Usage |
|---|---|
| `url('blog')`, `url_absolue('blog')` | une adresse du site (traduite sur les pages anglaises) |
| `actif('assets/site.css')` | un fichier, avec sa date pour vider le cache du navigateur |
| `image_url($chemin)` | l'adresse d'une image rangée |
| `e($texte)` | le texte échappé pour le HTML |
| `reglage('cle')`, `reglage_l('cle')` | un réglage |
| `elements('nom')` | une collection |
| `articles_publies($page)`, `article_par_slug($slug)`, `article_l($a, 'titre')`, `article_image($a)`, `article_extrait($a)`, `contenu_afficher($a['contenu'])` | le blog |
| `article_lecture($slug)` | compte une lecture (`POST blog/{slug}/vue`, voir l'exemple) |
| `contact_traiter('contact')`, `contact_champs()`, `csrf_champ()` | le formulaire de contact |
| `wp_rediriger_ancienne_adresse($chemin)` | les anciennes adresses WordPress, en 301 |
| `langue()`, `utilisateur_courant_si_session()`, `date_fr()`, `tronquer()` | divers |

## Le site bilingue

- Déclarez `'langues' => ['fr' => 'Français', 'en' => 'English']` et les adresses anglaises dans `routes_en`.
- Votre routeur reconnaît le préfixe `/en` et appelle `langue('en')` ; Live Y Dream en est l'exemple complet.
- À partir de là, `url()` écrit les adresses anglaises, `elements()` et `reglage_l()` renvoient l'anglais, et les articles affichent leur version anglaise.
- Dans l'administration, chaque champ `traduit` a son jumeau anglais.
- Avec le module `traduction`, DeepL traduit les articles : *Système → Traduction*.

## Vérifier

```
node outils/recette.mjs
```

La recette joue 28 scénarios dans un vrai navigateur (Chromium, avec `playwright-core`), sur une copie neuve du site exemple. Elle couvre :

- l'installation, les collections et les réglages ;
- le contact, avec l'e-mail capturé ;
- le blog, la sécurité et l'affichage sur téléphone.

## Mettre à jour le socle d'un site

Remplacez le dossier `socle/` du site par le nouveau, sans rien d'autre à toucher. La définition, les données et les images restent en place, et les nouvelles migrations du socle se jouent toutes seules à la visite suivante.
