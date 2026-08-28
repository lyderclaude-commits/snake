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
    $liens = match ($me['role'] ?? '') {
        // Cinq destinations d'administration alignées dans la barre la
        // remplissaient d'un bout à l'autre. Elles tiennent maintenant dans
        // un seul groupe qui se déplie ; les notifications et la déconnexion
        // restent dehors, ce sont les deux gestes qu'on ne veut pas chercher.
        'equipe' => [
            ['groupe', 'Administration', [
                ['?p=admin',     'Tableau de bord'],
                ['?p=catalogue', 'Décors'],
                ['?p=relecture', 'Relecture'],
                ['?p=scan',      'Contrôle d’entrée'],
                ['?p=comptes',   'Comptes'],
                ['?p=reglages',  'Réglages'],
                ['?p=liens',     'Liens courts'],
                ['?p=sauvegardes', 'Sauvegardes'],
            ]],
        ],
        'partenaire' => [
            ['?p=decors',     'Le catalogue'],
            ['?p=partenaire', 'Mes campagnes'],
            ['?p=liens',      'Liens courts'],
        ],
        'participant' => [
            ['?p=decors', 'Les décors'],
            ['?p=compte', 'Mon compte'],
        ],
        default => [
            ['?p=decors', 'Les décors'],
        ],
    };
    $ici = (string) ($_GET['p'] ?? 'accueil');

    /** Un lien de menu, marqué s'il désigne la page courante. */
    $lien = function (string $cible, string $nom) use ($ici): string {
        $actif = str_starts_with($cible, '?p=' . $ici);
        return '<a href="' . e(url($cible)) . '"' . ($actif ? ' aria-current="page"' : '') . '>'
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
                  $dedans = $dedans || str_starts_with($c, '?p=' . $ici);
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
