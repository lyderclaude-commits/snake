<section class="hero">
  <h1><?= e(reglage('slogan')) ?></h1>
  <p><?= nl2br(e(reglage('intro'))) ?></p>
  <a class="bouton" href="<?= url('contact') ?>">Parlons de votre projet</a>
</section>
<section>
  <h2>Nos réalisations</h2>
  <div class="grille">
    <?php foreach ($realisations as $r): ?>
      <article class="carte<?= (int) $r['vedette'] ? ' une' : '' ?>">
        <?php if ($r['image'] !== ''): ?><img src="<?= e(image_url((string) $r['image'])) ?>" alt="" loading="lazy"><?php endif; ?>
        <h3><?= e((string) $r['titre']) ?></h3>
        <p class="meta"><?= e((string) $r['client']) ?> · <?= (int) $r['annee'] ?></p>
        <p><?= e((string) $r['resume']) ?></p>
        <?php if ($r['chiffres']): ?>
          <ul class="chiffres"><?php foreach ($r['chiffres'] as $c): ?><li><b><?= e($c['valeur']) ?></b> <?= e($c['libelle']) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if ($r['lien'] !== ''): ?><a href="<?= e((string) $r['lien']) ?>" rel="noopener">Voir le projet →</a><?php endif; ?>
      </article>
    <?php endforeach; ?>
    <?php if (!$realisations): ?><p>Les réalisations arrivent bientôt.</p><?php endif; ?>
  </div>
</section>
