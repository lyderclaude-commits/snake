<?php $titre = 'Services'; ?>
<div class="entete">
  <div><h1>Les services</h1><p>Ce qui s’affiche dans « Ce que nous faisons » sur l’accueil, dans l’introduction et les sections détaillées de la page Services, et dans le pied de page. Dans l’ordre ci-dessous.</p></div>
  <div class="actions"><a class="bouton" href="<?= url('admin/services/nouveau') ?>">+ Nouveau service</a></div>
</div>
<form method="post" action="<?= url('admin/services') ?>">
  <?= csrf_champ() ?>
  <div class="tableau">
    <table>
      <thead><tr><th></th><th>Service</th><th>Page Services</th><th>Anglais</th><th>État</th><th class="chiffre">Ordre</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($liste as $s):
          $fr = json_decode($s['fr'], true) ?: [];
          $en = json_decode($s['en'], true) ?: []; ?>
        <tr>
          <td style="width:56px"><span class="logo-apercu" style="width:44px;height:44px"><?= icone($s['icone'], 22) ?></span></td>
          <td><a class="titre-ligne" href="<?= url('admin/services/' . $s['id']) ?>"><?= e((string) ($fr['titre'] ?? '')) ?></a><span class="sous">#<?= e($s['ancre']) ?> · <?= e($s['numero']) ?></span></td>
          <td><?= (int) $s['detail'] ? 'Section détaillée' : '<span class="aide">Carte seule</span>' ?></td>
          <td><?= ($en['titre'] ?? '') !== '' ? '<span class="pastille publie">Traduit</span>' : '<span class="pastille brouillon">À traduire</span>' ?></td>
          <td><?= (int) $s['visible'] ? '<span class="pastille publie">Visible</span>' : '<span class="pastille brouillon">Masqué</span>' ?>
              <?= (int) $s['vedette'] ? '<span class="pastille vedette">Mis en avant</span>' : '' ?></td>
          <td class="chiffre"><input type="number" name="ordre[<?= (int) $s['id'] ?>]" value="<?= (int) $s['ordre'] ?>" style="width:74px;text-align:right" aria-label="Ordre"></td>
          <td class="actions"><a class="bouton fant petit" href="<?= url('admin/services/' . $s['id']) ?>">Modifier</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$liste): ?><tr><td colspan="7" class="aide" style="padding:28px;text-align:center">Aucun service.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($liste): ?><p style="margin-top:12px"><button class="bouton fant" type="submit">Enregistrer l’ordre</button></p><?php endif; ?>
</form>
