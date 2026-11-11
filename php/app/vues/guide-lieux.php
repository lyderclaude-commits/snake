<?php
/**
 * Les lieux, en vitrine.
 *
 * SEPT lieux, pas un de plus — `VITRINE_LIEUX`, écrit dans
 * `guide-api.php`. Le catalogue complet vit dans l'application, et la
 * page se ferme sur sa porte.
 *
 * La page s'appelait « Tous les lieux » et montrait tout, avec une
 * recherche et deux sélecteurs. Les deux sont partis avec le
 * catalogue : chercher dans sept entrées n'aide personne, et un titre
 * qui promet « tous » au-dessus de sept lignes est un mensonge qu'on
 * se raconte à soi-même.
 *
 * La vue liste ou grille voyage toujours dans l'adresse : elle change
 * la façon de regarder, pas ce qu'on regarde.
 */
$vue = ($_GET['vue'] ?? '') === 'grille' ? 'grille' : 'liste';

$lieux = array_slice(api_lieux(null), 0, VITRINE_LIEUX);
$n = count($lieux);
?>
<div class="page-header">
  <div class="container"><div class="page-header-inner">
    <div class="breadcrumb"><a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">></span><span>Les lieux</span></div>
    <h1>Les lieux</h1>
    <p>Quelques bons coins du guide, à Lomé comme à Cotonou : bars, restaurants, hôtels, plages. La liste entière est dans l’application.</p>
  </div></div>
</div>

<section class="section">
  <div class="container">

    <?php if (api_base() === null): ?>
      <div class="carte" style="text-align:center;padding:56px 24px">
        <h2 style="font-family:var(--font-display);font-size:1.4rem;font-weight:800;margin:0 0 10px">Ça arrive bientôt</h2>
        <p class="aide" style="margin:0 auto;max-width:60ch">Les lieux du guide arrivent ici, en direct de l'application Wakabi. Revenez dans quelques jours.</p>
      </div>
    <?php else: ?>

    <div class="guide-zone">
      <div class="rangee-tete">
        <h2 class="headline"><?= $n ?> lieu<?= $n > 1 ? 'x' : '' ?></h2>
        <div class="rangee-outils">
          <?php $bascule_page = 'lieux'; $bascule_cle = 'vue'; $bascule_vue = $vue; $bascule_params = []; require __DIR__ . '/partiels/guide-bascule.php'; ?>
        </div>
      </div>

      <?php if (!$lieux): ?>
        <div class="carte" style="text-align:center;padding:56px 24px">
          <h2 style="font-family:var(--font-display);font-size:1.4rem;font-weight:800;margin:0 0 10px">Aucun lieu pour le moment</h2>
          <p class="aide" style="margin:0 auto 18px;max-width:60ch">Le guide n’a encore rien publié ici. L’application, elle, est déjà pleine.</p>
          <a class="btn btn-outline" href="<?= e(APPLICATION_URL) ?>"<?= sortie_externe(APPLICATION_URL) ?>>Ouvrir l’application</a>
        </div>
      <?php else: ?>

      <div class="vue-liste"<?= $vue === 'liste' ? '' : ' hidden' ?>>
        <div class="lieu-liste" style="margin-bottom:0">
          <?php foreach ($lieux as $li) require __DIR__ . '/partiels/lieu-rangee.php'; ?>
        </div>
      </div>

      <div class="vue-grille"<?= $vue === 'grille' ? '' : ' hidden' ?>>
        <div class="lieu-grille" style="margin-bottom:0">
          <?php foreach ($lieux as $li) require __DIR__ . '/partiels/lieu-carte.php'; ?>
        </div>
      </div>

      <?php endif; ?>
    </div>

    <?php
    $plus_titre = 'Sept lieux ici, tout le guide dans l’application';
    $plus_texte = 'Le site en montre un aperçu. Les bars, les restaurants, les hôtels et '
        . 'les plages des deux villes, avec leurs horaires et leurs photos, vivent dans '
        . 'l’application Wakabi.';
    require __DIR__ . '/partiels/guide-plus-app.php';
    ?>

    <div class="encart-ville" style="margin-top:24px">
      <h3>Vous gérez un lieu ?</h3>
      <p>Ajoutez-le au guide, publiez vos événements et vendez vos billets. C'est gratuit, et ça prend quelques minutes.</p>
      <a class="btn btn-primary btn-lg" href="<?= e(url('?p=partenaires')) ?>">Devenir Partenaire</a>
    </div>

    <?php endif; ?>

  </div>
</section>
