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
<meta name="description" content="<?= e($description ?? 'Créez votre badge et partagez-le. Wakabi Boost, le guide des bons plans.') ?>">
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
        'equipe' => [
            ['?p=admin',     'Tableau de bord'],
            ['?p=catalogue', 'Décors'],
            ['?p=relecture', 'Relecture'],
            ['?p=scan',      'Contrôle d’entrée'],
            ['?p=comptes',   'Comptes'],
        ],
        'partenaire' => [
            ['?p=decors',     'Le catalogue'],
            ['?p=partenaire', 'Mes campagnes'],
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
        <?php foreach ($liens as [$cible, $nom]):
            $actif = str_starts_with($cible, '?p=' . $ici); ?>
          <a href="<?= e(url($cible)) ?>"<?= $actif ? ' aria-current="page"' : '' ?>><?= e($nom) ?></a>
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
