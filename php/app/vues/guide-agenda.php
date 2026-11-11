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
$vue = ($_GET['vue'] ?? '') === 'grille' ? 'grille' : 'liste';

/* Les six qui arrivent le plus tôt : `api_agenda()` écarte ce qui est
   passé et trie par date, le découpage n'a donc qu'à prendre le début. */
$evenements = array_slice(api_agenda(), 0, VITRINE_EVENEMENTS);
$n = count($evenements);
?>
<div class="page-header">
  <div class="container"><div class="page-header-inner">
    <div class="breadcrumb"><a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">></span><span>Agenda</span></div>
    <h1>L'agenda</h1>
    <p>Les six prochaines sorties, à Lomé comme à Cotonou. L’agenda complet, mois par mois, est dans l’application.</p>
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

    <div class="guide-zone">
      <div class="rangee-tete">
        <h2 class="headline"><?= $n ?> événement<?= $n > 1 ? 's' : '' ?></h2>
        <div class="rangee-outils">
          <?php $bascule_page = 'agenda'; $bascule_cle = 'vue'; $bascule_vue = $vue; $bascule_params = []; require __DIR__ . '/partiels/guide-bascule.php'; ?>
        </div>
      </div>

      <?php if (!$evenements): ?>
        <div class="carte" style="text-align:center;padding:56px 24px">
          <h2 style="font-family:var(--font-display);font-size:1.4rem;font-weight:800;margin:0 0 10px">Rien d’annoncé pour l’instant</h2>
          <p class="aide" style="margin:0 auto 18px;max-width:60ch">Aucune date n’est publiée ici en ce moment. L’application tient l’agenda complet, au jour le jour.</p>
          <a class="btn btn-outline" href="<?= e(APPLICATION_URL) ?>"<?= sortie_externe(APPLICATION_URL) ?>>Ouvrir l’application</a>
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

    <?php
    $plus_titre = 'Six dates ici, tout l’agenda dans l’application';
    $plus_texte = 'Le site annonce les prochaines sorties. Le mois entier, ville par ville, '
        . 'avec les rappels et les billets, se lit dans l’application Wakabi.';
    require __DIR__ . '/partiels/guide-plus-app.php';
    ?>

    <div class="encart-ville" style="margin-top:24px">
      <h3>Un événement à ajouter ?</h3>
      <p>Organisateurs, lieux, associations : publiez votre événement dans l'agenda, gratuitement. Il paraît aussi dans les pages de sa ville.</p>
      <a class="btn btn-primary btn-lg" href="<?= e(url('?p=contact')) ?>">Publier un événement</a>
    </div>

    <?php endif; ?>

  </div>
</section>
