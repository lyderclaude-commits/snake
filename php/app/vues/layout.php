<?php
/** Gabarit HTML commun. $titre, $contenu et $me sont fournis par le routeur. */
$me = $me ?? utilisateur_courant();
$nonlues = $me ? notifications_non_lues($me['id']) : 0;
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#2563EB">
<title><?= e($titre ?? 'Wakabi Boost') ?></title>
<?php
/**
 * Les balises de partage et de référencement.
 *
 * Un lien collé dans WhatsApp arrivait nu : le domaine, l'adresse en
 * toutes lettres, rien d'autre. Or c'est par ce lien que tout circule —
 * c'est la boucle qui remplit la salle.
 *
 * La cause tenait en une ligne : l'adresse canonique était RECONSTRUITE en
 * ne gardant que `p` et `slug`, alors qu'un article s'identifie par `a`.
 * Chaque article annonçait donc l'index du blog comme sa propre adresse.
 * `url_canonique()` procède désormais par soustraction — voir `seo.php`.
 */
$_page = (string) ($_GET['p'] ?? 'accueil');
$_seo = seo_reglages();
$_desc = trim((string) ($description ?? '')) ?: $_seo['seo_description'];
$_ogt = $og_titre ?? ($titre ?? $_seo['seo_nom_site']);
$_img = seo_image($og_image ?? null);
$_ogu = $canonique ?? url_canonique();
$_type = $og_type ?? 'website';
/**
 * Une page peut se retirer elle-même des moteurs.
 *
 * `seo_indexable()` raisonne par ROUTE ; certaines pages n'engagent
 * qu'elles-mêmes — un décor archivé garde son aperçu de partage intact,
 * mais n'a plus à figurer dans les résultats. La route, elle, reste
 * indexable pour les décors en ligne.
 */
$_index = ($indexable ?? true) && seo_indexable($_page);
?>
<meta name="description" content="<?= e($_desc) ?>">
<link rel="canonical" href="<?= e($_ogu) ?>">
<?php
/* `noindex` sur tout ce qui n'est pas une page publique et stable. Ce
   n'est pas une protection — ces écrans demandent une session — mais un
   écran d'administration dans les résultats encombre le nom du site de
   pages que personne ne peut ouvrir. */
?>
<?php if (!$_index): ?>
<meta name="robots" content="noindex, follow">
<?php endif; ?>
<meta property="og:type" content="<?= e($_type) ?>">
<meta property="og:site_name" content="<?= e($_seo['seo_nom_site']) ?>">
<meta property="og:locale" content="fr_FR">
<meta property="og:title" content="<?= e($_ogt) ?>">
<meta property="og:description" content="<?= e($_desc) ?>">
<meta property="og:url" content="<?= e($_ogu) ?>">
<meta property="og:image" content="<?= e($_img['url']) ?>">
<meta property="og:image:secure_url" content="<?= e($_img['url']) ?>">
<meta property="og:image:type" content="<?= e($_img['type']) ?>">
<meta property="og:image:width" content="<?= (int) $_img['largeur'] ?>">
<meta property="og:image:height" content="<?= (int) $_img['hauteur'] ?>">
<meta property="og:image:alt" content="<?= e($_ogt) ?>">
<?php
/* Un article dit QUAND il a paru et QUI l'a écrit : c'est ce qui fait
   apparaître une date dans les résultats, et ce qui distingue un texte
   daté d'une page de service. */
?>
<?php foreach (($og_article ?? []) as $_cle => $_val): ?>
<meta property="article:<?= e($_cle) ?>" content="<?= e((string) $_val) ?>">
<?php endforeach; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($_ogt) ?>">
<meta name="twitter:description" content="<?= e($_desc) ?>">
<meta name="twitter:image" content="<?= e($_img['url']) ?>">
<?php if ($_seo['seo_verif_google'] !== ''): ?>
<meta name="google-site-verification" content="<?= e($_seo['seo_verif_google']) ?>">
<?php endif; ?>
<?php if ($_seo['seo_verif_bing'] !== ''): ?>
<meta name="msvalidate.01" content="<?= e($_seo['seo_verif_bing']) ?>">
<?php endif; ?>
<?php
/**
 * Les données structurées, sur les pages publiques uniquement.
 *
 * Sur un écran d'administration elles ne servent à rien et pèsent sur
 * chaque affichage. Le graphe est écrit d'un bloc : Google préfère un
 * `@graph` unique à cinq balises `<script>` qui se contredisent.
 */
$_graphe = array_values(array_filter([
    jsonld_organisation(),
    $jsonld ?? null,
    ($fil ?? null) ? jsonld_fil($fil) : null,
]));
?>
<?php if ($_index && $_graphe): ?>
<script type="application/ld+json"><?= jsonld(['@context' => 'https://schema.org', '@graph' => $_graphe]) ?></script>
<?php endif; ?>
<?php
/**
 * Le favicon, RÉDUIT lui aussi.
 *
 * Il pointait sur `logo.png` : cent kilooctets téléchargés pour dessiner
 * seize pixels de carré dans un onglet, à chaque page. Il passe par la
 * route des vignettes, comme le logo de la barre et comme chaque décor.
 *
 * WebP sans repli, et c'est cohérent : toutes les images du produit sont
 * déjà servies en WebP. Un navigateur qui ne le lit pas ne voit aucune
 * vignette de décor — ce n'est pas le favicon qui lui manquerait d'abord.
 */
