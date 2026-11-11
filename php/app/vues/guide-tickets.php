<?php
/**
 * La billetterie.
 *
 * Les événements dont les billets sont en vente, lus dans l'API du
 * backend officiel. La page affiche la liste ; le panneau d'achat
 * s'ouvre pour l'événement choisi explicitement (`?e=<id>`, carte
 * cliquée dans la liste) — à condition que l'API décrive ses
 * catégories de billets. Le paiement en ligne, lui, n'est pas encore
 * branché : les boutons se montent grisés, avec le chemin de traverse
 * vers l'application, où l'achat marche déjà. Le jour où le backend
 * encaisse, les deux spans deviennent des liens, sans rien redessiner.
 */
$en_vente = api_en_vente();
$id = (string) ($_GET['e'] ?? '');
$choisi = null;
foreach ($en_vente as $ev) {
    if ($ev['id'] !== '' && $ev['id'] === $id) {
        $choisi = $ev;
        break;
    }
}
/* Le panneau ne s'ouvre que sur un choix explicite (`?e=<id>`).
   Sans choix, la page affiche la liste seule. L'événement affiché
   dans le panneau sort de la liste : il est déjà mis en avant
   au-dessus, inutile de le doubler. */
if ($choisi !== null) {
    $en_vente = array_values(array_filter($en_vente,
        function ($ev) use ($choisi) {
            return $ev !== $choisi
                && ($choisi['id'] === '' || $ev['id'] !== $choisi['id']);
        }));
}
$tiers = [];
if ($choisi !== null && is_array($choisi['categories'])) {
    foreach ($choisi['categories'] as $t) {
        if (!is_array($t)) {
            continue;
        }
        $nom = api_champ($t, ['nom', 'name', 'titre', 'title']);
        if ($nom === '') {
            continue;
        }
        $tiers[] = [
            'nom' => $nom,
            'prix' => (int) (api_champ($t, ['prix', 'price', 'montant']) ?: 0),
            'detail' => api_champ($t, ['description', 'detail', 'info', 'details']),
        ];
    }
}
?>
<div class="page-header">
  <div class="container"><div class="page-header-inner">
    <div class="breadcrumb"><a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">></span><span>Billetterie</span></div>
    <h1>La billetterie</h1>
    <p>Choisissez votre place, payez par Mobile Money, recevez votre billet avec son QR, sur WhatsApp, en une minute.</p>
  </div></div>
</div>

