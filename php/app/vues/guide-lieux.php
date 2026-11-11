<?php
/**
 * Tous les lieux.
 *
 * La liste complète des lieux du guide, des deux villes, avec la
 * recherche et ses sélecteurs : ville, catégorie. La vue liste ou
 * grille voyage dans l'adresse, et la bascule permute sur place :
 * les deux vues sont rendues, l'une cachée.
 *
 * Les catégories se dérivent des données : une nouvelle catégorie qui
 * arrive par l'API gagne son option toute seule.
 */
$q = (string) ($_GET['q'] ?? '');
$cat = api_cle_type((string) ($_GET['cat'] ?? ''));
$ville = in_array($_GET['ville'] ?? '', ['lome', 'cotonou'], true) ? (string) $_GET['ville'] : '';
$vue = ($_GET['vue'] ?? '') === 'grille' ? 'grille' : 'liste';

$tous = api_lieux(null);
$lieux = api_cherche(api_par_categorie($tous, $cat), $q);
if ($ville !== '') {
    $lieux = array_values(array_filter($lieux, fn(array $li) => $li['ville'] === $ville));
}
$categories = api_categories($tous);
$filtres = array_filter(['cat' => $cat, 'ville' => $ville, 'q' => $q]);
$n = count($lieux);
?>
<div class="page-header">
  <div class="container"><div class="page-header-inner">
    <div class="breadcrumb"><a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">></span><span>Tous les lieux</span></div>
    <h1>Tous les lieux</h1>
    <p>Les lieux du guide, à Lomé comme à Cotonou : bars, restaurants, hôtels, plages. Cherchez, filtrez, et trouvez votre bon coin.</p>
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

    <?php
    $recherche_page = 'lieux';
    $recherche_q = $q;
    $recherche_params = [];
    $recherche_placeholder = 'Chercher un lieu, un quartier, une spécialité...';
    $recherche_selects = [
        ['nom' => 'ville', 'libelle' => 'Ville', 'valeur' => $ville,
         'options' => ['' => 'Ville', 'lome' => 'Lomé', 'cotonou' => 'Cotonou']],
        ['nom' => 'cat', 'libelle' => 'Catégorie', 'valeur' => $cat,
         'options' => ['' => 'Catégorie'] + $categories],
    ];
    require __DIR__ . '/partiels/guide-recherche.php';
    ?>

    <div class="guide-zone">
      <div class="rangee-tete">
        <h2 class="headline"><?= $n ?> lieu<?= $n > 1 ? 'x' : '' ?></h2>
        <div class="rangee-outils">
          <?php $bascule_page = 'lieux'; $bascule_cle = 'vue'; $bascule_vue = $vue; $bascule_params = $filtres; require __DIR__ . '/partiels/guide-bascule.php'; ?>
        </div>
      </div>

      <?php if (!$lieux): ?>
        <div class="carte" style="text-align:center;padding:56px 24px">
          <h2 style="font-family:var(--font-display);font-size:1.4rem;font-weight:800;margin:0 0 10px">Aucun lieu trouvé</h2>
          <p class="aide" style="margin:0 auto 18px;max-width:60ch">Aucun lieu ne correspond à cette recherche. Élargissez les filtres, ou revenez bientôt.</p>
          <a class="btn btn-outline" href="<?= e(url('?p=lieux')) ?>">Voir tous les lieux</a>
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

    <div class="encart-ville">
      <h3>Vous gérez un lieu ?</h3>
      <p>Ajoutez-le au guide, publiez vos événements et vendez vos billets. C'est gratuit, et ça prend quelques minutes.</p>
      <a class="btn btn-primary btn-lg" href="<?= e(url('?p=partenaires')) ?>">Devenir Partenaire</a>
    </div>

    <?php endif; ?>

  </div>
</section>
