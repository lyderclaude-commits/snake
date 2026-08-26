<?php
/** Gabarit HTML commun. $titre, $contenu et $me sont fournis par le routeur. */
$me = $me ?? utilisateur_courant();
$nonlues = $me ? notifications_non_lues($me['id']) : 0;
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre ?? 'Wakabi Boost') ?></title>
<meta name="description" content="<?= e($description ?? 'Créez votre badge et partagez-le. Wakabi Boost — le guide des bons plans.') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(url('public/wakabi.css')) ?>">
</head>
<body>

<header class="barre">
  <div class="barre-in">
    <a class="marque" href="<?= e(url('')) ?>">WAKABI<span>.</span></a>
    <nav>
      <a href="<?= e(url('?p=decors')) ?>">Décors</a>
      <?php if ($me): ?>
        <?php if ($me['role'] === 'equipe'): ?>
          <a href="<?= e(url('?p=admin')) ?>">Administration</a>
          <a href="<?= e(url('?p=catalogue')) ?>">Décors</a>
          <a href="<?= e(url('?p=relecture')) ?>">Relecture</a>
          <a href="<?= e(url('?p=scan')) ?>">Contrôle d’entrée</a>
        <?php elseif ($me['role'] === 'partenaire'): ?>
          <a href="<?= e(url('?p=partenaire')) ?>">Mes campagnes</a>
        <?php else: ?>
          <a href="<?= e(url('?p=compte')) ?>">Mon compte</a>
        <?php endif; ?>
        <a href="<?= e(url('?p=notifications')) ?>">
          Notifications<?= $nonlues ? ' <b style="color:var(--orange)">' . $nonlues . '</b>' : '' ?>
        </a>
        <form method="post" action="<?= e(url('?p=deconnexion')) ?>" style="display:inline">
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <button class="bouton fant petit" type="submit">Déconnexion</button>
        </form>
      <?php else: ?>
        <a href="<?= e(url('?p=connexion')) ?>">Connexion</a>
        <a class="bouton petit" href="<?= e(url('?p=inscription')) ?>">Créer un compte</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main><?= $contenu ?></main>

<footer>
  <div class="contenu" style="padding-bottom:0">
    WAKABI — <?= e(WAKABI_SIGNATURE) ?> · Lomé · Cotonou · Abidjan
  </div>
</footer>

</body>
</html>
