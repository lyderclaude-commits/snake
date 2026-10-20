<?php $titre = 'Messages'; $nl = count(array_filter($liste, fn($m) => !(int) $m['lu'])); ?>
<div class="entete">
  <div><h1>Messages</h1><p>Les demandes envoyées par le formulaire de contact. <?= $nl ? '<b>' . $nl . ' non lue(s).</b>' : 'Tout est lu.' ?>
    Chaque demande est aussi envoyée par e-mail à <?= e(implode(', ', courriel_destinataires())) ?>.</p></div>
  <?php if ($nl): ?><div class="actions"><form method="post" action="<?= url('admin/messages') ?>"><?= csrf_champ() ?><input type="hidden" name="action" value="tout-lu"><button class="bouton fant" type="submit">Tout marquer comme lu</button></form></div><?php endif; ?>
</div>
<div class="tableau">
  <table>
    <thead><tr><th>De</th><th>Besoin</th><th>Budget</th><th>Reçu</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($liste as $m): ?>
      <tr class="<?= (int) $m['lu'] ? '' : 'non-lu' ?>">
        <td><a class="titre-ligne" href="<?= url('admin/messages/' . $m['id']) ?>"><?= e($m['nom']) ?></a><?= (int) $m['lu'] ? '' : ' <span class="pastille nouveau">Nouveau</span>' ?>
          <?= ($m['langue'] ?? 'fr') === 'en' ? ' <span class="drapeau" title="Envoyée depuis le site anglais">EN</span>' : '' ?>
          <?= str_starts_with((string) $m['courriel'], 'échec') ?' <span class="pastille refuse" title="' . e($m['courriel']) . '">E-mail non parti</span>' : '' ?>
          <span class="sous"><?= e($m['email']) ?><?= $m['entreprise'] !== '' ? ' · ' . e($m['entreprise']) : '' ?></span></td>
        <td><?= e($m['besoin'] ?: '—') ?></td>
        <td><?= e($m['budget'] ?: '—') ?></td>
        <td class="aide" style="white-space:nowrap"><?= e(il_y_a($m['cree_le'])) ?></td>
        <td class="actions"><a class="bouton fant petit" href="<?= url('admin/messages/' . $m['id']) ?>">Lire</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$liste): ?><tr><td colspan="5" class="aide" style="padding:28px;text-align:center">Aucune demande reçue pour l’instant.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
