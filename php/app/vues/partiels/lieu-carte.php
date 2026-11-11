<?php
/**
 * Une carte de lieu, pour la vue grille.
 *
 * Le même contenu que la rangée, empilé : visuel en haut, corps dessous.
 *
 * Variable attendue : $li, une entrée de `api_lieux()`.
 */
$fiche = url('?p=lieu&id=' . rawurlencode($li['id'] !== '' ? $li['id'] : $li['nom']));
?>
<a class="lieu-tuile" href="<?= e($fiche) ?>">
  <div class="lieu-tuile-visuel <?= api_visuel($li['nom']) ?>">
    <?php if ($li['image'] !== ''): ?>
      <img src="<?= e($li['image']) ?>" alt="" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">
    <?php else: ?>
      <span class="evt-initiale"><?= e(api_initiale($li['nom'])) ?></span>
    <?php endif; ?>
    <?php if ($li['categorie'] !== ''): ?><span class="tag tag-clair"><?= e($li['categorie']) ?></span><?php endif; ?>
  </div>
  <div class="lieu-tuile-corps">
    <h3><?= e($li['nom']) ?></h3>
    <?php if ($li['adresse'] !== ''): ?>
      <p class="lieu-adresse"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"></path><circle cx="12" cy="10" r="2.6"></circle></svg><?= e($li['adresse']) ?></p>
    <?php endif; ?>
    <?php if ($li['info'] !== ''): ?><p class="lieu-info"><?= e($li['info']) ?></p><?php endif; ?>
    <span class="btn btn-outline btn-sm">Voir la fiche</span>
  </div>
</a>
