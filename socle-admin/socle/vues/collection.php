<?php
/** La liste d'une collection : ordonnable, avec son aperçu, sa traduction et son état. */
$titre = $col['titre'];
$bilingue = isset(LANGUES['en']) && array_filter($col['champs'], fn($c) => $c['traduit']);
$images = collection_champs_de_type($col['nom'], 'image');
$a_apercu = is_callable($col['apercu']) || $images;
$nb_colonnes = 4 + ($a_apercu ? 1 : 0) + ($bilingue ? 1 : 0) + count($col['colonnes']);
?>
<div class="entete">
  <div><h1><?= e($col['titre']) ?></h1><?php if ($col['description'] !== ''): ?><p><?= $col['description'] ?></p><?php endif; ?></div>
  <div class="actions"><a class="bouton" href="<?= url('admin/' . $col['nom'] . '/nouveau') ?>">+ <?= e(collection_mot($col, 'Nouveau', 'Nouvelle') . ' ' . $col['element']) ?></a></div>
</div>
<form method="post" action="<?= url('admin/' . $col['nom']) ?>">
  <?= csrf_champ() ?>
  <div class="tableau">
    <table>
      <thead><tr>
        <?php if ($a_apercu): ?><th></th><?php endif; ?>
        <th><?= e(mb_strtoupper(mb_substr($col['element'], 0, 1)) . mb_substr($col['element'], 1)) ?></th>
        <?php foreach ($col['colonnes'] as $libelle => $f): ?><th><?= e((string) $libelle) ?></th><?php endforeach; ?>
        <?php if ($bilingue): ?><th>Anglais</th><?php endif; ?>
        <th>État</th><th class="chiffre">Ordre</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($liste as $x):
          $lien = url('admin/' . $col['nom'] . '/' . $x['id']);
          $sous = is_callable($col['sous_titre']) ? (string) ($col['sous_titre'])($x) : ''; ?>
        <tr>
          <?php if ($a_apercu): ?>
            <td class="apercu-liste"><?php if (is_callable($col['apercu'])): ?><?= ($col['apercu'])($x) ?><?php else:
                $img = (string) ($x[$images[0]] ?? ''); ?><span class="logo-apercu"><?= $img !== '' ? '<img src="' . e(image_url($img)) . '" alt="">' : '—' ?></span><?php endif; ?></td>
          <?php endif; ?>
          <td><a class="titre-ligne" href="<?= $lien ?>"><?= e(element_titre($x)) ?></a><?php if ($sous !== ''): ?><span class="sous"><?= e($sous) ?></span><?php endif; ?></td>
          <?php foreach ($col['colonnes'] as $f): ?><td><?= is_callable($f) ? $f($x) : '' ?></td><?php endforeach; ?>
          <?php if ($bilingue): ?>
            <td><?= element_traduit($x) ? '<span class="pastille publie">Traduit</span>' : '<span class="pastille brouillon">À traduire</span>' ?></td>
          <?php endif; ?>
          <td><?= (int) $x['visible'] ? '<span class="pastille publie">' . e($col['visible']['oui']) . '</span>' : '<span class="pastille brouillon">' . e($col['visible']['non']) . '</span>' ?>
              <?= is_callable($col['pastilles']) ? ($col['pastilles'])($x) : '' ?></td>
          <td class="chiffre"><input type="number" name="ordre[<?= (int) $x['id'] ?>]" value="<?= (int) $x['ordre'] ?>" style="width:74px;text-align:right" aria-label="Ordre"></td>
          <td class="actions"><a class="bouton fant petit" href="<?= $lien ?>">Modifier</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$liste): ?><tr><td colspan="<?= $nb_colonnes ?>" class="aide" style="padding:28px;text-align:center"><?= e($col['vide']) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (count($liste) > 1): ?><p style="margin-top:12px"><button class="bouton fant" type="submit">Enregistrer l’ordre</button></p><?php endif; ?>
</form>
