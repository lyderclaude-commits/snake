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
          foreach ($sous as [$r, , ]) {
              $dedans = $dedans || $ici_v($r);
          }
          ?>
          <li><details class="wk-grp<?= $dedans ? ' wk-ici' : '' ?>">
            <summary<?= $dedans ? ' aria-current="true"' : '' ?>><?= e($nom_g) ?><?= $chev ?></summary>
            <div class="wk-volet">
              <?php foreach ($sous as [$r, $n, $aide]): ?>
                <a href="<?= e(url($r)) ?>">
                  <?= icone_vitrine($r) ?>
                  <span><?= e($n) ?><small><?= e($aide) ?></small></span>
                </a>
              <?php endforeach; ?>
            </div>
          </details></li>
        <?php endif; ?>
      <?php endforeach; ?>
    </ul>

    <div class="wk-droite">
      <a class="wk-b-out" href="<?= e(url('?p=partenaires')) ?>">Devenir Partenaire</a>
      <a class="wk-b-pri" href="<?= e(APPLICATION_URL) ?>" target="_blank" rel="noopener">Télécharger</a>

      <?php
      /**
       * Le compte : un `details`, pas un script.
       *
       * Il s'ouvre au clavier, il survit à un script qui ne charge pas, et
       * il se referme à l'échappement. Tout le reste de ce gabarit suit
       * déjà cette règle — le menu mobile la suivait avant lui.
       */
      ?>
      <details class="wk-compte<?= $me ? ' wk-sien' : '' ?>">
        <summary aria-label="<?= $me ? e('Mon compte, ' . $me['nom']) : 'Mon compte' ?>">
          <?php if ($me): ?>
            <span class="wk-pastille" aria-hidden="true"><?= e(initiales((string) $me['nom'])) ?></span>
          <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                 style="width:20px;height:20px"><circle cx="12" cy="8" r="3.6"></circle>
              <path d="M4.8 20c0-3.5 3.2-5.6 7.2-5.6s7.2 2.1 7.2 5.6"></path></svg>
          <?php endif; ?>
          <?= $chev ?>
        </summary>

        <div class="wk-volet-u">
          <?php if ($me): ?>
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
          <?php else: ?>
            <a href="<?= e(url('?p=connexion')) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="1.9"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                   style="width:18px;height:18px"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                <polyline points="10 17 15 12 10 7"></polyline>
                <line x1="15" y1="12" x2="3" y2="12"></line></svg>
              Connexion
            </a>
            <a href="<?= e(url('?p=inscription')) ?>" style="background:#EFF6FF">
              <svg viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.9"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                   style="width:18px;height:18px"><path d="M15 20v-1.6c0-2-1.8-3.4-4-3.4H8c-2.2 0-4 1.4-4 3.4V20"></path>
                <circle cx="9.5" cy="8" r="3.4"></circle>
                <line x1="18" y1="7" x2="18" y2="13"></line>
                <line x1="21" y1="10" x2="15" y2="10"></line></svg>
              Créer un compte
            </a>
          <?php endif; ?>
        </div>
      </details>

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
              <?php foreach ($entree[2] as [$r, $n, ]): ?>
                <a class="wk-sous" href="<?= e(url($r)) ?>"><?= e($entree[1]) ?> · <?= e($n) ?></a>
              <?php endforeach; ?>
            <?php endif; ?>
          <?php endforeach; ?>
          <div class="wk-sep"></div>
          <a href="<?= e(url('?p=contact')) ?>">Contact</a>
          <a href="<?= e(url('?p=partenaires')) ?>">Devenir Partenaire</a>
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
                ['?p=catalogue',      'Décors',               'decors_tous'],
                ['?p=relecture',      'Relecture des décors', 'valider'],
                ['?p=blog-admin',     'Le blog',              'articles'],
                ['?p=blog-relecture', 'Relecture du blog',    'valider'],
            ]],
            ['groupe', 'Audience', [
                ['?p=comptes',   'Comptes',            'comptes'],
                ['?p=regie',     'Régie',              'regie'],
                ['?p=canaux',    'Canaux',             'push'],
                ['?p=liens',     'Liens courts',       'liens'],
            ]],
            ['?p=scan', 'Entrée', 'scan'],
            /**
             * Les rapports restent hors groupe, eux aussi.
             *
             * Non par manque de place — les trois groupes sont pleins —
             * mais parce que c'est une destination qu'on ouvre le 1er du
             * mois, à côté de l'entrée et du tableau de bord. La ranger
             * dans « Système » l'aurait mise avec les sauvegardes, qu'on
             * ouvre une fois l'an.
             */
            ['?p=rapports', 'Rapports', 'decors_tous'],
            /**
             * Quatre destinations par groupe, jamais cinq.
             *
             * La règle tient depuis la refonte des menus : au-delà, un
             * déroulant devient une liste qu'on parcourt au lieu d'un
             * rangement qu'on connaît. Facturation y entre, et « Mon
             * profil » en sort — ce n'est pas de l'administration de
             * l'installation, c'est le compte de la personne, et il est
             * déjà hors groupe dans le menu d'un organisateur.
             */
            ['groupe', 'Système', [
                ['?p=reglages',    'Réglages',    'reglages'],
                ['?p=facturation', 'Facturation', 'comptes'],
                ['?p=sauvegardes', 'Sauvegardes', 'reglages'],
                ['?p=journal',     'Journal',     'comptes'],
            ]],
            ['?p=profil', 'Mon profil', null],
        ],
        droit($me, 'decors_siens') => [
            ['?p=partenaire', 'Tableau de bord', 'decors_siens'],
            ['groupe', 'Promotion', [
                ['?p=liens',      'Liens courts', 'liens'],
                ['?p=canaux',     'Canaux',       'push'],
                ['?p=regie',      'Régie',        'regie'],
                ['?p=blog-admin', 'Mes articles',       'articles'],
            ]],
            ['?p=scan',        'Entrée',        'scan'],
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
       * Trois restent dehors, et pour trois raisons différentes :
       * « Télécharger » va au magasin d'applications ; la confidentialité
       * et les CGU n'ont pas encore été portées et vivent toujours
       * là-bas. Le jour où elles le seront, ces deux lignes suivront.
       */
      $colonnes = [
        'Produit' => [
          ['Les décors',        url('?p=decors')],
          ['Le blog',           url('?p=blog')],
          ['L’application',     url('?p=application')],
          ['Télécharger',       APPLICATION_URL],
        ],
        'Boost' => [
          ['Push',          url('?p=boost-push')],
          ['Régie',         url('?p=boost-regie')],
          ['Liens courts',  url('?p=boost-liens')],
          ['Créer un compte', url('?p=inscription')],
        ],
        'Partenaires' => [
          ['Devenir partenaire', url('?p=partenaires')],
          ['Nos villes',         url('?p=villes')],
          ['Partenariat',        url('?p=contact')],
        ],
        'Wakabi' => [
          ['À propos',        url('?p=a-propos')],
          ['Contact',         url('?p=contact')],
          ['Confidentialité', GUIDE_URL . '/confidentialite.html'],
          ['CGU',             GUIDE_URL . '/cgu.html'],
        ],
      ];
      foreach ($colonnes as $titre => $entrees): ?>
        <div class="pg-col">
          <h4><?= e($titre) ?></h4>
          <div class="pg-liens">
            <?php foreach ($entrees as [$nom, $adresse]): ?>
              <a href="<?= e($adresse) ?>"<?= sortie_externe($adresse) ?>><?= e($nom) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="pg-bas">
      <span class="pg-copy">© <?= date('Y') ?> Wakabileguide.com · Tous droits réservés.
      Fait avec amour.</span>
      <div class="pg-legal">
        <a href="<?= e(GUIDE_URL) ?>/confidentialite.html"<?= sortie_externe(GUIDE_URL) ?>>Politique de confidentialité</a>
        <a href="<?= e(GUIDE_URL) ?>/cgu.html"<?= sortie_externe(GUIDE_URL) ?>>CGU</a>
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