$ico = logo_reduit(64);
$icoType = 'image/webp';
if ($ico === null && ($f = logo_fichier()) !== null) {
    $ico = $f['url'];
    $icoType = $f['type'];
}
?>
<?php if ($ico): ?><link rel="icon" href="<?= e($ico) ?>" type="<?= e($icoType) ?>" sizes="64x64"><?php endif; ?>
<?php
/**
 * UNE feuille par page, jamais les deux.
 *
 * Les pages venues de wakabileguide.com gardent la leur : le guide pose
 * des règles sur des éléments NUS — body, a, button, input — et Boost
 * aussi. Chargées ensemble, elles se battraient sur chaque bouton et
 * chaque champ du site, et le perdant changerait selon l'ordre des
 * balises. Séparées, chaque page rend exactement comme avant la fusion.
 *
 * `entete.css`, lui, est servi des DEUX côtés : c'est le header commun,
 * et c'est la seule pièce qui traverse la frontière. Il porte pour cette
 * raison ses propres couleurs et le préfixe `wk-`.
 */
?>
<link rel="stylesheet" href="<?= e(actif(page_du_guide($_page) ? 'public/guide.css' : 'public/wakabi.css')) ?>">
<link rel="stylesheet" href="<?= e(actif('public/entete.css')) ?>">
<?php /* L'apparition au défilement des pages du guide. Le contenu se lit
         sans lui : voir `public/guide.js` et la règle `html.js`. */ ?>
<?php if (page_du_guide($_page)): ?>
<script src="<?= e(actif('public/guide.js')) ?>" defer></script>
<?php endif; ?>
<?php /* Les menus se referment quand on clique ailleurs. Servi partout,
         comme `entete.css` : le header est le même des deux côtés. */ ?>
<script src="<?= e(actif('public/entete.js')) ?>" defer></script>
</head>
<body>

<?php
/**
 * DEUX barres, et non une seule qui se réarrange.
 *
 * La vitrine et l'atelier sont deux métiers. Le site public garde le même
 * menu pour tout le monde — un menu qui change à la connexion oblige à
 * réapprendre le site au moment où l'on vient d'y entrer. Les écrans de
 * travail, eux, gardent la barre qui se déduit des droits, et elle n'a
 * pas bougé : ce qui marchait continue de marcher.
 *
 * `barre_vitrine()` (app/vitrine.php) tranche, et sa règle tient en une
 * phrase : on est au travail dès qu'on est connecté ET ailleurs que sur
 * le site public.
 */
?>
<?php if (barre_vitrine($me, $_page)): ?>
<?php
/**
 * La barre de la vitrine : la même pour tout le monde.
 *
 * Elle ne se réarrange pas selon qui regarde. Un site public dont le
 * menu change à la connexion oblige chacun à réapprendre où sont les
 * choses au moment précis où il vient d'arriver chez lui — et il oblige
 * surtout à expliquer deux fois le même site.
 *
 * Seul le volet du compte connaît deux états. Et comme le menu ne bouge
 * pas, ce volet devient la SEULE porte vers le tableau de bord : c'est
 * pourquoi « Mon tableau de bord » y figure en tête. Sans lui, quelqu'un
 * de connecté sur la vitrine n'aurait aucun moyen de rentrer chez lui.
 */
$chev = '<svg class="wk-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
      . 'stroke-width="2.4" stroke-linecap="round" aria-hidden="true">'
      . '<polyline points="6 9 12 15 18 9"></polyline></svg>';
$ici_v = fn(string $r): bool => str_contains($r, '?p=' . $_page);
/* Le côté où l'on se trouve, lu dans le menu : c'est lui qui fait
   basculer le texte du bouton unique du header. */
