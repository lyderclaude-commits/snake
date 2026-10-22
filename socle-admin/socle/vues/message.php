<?php
$titre = 'Message de ' . $m['nom'];
$wa = message_whatsapp($m);
?>
<a class="retour" href="<?= url('admin/messages') ?>"><i>←</i>Messages</a>
<div class="entete"><div><h1><?= e($m['nom']) ?></h1><p>Reçu le <?= e(date_fr($m['cree_le'])) ?> à <?= e(date('H:i', strtotime((string) $m['cree_le']))) ?>.</p></div>
  <div class="actions">
    <?php if ($wa !== ''): ?><a class="bouton" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Bonjour ' . $m['nom'] . ', ici ' . reglage('nom_site') . '. Merci pour votre message.') ?>" target="_blank" rel="noopener">Répondre sur WhatsApp</a><?php endif; ?>
    <a class="bouton fant" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Votre demande — ' . reglage('nom_site')) ?>">Répondre par e-mail</a>
  </div>
</div>
<div class="carte">
  <dl class="fiche">
    <?php foreach (contact_champs() as $k => $c): if (in_array($k, ['nom', 'message'], true)) continue;
        $v = (string) ($m[$k] ?? ''); ?>
      <dt><?= e($c['libelle']) ?></dt><dd><?php if ($v === ''): ?>—<?php elseif ($c['type'] === 'email'): ?><a href="mailto:<?= e($v) ?>"><?= e($v) ?></a><?php elseif ($c['type'] === 'tel'): ?><a href="tel:<?= e((string) preg_replace('/[^\d+]/', '', $v)) ?>"><?= e($v) ?></a><?php else: ?><?= e($v) ?><?php endif; ?></dd>
    <?php endforeach; ?>
    <?php if (count(LANGUES) > 1): ?>
      <dt>Langue</dt><dd><?= ($m['langue'] ?? 'fr') !== 'fr' ? '<span class="drapeau">' . e(strtoupper((string) $m['langue'])) . '</span> Site ' . e(langue_nom((string) $m['langue'])) . ' — répondez en ' . e(langue_nom((string) $m['langue'])) : 'Français' ?></dd>
    <?php endif; ?>
  </dl>
  <?php $envoi = (string) $m['courriel']; ?>
  <div class="msg <?= str_starts_with($envoi, 'envoyé') ? 'ok' : ($envoi === '' ? 'info' : 'err') ?>" style="margin:16px 0 0">
    <b>Copie par e-mail :</b> <?= $envoi !== '' ? e($envoi) : 'pas encore envoyée.' ?>
  </div>
  <div class="corps-message"><?= $m['message'] !== '' ? e($m['message']) : '<span class="aide">Pas de message.</span>' ?></div>
</div>
<div class="rangee" style="margin-top:18px">
  <form method="post" action="<?= url('admin/messages/' . (int) $m['id'] . '/renvoyer') ?>"><?= csrf_champ() ?><button class="bouton fant" type="submit">Renvoyer par e-mail</button></form>
  <form method="post" action="<?= url('admin/messages/' . (int) $m['id'] . '/non-lu') ?>"><?= csrf_champ() ?><button class="bouton fant" type="submit">Marquer comme non lu</button></form>
  <form method="post" action="<?= url('admin/messages/' . (int) $m['id'] . '/supprimer') ?>" data-confirmer="Supprimer ce message ?"><?= csrf_champ() ?><button class="bouton danger fant" type="submit">Supprimer</button></form>
</div>
