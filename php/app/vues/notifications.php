<div class="contenu">
  <section class="entete"><h1>Notifications</h1></section>
  <div class="carte">
    <?php if (!$liste): ?>
      <p style="color:var(--text2);margin:0">Rien pour l’instant.</p>
    <?php else: foreach ($liste as $n): ?>
      <div style="border-top:1px solid var(--border);padding:12px 0">
        <div class="rangee" style="justify-content:space-between">
          <b><?= e($n['titre']) ?></b>
          <span class="aide"><?= e(substr($n['cree_le'], 0, 10)) ?></span>
        </div>
        <?php if ($n['corps']): ?><p style="color:var(--text2);margin:.3em 0 0"><?= e($n['corps']) ?></p><?php endif; ?>
        <?php if ($n['lien']): ?><a class="aide" href="<?= e(url($n['lien'])) ?>">Ouvrir</a><?php endif; ?>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
