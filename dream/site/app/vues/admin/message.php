<?php
$titre = 'Message de ' . $m['nom'];
$wa = preg_replace('/\D+/', '', (string) $m['whatsapp']);
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
    <dt>E-mail</dt><dd><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a></dd>
    <dt>WhatsApp</dt><dd><?= e($m['whatsapp']) ?></dd>
    <dt>Entreprise</dt><dd><?= e($m['entreprise'] ?: '—') ?></dd>
    <dt>Besoin</dt><dd><?= e($m['besoin'] ?: '—') ?></dd>
    <dt>Budget</dt><dd><?= e($m['budget'] ?: '—') ?></dd>
  </dl>
  <div class="corps-message"><?= $m['message'] !== '' ? e($m['message']) : '<span class="aide">Pas de message.</span>' ?></div>
</div>
<div class="rangee" style="margin-top:18px">
  <form method="post" action="<?= url('admin/messages/' . (int) $m['id'] . '/non-lu') ?>"><?= csrf_champ() ?><button class="bouton fant" type="submit">Marquer comme non lu</button></form>
  <form method="post" action="<?= url('admin/messages/' . (int) $m['id'] . '/supprimer') ?>" data-confirmer="Supprimer ce message ?"><?= csrf_champ() ?><button class="bouton danger fant" type="submit">Supprimer</button></form>
</div>
