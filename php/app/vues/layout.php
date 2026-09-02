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
 * Les balises de partage.
 *
 * Un lien collé dans WhatsApp arrivait nu : un titre gris, rien d'autre.
 * Or c'est par ce lien que tout circule — c'est la boucle qui remplit la
 * salle. Une vignette et un titre valent ici plus que bien des écrans.
 *
 * `og:url` porte l'adresse CANONIQUE : sans elle, un lien recopié avec des
 * paramètres de suivi se partage comme une page différente, et les
 * compteurs de partage repartent de zéro à chaque copie.
 */
$_desc = $description ?? 'Créez votre badge et partagez-le. Wakabi Boost, le guide des bons plans.';
$_ogt = $og_titre ?? ($titre ?? 'Wakabi Boost');
$_ogi = $og_image ?? url_og();
$_ogu = base_url() . '/index.php?p=' . rawurlencode((string) ($_GET['p'] ?? 'accueil'))
      . (isset($_GET['slug']) ? '&slug=' . rawurlencode((string) $_GET['slug']) : '');
?>
<meta name="description" content="<?= e($_desc) ?>">
<link rel="canonical" href="<?= e($_ogu) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Wakabi Boost">
<meta property="og:locale" content="fr_FR">
<meta property="og:title" content="<?= e($_ogt) ?>">
<meta property="og:description" content="<?= e($_desc) ?>">
<meta property="og:url" content="<?= e($_ogu) ?>">
<meta property="og:image" content="<?= e($_ogi) ?>">
<meta property="og:image:width" content="<?= OG_LARGEUR ?>">
<meta property="og:image:height" content="<?= OG_HAUTEUR ?>">
<meta property="og:image:alt" content="<?= e($_ogt) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($_ogt) ?>">
<meta name="twitter:description" content="<?= e($_desc) ?>">
<meta name="twitter:image" content="<?= e($_ogi) ?>">
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
        default => [
            ['?p=decors', 'Les décors', null],
            ['?p=blog',   'Le blog',    null],
        ],
    };

    /**
     * Le filtre : le droit du rôle ET, pour un client, ce que son offre
     * comprend.
     *
     * Les deux conditions sont distinctes et il faut les deux. Un éditeur
     * n'a pas le droit d'écrire à la base, quelle que soit son offre — il
     * n'en a pas. Un organisateur en Découverte a le droit, mais n'a pas
     * acheté la fonction. Montrer un lien qui mène à un refus est une
     * façon de vendre ; le montrer à chaque page en est une de lasser.
     */
    $permis = function (?string $besoin) use ($me): bool {
        if ($besoin === null) {
            return true;
        }
        if (!droit($me, $besoin)) {
            return false;
        }
        return match ($besoin) {
            'regie' => capacite($me, 'regie'),
            'push' => capacite($me, 'telegram_push'),
            'liens' => quota($me, 'liens_courts') !== 0,
            default => true,
        };
    };

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
    $lien = function (string $cible, string $nom) use ($courante): string {
        return '<a href="' . e(url($cible)) . '"' . ($courante($cible) ? ' aria-current="page"' : '') . '>'
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

<main><?= $contenu ?></main>

<footer>
  <div class="contenu pied">
    <a href="<?= e(url('')) ?>" aria-label="Wakabi Boost, accueil"><?= logo_wakabi('logo pied-logo') ?></a>
    <span><?= e(WAKABI_SIGNATURE) ?> · Lomé · Cotonou · Abidjan</span>
  </div>
</footer>

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