<section class="section">
  <div class="container">

    <?php if (api_base() === null): ?>
      <div class="carte" style="text-align:center;padding:56px 24px">
        <h2 style="font-family:var(--font-display);font-size:1.4rem;font-weight:800;margin:0 0 10px">Ça arrive bientôt</h2>
        <p class="aide" style="margin:0 auto;max-width:60ch">La billetterie web arrive ici, en direct de l'application Wakabi. Revenez dans quelques jours.</p>
      </div>
    <?php else: ?>

    <?php if ($choisi !== null && $tiers): ?>
    <div class="rangee-tete">
      <h2 class="headline headline-md">Votre billet pour <?= e($choisi['titre']) ?></h2>
    </div>
    <div class="billet-panneau">
      <div class="billet-visuel <?= api_visuel($choisi['titre']) ?>">
        <?php if ($choisi['image'] !== ''): ?>
          <img src="<?= e($choisi['image']) ?>" alt="" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">
        <?php else: ?>
          <span class="billet-initiale"><?= e(api_initiale($choisi['titre'])) ?></span>
        <?php endif; ?>
        <?php if ($choisi['date'] !== ''): ?>
          <span class="evt-date"><b><?= e(api_date_jour($choisi['date'])) ?></b><span><?= e(api_date_mois($choisi['date'])) ?></span></span>
        <?php endif; ?>
      </div>
      <div class="billet-corps">
        <h2><?= e($choisi['titre']) ?></h2>
        <?php if ($choisi['date'] !== ''): ?>
          <p class="billet-quand"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="16" rx="2.5"></rect><line x1="3" y1="10" x2="21" y2="10"></line><line x1="8" y1="3" x2="8" y2="7"></line><line x1="16" y1="3" x2="16" y2="7"></line></svg><?= e(api_date_jour($choisi['date'])) ?> <?= e(mb_strtolower(api_date_mois_complet($choisi['date']))) ?><?= api_date_heure($choisi['date']) !== '' ? ' · ' . e(api_date_heure($choisi['date'])) : '' ?></p>
        <?php endif; ?>
        <?php if ($choisi['lieu'] !== ''): ?>
          <p class="billet-quand"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"></path><circle cx="12" cy="10" r="2.6"></circle></svg><?= e($choisi['lieu']) ?><?= $choisi['ville'] !== '' ? ', ' . e(ucfirst($choisi['ville'])) : '' ?></p>
        <?php endif; ?>

        <div class="billet-tiers">
          <?php foreach ($tiers as $i => $t): ?>
            <div class="billet-tier<?= $i === 0 ? ' choisi' : '' ?>">
              <span class="billet-radio"></span>
              <span class="billet-nom"><b><?= e($t['nom']) ?></b><?php if ($t['detail'] !== ''): ?><small><?= e($t['detail']) ?></small><?php endif; ?></span>
              <span class="billet-prix"><?= e(api_prix($t['prix']) ?: 'Prix à venir') ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="billet-total">
          <span>Total à payer</span>
          <b><?= e(api_prix($tiers[0]['prix']) ?: '—') ?></b>
        </div>

        <span class="btn btn-primary btn-lg" aria-disabled="true" style="opacity:.55;cursor:default">Payer par T-Money / Flooz</span>
        <span class="btn btn-outline btn-lg" aria-disabled="true" style="opacity:.55;cursor:default;margin-left:12px">Payer par carte</span>

        <div class="billet-note">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="3"></rect><path d="M8 9h2M8 13h2M8 17h2M13 9h3M13 13h3M13 17h3"></path></svg>
          <span>Le paiement en ligne arrive bientôt sur le guide. En attendant, vos billets s'achètent dans l'application Wakabi, et votre billet avec son QR arrive sur WhatsApp. <a href="<?= e(url('?p=application')) ?>">Ouvrir l'application</a>.</span>
        </div>
      </div>
    </div>

    <?php endif; ?>

    <?php if ($en_vente): ?>
    <div class="rangee-tete">
      <h2 class="headline headline-sm">En vente en ce moment</h2>
    </div>
    <div class="evt-grille">
      <?php foreach (array_slice($en_vente, 0, VITRINE_EVENEMENTS) as $ev): ?>
        <?php $ev_etat = is_array($ev['categories']) ? 'En vente' : 'Bientôt'; $ev_avec_date = true; $ev_compte = is_array($ev['categories']) ? count($ev['categories']) : 0; ?>
        <?php require __DIR__ . '/partiels/evt-carte.php'; ?>
      <?php endforeach; ?>
    </div>
    <?php elseif ($choisi === null): ?>
      <p class="aide" style="margin:0 0 60px">Aucun billet en vente pour le moment. Revenez bientôt, ou ouvrez la billetterie de votre propre événement.</p>
    <?php endif; ?>

    <div class="rangee-tete centre">
      <h2 class="headline headline-sm">Votre billet, en trois gestes</h2>
    </div>

    <div class="steps-grid" style="grid-template-columns:repeat(3,1fr)">
      <div class="step-card">
        <div class="step-num">1</div>
        <h3 class="step-title">Choisissez</h3>
        <p class="step-desc">L'événement, la catégorie, le nombre de places. Le total s'affiche avant de payer.</p>
      </div>
      <div class="step-card">
        <div class="step-num">2</div>
        <h3 class="step-title">Payez</h3>
        <p class="step-desc">T-Money, Flooz ou carte bancaire. Le paiement passe par le serveur sécurisé de Wakabi.</p>
      </div>
      <div class="step-card">
        <div class="step-num">3</div>
        <h3 class="step-title">Présentez</h3>
        <p class="step-desc">Le billet arrive sur WhatsApp avec son QR. Un scan à l'entrée, et c'est tout.</p>
      </div>
    </div>

    <div class="encart-ville">
      <h3>Vous organisez un événement ?</h3>
      <p>Ouvrez votre billetterie en quelques minutes : catégories, quotas, prix. Wakabi encaisse, vous suivez vos ventes en direct depuis votre tableau de bord.</p>
      <a class="btn btn-primary btn-lg" href="<?= e(url('?p=partenaires')) ?>">Ouvrir ma billetterie</a>
    </div>

    <?php endif; ?>

  </div>
</section>