$_cote_boost = in_array($_page, pages_boost(), true);
?>
<header class="wk-tete">
  <div class="wk-in">

    <a class="wk-marque" href="<?= e(url('')) ?>" aria-label="Wakabi, accueil"><?= logo_wakabi() ?></a>

    <ul class="wk-menu">
      <?php foreach (menu_vitrine() as $entree): ?>
        <?php if ($entree[0] !== 'groupe'): ?>
          <li><a href="<?= e(url($entree[0])) ?>"<?= $ici_v($entree[0]) ? ' aria-current="page"' : '' ?>><?= e($entree[1]) ?></a></li>
        <?php else: [, $nom_g, $sous] = $entree; ?>
          <?php
          $dedans = false;
          foreach ($sous as $sous_e) {
              /* Une entrée en attente n'est pas une page : elle ne peut
                 pas être « ici », et ne marque donc jamais le groupe. */
              $dedans = $dedans
                || (($sous_e[3] ?? '') !== 'attente' && $ici_v($sous_e[0]));
          }
          ?>
          <li><details class="wk-grp<?= $dedans ? ' wk-ici' : '' ?>">
            <summary<?= $dedans ? ' aria-current="true"' : '' ?>><?= e($nom_g) ?><?= $chev ?></summary>
            <div class="wk-volet">
              <?php foreach ($sous as $sous_e): [$r, $n, $aide] = $sous_e; ?>
                <?php if (($sous_e[3] ?? '') === 'attente'): ?>
                  <a class="wk-attente" aria-disabled="true">
                    <?= icone_vitrine($r) ?>
                    <span><?= e($n) ?><small><?= e($aide) ?></small></span>
                  </a>
                <?php else: ?>
                  <a href="<?= e(url($r)) ?>">
                    <?= icone_vitrine($r) ?>
                    <span><?= e($n) ?><small><?= e($aide) ?></small></span>
                  </a>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </details></li>
        <?php endif; ?>
      <?php endforeach; ?>
    </ul>

    <div class="wk-droite">
      <?php
      /**
       * Un seul bouton, et il dit où l'on est.
       *
       * Quatre boutons se tenaient ici — Devenir Partenaire, Télécharger,
       * Connexion, Créer un compte — et chacun criait dans une main déjà
       * pleine. Il en reste un, qui change de texte avec le côté de la
       * page : « Créer mon espace » sur Boost, où l'on vient pour se
       * lancer ; « Télécharger l'app » partout ailleurs, où l'on vient
       * d'abord sortir. Un membre n'a pas d'espace à créer : il voit
       * toujours le bouton des magasins, son espace est dans le volet du
       * compte juste à côté.
       *
       * « Télécharger » n'ouvre pas un magasin : il ouvre un petit volet
       * qui propose LES DEUX. L'App Store n'est pas encore publié -
       * `APP_STORE_URL` est vide, et tant qu'il l'est, la proposition
       * apparaît en gris, non cliquable. Le sélecteur `details.wk-stores`
       * (dans `public/entete.js`) fait entrer le volet dans le fil des
       * menus : clic à côté et échap le replient.
       */
      if ($_cote_boost && !$me): ?>
        <a class="wk-b-pri" href="<?= e(url('?p=inscription')) ?>">Créer mon espace</a>
      <?php else: ?>
        <details class="wk-stores">
          <summary class="wk-b-pri">Télécharger l’app</summary>
          <div class="wk-stores-volet">
            <a href="<?= e(APPLICATION_URL) ?>"<?= sortie_externe(APPLICATION_URL) ?>>Google&nbsp;Play</a>
            <?php if (APP_STORE_URL !== ''): ?>
              <a href="<?= e(APP_STORE_URL) ?>"<?= sortie_externe(APP_STORE_URL) ?>>App&nbsp;Store</a>
            <?php else: ?>
              <span class="wk-stores-attente" aria-disabled="true">App&nbsp;Store bientôt disponible</span>
            <?php endif; ?>
          </div>
        </details>
      <?php endif; ?>

      <?php
      /**
       * Le compte : un `details`, pas un script — et seulement pour qui
       * en a un. Un visiteur voit « Connexion » en simple lien : une
       * porte, pas un meuble. Le volet du compte, lui, s'ouvre au
       * clavier, survit à un script qui ne charge pas, et se referme à
       * l'échappement.
       */
      ?>
      <?php if ($me): ?>
      <details class="wk-compte wk-sien">
        <summary aria-label="<?= e('Mon compte, ' . $me['nom']) ?>">
          <span class="wk-pastille" aria-hidden="true"><?= e(initiales((string) $me['nom'])) ?></span>
          <?= $chev ?>
        </summary>

        <div class="wk-volet-u">
          <div class="wk-qui">
            <b><?= e((string) $me['nom']) ?></b>
            <span><?= e((string) $me['email']) ?><?php
              $f = formule_affichee($me);
              echo $f ? ' · ' . e($f) : ''; ?></span>
          </div>
          <a href="<?= e(url(accueil_de($me))) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="1.9"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                 style="width:18px;height:18px"><rect x="3" y="3" width="7" height="9" rx="1.5"></rect>
              <rect x="14" y="3" width="7" height="5" rx="1.5"></rect>
              <rect x="14" y="12" width="7" height="9" rx="1.5"></rect>
              <rect x="3" y="16" width="7" height="5" rx="1.5"></rect></svg>
            Mon tableau de bord
          </a>
          <a href="<?= e(url('?p=profil')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="1.9"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                 style="width:18px;height:18px"><circle cx="12" cy="8" r="3.6"></circle>
              <path d="M4.8 20c0-3.5 3.2-5.6 7.2-5.6s7.2 2.1 7.2 5.6"></path></svg>
            Mon profil
          </a>
          <a href="<?= e(url('?p=notifications')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="1.9"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                 style="width:18px;height:18px"><path d="M18 8.5a6 6 0 1 0-12 0c0 6-2 7.5-2 7.5h16s-2-1.5-2-7.5"></path>
              <path d="M13.7 20a2 2 0 0 1-3.4 0"></path></svg>
            Notifications<?= $nonlues ? '<span class="wk-nb">' . (int) $nonlues . '</span>' : '' ?>
          </a>
          <div class="wk-sep"></div>
          <form method="post" action="<?= e(url('?p=deconnexion')) ?>">
            <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
            <button class="wk-sortir" type="submit">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                   style="width:18px;height:18px"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line></svg>
              Déconnexion
            </button>
          </form>
        </div>
      </details>
      <?php else: ?>
      <a class="wk-lien-connexion" href="<?= e(url('?p=connexion')) ?>">Connexion</a>
      <?php endif; ?>

      <?php
      /* Le hamburger reprend le MÊME menu, à plat : les sous-entrées de
         « Boost » y sont décalées plutôt que repliées, parce qu'un
         déroulant dans un déroulant se referme sur le doigt. */
      ?>
      <details class="wk-burger">
        <summary aria-label="Menu"><span></span><span></span><span></span></summary>
        <div class="wk-volet">
          <?php foreach (menu_vitrine() as $entree): ?>
            <?php if ($entree[0] !== 'groupe'): ?>
              <a href="<?= e(url($entree[0])) ?>"<?= $ici_v($entree[0]) ? ' aria-current="page"' : '' ?>><?= e($entree[1]) ?></a>
            <?php else: ?>
              <?php foreach ($entree[2] as $sous_e): [$r, $n] = $sous_e; ?>
                <?php if (($sous_e[3] ?? '') === 'attente'): ?>
                  <span class="wk-sous wk-attente" aria-disabled="true"><?= e($entree[1]) ?> · <?= e($n) ?></span>
                <?php else: ?>
                  <a class="wk-sous" href="<?= e(url($r)) ?>"><?= e($entree[1]) ?> · <?= e($n) ?></a>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php endif; ?>
          <?php endforeach; ?>
          <div class="wk-sep"></div>
          <?php if (!$me): ?>
            <a href="<?= e(url('?p=connexion')) ?>">Connexion</a>
            <?php
            /* Le volet du compte n'existe plus pour un visiteur : la
               proposition de notifications y vivait, et déménage ici.
               Elle part cachée, le script la révèle — cf. le partiel. */
            require RACINE . '/app/vues/partiels/push-menu.php';
            ?>
          <?php endif; ?>
        </div>
      </details>
    </div>

  </div>
