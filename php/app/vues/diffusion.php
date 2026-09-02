<?php
/** Écrire aux navigateurs abonnés. */
$erreur = $erreur ?? null;
$message = $message ?? null;
$total = array_sum($compte);
?>
<div class="contenu">
  <section class="entete">
    <h1>Notifications push</h1>
    <p><?= $equipe
      ? 'Un message sur l’écran des gens qui ont accepté d’en recevoir — même le site fermé.'
      : 'Un message aux invités de vos campagnes, sur leur navigateur, même le site fermé.' ?></p>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <?php if (!$disponible): ?>
    <div class="msg err">
      <strong>Cet hébergement ne peut pas envoyer de notifications.</strong>
      <p style="margin:.35em 0 6px">Ce sont des extensions PHP standard : votre hébergeur les
      active sur demande. Voici ce qui manque, nommément — transmettez-lui cette liste.</p>
      <ul style="margin:0;padding-left:1.1em">
        <?php foreach ($prerequis as $quoi => $present): ?>
          <li><?= $present ? '✓' : '✕' ?> <?= e($quoi) ?><?= $present ? '' : ' — absent' ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <!-- ---------- l'essai sur soi-même ---------- -->
  <div class="carte" style="margin-bottom:16px">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
      <div>
        <h3 style="margin:0 0 4px">Vérifier que ça marche</h3>
        <p class="aide" style="margin:0">
          <?php if ($mes_abonnements): ?>
            Ce compte a <strong><?= (int) $mes_abonnements ?></strong> navigateur(s) abonné(s).
            L’essai leur envoie une vraie notification, et dit exactement ce que le service de
            push a répondu.
          <?php else: ?>
            <strong>Ce compte n’a aucun navigateur abonné.</strong> Ouvrez
            <a href="<?= e(url('?p=profil')) ?>">Mon profil</a> et cliquez « Recevoir les
            notifications » sur cet appareil — sinon il n’y a rien à qui écrire.
          <?php endif; ?>
        </p>
      </div>
      <form method="post" action="<?= e(url('?p=diffusion')) ?>" style="margin:0">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <button class="bouton fant" type="submit" name="action" value="essai"
                <?= $disponible && $mes_abonnements ? '' : 'disabled' ?>>M’envoyer un essai</button>
      </form>
    </div>

    <?php if ($essai): ?>
      <div class="tableau" style="margin-top:16px">
        <table>
          <thead><tr><th>Navigateur</th><th>Service</th><th>Réponse</th></tr></thead>
          <tbody>
            <?php foreach ($essai as $l): ?>
              <tr>
                <td class="aide"><?= e(mb_substr($l['agent'], 0, 60)) ?></td>
                <td class="aide"><?= e($l['hote']) ?></td>
                <td>
                  <span class="pastille <?= $l['ok'] ? 'publie' : 'refuse' ?>">
                    <?= $l['ok'] ? 'Remise' : ($l['code'] ? 'HTTP ' . (int) $l['code'] : 'Échec') ?>
                  </span>
                  <?php if (!$l['ok'] && $l['message']): ?>
                    <span class="aide" style="display:block"><?= e(mb_substr((string) $l['message'], 0, 200)) ?></span>
                  <?php endif; ?>
                  <?php if ($l['mort']): ?>
                    <span class="aide" style="display:block">Abonnement périmé : il vient d’être
                    effacé. Réabonnez ce navigateur depuis Mon profil.</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="aide" style="margin:12px 0 0">
        <strong>« Remise » veut dire que le service de push a accepté le message</strong>, pas
        qu’il s’est affiché. Si rien n’apparaît malgré une remise, le problème est côté
        navigateur : notifications coupées au niveau du système, mode « Ne pas déranger »,
        ou un ancien service worker resté en place — dans ce dernier cas, rechargez la page
        en forçant le cache (Ctrl+Maj+R).
      </p>
    <?php endif; ?>
  </div>

  <div class="carte">
    <h3 style="margin:0 0 4px">Le message</h3>
    <p class="aide" style="margin:0 0 16px">Court. Une notification s’affiche dans un bandeau
    de deux lignes, et ce qui dépasse est coupé sans prévenir.</p>

    <form method="post" action="<?= e(url('?p=diffusion')) ?>">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

      <div class="champ">
        <label for="d-segment">À qui</label>
        <select id="d-segment" name="segment">
          <?php foreach ($segments as $cle => $lib): ?>
            <option value="<?= e($cle) ?>" <?= $saisie['segment'] === $cle ? 'selected' : '' ?>>
              <?= e($lib) ?> — <?= (int) $compte[$cle] ?> abonné(s)
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (!$equipe): ?>
          <p class="aide">Seuls les invités qui ont fait un badge sur vos campagnes
          <em>et</em> accepté les notifications. La base du guide ne se loue pas.</p>
        <?php endif; ?>
      </div>

      <div class="champ">
        <label for="d-titre">Titre <span style="font-weight:400">(60 caractères)</span></label>
        <input id="d-titre" name="titre" type="text" required maxlength="60"
               placeholder="Nouvelle campagne : Afro Night Lomé"
               value="<?= e($saisie['titre']) ?>">
      </div>

      <div class="champ">
        <label for="d-corps">Texte <span style="font-weight:400">(180 caractères)</span></label>
        <textarea id="d-corps" name="corps" rows="3" maxlength="180"
                  placeholder="Faites votre badge avant samedi et gagnez des Koris à l’entrée."><?= e($saisie['corps']) ?></textarea>
      </div>

      <div class="champ">
        <label for="d-lien">Où mène le clic</label>
        <input id="d-lien" name="lien" type="url" placeholder="<?= e(base_url() . '/index.php?p=decors') ?>"
               value="<?= e($saisie['lien']) ?>">
        <p class="aide">
          <?= $equipe
            ? 'Vide : la page des décors.'
            : 'Vide : la page des décors. Sinon une adresse ' . e(implode(' ou ', WAKABI_DOMAINES)) . '.' ?>
        </p>
      </div>

      <div class="rangee" style="margin-top:14px;gap:10px;align-items:center">
        <button class="bouton" type="submit" <?= $disponible && $total > 0 ? '' : 'disabled' ?>>Envoyer</button>
        <span class="aide"><?= $total > 0
          ? 'Part immédiatement, et ne se rattrape pas.'
          : 'Personne n’est encore abonné : le bouton s’ouvrira au premier abonnement.' ?></span>
      </div>
    </form>
  </div>

  <div class="carte" style="margin-top:16px">
    <h3 style="margin:0 0 8px">Comment les gens s’abonnent</h3>
    <ul style="margin:0;padding-left:1.1em;line-height:1.7">
      <li>Le bouton se trouve dans <a href="<?= e(url('?p=profil')) ?>">Mon profil</a>, et sous
      le badge que l’invité vient de télécharger — c’est là qu’on accepte le plus.</li>
      <li>Il faut <strong>HTTPS</strong> : sans certificat, le navigateur refuse tout
      abonnement, sans message d’erreur visible.</li>
      <li>Un abonnement appartient à un <strong>navigateur</strong>, pas à une personne :
      le même invité sur son téléphone et sur son poste en fait deux.</li>
      <li>Les abonnements périmés — navigateur vidé, notifications rouvertes puis refusées —
      sont effacés automatiquement à l’envoi suivant.</li>
    </ul>
  </div>
</div>
