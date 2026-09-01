<?php
/** La régie : mes campagnes e-mail, ou toutes pour l'équipe. */
$erreur = $erreur ?? null;
$message = $message ?? null;
$attente = array_filter($liste, fn(array $c) => $c['statut'] === 'en_relecture');
?>
<div class="contenu">
  <section class="entete">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
      <div>
        <h1>Régie e-mail</h1>
        <p><?= $equipe
          ? 'Ce qui part sous le nom du guide — le vôtre, et celui des organisateurs.'
          : 'Écrivez à vos invités. Chaque campagne est relue par l’équipe avant de partir.' ?></p>
      </div>
      <a class="bouton" href="<?= e(url('?p=regie-ecrire')) ?>">Nouvelle campagne</a>
    </div>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <?php if (!$branche): ?>
    <div class="msg err" style="margin-bottom:16px">
      <strong>Le transport e-mail est éteint.</strong>
      <p style="margin:.35em 0 0">Vous pouvez écrire et faire relire une campagne, mais rien ne
      partira tant que le serveur d’envoi n’est pas réglé<?= $equipe
        ? ' — <a href="' . e(url('?p=reglages')) . '">c’est ici</a>.'
        : '. Prévenez l’équipe.' ?></p>
    </div>
  <?php endif; ?>

  <?php if ($equipe && $attente): ?>
    <div class="files" style="margin-bottom:18px">
      <a class="file urgent" href="#file">
        <b><?= count($attente) ?></b><span>campagne(s) à relire</span>
      </a>
    </div>
  <?php endif; ?>

  <?php if (!$equipe): ?>
    <?php $q = $quota; ?>
    <div class="carte" style="margin-bottom:16px">
      <div class="marche" style="margin:0">
        <div class="haut">
          <span>E-mails envoyés ce mois</span>
          <b><?= (int) $q['utilises'] ?><?= $q['max'] < 0 ? '' : ' / ' . (int) $q['max'] ?></b>
        </div>
        <div class="rail"><i style="width:<?= $q['max'] < 0 ? 6 : min(100, (int) round($q['utilises'] / max(1, $q['max']) * 100)) ?>%"></i></div>
        <span class="taux">
          <?php if ($q['max'] < 0): ?>
            Sans limite avec votre offre.
          <?php else: ?>
            Il vous en reste <strong><?= (int) $q['reste'] ?></strong>. Chaque destinataire compte pour un,
            et le compteur repart le 1er du mois.
          <?php endif; ?>
        </span>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!$liste): ?>
    <div class="carte">
      <h3 style="margin:0 0 6px">Aucune campagne pour l’instant</h3>
      <p class="aide" style="margin:0 0 14px">Vos invités ont laissé une adresse en créant leur badge.
      C’est une audience que vous avez constituée vous-même : elle vous connaît déjà.</p>
      <a class="bouton" href="<?= e(url('?p=regie-ecrire')) ?>">Écrire la première</a>
    </div>
  <?php else: ?>
    <div class="tableau" id="file">
      <table>
        <thead>
          <tr><th>Campagne</th><?= $equipe ? '<th>Auteur</th>' : '' ?><th>Cible</th><th>État</th>
          <th class="chiffre">Partis</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($liste as $c): ?>
            <tr>
              <td>
                <a href="<?= e(url('?p=regie-campagne&id=' . urlencode($c['id']))) ?>"><strong><?= e($c['sujet']) ?></strong></a>
                <span class="aide" style="display:block"><?= e(gmdate('d/m/Y', strtotime((string) $c['cree_le']))) ?></span>
              </td>
              <?php if ($equipe): ?><td class="aide"><?= e($c['auteur_nom'] ?? '—') ?></td><?php endif; ?>
              <td class="aide"><?= e(REGIE_CIBLES[$c['cible']][0] ?? $c['cible']) ?></td>
              <td><span class="pastille <?= e($c['statut']) ?>"><?= e(REGIE_STATUTS[$c['statut']] ?? $c['statut']) ?></span></td>
              <td class="chiffre"><?= (int) $c['envoyes'] ?><?= $c['destinataires'] ? ' / ' . (int) $c['destinataires'] : '' ?></td>
              <td style="text-align:right;white-space:nowrap">
                <a class="bouton fant petit" href="<?= e(url('?p=regie-campagne&id=' . urlencode($c['id']))) ?>">Ouvrir</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($equipe): ?>
    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 4px">Vider la file toute seule</h3>
      <p class="aide" style="margin:0 0 12px">Sans cette tâche, une campagne de deux mille
      destinataires demande de cliquer <?= (int) ceil(2000 / REGIE_LOT) ?> fois. Avec elle,
      l’envoi se poursuit seul, lot par lot, jusqu’à la fin.</p>
      <pre style="overflow-x:auto;background:var(--bg2);padding:12px;border-radius:10px;margin:0"><code>*/5 * * * * curl -s "<?= e($url_cron) ?>" &gt;/dev/null</code></pre>
      <p class="aide" style="margin:12px 0 0">La clé est la même que celle des sauvegardes,
      et elle remplace le mot de passe : ne la publiez pas. La tâche ne fait rien quand aucune
      campagne n’est en cours d’envoi — la lancer toutes les cinq minutes ne coûte rien.</p>
    </div>
  <?php endif; ?>

  <div class="carte" style="margin-top:16px">
    <h3 style="margin:0 0 8px">Les règles, écrites une fois pour toutes</h3>
    <ul style="margin:0;padding-left:1.1em;line-height:1.7">
      <li><strong>Chaque message porte un lien de désabonnement</strong>, ajouté automatiquement.
      Il ne se retire pas : c’est la loi, et c’est ce qui évite d’être signalé comme indésirable.</li>
      <li>Un désabonnement vaut <strong>pour toujours et pour tout le monde</strong>. Quelqu’un qui
      part ne réapparaît dans aucune campagne, ni la vôtre ni la nôtre.</li>
      <li>L’envoi part <strong>par lots de <?= REGIE_LOT ?></strong> : un hébergement mutualisé coupe
      un script au bout de trente secondes. La liste est figée avant le premier envoi, donc personne
      ne reçoit deux fois, même si l’on reprend.</li>
      <?php if (!$equipe): ?>
        <li>L’équipe relit avant l’envoi. C’est le nom du guide qui part sur ces messages, et une
        adresse signalée abîme la délivrabilité de tout le monde — y compris vos propres envois.</li>
      <?php endif; ?>
    </ul>
  </div>
</div>
