<?php $titre = 'Témoignages'; ?>
<div class="entete">
  <div><h1>Les témoignages</h1><p>« Ce que disent nos clients », sur l’accueil. Quatre se lisent bien côte à côte ; au-delà, la grille passe à la ligne.</p></div>
  <div class="actions"><a class="bouton" href="<?= url('admin/temoignages/nouveau') ?>">+ Nouveau témoignage</a></div>
</div>
<form method="post" action="<?= url('admin/temoignages') ?>">
  <?= csrf_champ() ?>
  <div class="tableau">
    <table>
      <thead><tr><th>Client</th><th>Témoignage</th><th>Anglais</th><th>État</th><th class="chiffre">Ordre</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($liste as $x):
          $fr = json_decode($x['fr'], true) ?: [];
          $en = json_decode($x['en'], true) ?: []; ?>
        <tr>
          <td><a class="titre-ligne" href="<?= url('admin/temoignages/' . $x['id']) ?>"><?= e($x['entreprise']) ?></a><span class="sous"><?= e((string) ($fr['auteur'] ?? '')) ?> · <?= str_repeat('★', (int) $x['note']) ?></span></td>
          <td class="aide" style="max-width:420px"><?= e(tronquer((string) ($fr['texte'] ?? ''), 110)) ?></td>
          <td><?= ($en['texte'] ?? '') !== '' ? '<span class="pastille publie">Traduit</span>' : '<span class="pastille brouillon">À traduire</span>' ?></td>
          <td><?= (int) $x['visible'] ? '<span class="pastille publie">Visible</span>' : '<span class="pastille brouillon">Masqué</span>' ?></td>
          <td class="chiffre"><input type="number" name="ordre[<?= (int) $x['id'] ?>]" value="<?= (int) $x['ordre'] ?>" style="width:74px;text-align:right" aria-label="Ordre"></td>
          <td class="actions"><a class="bouton fant petit" href="<?= url('admin/temoignages/' . $x['id']) ?>">Modifier</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$liste): ?><tr><td colspan="6" class="aide" style="padding:28px;text-align:center">Aucun témoignage : la section disparaît de l’accueil tant qu’il n’y en a pas.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($liste): ?><p style="margin-top:12px"><button class="bouton fant" type="submit">Enregistrer l’ordre</button></p><?php endif; ?>
</form>