</header>

<?php else: ?>
<header class="barre">
  <div class="barre-in">
    <a class="marque" href="<?= e(url('')) ?>" aria-label="Wakabi Boost, accueil"><?= logo_wakabi() ?></a>

    <?php
    /**
     * Le menu suit le rôle, et ne montre qu'une entrée par destination.
     *
     * « Décors » désignait à la fois le catalogue public et celui de
     * l'équipe : deux liens du même nom côte à côte. Pour un membre de
     * l'équipe, son catalogue contient déjà un accès au décor en ligne :
     * le lien public est donc redondant, et il disparaît.
     */
    /**
     * Le menu se déduit des DROITS, pas d'une liste par rôle.
     *
     * Chaque entrée déclare le droit qu'elle demande, et le menu ne garde
     * que celles qui passent. Ajouter un rôle ne demande alors aucune
     * retouche ici : c'est la table `ROLES_DROITS` qui décide, et elle
     * décide aussi de ce que l'écran laisse faire — les deux ne peuvent
     * plus diverger. Un menu écrit à la main finit toujours par proposer
     * une page qui refuse, ou par cacher une page qui marche.
     *
     * Deux formes seulement. Celle de la maison — pour qui voit TOUS les
     * décors — se range en trois groupes ; celle de qui ne voit que les
     * siens reste plate, parce qu'elle tient en quatre entrées.
     *
     * Et deux destinations restent hors des groupes, délibérément : le
     * tableau de bord, parce qu'un point de départ ne se cherche pas dans
     * un tiroir ; le contrôle d'entrée, parce qu'il s'utilise debout à une
     * porte, sur un téléphone, avec une file qui attend.
     */
    $forme = match (true) {
        droit($me, 'decors_tous') => [
            ['?p=admin', 'Tableau de bord', 'decors_tous'],
            ['groupe', 'Contenus', [
                ['?p=catalogue',      'Badges',               'decors_tous'],
                ['?p=relecture',      'Relecture des badges', 'valider'],
                ['?p=blog-admin',     'Le blog',              'articles'],
                ['?p=blog-relecture', 'Relecture du blog',    'valider'],
            ]],
            /**
             * Campagnes : tout ce qui part vers les gens, même canal, même
             * écran. Le push navigateur, Telegram et WhatsApp partent du
             * même composer (« Notification ») ; l'e-mail garde son atelier
             * (« Campagne Email ») ; et le contrôle d'entrée, qui s'utilise
             * debout à une porte avec une file qui attend, rejoint le
             * groupe sous son nouveau nom, « Scanner Qr ».
             */
            ['groupe', 'Campagnes', [
                ['?p=notification', 'Notification',   'push'],
                ['?p=regie',       'Campagne Email',  'regie'],
                ['?p=liens',        'Liens courts',   'liens'],
                ['?p=canaux',       'Canaux',         'push'],
                ['?p=scan',         'Scanner Qr',     'scan'],
            ]],
            /**
             * Système : ce qui regarde l'installation elle-même, pas
             * l'écriture aux gens. Comptes et Rapports y remontent, et la
             * Facturation en sort — elle est devenue une page atteinte
             * depuis le tableau de bord et le compte, pas une entrée du
             * menu : un client a son espace, l'équipe a ses factures, et
             * ni l'un ni l'autre n'ouvre l'écran tous les jours.
             *
             * Les groupes portent maintenant cinq entrées, et l'assumer :
             * au-delà, un déroulant devient une liste qu'on PARCOURT —
             * mais cinq rangements bien nommés restent un rangement
             * qu'on connaît. « Mon profil » reste hors groupe : c'est le
             * compte de la personne, pas de l'installation.
             */
            ['groupe', 'Système', [
                ['?p=comptes',     'Comptes',     'comptes'],
                ['?p=reglages',    'Réglages',    'reglages'],
                ['?p=rapports',    'Rapports',    'decors_tous'],
                ['?p=sauvegardes', 'Sauvegardes', 'reglages'],
                ['?p=journal',     'Journal',     'comptes'],
            ]],
            ['?p=profil', 'Mon profil', null],
        ],
        droit($me, 'decors_siens') => [
            ['?p=partenaire', 'Tableau de bord', 'decors_siens'],
            ['groupe', 'Promotion', [
                ['?p=liens',         'Liens courts',   'liens'],
                ['?p=canaux',        'Canaux',         'push'],
                ['?p=notification',  'Notification',   'push'],
                ['?p=regie',         'Campagne Email', 'regie'],
                ['?p=blog-admin',    'Mes articles',   'articles'],
            ]],
            ['?p=scan',        'Scanner Qr',    'scan'],
            // Le droit `regie` plutôt que `decors_siens` : un éditeur de la
            // maison passe aussi par ce menu, et il n'a ni campagnes ni
            // audience à lui. L'écran le renverrait chez lui ; autant ne
            // pas le lui proposer.
            ['?p=rapports',    'Rapports',      'regie'],
            ['?p=decors',      'Le catalogue',  null],
            ['?p=facturation', 'Facturation',   null],
            ['?p=profil',      'Mon profil',    null],
        ],
        $me !== null => [
            ['?p=scan',   'Scanner Qr',  'scan'],
            ['?p=decors', 'Les Badges',  null],
            ['?p=blog',   'Le blog',     null],
            ['?p=compte', 'Mon compte',  null],
            ['?p=profil', 'Mon profil',  null],
        ],
        /**
         * Le visiteur pas encore connecté, lui, voit d'abord les DEUX
         * produits — le générateur de badges et le guide dont il est né.
         * Un lien vers le guide ne dit pas seulement où aller : il dit à
         * qui l'on a affaire, et c'est ce qui manque le plus à une
         * vitrine que personne ne connaît encore.
         *
         * Les deux disparaissent à la connexion : le membre sait où il
         * est, et son menu doit se remplir de ce qu'il vient y faire.
         */
        default => [
            ['?p=accueil', 'Wakabi Boost',    null],
            [GUIDE_URL,    'Wakabi le guide', null],
            ['?p=decors',  'Les Badges',      null],
            ['?p=blog',    'Le blog',         null],
        ],
    };

    /**
     * Le filtre vit dans `auth.php`, pas ici.
     *
     * Le menu n'est pas le seul à poser la question : le tableau de bord
     * range les mêmes destinations en raccourcis. Tant que chacun avait sa
     * liste, elles ont divergé. Montrer un lien qui mène à un refus est
     * une façon de vendre ; le montrer à chaque page en est une de lasser.
     */
    $permis = fn(?string $besoin): bool => destination_permise($me, $besoin);

    $liens = [];
    foreach ($forme as $entree) {
        if ($entree[0] !== 'groupe') {
            if ($permis($entree[2])) {
                $liens[] = [$entree[0], $entree[1]];
            }
            continue;
        }
        // Un groupe entièrement filtré disparaît, plutôt que de rester
        // ouvert sur rien.
        $garde = [];
        foreach ($entree[2] as [$cible, $nom, $besoin]) {
            if ($permis($besoin)) {
                $garde[] = [$cible, $nom];
            }
        }
        if ($garde) {
            $liens[] = ['groupe', $entree[1], $garde];
        }
    }

    $ici = (string) ($_GET['p'] ?? 'accueil');

    /**
     * Un lien de menu, marqué s'il désigne la page courante.
     *
     * « Désigner » veut dire la page elle-même OU une de ses sous-pages :
     * `?p=regie` reste marqué pendant qu'on rédige une campagne
     * (`regie-ecrire`, `regie-campagne`), sinon le repère disparaîtrait au
     * moment précis où l'on a besoin de savoir où l'on est. Le tiret est
     * exigé : sans lui, `?p=decors` se marquerait sur `?p=decor`.
     */
    $courante = function (string $cible) use ($ici): bool {
        $p = substr($cible, 3);
        return $ici === $p || str_starts_with($ici, $p . '-');
    };
    /**
     * Une cible absolue s'en va telle quelle, et `sortie_externe()` décide
     * seule si elle mérite un onglet. Une cible relative passe par `url()`
     * comme avant, et ne peut pas sortir.
     */
    $lien = function (string $cible, string $nom) use ($courante): string {
        $href = est_externe($cible) ? $cible : url($cible);
        $marque = !est_externe($cible) && $courante($cible) ? ' aria-current="page"' : '';
        return '<a href="' . e($href) . '"' . $marque . sortie_externe($href) . '>'
             . e($nom) . '</a>';
    };
    ?>

    <?php
    /**
     * Le menu mobile est un <details> : il s'ouvre et se ferme sans une ligne
     * de JavaScript, se pilote au clavier, et reste ouvrable si le script ne
     * charge pas. Au-dessus de 900 px, le volet est simplement toujours
     * déplié et le bouton disparaît.
     */
    ?>
    <details class="menu">
      <summary aria-label="Menu">
        <span class="ouvrir"><?= icone('menu') ?></span>
        <span class="fermer"><?= icone('croix') ?></span>
      </summary>
      <nav>
        <?php foreach ($liens as $entree): ?>
          <?php if ($entree[0] === 'groupe'):
              [, $titre_groupe, $sous] = $entree;
              // Marqué, pas déplié : un volet ouvert d'office recouvrirait le
              // haut de la page qu'on vient d'ouvrir.
              $dedans = false;
              foreach ($sous as [$c, ]) {
                  $dedans = $dedans || $courante($c);
              } ?>
            <details class="deroulant">
              <summary<?= $dedans ? ' aria-current="true"' : '' ?>>
                <?= e($titre_groupe) ?><?= icone('chevron') ?>
              </summary>
              <div class="volet">
                <?php foreach ($sous as [$c, $n]): ?><?= $lien($c, $n) ?><?php endforeach; ?>
              </div>
            </details>
          <?php else: ?>
            <?= $lien($entree[0], $entree[1]) ?>
          <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($me): ?>
          <span class="sep" aria-hidden="true"></span>
          <a href="<?= e(url('?p=notifications')) ?>">
            Notifications<?= $nonlues ? ' <b class="compteur">' . $nonlues . '</b>' : '' ?>
          </a>
          <form method="post" action="<?= e(url('?p=deconnexion')) ?>">
            <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
            <button class="bouton fant petit" type="submit">Déconnexion</button>
          </form>
        <?php else: ?>
          <span class="sep" aria-hidden="true"></span>
          <a href="<?= e(url('?p=connexion')) ?>">Connexion</a>
          <a class="bouton petit" href="<?= e(url('?p=inscription')) ?>">Créer un compte</a>
        <?php endif; ?>
      </nav>
    </details>
  </div>
