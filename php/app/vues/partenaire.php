<div class="contenu">
  <section class="entete">
    <div class="rangee" style="justify-content:space-between">
      <div>
        <h1>Mes campagnes</h1>
        <p><?= e($me['organisation'] ?: $me['nom']) ?></p>
      </div>
      <a class="bouton" href="<?= e(url('?p=nouveau')) ?>">Nouveau décor</a>
    </div>
  </section>

  <?php if (!empty($_GET['ok'])): ?><div class="msg ok" role="status"><?= e($_GET['ok']) ?></div><?php endif; ?>
  <?php if (!empty($_GET['err'])): ?><div class="msg err" role="alert"><?= e($_GET['err']) ?></div><?php endif; ?>

  <?php
  $emis = 0; $vus = 0; $presents = 0;
  foreach ($liste as $d) { $p = presence($d['id']); $emis += $p['emis']; $presents += $p['scannes']; $vus += (int) $d['vues']; }
  ?>
  <div class="grille g4" style="margin-bottom:22px">
    <div class="stat p"><b><?= count($liste) ?></b><span>campagnes</span></div>
    <div class="stat"><b><?= $vus ?></b><span>vues</span></div>
    <div class="stat o"><b><?= $emis ?></b><span>badges téléchargés</span></div>
    <div class="stat v"><b><?= $presents ?></b><span>présences scannées</span></div>
  </div>

  <?php if ($emis > 0 && $presents === 0): ?>
    <div class="msg info">
      <strong>C’est le QR qui fait la différence.</strong> <?= $emis ?> badge(s) téléchargé(s),
      0 personne réellement venue — un générateur classique s’arrête au premier chiffre.
      Scannez les badges à l’entrée pour mesurer le second.
    </div>
  <?php endif; ?>

  <?php if (!$liste): ?>
    <div class="carte"><p style="margin:0;color:var(--text2)">Aucune campagne. Créez votre premier décor.</p></div>
  <?php else: foreach ($liste as $d):
      $p = presence($d['id']); $rapport = lire_prevol($d['id']); ?>
    <div class="carte" style="margin-bottom:14px">
      <div class="rangee" style="justify-content:space-between">
        <div>
          <b class="display" style="font-size:1.05rem"><?= e($d['titre']) ?></b>
          <span class="pastille <?= e($d['statut']) ?>" style="margin-left:8px"><?= e(statut_libelle($d['statut'])) ?></span>
        </div>
        <span class="aide">↓ <?= (int) $d['telechargements'] ?> · 👁 <?= (int) $d['vues'] ?> · ✓ <?= $p['scannes'] ?></span>
      </div>

      <?php if ($d['motif']): ?>
        <div class="msg <?= $d['statut'] === 'refuse' ? 'err' : 'info' ?>" style="margin:12px 0 0">
          <strong>Retour de relecture :</strong> <?= e($d['motif']) ?>
        </div>
      <?php endif; ?>

      <?php if ($rapport && !$rapport['passe']): ?>
        <div class="msg err" style="margin:12px 0 0">
          <strong>Le contrôle automatique a relevé des problèmes bloquants.</strong>
          <ul><?php foreach ($rapport['controles'] as $c): if ($c['etat'] === 'echec'): ?>
            <li><?= e($c['message']) ?></li>
          <?php endif; endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <div class="rangee" style="margin-top:12px">
        <?php if (in_array($d['statut'], ['brouillon', 'corrections', 'refuse'], true)): ?>
          <a class="bouton fant petit" href="<?= e(url('?p=modifier&id=' . urlencode($d['id']))) ?>">Modifier</a>
        <?php endif; ?>
        <?php if (in_array($d['statut'], ['brouillon', 'corrections'], true)): ?>
          <form method="post" action="<?= e(url('?p=soumettre')) ?>">
            <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
            <input type="hidden" name="id" value="<?= e($d['id']) ?>">
            <button class="bouton petit" type="submit">Soumettre à la relecture</button>
          </form>
        <?php endif; ?>
        <?php if ($d['statut'] === 'publie'): ?>
          <a class="bouton fant petit" href="<?= e(url('?p=decor&slug=' . urlencode($d['slug']))) ?>">Voir en ligne</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>
