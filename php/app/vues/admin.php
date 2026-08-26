<div class="contenu">
  <section class="entete">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start">
      <div>
        <h1>Administration</h1>
        <p>Vue d’ensemble de Wakabi Boost.</p>
      </div>
      <div class="rangee">
        <a class="bouton fant" href="<?= e(url('?p=catalogue')) ?>">Tous les décors</a>
        <a class="bouton" href="<?= e(url('?p=nouveau')) ?>">+ Nouveau décor</a>
      </div>
    </div>
  </section>

  <div class="grille g3" style="margin-bottom:22px">
    <div class="stat p"><b><?= (int) $stats['publies'] ?></b><span>décors publiés</span></div>
    <div class="stat o"><b><?= (int) $stats['en_attente'] ?></b><span>en attente de relecture</span></div>
    <div class="stat"><b><?= (int) $stats['badges'] ?></b><span>badges émis</span></div>
    <div class="stat v"><b><?= (int) $stats['presences'] ?></b><span>présences scannées</span></div>
    <div class="stat"><b><?= (int) $stats['vues'] ?></b><span>vues du catalogue</span></div>
    <div class="stat"><b><?= (int) $stats['comptes'] ?></b><span>comptes</span></div>
  </div>

  <?php if ($stats['badges'] > 0): ?>
    <div class="msg info">
      <strong>Présence réelle :</strong> <?= (int) $stats['presences'] ?> scannés sur
      <?= (int) $stats['badges'] ?> badges émis — soit
      <?= (int) round($stats['presences'] / max(1, $stats['badges']) * 100) ?> %.
    </div>
  <?php endif; ?>

  <div class="carte" style="margin-bottom:18px">
    <h3>Téléchargements — 14 derniers jours</h3>
    <?php $max = max(1, max(array_column($serie, 'n'))); ?>
    <div class="histo">
      <?php foreach ($serie as $j): ?>
        <div style="height:<?= (int) round($j['n'] / $max * 100) ?>%"
             title="<?= e($j['jour']) ?> : <?= (int) $j['n'] ?>"></div>
      <?php endforeach; ?>
    </div>
    <p class="aide" style="margin-top:8px"><?= array_sum(array_column($serie, 'n')) ?> au total sur la période</p>
  </div>

  <div class="tableau">
    <table>
      <thead><tr><th>Décor</th><th>Auteur</th><th>Statut</th><th>↓</th><th>👁</th></tr></thead>
      <tbody>
      <?php foreach ($derniers as $d): ?>
        <tr>
          <td>
            <a href="<?= e(url('?p=modifier&id=' . urlencode($d['id']))) ?>"><b><?= e($d['titre']) ?></b></a>
            <br><span class="aide">/<?= e($d['slug']) ?></span>
          </td>
          <td><?= e($d['auteur_nom'] ?: '—') ?><br><span class="aide"><?= e($d['cree_par']) ?></span></td>
          <td><span class="pastille <?= e($d['statut']) ?>"><?= e(statut_libelle($d['statut'])) ?></span></td>
          <td class="mono"><?= (int) $d['telechargements'] ?></td>
          <td class="mono"><?= (int) $d['vues'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