</header>
<?php endif; ?>

<main><?= $contenu ?></main>

<?php
/**
 * Le bouton WhatsApp, flottant en bas à droite.
 *
 * Il n'existe que là où la barre de vitrine se montre : le site public.
 * Dans l'atelier, on est chez soi au travail — un bouton de contact
 * n'a rien à y faire. Un numéro absent ne laisse rien voir non plus :
 * un bouton qui mène nulle part est pire qu'un bouton manquant.
 *
 * `wa.me` ouvre la discussion, avec le message du réglage déjà écrit —
 * le visiteur n'a plus qu'à envoyer. Un autre onglet, pour que le site
 * reste ouvert derrière : on ne perd pas son visiteur en lui tendant
 * un téléphone.
 *
 * Placé AVANT le bouton « haut de page » dans le DOM : le sélecteur
 * frère `.wk-wa ~ .haut-de-page` fait alors monter celui-ci au-dessus
 * du bouton WhatsApp, sans une ligne de script ni une classe en plus.
 */
if (barre_vitrine($me, $_page) && ($wa = whatsapp_contact()) !== null): ?>
<a class="wk-wa" href="https://wa.me/<?= e($wa['numero']) ?>?text=<?= e(rawurlencode($wa['message'])) ?>"
   target="_blank" rel="noopener" aria-label="Nous écrire sur WhatsApp">
  <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
