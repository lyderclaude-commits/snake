<?php $titre = 'Recherche'; ?>
<div class="entete"><div><h1>Recherche</h1><p><?= $q === '' ? 'Tapez un mot.' : 'Résultats pour « ' . e($q) . ' ».' ?></p></div></div>
<form class="chercher" method="get" action="<?= url('admin/recherche') ?>"><input type="search" name="q" value="<?= e($q) ?>" autofocus><button class="bouton fant" type="submit">Chercher</button></form>
<div class="carte bord-file">
  <?php foreach ($articles as $a): ?>
    <a class="bord-ligne" href="<?= url('admin/articles/' . $a['id']) ?>"><span class="bord-genre article">Article</span><span class="bord-titre"><?= e($a['titre']) ?></span><span class="bord-age"><?= e(date_fr($a['publie_le'])) ?></span></a>
  <?php endforeach; ?>
  <?php foreach ($elements as $x): $c = collection($x['collection']); ?>
    <a class="bord-ligne" href="<?= url('admin/' . $x['collection'] . '/' . $x['id']) ?>"><span class="bord-genre campagne"><?= e(mb_strtoupper(mb_substr($c['element'], 0, 1)) . mb_substr($c['element'], 1)) ?></span><span class="bord-titre"><?= e(element_titre($x)) ?></span><span class="bord-auteur"><?= e($c['titre']) ?></span></a>
  <?php endforeach; ?>
  <?php foreach ($messages as $m): ?>
    <a class="bord-ligne" href="<?= url('admin/messages/' . $m['id']) ?>"><span class="bord-genre message">Message</span><span class="bord-titre"><?= e($m['nom']) ?> · <?= e($m['email']) ?></span><span class="bord-age"><?= e(il_y_a($m['cree_le'])) ?></span></a>
  <?php endforeach; ?>
  <?php if (!$articles && !$elements && !$messages): ?><p class="aide bord-vide">Aucun résultat.</p><?php endif; ?>
</div>
