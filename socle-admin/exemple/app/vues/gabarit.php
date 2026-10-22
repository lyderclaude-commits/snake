<?php /** Le gabarit des pages publiques du site exemple. */ ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(isset($titre) ? $titre . ' · ' . reglage('nom_site') : reglage('nom_site') . ' — ' . reglage('slogan')) ?></title>
<?php if (reglage('meta_description') !== ''): ?><meta name="description" content="<?= e(reglage('meta_description')) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(actif('assets/site.css')) ?>">
</head>
<body>
<header class="tete">
  <a class="logo" href="<?= url('') ?>"><?= e(reglage('nom_site')) ?></a>
  <nav>
    <a href="<?= url('') ?>">Accueil</a>
    <a href="<?= url('blog') ?>">Blog</a>
    <a href="<?= url('contact') ?>">Contact</a>
  </nav>
</header>
<main><?= $contenu ?></main>
<footer class="pied">
  <span><?= e(reglage('nom_site')) ?> · <?= e(reglage('adresse')) ?></span>
  <a href="mailto:<?= e(reglage('email')) ?>"><?= e(reglage('email')) ?></a>
  <span><?= e(reglage('telephone')) ?></span>
</footer>
</body>
</html>
