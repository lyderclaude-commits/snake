<?php
/**
 * Sortir à Cotonou.
 *
 * Le même gabarit que Lomé, avec les données de l'autre ville. Si un
 * jour les deux pages divergent — une section ici, pas là — ce sera
 * le moment de les séparer. En attendant, deux fichiers jumeaux se
 * relisent mieux qu'un fichier à drapeaux.
 *
 * Vitrine, et non catalogue : six événements, sept lieux, et le reste
 * s'ouvre dans l'application. La recherche et les filtres sont partis
 * avec le catalogue, pour la raison dite sur la page de Lomé.
 */
$vue_evts = ($_GET['evts'] ?? '') === 'liste' ? 'liste' : 'grille';
$vue_lieux = ($_GET['lieux'] ?? '') === 'grille' ? 'grille' : 'liste';

$evenements = array_slice(api_evenements('cotonou'), 0, VITRINE_EVENEMENTS);
$lieux = array_slice(api_lieux('cotonou'), 0, VITRINE_LIEUX);
?>
<div class="page-header">
  <div class="container"><div class="page-header-inner">
    <div class="breadcrumb"><a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">></span><span>Cotonou</span></div>
    <h1>Sortir à Cotonou</h1>
    <p>Les lieux qui comptent, les événements qui arrivent : tout ce qui se passe à Cotonou, au même endroit.</p>
  </div></div>
</div>

<section class="section">
  <div class="container">

    <?php if (api_base() === null): ?>
      <div class="carte" style="text-align:center;padding:56px 24px">
        <h2 style="font-family:var(--font-display);font-size:1.4rem;font-weight:800;margin:0 0 10px">Ça arrive bientôt</h2>
        <p class="aide" style="margin:0 auto;max-width:60ch">Les lieux et les événements de Cotonou arrivent ici, en direct de l'application Wakabi. Revenez dans quelques jours.</p>
      </div>
    <?php else: ?>

    <div class="guide-zone">
      <div class="rangee-tete">
        <h2 class="headline headline-md">Événements à venir</h2>
        <div class="rangee-outils">
          <?php $bascule_page = 'sortir-cotonou'; $bascule_cle = 'evts'; $bascule_vue = $vue_evts; $bascule_params = []; require __DIR__ . '/partiels/guide-bascule.php'; ?>
          <a class="btn btn-outline btn-sm" href="<?= e(APPLICATION_URL) ?>"<?= sortie_externe(APPLICATION_URL) ?>>Plus dans l’application</a>
        </div>
      </div>
      <?php if (!$evenements): ?>
        <p class="aide" style="margin:0 0 60px">Aucun événement annoncé pour le moment.</p>
      <?php else: ?>
        <div class="vue-grille"<?= $vue_evts === 'grille' ? '' : ' hidden' ?>>
          <div class="evt-grille" style="margin-bottom:0">
            <?php foreach ($evenements as $ev) require __DIR__ . '/partiels/evt-carte.php'; ?>
          </div>
        </div>
        <div class="vue-liste"<?= $vue_evts === 'liste' ? '' : ' hidden' ?>>
          <?php foreach ($evenements as $ev) require __DIR__ . '/partiels/evt-rangee.php'; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="guide-zone" style="margin-top:60px">
      <div class="rangee-tete">
        <h2 class="headline headline-md">Les lieux</h2>
        <div class="rangee-outils">
          <?php $bascule_page = 'sortir-cotonou'; $bascule_cle = 'lieux'; $bascule_vue = $vue_lieux; $bascule_params = []; require __DIR__ . '/partiels/guide-bascule.php'; ?>
          <a class="btn btn-outline btn-sm" href="<?= e(APPLICATION_URL) ?>"<?= sortie_externe(APPLICATION_URL) ?>>Plus dans l’application</a>
        </div>
      </div>
      <?php if (!$lieux): ?>
        <p class="aide" style="margin:0 0 60px">Aucun lieu publié pour le moment.</p>
      <?php else: ?>
        <div class="vue-liste"<?= $vue_lieux === 'liste' ? '' : ' hidden' ?>>
          <div class="lieu-liste" style="margin-bottom:0">
            <?php foreach ($lieux as $li) require __DIR__ . '/partiels/lieu-rangee.php'; ?>
          </div>
        </div>
        <div class="vue-grille"<?= $vue_lieux === 'grille' ? '' : ' hidden' ?>>
          <div class="lieu-grille" style="margin-bottom:0">
            <?php foreach ($lieux as $li) require __DIR__ . '/partiels/lieu-carte.php'; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="encart-ville">
      <h3>Vous gérez un lieu à Cotonou ?</h3>
      <p>Ajoutez-le au guide, publiez vos événements et vendez vos billets. C'est gratuit, et ça prend quelques minutes.</p>
      <a class="btn btn-primary btn-lg" href="<?= e(url('?p=partenaires')) ?>">Devenir Partenaire</a>
    </div>

    <?php endif; ?>

  </div>
</section>
