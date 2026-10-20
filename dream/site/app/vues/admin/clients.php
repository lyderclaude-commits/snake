<?php $titre = 'Logos clients'; ?>
<div class="entete">
  <div><h1>Ils nous font confiance</h1><p>Les clients de la bande de logos, sur l’accueil. Sans logo, le nom s’affiche en lettres capitales — comme dans la maquette. Les logos SVG ou PNG sur fond transparent rendent le mieux.</p></div>
</div>

<details class="carte" style="padding:0;margin-bottom:22px">
  <summary class="rangee" style="padding:16px 20px;cursor:pointer;list-style:none"><span class="bouton">+ Ajouter un client</span><span class="aide" style="margin:0">Son nom, et son logo si vous l’avez.</span></summary>
  <form method="post" action="<?= url('admin/clients') ?>" style="padding:16px 20px 20px;border-top:1px solid var(--border)">
    <?= csrf_champ() ?><input type="hidden" name="action" value="ajouter">
    <div class="champs">
      <div class="champ"><label for="c-nom">Nom</label><input id="c-nom" name="nom" required placeholder="ECOBANK"></div>
      <div class="champ"><label for="c-lien">Lien (facultatif)</label><input id="c-lien" name="lien" type="url" placeholder="https://…"></div>
    </div>
    <div class="champ">
      <label>Logo (facultatif)</label>
      <div class="rangee">
        <div class="logo-apercu" data-couverture>—</div>
        <input type="hidden" name="logo" value="" data-couverture-champ>
        <button class="bouton fant petit" type="button" data-choisir-couverture>Choisir dans la médiathèque</button>
        <button class="bouton fant petit" type="button" data-retirer-couverture hidden>Retirer</button>
      </div>
      <p class="aide">Téléversez d’abord le logo dans la médiathèque, ou directement depuis la fenêtre de choix.</p>
    </div>
    <button class="bouton" type="submit">Ajouter</button>
  </form>
</details>

<form method="post" action="<?= url('admin/clients') ?>">
  <?= csrf_champ() ?><input type="hidden" name="action" value="ordre">
  <div class="tableau">
    <table>
      <thead><tr><th>Logo</th><th>Client</th><th>État</th><th class="chiffre">Ordre</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($liste as $c): ?>
        <tr>
          <td style="width:140px"><span class="logo-apercu"><?= $c['logo'] !== '' ? '<img src="' . e(image_url($c['logo'])) . '" alt="">' : e($c['nom']) ?></span></td>
          <td><a class="titre-ligne" href="<?= url('admin/clients/' . $c['id']) ?>"><?= e($c['nom']) ?></a><?php if ($c['lien'] !== ''): ?><span class="sous"><?= e($c['lien']) ?></span><?php endif; ?></td>
          <td><?= (int) $c['visible'] ? '<span class="pastille publie">Visible</span>' : '<span class="pastille brouillon">Masqué</span>' ?>
              <?= $c['logo'] === '' ? '<span class="pastille brouillon">Nom seul</span>' : '' ?></td>
          <td class="chiffre"><input type="number" name="ordre[<?= (int) $c['id'] ?>]" value="<?= (int) $c['ordre'] ?>" style="width:74px;text-align:right" aria-label="Ordre"></td>
          <td class="actions"><a class="bouton fant petit" href="<?= url('admin/clients/' . $c['id']) ?>">Modifier</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$liste): ?><tr><td colspan="5" class="aide" style="padding:28px;text-align:center">Aucun client : la bande de logos disparaît de l’accueil.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($liste): ?><p style="margin-top:12px"><button class="bouton fant" type="submit">Enregistrer l’ordre</button></p><?php endif; ?>
</form>
<?php require __DIR__ . '/_selecteur.php'; ?>
