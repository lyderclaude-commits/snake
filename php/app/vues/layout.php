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
<link rel="stylesheet" href="<?= e(url('public/wakabi.css')) ?>">
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
     * Le menu suit le RÔLE, et se range par intention.
     *
     * L'administration tenait dans un seul déroulant. À dix entrées, il
     * était devenu une liste où l'on cherche : « Relecture » et
     * « Sauvegardes » y voisinaient sans rapport, et il fallait relire les
     * dix pour en trouver une. Trois groupes courts se parcourent d'un
     * coup d'œil parce que chacun répond à une question — qu'est-ce que je
     * publie, à qui je parle, comment tourne la machine.
     *
     * Deux destinations restent HORS des groupes, et c'est délibéré :
     *
     *  - le tableau de bord, parce qu'il est le point de départ et qu'un
     *    point de départ ne se cherche pas dans un tiroir ;
     *  - le contrôle d'entrée, parce qu'il s'utilise debout à une porte,
     *    sur un téléphone, avec une file qui attend. Deux gestes de plus
     *    pour l'ouvrir, ce sont deux gestes répétés à chaque soirée.
     */
    $liens = match ($me['role'] ?? '') {
        'equipe' => [
            ['?p=admin', 'Tableau de bord'],
            ['groupe', 'Contenus', [
                ['?p=catalogue',      'Décors'],
                ['?p=relecture',      'Relecture des décors'],
                ['?p=blog-admin',     'Le blog'],
                ['?p=blog-relecture', 'Relecture du blog'],
            ]],
            ['groupe', 'Audience', [
                ['?p=comptes',   'Comptes'],
                ['?p=regie',     'Régie e-mail'],
                ['?p=diffusion', 'Notifications push'],
                ['?p=liens',     'Liens courts'],
            ]],
            ['?p=scan', 'Entrée'],
            ['groupe', 'Système', [
                ['?p=reglages',    'Réglages'],
                ['?p=sauvegardes', 'Sauvegardes'],
                ['?p=profil',      'Mon profil'],
            ]],
        ],
        'partenaire' => [
            ['?p=partenaire', 'Tableau de bord'],
            ['groupe', 'Promotion', [
                ['?p=liens',      'Liens courts'],
                ['?p=diffusion',  'Notifications push'],
                ['?p=regie',      'Régie e-mail'],
                ['?p=blog-admin', 'Mes articles'],
            ]],
            ['?p=decors', 'Le catalogue'],
            ['?p=profil', 'Mon profil'],
        ],
        'participant' => [
            ['?p=decors', 'Les décors'],
            ['?p=blog',   'Le blog'],
            ['?p=compte', 'Mon compte'],
            ['?p=profil', 'Mon profil'],
        ],
        default => [
            ['?p=decors', 'Les décors'],
            ['?p=blog',   'Le blog'],
        ],
    };

    /**
     * Le groupe « Promotion » ne montre que ce que l'offre donne.
     *
     * Montrer un lien qui mène à « cette page vient avec une autre offre »
     * est une façon de vendre ; le montrer À CHAQUE PAGE en est une de
     * lasser. L'organisateur qui y a droit le voit, les autres le
     * découvrent sur la page des offres — et un groupe qui se viderait
     * entièrement disparaît, plutôt que de rester ouvert sur rien.
     */
    if (($me['role'] ?? '') === 'partenaire') {
        foreach ($liens as $i => $entree) {
            if (($entree[0] ?? '') !== 'groupe' || ($entree[1] ?? '') !== 'Promotion') {
                continue;
            }
            $garde = [];
            foreach ($entree[2] as $sous) {
                $besoin = match ($sous[0]) {
                    '?p=diffusion' => 'telegram_push',
                    '?p=regie' => 'regie',
                    default => null,
                };
                if ($besoin === null || capacite($me, $besoin)) {
                    $garde[] = $sous;
                }
            }
            if ($garde) {
                $liens[$i][2] = $garde;
            } else {
                unset($liens[$i]);
            }
        }
        $liens = array_values($liens);
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

</body>
</html>
