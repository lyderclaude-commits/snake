<?php
/**
 * Une rangée de la liste des lieux.
 *
 * La rangée entière mène à la fiche du lieu (`?p=lieu&id=...`) : on lit
 * d'abord, on sort vers Google Maps seulement si on le veut — le bouton
 * « Voir sur la carte » vit dans la fiche, pas ici.
 *
 * Variable attendue : $li, une entrée de `api_lieux()`.
 */
$fiche = url('?p=lieu&id=' . rawurlencode($li['id'] !== '' ? $li['id'] : $li['nom']));
?>
<a class="lieu-carte" href="<?= e($fiche) ?>">
  <div class="lieu-visuel <?= api_visuel($li['nom']) ?>">
    <?php if ($li['image'] !== ''): ?>
      <img src="<?= e($li['image']) ?>" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;border-radius:inherit">
    <?php else: ?>
      <?= e(api_initiale($li['nom'])) ?>
    <?php endif; ?>
  </div>
  <div class="lieu-corps">
    <h3><?= e($li['nom']) ?><?php if ($li['categorie'] !== ''): ?> <span class="tag"><?= e($li['categorie']) ?></span><?php endif; ?></h3>
    <?php if ($li['adresse'] !== ''): ?>
      <p class="lieu-adresse"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"></path><circle cx="12" cy="10" r="2.6"></circle></svg><?= e($li['adresse']) ?></p>
    <?php endif; ?>
    <?php if ($li['info'] !== ''): ?><p class="lieu-info"><?= e($li['info']) ?></p><?php endif; ?>
  </div>
  <span class="btn btn-outline btn-sm">Voir la fiche</span>
</a>
