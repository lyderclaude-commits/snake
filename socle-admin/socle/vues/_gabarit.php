<?php
/**
 * Le gabarit de l'administration : la barre de Wakabi, à l'identique —
 * logo à gauche, destinations à droite, groupes repliés derrière un
 * intitulé, et un menu `<details>` qui s'ouvre sans script sur téléphone.
 * Le menu se construit d'après les modules et les collections du site.
 */
$_ici = chemin_demande();
$_est = fn(string $r): bool => $r === 'admin' ? $_ici === 'admin' : ($_ici === $r || str_starts_with($_ici, $r . '/'));
$_cour = fn(string $r): string => $_est($r) ? ' aria-current="page"' : '';
$_chev = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>';
$_nonlus = $me ? messages_non_lus() : 0;
$_couleur = marque_couleur();
$_css_site = (string) def('marque.css', '');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="<?= e($_couleur) ?>">
<title><?= e(($titre ?? 'Administration') . ' · ' . reglage('nom_site')) ?></title>
<link rel="icon" href="<?= e(actif((string) def('marque.favicon', 'socle/assets/favicon.svg'))) ?>">
<link rel="stylesheet" href="<?= e(actif('socle/assets/admin.css')) ?>">
<?php if (strtolower($_couleur) !== '#2563eb'): ?>
<style>:root{--primary:<?= e($_couleur) ?>;--primary-d:color-mix(in srgb,<?= e($_couleur) ?> 80%,#000);--primary-wash:color-mix(in srgb,<?= e($_couleur) ?> 8%,#fff)}</style>
<?php endif; ?>
<?php if ($_css_site !== ''): ?><link rel="stylesheet" href="<?= e(actif($_css_site)) ?>"><?php endif; ?>
<?php /* Le manifeste : sur iPhone, les notifications n'existent que pour un site ajouté à l'écran d'accueil. */ ?>
<link rel="manifest" href="<?= e(url('admin/manifest.json')) ?>">
<link rel="apple-touch-icon" href="<?= e(url(marque_icone())) ?>">
<script src="<?= e(actif('socle/assets/admin.js')) ?>" defer></script>
</head>
<body data-csrf="<?= e(csrf_jeton()) ?>" data-base="<?= e(base_chemin()) ?>">

<?php if (empty($nu)): ?>
<header class="barre">
  <div class="barre-in">
    <a class="marque" href="<?= url('admin') ?>" aria-label="Administration, tableau de bord">
      <?= marque_logo() ?><small>Admin</small>
    </a>
    <details class="menu">
      <summary aria-label="Menu">
        <svg class="ouvrir" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        <svg class="fermer" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </summary>
      <nav>
        <a href="<?= url('admin') ?>"<?= $_cour('admin') ?>>Tableau de bord</a>
        <?php foreach (admin_menu($me) as $_groupe => $_liens):
            $_actif = (bool) array_filter($_liens, fn($l) => $_est($l[0])); ?>
        <details class="deroulant">
          <summary<?= $_actif ? ' aria-current="true"' : '' ?>><?= e($_groupe) ?><?= $_chev ?></summary>
          <div class="volet">
            <?php foreach ($_liens as [$_r, $_t, $_a]): ?>
              <a href="<?= url($_r) ?>"<?= $_cour($_r) ?>><?= e($_t) ?><?php if ($_a !== ''): ?><small><?= e($_a) ?></small><?php endif; ?></a>
            <?php endforeach; ?>
          </div>
        </details>
        <?php if ($_groupe === 'Contenus' && module('messages')): ?>
        <a href="<?= url('admin/messages') ?>"<?= $_cour('admin/messages') ?>>Messages<?php if ($_nonlus): ?><span class="compteur" aria-label="<?= $_nonlus ?> non lus"><?= $_nonlus ?></span><?php endif; ?></a>
        <?php endif; ?>
        <?php endforeach; ?>
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
  <?php if (def('plan_du_site', true)): ?><a href="<?= url('sitemap.xml') ?>">Plan du site</a><?php endif; ?>
</footer>
<?php else: ?>
<main class="connexion-page"><?= $contenu ?></main>
<?php endif; ?>
</body>
</html>
