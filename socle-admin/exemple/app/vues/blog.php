<h1>Blog</h1>
<div class="grille">
  <?php foreach ($liste['articles'] as $a): ?>
    <article class="carte">
      <?php if (($img = article_image($a)) !== ''): ?><img src="<?= e($img) ?>" alt="" loading="lazy"><?php endif; ?>
      <h3><a href="<?= url('blog/' . rawurlencode($a['slug'])) ?>"><?= e($a['titre']) ?></a></h3>
      <p class="meta"><?= e(date_fr($a['publie_le'])) ?></p>
      <p><?= e(article_extrait($a, 160)) ?></p>
    </article>
  <?php endforeach; ?>
  <?php if (!$liste['articles']): ?><p>Aucun article pour l’instant.</p><?php endif; ?>
</div>
<?php if ($liste['pages'] > 1): ?>
  <nav class="pages"><?php for ($i = 1; $i <= $liste['pages']; $i++): ?><a href="<?= url('blog?page=' . $i) ?>"<?= $i === $liste['page'] ? ' aria-current="page"' : '' ?>><?= $i ?></a><?php endfor; ?></nav>
<?php endif; ?>