</a>
<?php endif; ?>

<?php if ($_page === 'accueil'): ?>
<?php
/**
 * Le retour en tête, sur la vitrine seule.
 *
 * Neuf sections, dont un comparatif et une grille de tarifs : arrivé au
 * pied de page, le visiteur est loin des deux boutons qui décident de
 * tout. Sur un téléphone, remonter au pouce prend une dizaine de gestes,
 * et beaucoup préfèrent fermer l'onglet — c'est-à-dire partir.
 *
 * Ailleurs, la question ne se pose pas : les écrans de travail tiennent
 * en un écran ou deux, et un bouton flottant y masquerait une ligne de
 * tableau.
 *
 * Il est CACHÉ au départ, et par l'attribut `hidden` plutôt que par le
 * style : sans script, il ne s'affiche jamais — mieux vaut pas de bouton
 * qu'un bouton qui ne fait rien.
 */
?>
<button class="haut-de-page" id="haut-de-page" type="button" hidden
        aria-label="Revenir en haut de la page">
  <?= icone('haut') ?>
</button>
<?php
/* L'invitation aux notifications partage ce bas d'écran : elle se tient à
   gauche, lui à droite, et sur téléphone elle le pousse vers le haut. Elle
   n'est proposée qu'à qui a quelque chose à recevoir — un éditeur n'a
   rien à y gagner, `push_ouvert_a()` le dit pour tout le monde. */
