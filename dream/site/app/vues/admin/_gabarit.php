<?php
/**
 * Le gabarit de l'administration : la barre de Wakabi, à l'identique —
 * logo à gauche, destinations à droite, groupes repliés derrière un
 * intitulé, et un menu `<details>` qui s'ouvre sans script sur téléphone.
 */
$_ici = chemin_demande();
$_est = fn(string $r): bool => $r === 'admin' ? $_ici === 'admin' : str_starts_with($_ici, $r);
$_cour = fn(string $r): string => $_est($r) ? ' aria-current="page"' : '';
$_chev = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>';
$_nonlus = $me ? messages_non_lus() : 0;
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#2563EB">
<title><?= e(($titre ?? 'Administration') . ' · ' . reglage('nom_site')) ?></title>
<link rel="icon" href="<?= e(actif('assets/images/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(actif('assets/css/admin.css')) ?>">
<script src="<?= e(actif('assets/js/admin.js')) ?>" defer></script>
</head>
<body data-csrf="<?= e(csrf_jeton()) ?>" data-base="<?= e(base_chemin()) ?>">

<?php if (empty($nu)): ?>
<header class="barre">
  <div class="barre-in">
    <a class="marque" href="<?= url('admin') ?>" aria-label="Administration, tableau de bord">
      <span class="logo-lyd">LIVE<span>Y</span>DREAM</span><small>Admin</small>
    </a>
    <details class="menu">
      <summary aria-label="Menu">
        <svg class="ouvrir" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        <svg class="fermer" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </summary>
      <nav>
        <a href="<?= url('admin') ?>"<?= $_cour('admin') ?>>Tableau de bord</a>
        <details class="deroulant">
          <summary<?= $_est('admin/articles') || $_est('admin/campagnes') || $_est('admin/medias') ? ' aria-current="true"' : '' ?>>Contenus<?= $_chev ?></summary>
          <div class="volet">
            <a href="<?= url('admin/articles') ?>"<?= $_cour('admin/articles') ?>>Articles<small>Le blog</small></a>
            <a href="<?= url('admin/campagnes') ?>"<?= $_cour('admin/campagnes') ?>>Campagnes<small>Le portfolio</small></a>
            <a href="<?= url('admin/medias') ?>"<?= $_cour('admin/medias') ?>>Médiathèque<small>Toutes les images</small></a>
          </div>
        </details>
        <a href="<?= url('admin/messages') ?>"<?= $_cour('admin/messages') ?>>Messages<?php if ($_nonlus): ?><span class="compteur" aria-label="<?= $_nonlus ?> non lus"><?= $_nonlus ?></span><?php endif; ?></a>
        <?php if (est_admin($me)): ?>
        <details class="deroulant">
          <summary<?= $_est('admin/reglages') || $_est('admin/comptes') || $_est('admin/import') ? ' aria-current="true"' : '' ?>>Système<?= $_chev ?></summary>
          <div class="volet">
            <a href="<?= url('admin/reglages') ?>"<?= $_cour('admin/reglages') ?>>Réglages<small>Coordonnées, accueil, SEO</small></a>
            <a href="<?= url('admin/comptes') ?>"<?= $_cour('admin/comptes') ?>>Comptes<small>Qui peut publier</small></a>
            <a href="<?= url('admin/import') ?>"<?= $_cour('admin/import') ?>>Import WordPress<small>Articles et images</small></a>
          </div>
        </details>
        <?php endif; ?>
        <a href="<?= url('') ?>" target="_blank" rel="noopener">Voir le site</a>
        <span class="sep" aria-hidden="true"></span>
        <a href="<?= url('admin/profil') ?>"<?= $_cour('admin/profil') ?>>Mon profil</a>
        <form method="post" action="<?= url('admin/deconnexion') ?>">
          <?= csrf_champ() ?>
          <button class="bouton fant petit" type="submit">Déconnexion</button>
        </form>
      </nav>
    </details>
  </div>
</header>
<?php endif; ?>

<?php if (empty($nu)): ?>
<main class="<?= e($classe_main ?? 'contenu') ?>">
  <?php foreach (flashes() as [$_t, $_m]): ?>
    <div class="msg <?= e($_t) ?>" role="<?= $_t === 'err' ? 'alert' : 'status' ?>" style="margin-top:20px"><?= e($_m) ?></div>
  <?php endforeach; ?>
  <?= $contenu ?>
</main>
<footer class="pied">
  <span><?= e(reglage('nom_site')) ?> · administration</span>
  <a href="<?= url('') ?>">Le site</a>
  <a href="<?= url('sitemap.xml') ?>">Plan du site</a>
</footer>
<?php else: ?>
<main class="connexion-page"><?= $contenu ?></main>
<?php endif; ?>
</body>
</html>
