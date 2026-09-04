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
<?php $ico = logo_fichier(); ?>
<?php if ($ico): ?><link rel="icon" href="<?= e($ico['url']) ?>" type="<?= e($ico['type']) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(actif('public/wakabi.css')) ?>">
</head>
<body>

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
                ['?p=catalogue',      'Décors',               'decors_tous'],
                ['?p=relecture',      'Relecture des décors', 'valider'],
                ['?p=blog-admin',     'Le blog',              'articles'],
                ['?p=blog-relecture', 'Relecture du blog',    'valider'],
            ]],
            ['groupe', 'Audience', [
                ['?p=comptes',   'Comptes',            'comptes'],
                ['?p=regie',     'Régie e-mail',       'regie'],
                ['?p=diffusion', 'Notifications push', 'push'],
                ['?p=liens',     'Liens courts',       'liens'],
            ]],
            ['?p=scan', 'Entrée', 'scan'],
            ['groupe', 'Système', [
                ['?p=reglages',    'Réglages',    'reglages'],
                ['?p=sauvegardes', 'Sauvegardes', 'reglages'],
                ['?p=journal',     'Journal',     'comptes'],
                ['?p=profil',      'Mon profil',  null],
            ]],
        ],
        droit($me, 'decors_siens') => [
            ['?p=partenaire', 'Tableau de bord', 'decors_siens'],
            ['groupe', 'Promotion', [
                ['?p=liens',      'Liens courts',       'liens'],
                ['?p=diffusion',  'Notifications push', 'push'],
                ['?p=regie',      'Régie e-mail',       'regie'],
                ['?p=blog-admin', 'Mes articles',       'articles'],
            ]],
            ['?p=scan',   'Entrée',        'scan'],
            ['?p=decors', 'Le catalogue',  null],
            ['?p=profil', 'Mon profil',    null],
        ],
        $me !== null => [
            ['?p=scan',   'Entrée',      'scan'],
            ['?p=decors', 'Les décors',  null],
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
            ['?p=decors',  'Les décors',      null],
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
     * Une cible absolue s'en va telle quelle, et emporte les précautions
     * qui vont avec : `rel` coupe l'accès à notre fenêtre depuis la sienne.
     * Une cible relative, elle, passe par `url()` comme avant.
     */
    $lien = function (string $cible, string $nom) use ($courante): string {
        $externe = str_starts_with($cible, 'http://') || str_starts_with($cible, 'https://');
        $href = $externe ? $cible : url($cible);
        $marque = !$externe && $courante($cible) ? ' aria-current="page"' : '';
        $sortie = $externe ? ' rel="noopener noreferrer"' : '';
        return '<a href="' . e($href) . '"' . $marque . $sortie . '>' . e($nom) . '</a>';
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

<main><?= $contenu ?></main>

<?php
/**
 * Deux pieds de page, et un seul par écran.
 *
 * Les pages PUBLIQUES — la vitrine, le catalogue, le blog et ses
 * articles — sont celles qu'un inconnu ouvre. Elles doivent dire qui
 * édite ce service, où le trouver ailleurs, et à quoi il s'engage :
 * c'est le pied de page du guide, celui que le reste de la maison porte
 * déjà. Un lecteur qui vient de finir un article est exactement celui à
 * qui l'on veut montrer le chemin vers la suite.
 *
 * Le Studio (`?p=decor`) en est exclu, bien qu'il soit public : on n'y
 * lit pas, on y fabrique. Quatre colonnes de liens sous l'outil
 * pousseraient vers le bas la seule chose qu'on est venu y faire.
 *
 * Partout ailleurs on est CHEZ SOI, connecté, au travail : la signature
 * discrète suffit, et rien ne doit repousser l'écran qu'on utilise.
 */
$_vitrine = in_array($_page, ['accueil', 'decors', 'blog'], true);
?>

<?php if ($_vitrine): ?>
<footer class="pied-guide">
  <div class="contenu">
    <div class="pg-grille">
      <div>
        <a href="<?= e(url('')) ?>" aria-label="Wakabi Boost, accueil"><?= logo_wakabi('logo pg-logo') ?></a>
        <p class="pg-accroche">Le guide qui transforme chaque sortie en expérience
        inoubliable. Explorez. Découvrez. Connectez.</p>
        <div class="pg-reseaux">
          <?php foreach (RESEAUX_WAKABI as $cle => [$nom, $adresse]): ?>
            <a class="pg-soc" href="<?= e($adresse) ?>" target="_blank"
               rel="noopener noreferrer" aria-label="<?= e($nom) ?>"><?= icone_reseau($cle) ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php
      /**
       * Les colonnes du guide. Elles sortent vers wakabileguide.com, sauf
       * les deux qui vivent ICI — les décors et le blog de Boost.
       */
      $colonnes = [
        'Produit' => [
          ['Les décors',        url('?p=decors')],
          ['Le blog',           url('?p=blog')],
          ['L’application',     GUIDE_URL . '/application.html'],
          ['Télécharger',       'https://play.google.com/store/apps/details?id=com.wakabi.wakabimobile'],
        ],
        'Partenaires' => [
          ['Devenir partenaire', GUIDE_URL . '/partenaires.html'],
          ['Créer un compte',    url('?p=inscription')],
          ['Nos villes',         GUIDE_URL . '/villes.html'],
          ['Partenariat',        GUIDE_URL . '/contact.html'],
        ],
        'Wakabi' => [
          ['À propos',        GUIDE_URL . '/a-propos.html'],
          ['Le guide',        GUIDE_URL . '/'],
          ['Contact',         GUIDE_URL . '/contact.html'],
          ['Confidentialité', GUIDE_URL . '/confidentialite.html'],
          ['CGU',             GUIDE_URL . '/cgu.html'],
        ],
      ];
      foreach ($colonnes as $titre => $entrees): ?>
        <div class="pg-col">
          <h4><?= e($titre) ?></h4>
          <div class="pg-liens">
            <?php foreach ($entrees as [$nom, $adresse]): ?>
              <a href="<?= e($adresse) ?>"<?= str_starts_with($adresse, GUIDE_URL) || str_starts_with($adresse, 'https://play')
                    ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= e($nom) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="pg-bas">
      <span class="pg-copy">© <?= date('Y') ?> Wakabileguide.com — Tous droits réservés.
      Fait avec amour.</span>
      <div class="pg-legal">
        <a href="<?= e(GUIDE_URL) ?>/confidentialite.html" target="_blank" rel="noopener noreferrer">Politique de confidentialité</a>
        <a href="<?= e(GUIDE_URL) ?>/cgu.html" target="_blank" rel="noopener noreferrer">CGU</a>
        <span class="pg-version">v<?= e(VERSION) ?></span>
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
 * « Audience », allez ailleurs, et le volet reste ouvert par-dessus la
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

</body>
</html>