if (push_ouvert_a($me)) {
    require RACINE . '/app/vues/partiels/push-invite.php';
}
?>
<script>
(function () {
  var b = document.getElementById('haut-de-page');
  if (!b) { return; }

  /* Le seuil : une hauteur d'écran. En dessous, le haut est encore à
     portée de deux gestes, et le bouton ne ferait qu'encombrer. */
  var seuil = function () { return window.innerHeight * 1.2; };
  var attend = false;

  var juger = function () {
    b.hidden = window.scrollY < seuil();
    attend = false;
  };
  /* Le défilement se déclenche des dizaines de fois par seconde ; on ne
     décide qu'une fois par image, sinon la page saccade sur un téléphone
     modeste au moment précis où elle doit être fluide. */
  window.addEventListener('scroll', function () {
    if (!attend) { attend = true; requestAnimationFrame(juger); }
  }, { passive: true });
  juger();

  b.addEventListener('click', function () {
    /* Qui a demandé moins d'animations en a demandé ici aussi : un long
       glissement de six écrans donne le vertige à qui y est sensible. */
    var doux = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({ top: 0, behavior: doux ? 'smooth' : 'auto' });
    /* Le focus revient au début du document : sans cela, la tabulation
       repartirait d'en bas, là où l'œil n'est plus. */
    var premier = document.querySelector('header a, header button');
    if (premier) { premier.focus({ preventScroll: true }); }
  });
})();
</script>
<?php endif; ?>

<?php
/**
 * Deux pieds de page, et un seul par écran.
 *
 * LE PIED SUIT LA BARRE : la même règle décide des deux. Une page qui
 * porte la barre de vitrine porte le pied de la vitrine, et une page de
 * travail porte la signature discrète. Ils allaient auparavant chacun
 * de leur côté — trois pages avaient le grand pied, toutes les autres
 * le petit — si bien qu'une page du guide s'ouvrait sous le menu
 * complet et se refermait sur une ligne de logo. La coupure qu'on
 * venait de supprimer en haut revenait en bas.
 *
 * Ce pied-là n'est pas un ornement : c'est le PLAN DU SITE. Sur un
 * téléphone, menu refermé, c'est par lui qu'on retrouve « Partenaires »
 * ou « CGU ». Le priver d'une page, c'est y enfermer le visiteur.
 *
 * Partout ailleurs on est CHEZ SOI, connecté, au travail : la signature
 * discrète suffit, et rien ne doit repousser l'écran qu'on utilise.
 */
$_vitrine = barre_vitrine($me, $_page);
?>

<?php if ($_vitrine): ?>
<footer class="pied-guide">
  <div class="pg-in">
    <div class="pg-grille">
      <div>
        <a href="<?= e(url('')) ?>" aria-label="Wakabi Boost, accueil"><?= logo_wakabi('logo pg-logo') ?></a>
        <p class="pg-accroche">Le guide qui transforme chaque sortie en expérience
        inoubliable. Explorez. Découvrez. Connectez.</p>
        <div class="pg-reseaux">
          <?php foreach (RESEAUX_WAKABI as $cle => [$nom, $adresse]): ?>
            <a class="pg-soc" href="<?= e($adresse) ?>"<?= sortie_externe($adresse) ?>
               aria-label="<?= e($nom) ?>"><?= icone_reseau($cle) ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php
      /**
       * Les colonnes du pied de page, désormais INTERNES.
       *
       * Elles sortaient vers wakabileguide.com/application.html et ses
       * voisines. Ces pages sont maintenant des routes d'ici : continuer
       * à sortir enverrait le visiteur sur l'ancien site statique, avec
       * son ancien header, et il faudrait qu'il revienne à la main —
       * c'est-à-dire exactement la coupure que la fusion supprime.
       *
       * Trois choix récents, et leur pourquoi :
       *
       *   · « Partenariat » a disparu : la colonne Partenaires offrait
       *     « Devenir partenaire », « Nos villes » ET « Partenariat »,
       *     les deux premiers renvoyant déjà à tout le troisième.
       *
       *   · « Contact » est devenu « Contacts », comme dans le menu —
       *     une page, un nom, partout.
       *
       *   · CGU et confidentialité ne sont CITES qu'UNE fois. Elles
       *     étaient à la fois dans la colonne Wakabi et dans la ligne
       *     légale du bas, sous des noms légèrement différents. Elles
       *     vivent désormais uniquement dans cette ligne du bas, parce
       *     que c'est là qu'un visiteur anxieux les cherche au moment
       *     précis où il hésite à créer un compte — et sous deux noms
       *     qui disent à quoi elles servent : « Mentions légales » et
       *     « Protection des données ».
       */
      $colonnes = [
        'Produit' => [
          ['Badges',            url('?p=decors')],
          ['Le blog',           url('?p=blog')],
          ['L’application',     url('?p=application')],
          /* « Télécharger » ne va plus droit à un magasin : il ouvre un
             petit volet qui propose LES DEUX. L'App Store n'est pas
             encore publié — `APP_STORE_URL` est vide, et tant qu'il
             l'est, la proposition apparaît en gris, non cliquable. */
          ['Télécharger',       APPLICATION_URL],
        ],
        'Boost' => [
          ['WhatsApp et notifications', url('?p=boost-push')],
          ['Campagnes e-mail',          url('?p=boost-regie')],
          ['Liens courts',              url('?p=boost-liens')],
          ['Créer un compte',           url('?p=inscription')],
        ],
        'Partenaires' => [
          ['Devenir partenaire', url('?p=partenaires')],
          ['Nos villes',         url('?p=villes')],
        ],
        'Wakabi' => [
          ['À propos',        url('?p=a-propos')],
          ['Contacts',        url('?p=contact')],
        ],
      ];
      foreach ($colonnes as $titre => $entrees): ?>
        <div class="pg-col">
          <?php
          /* `h2` et non `h4` : le pied suit le `h1` de la page, et sauter
             deux niveaux rompt le plan que lit un lecteur d'écran. Sa
             taille vient de `.pg-col h2`, pas de son niveau. */
          ?>
          <h2><?= e($titre) ?></h2>
          <div class="pg-liens">
            <?php foreach ($entrees as [$nom, $adresse]): ?>
              <?php if ($nom === 'Télécharger'): ?>
                <?php /* Le volet des magasins, dans le fil des liens. Un
                       `<details>` natif : même sans script, il s'ouvre et
                       se referme. Avec, clic à côté et Échap le replient —
                       la classe le fait entrer dans le fil du script de
                       fermeture, plus bas dans ce gabarit. */ ?>
                <details class="pg-stores menu">
                  <summary>Télécharger</summary>
                  <div class="pg-stores-liste">
                    <a href="<?= e(APPLICATION_URL) ?>"<?= sortie_externe(APPLICATION_URL) ?>>Google&nbsp;Play</a>
                    <?php if (APP_STORE_URL !== ''): ?>
                      <a href="<?= e(APP_STORE_URL) ?>"<?= sortie_externe(APP_STORE_URL) ?>>App&nbsp;Store</a>
                    <?php else: ?>
                      <span class="pg-stores-attente" aria-disabled="true">App&nbsp;Store · bientôt</span>
                    <?php endif; ?>
                  </div>
                </details>
              <?php else: ?>
                <a href="<?= e($adresse) ?>"<?= sortie_externe($adresse) ?>><?= e($nom) ?></a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="pg-bas">
      <span class="pg-copy">© <?= date('Y') ?> Wakabileguide.com · Tous droits réservés.
      Fait avec amour.</span>
      <div class="pg-legal">
        <a href="<?= e(url('?p=cgu')) ?>">Mentions légales</a>
        <a href="<?= e(url('?p=confidentialite')) ?>">Protection des données</a>
      </div>
    </div>
  </div>
