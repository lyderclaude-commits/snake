<?php
/**
 * L'agenda.
 *
 * Tous les événements à venir, des deux villes, avec la recherche et
 * ses sélecteurs : période, ville, catégorie. Les filtres voyagent
 * dans l'adresse — un agenda filtré est une adresse qu'on peut coller
 * à un ami. L'ancien paramètre unique `filtre` se lit encore : ses
 * adresses déjà partagées ne meurent pas.
 *
 * Les deux vues (liste, grille) sont rendues toutes les deux, l'une
 * cachée : la bascule permute sur place, sans recharger la page.
 *
 * « Cette semaine » et « ce mois-ci » se comptent en UTC : Lomé et
 * Cotonou sont à GMT+0, le serveur n'a donc pas de fuseau à deviner.
 */
$filtre = (string) ($_GET['filtre'] ?? '');
$periode = (string) ($_GET['periode'] ?? '');
$ville = (string) ($_GET['ville'] ?? '');
if ($filtre !== '' && $periode === '' && $ville === '') {
    if (in_array($filtre, ['semaine', 'mois'], true)) {
        $periode = $filtre;
    }
    if (in_array($filtre, ['lome', 'cotonou'], true)) {
        $ville = $filtre;
    }
}
$periode = in_array($periode, ['semaine', 'mois'], true) ? $periode : '';
$ville = in_array($ville, ['lome', 'cotonou'], true) ? $ville : '';
$q = (string) ($_GET['q'] ?? '');
$cat = api_cle_type((string) ($_GET['cat'] ?? ''));
$vue = ($_GET['vue'] ?? '') === 'grille' ? 'grille' : 'liste';

$tout = api_agenda();
$evenements = api_cherche(api_par_categorie($tout, $cat), $q);
$evenements = array_values(array_filter($evenements, function (array $e) use ($periode, $ville): bool {
    if ($ville !== '' && $e['ville'] !== $ville) {
        return false;
    }
    if ($periode === 'semaine') {
        return $e['date'] !== '' && strtotime($e['date']) <= time() + 7 * 86400;
    }
    if ($periode === 'mois') {
        return $e['date'] !== '' && substr($e['date'], 0, 7) === gmdate('Y-m');
    }
    return true;
}));
$categories = api_categories($tout);
$filtres = array_filter(['periode' => $periode, 'ville' => $ville, 'cat' => $cat, 'q' => $q]);
$n = count($evenements);
?>
<div class="page-header">
  <div class="container"><div class="page-header-inner">
    <div class="breadcrumb"><a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">></span><span>Agenda</span></div>
    <h1>L'agenda</h1>
    <p>Ce qui sort cette semaine et ce mois-ci, à Lomé comme à Cotonou. Cherchez, filtrez, et trouvez votre sortie.</p>
  </div></div>
</div>

<section class="section">
  <div class="container">

    <?php if (api_base() === null): ?>
      <div class="carte" style="text-align:center;padding:56px 24px">
        <h2 style="font-family:var(--font-display);font-size:1.4rem;font-weight:800;margin:0 0 10px">Ça arrive bientôt</h2>
        <p class="aide" style="margin:0 auto;max-width:60ch">L'agenda des sorties arrive ici, en direct de l'application Wakabi. Revenez dans quelques jours.</p>
      </div>
    <?php else: ?>

    <?php
    $recherche_page = 'agenda';
    $recherche_q = $q;
    $recherche_params = [];
    $recherche_placeholder = 'Chercher un événement, un lieu, un quartier...';
    $recherche_selects = [
        ['nom' => 'periode', 'libelle' => 'Période', 'valeur' => $periode,
         'options' => ['' => 'Période', 'semaine' => 'Cette semaine', 'mois' => 'Ce mois-ci']],
        ['nom' => 'ville', 'libelle' => 'Ville', 'valeur' => $ville,
         'options' => ['' => 'Ville', 'lome' => 'Lomé', 'cotonou' => 'Cotonou']],
        ['nom' => 'cat', 'libelle' => 'Catégorie', 'valeur' => $cat,
         'options' => ['' => 'Catégorie'] + $categories],
    ];
    require __DIR__ . '/partiels/guide-recherche.php';
    ?>

    <div class="guide-zone">
      <div class="rangee-tete">
        <h2 class="headline"><?= $n ?> événement<?= $n > 1 ? 's' : '' ?></h2>
        <div class="rangee-outils">
          <?php $bascule_page = 'agenda'; $bascule_cle = 'vue'; $bascule_vue = $vue; $bascule_params = $filtres; require __DIR__ . '/partiels/guide-bascule.php'; ?>
        </div>
      </div>

      <?php if (!$evenements): ?>
        <div class="carte" style="text-align:center;padding:56px 24px">
          <h2 style="font-family:var(--font-display);font-size:1.4rem;font-weight:800;margin:0 0 10px">Rien de prévu ici</h2>
          <p class="aide" style="margin:0 auto 18px;max-width:60ch">Aucun événement ne correspond à cette recherche. Élargissez les filtres, ou revenez bientôt.</p>
          <a class="btn btn-outline" href="<?= e(url('?p=agenda')) ?>">Voir tout l'agenda</a>
        </div>
      <?php else: ?>

      <div class="vue-liste"<?= $vue === 'liste' ? '' : ' hidden' ?>>
        <?php $mois_courant = null; ?>
        <?php foreach ($evenements as $ev): ?>
          <?php
          $mois = api_date_mois_complet($ev['date']);
          if ($mois === '') {
              $mois = 'À venir';
          }
          if ($mois !== $mois_courant):
              $mois_courant = $mois; ?>
            <div class="agenda-mois"><?= e($mois) ?></div>
          <?php endif; ?>
          <?php require __DIR__ . '/partiels/evt-rangee.php'; ?>
        <?php endforeach; ?>
      </div>

      <div class="vue-grille"<?= $vue === 'grille' ? '' : ' hidden' ?>>
        <div class="evt-grille" style="margin-bottom:0">
          <?php foreach ($evenements as $ev) require __DIR__ . '/partiels/evt-carte.php'; ?>
        </div>
      </div>

      <?php endif; ?>
    </div>

    <div class="encart-ville">
      <h3>Un événement à ajouter ?</h3>
      <p>Organisateurs, lieux, associations : publiez votre événement dans l'agenda, gratuitement. Il paraît aussi dans les pages de sa ville.</p>
      <a class="btn btn-primary btn-lg" href="<?= e(url('?p=contact')) ?>">Publier un événement</a>
    </div>

    <?php endif; ?>

  </div>
</section>
