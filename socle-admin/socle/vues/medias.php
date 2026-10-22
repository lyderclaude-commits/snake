<?php $titre = 'Médiathèque'; ?>
<div class="entete">
  <div><h1>Médiathèque</h1><p><?= (int) $total ?> image(s), <?= e(taille_lisible($poids)) ?> au total. Toutes sont hébergées sur ce serveur — les images importées de WordPress comprises.</p></div>
</div>
<form class="depot carte plate" method="post" action="<?= url('admin/medias/televerser') ?>" enctype="multipart/form-data" data-depot>
  <?= csrf_champ() ?>
  <p style="margin:0 0 10px"><b>Ajouter une image</b> — glissez-la ici, ou choisissez-la.</p>
  <input type="file" name="fichier" accept="image/jpeg,image/png,image/webp,image/gif" required aria-label="Image à téléverser">
  <p class="aide">JPEG, PNG, WebP ou GIF, 15 Mo au plus. Au-delà de 2000 px de large, l’image est réduite à l’envoi.</p>
  <button class="bouton" type="submit" style="margin-top:10px">Téléverser</button>
</form>
<div class="medias" style="margin-top:20px">
  <?php foreach ($liste as $m): $u = media_utilisations($m['chemin']); ?>
    <div class="media">
      <a href="<?= e(url($m['chemin'])) ?>" target="_blank" rel="noopener"><img src="<?= e(url($m['chemin'])) ?>" alt="<?= e($m['nom']) ?>" loading="lazy"></a>
      <div class="bas">
        <b title="<?= e($m['nom']) ?>"><?= e($m['nom']) ?></b>
        <span><?= (int) $m['largeur'] ?>×<?= (int) $m['hauteur'] ?> · <?= e(taille_lisible((int) $m['taille'])) ?></span>
        <span><?= $u ? 'utilisée ' . $u . ' fois' : 'inutilisée' ?><?= $m['source_url'] ? ' · importée' : '' ?></span>
        <div class="rangee">
          <button class="bouton fant petit" type="button" data-copier="<?= e(url_absolue($m['chemin'])) ?>">Copier l’adresse</button>
          <form class="enligne" method="post" action="<?= url('admin/medias/' . (int) $m['id'] . '/supprimer') ?>" data-confirmer="<?= $u ? 'Cette image est utilisée ' . $u . ' fois : la supprimer laissera un trou. Continuer ?' : 'Supprimer cette image ?' ?>">
            <?= csrf_champ() ?><button class="bouton danger fant petit" type="submit">Supprimer</button>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php if (!$liste): ?><p class="aide" style="margin-top:18px">La médiathèque est vide.</p><?php endif; ?>
<?php if ($pages > 1): ?><nav class="pages"><?php for ($i = 1; $i <= $pages; $i++): ?><?php if ($i === $page): ?><span class="ici"><?= $i ?></span><?php else: ?><a href="<?= url('admin/medias?page=' . $i) ?>"><?= $i ?></a><?php endif; ?><?php endfor; ?></nav><?php endif; ?>