</footer>
<?php else: ?>
<footer>
  <div class="contenu pied">
    <a href="<?= e(url('')) ?>" aria-label="Wakabi Boost, accueil"><?= logo_wakabi('logo pied-logo') ?></a>
    <span><?= e(WAKABI_SIGNATURE) ?> · Lomé · Cotonou · Abidjan
    <span class="aide" style="margin-left:8px">v<?= e(VERSION) ?></span></span>
  </div>
</footer>
<?php endif; ?>

<?php
/**
 * Refermer les menus quand on clique ailleurs.
 *
 * Un `<details>` natif ne se referme QUE par son propre résumé : ouvrez
 * « Campagnes », allez ailleurs, et le volet reste ouvert par-dessus la
 * page — parfois deux à la fois. C'est le seul endroit du site où le
 * comportement du navigateur ne suffit pas.
 *
 * Ce script AJOUTE une commodité, il ne porte rien d'essentiel : sans
 * JavaScript le menu s'ouvre, se parcourt et se ferme exactement comme
 * avant, au clavier compris. C'est pourquoi il est écrit ici en clair
 * plutôt que dans un paquet à charger — une dizaine de lignes qui ne
 * valent pas un aller-retour réseau.
 */
?>
<script>
(function () {
  var volets = function () { return document.querySelectorAll('details.menu[open], details.deroulant[open]'); };

  document.addEventListener('click', function (e) {
    Array.prototype.forEach.call(volets(), function (d) {
      // Le clic DANS un menu ouvert ne le referme pas : on y navigue.
      if (!d.contains(e.target)) { d.open = false; }
    });
  });

  /* Un lien suivi ferme le menu : sur une ancre de la même page, rien ne
     recharge, et le volet resterait ouvert sur la section qu'on vient
     d'atteindre. */
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('.menu a, .deroulant a') : null;
    if (a) { Array.prototype.forEach.call(volets(), function (d) { d.open = false; }); }
  });

  /* Échap referme, et rend le focus au bouton — sinon on se retrouve à
     tabuler dans un menu invisible. */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    Array.prototype.forEach.call(volets(), function (d) {
      d.open = false;
      var s = d.querySelector(':scope > summary');
      if (s) { s.focus(); }
    });
  });

  /* Un seul déroulant ouvert à la fois : deux volets superposés se
     recouvrent, et on ne sait plus lequel on lit. */
  Array.prototype.forEach.call(document.querySelectorAll('details.deroulant'), function (d) {
    d.addEventListener('toggle', function () {
      if (!d.open) { return; }
      Array.prototype.forEach.call(document.querySelectorAll('details.deroulant[open]'), function (autre) {
        if (autre !== d) { autre.open = false; }
      });
    });
  });
})();
</script>

<?php
/* Le contexte des notifications, en fin de page et une seule fois.
   Les blocs qui vivent dans un conteneur serré — le volet du compte,
   l'invitation — ne peuvent pas le porter eux-mêmes ; la carte du profil
   ou d'un décor, si, et elle l'a déjà fait plus haut avec SON décor. */
if (push_contexte_attendu()) {
    echo push_contexte_html();
}
?>
</body>
</html>
