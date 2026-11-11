<?php
/**
 * Sortir à Lomé.
 *
 * Les données ne viennent pas de la base locale : elles arrivent de
 * l'API du backend officiel de l'application, lue par `app/guide-api.php`.
 * Sans API réglée, ou si elle ne répond pas, la page reste debout avec
 * son état vide — jamais une erreur blanche au milieu du guide.
 *
 * La recherche porte sur les deux sections à la fois, le sélecteur de
 * catégorie aussi ; les options se dérivent des données, pas d'une
 * liste écrite à la main. Chaque section rend ses deux vues (liste,
 * grille), l'une cachée : la bascule permute sur place, sans recharger.
 */
$type = api_cle_type((string) ($_GET['type'] ?? ''));
$q = (string) ($_GET['q'] ?? '');
$vue_evts = ($_GET['evts'] ?? '') === 'liste' ? 'liste' : 'grille';
$vue_lieux = ($_GET['lieux'] ?? '') === 'grille' ? 'grille' : 'liste';

$tous_evts = api_evenements('lome');
$tous_lieux = api_lieux('lome');
$evenements = api_cherche(api_par_categorie($tous_evts, $type), $q);
$lieux = api_cherche(api_par_categorie($tous_lieux, $type), $q);
$types = api_categories(array_merge($tous_evts, $tous_lieux));
?>
<div class="page-header">
  <div class="container"><div class="page-header-inner">
    <div class="breadcrumb"><a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">></span><span>Lomé</span></div>
    <h1>Sortir à Lomé</h1>
    <p>Les lieux qui comptent, les événements qui arrivent : tout ce qui se passe à Lomé, au même endroit.</p>
  </div></div>
</div>

<section class="section">
  <div class="container">

    <?php if (api_base() === null): ?>
      <div class="carte" style="text-align:center;padding:56px 24px">
        <h2 style="font-family:var(--font-display);font-size:1.4rem;font-weight:800;margin:0 0 10px">Ça arrive bientôt</h2>
        <p class="aide" style="margin:0 auto;max-width:60ch">Les lieux et les événements de Lomé arrivent ici, en direct de l'application Wakabi. Revenez dans quelques jours.</p>
      </div>
    <?php else: ?>

    <?php
    $recherche_page = 'sortir-lome';
    $recherche_q = $q;
    $recherche_params = [];
    $recherche_placeholder = 'Chercher un lieu, un événement, un quartier...';
    $recherche_selects = [
        ['nom' => 'type', 'libelle' => 'Catégorie', 'valeur' => $type,
         'options' => ['' => 'Catégorie'] + $types],
    ];
    require __DIR__ . '/partiels/guide-recherche.php';
    ?>

    <?php if ($q !== '' && !$evenements && !$lieux): ?>
      <div class="carte" style="text-align:center;padding:56px 24px">
        <h2 style="font-family:var(--font-display);font-size:1.4rem;font-weight:800;margin:0 0 10px">Aucun résultat</h2>
        <p class="aide" style="margin:0 auto 18px;max-width:60ch">Rien ne correspond à « <?= e($q) ?> » à Lomé. Essayez un autre mot, ou élargissez les filtres.</p>
        <a class="btn btn-outline" href="<?= e(url('?p=sortir-lome')) ?>">Tout afficher</a>
      </div>
    <?php else: ?>

    <div class="guide-zone">
      <div class="rangee-tete">
        <h2 class="headline headline-md">Événements à venir</h2>
        <div class="rangee-outils">
          <?php $bascule_page = 'sortir-lome'; $bascule_cle = 'evts'; $bascule_vue = $vue_evts; $bascule_params = array_filter(['type' => $type, 'q' => $q]); require __DIR__ . '/partiels/guide-bascule.php'; ?>
          <a class="btn btn-outline btn-sm" href="<?= e(url('?p=agenda&ville=lome')) ?>">Voir tout</a>
        </div>
      </div>
      <?php if (!$evenements): ?>
        <p class="aide" style="margin:0 0 60px">Aucun événement dans cette catégorie pour le moment.</p>
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
          <?php $bascule_page = 'sortir-lome'; $bascule_cle = 'lieux'; $bascule_vue = $vue_lieux; $bascule_params = array_filter(['type' => $type, 'q' => $q]); require __DIR__ . '/partiels/guide-bascule.php'; ?>
          <a class="btn btn-outline btn-sm" href="<?= e(url('?p=lieux')) ?>">Tous les lieux</a>
        </div>
      </div>
      <?php if (!$lieux): ?>
        <p class="aide" style="margin:0 0 60px">Aucun lieu dans cette catégorie pour le moment.</p>
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
      <h3>Vous gérez un lieu à Lomé ?</h3>
      <p>Ajoutez-le au guide, publiez vos événements et vendez vos billets. C'est gratuit, et ça prend quelques minutes.</p>
      <a class="btn btn-primary btn-lg" href="<?= e(url('?p=partenaires')) ?>">Devenir Partenaire</a>
    </div>

    <?php endif; ?>

    <?php endif; ?>

  </div>
</section>
