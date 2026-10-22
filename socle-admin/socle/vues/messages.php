<?php $titre = 'Messages'; $nl = count(array_filter($liste, fn($m) => !(int) $m['lu']));
$colonnes = array_filter(contact_champs(), fn($c, $k) => $c['liste'] && !in_array($k, ['nom', 'email', 'message'], true), ARRAY_FILTER_USE_BOTH); ?>
<div class="entete">
  <div><h1>Messages</h1><p>Les demandes envoyées par le formulaire de contact. <?= $nl ? '<b>' . $nl . ' non lue(s).</b>' : 'Tout est lu.' ?>
    <?php if (courriel_destinataires()): ?>Chaque demande est aussi envoyée par e-mail à <?= e(implode(', ', courriel_destinataires())) ?>.<?php endif; ?></p></div>
  <?php if ($nl): ?><div class="actions"><form method="post" action="<?= url('admin/messages') ?>"><?= csrf_champ() ?><input type="hidden" name="action" value="tout-lu"><button class="bouton fant" type="submit">Tout marquer comme lu</button></form></div><?php endif; ?>
</div>
<div class="tableau">
  <table>
    <thead><tr><th>De</th><?php foreach ($colonnes as $c): ?><th><?= e($c['libelle']) ?></th><?php endforeach; ?><th>Reçu</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($liste as $m): $complement = message_complement($m); ?>
      <tr class="<?= (int) $m['lu'] ? '' : 'non-lu' ?>">
        <td><a class="titre-ligne" href="<?= url('admin/messages/' . $m['id']) ?>"><?= e($m['nom']) ?></a><?= (int) $m['lu'] ? '' : ' <span class="pastille nouveau">Nouveau</span>' ?>
          <?= ($m['langue'] ?? 'fr') !== 'fr' ? ' <span class="drapeau" title="Envoyée depuis le site ' . e(langue_nom((string) $m['langue'])) . '">' . e(strtoupper((string) $m['langue'])) . '</span>' : '' ?>
          <?= str_starts_with((string) $m['courriel'], 'échec') ?' <span class="pastille refuse" title="' . e($m['courriel']) . '">E-mail non parti</span>' : '' ?>
          <span class="sous"><?= e($m['email']) ?><?= $complement !== '' ? ' · ' . e($complement) : '' ?></span></td>
        <?php foreach ($colonnes as $k => $c): ?><td><?= e(($m[$k] ?? '') !== '' ? (string) $m[$k] : '—') ?></td><?php endforeach; ?>
        <td class="aide" style="white-space:nowrap"><?= e(il_y_a($m['cree_le'])) ?></td>
        <td class="actions"><a class="bouton fant petit" href="<?= url('admin/messages/' . $m['id']) ?>">Lire</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$liste): ?><tr><td colspan="<?= 3 + count($colonnes) ?>" class="aide" style="padding:28px;text-align:center">Aucune demande reçue pour l’instant.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
