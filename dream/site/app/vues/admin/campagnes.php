<?php $titre = 'Campagnes'; ?>
<div class="entete">
  <div><h1>Les campagnes</h1><p>Le portfolio de la page Campagnes. Les quatre premières apparaissent aussi sur l’accueil : les campagnes « à la une » d’abord, puis selon l’ordre ci-dessous.</p></div>
  <div class="actions"><a class="bouton" href="<?= url('admin/campagnes/nouveau') ?>">+ Nouvelle campagne</a></div>
</div>
<form method="post" action="<?= url('admin/campagnes') ?>">
  <?= csrf_champ() ?>
  <div class="tableau">
    <table>
      <thead><tr><th></th><th>Campagne</th><th>Catégorie</th><th>Année</th><th>État</th><th class="chiffre">Ordre</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($liste as $c): ?>
        <tr>
          <td style="width:72px"><img class="miniature" src="<?= e(image_url($c['image'])) ?>" alt="" loading="lazy"></td>
          <td><a class="titre-ligne" href="<?= url('admin/campagnes/' . $c['id']) ?>"><?= e($c['titre']) ?></a><span class="sous"><?= e($c['client']) ?></span></td>
          <td><?= e(categorie_campagne($c['categorie'])) ?></td>
          <td><?= e($c['annee']) ?></td>
          <td><?= (int) $c['publie'] ? '<span class="pastille publie">En ligne</span>' : '<span class="pastille brouillon">Masquée</span>' ?>
              <?= (int) $c['vedette'] ? '<span class="pastille vedette">À la une</span>' : '' ?>
              <?= str_starts_with($c['image'], 'http') ? '<span class="pastille nouveau" title="Image encore hébergée ailleurs">Image externe</span>' : '' ?></td>
          <td class="chiffre"><input type="number" name="ordre[<?= (int) $c['id'] ?>]" value="<?= (int) $c['ordre'] ?>" style="width:74px;text-align:right" aria-label="Ordre"></td>
          <td class="actions"><a class="bouton fant petit" href="<?= url('admin/campagnes/' . $c['id']) ?>">Modifier</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$liste): ?><tr><td colspan="7" class="aide" style="padding:28px;text-align:center">Aucune campagne pour l’instant.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($liste): ?><p style="margin-top:12px"><button class="bouton fant" type="submit">Enregistrer l’ordre</button></p><?php endif; ?>
</form>
