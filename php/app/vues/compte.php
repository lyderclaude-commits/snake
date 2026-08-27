<div class="contenu">
  <section class="entete">
    <h1>Bonjour <?= e($me['nom']) ?></h1>
    <p>Vos créations et votre solde de Koris.</p>
  </section>

  <div class="grille g3" style="margin-bottom:22px">
    <div class="stat p"><b><?= count($creations) ?></b><span>visuels créés</span></div>
    <div class="stat o"><b><?= (int) $solde ?> ₵</b><span>Koris gagnés</span></div>
    <div class="stat v"><b><?= count($historique) ?></b><span>présences validées</span></div>
  </div>

  <div class="grille g2">
    <div class="carte">
      <h3 style="margin-bottom:12px">Mes créations</h3>
      <?php if (!$creations): ?>
        <p style="color:var(--text2);margin:0">Aucune création pour l’instant.
        <a href="<?= e(url('?p=decors')) ?>">Choisir un décor</a>.</p>
      <?php else: foreach ($creations as $c): ?>
        <div class="rangee" style="justify-content:space-between;border-top:1px solid var(--border);padding:9px 0">
          <a href="<?= e(url('?p=decor&slug=' . urlencode($c['slug']))) ?>"><?= e($c['titre']) ?></a>
          <span class="aide"><?= e(substr($c['cree_le'], 0, 10)) ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <div class="carte">
      <h3 style="margin-bottom:12px">Mes Koris</h3>
      <?php if (!$historique): ?>
        <p style="color:var(--text2);margin:0">Les Koris se gagnent <strong>à l’entrée</strong>,
        quand votre badge est scanné, pas au téléchargement.</p>
      <?php else: foreach ($historique as $k): ?>
        <div class="rangee" style="justify-content:space-between;border-top:1px solid var(--border);padding:9px 0">
          <span><?= e($k['motif']) ?></span>
          <b style="color:var(--teal)">+<?= (int) $k['montant'] ?> ₵</b>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <p class="aide" style="margin-top:18px">
    Ce qui est enregistré : le décor utilisé et la date. <strong>Pas votre photo</strong> :
    elle est traitée dans votre navigateur et n’arrive jamais sur nos serveurs.
  </p>
</div>
