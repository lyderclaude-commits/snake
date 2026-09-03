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

  <!-- ---------- l'historique ---------- -->
  <?php
  /**
   * Ce qui est déjà parti.
   *
   * Deux nombres, et ils ne disent pas la même chose : `remises` compte les
   * NAVIGATEURS auxquels le service de notification a accepté le message,
   * `personnes` compte les gens derrière. Le second est celui qu'on cite ;
   * le premier est celui qui explique un écart.
   *
   * Aucun des deux ne prouve que quelqu'un a LU la notification — un
   * téléphone éteint la recevra plus tard, un autre l'écartera sans la
   * regarder. Le dire ici évite qu'on lise « 340 » comme « 340 personnes
   * ont vu l'annonce », ce que le chiffre ne dit pas.
   */
  $vers = function (int $n): string { return url('?p=diffusion' . ($n > 1 ? '&n=' . $n : '')); };
  ?>
  <div class="carte" style="margin-top:16px">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
      <div>
        <h3 style="margin:0 0 4px">Ce qui est déjà parti</h3>
        <p class="aide" style="margin:0">
          <?php if ($combien_diff === 0): ?>
            Rien encore. Chaque envoi laissera ici sa trace : à qui, combien, et quand.
          <?php else: ?>
            <?= (int) $combien_diff ?> envoi<?= $combien_diff > 1 ? 's' : '' ?> enregistré<?= $combien_diff > 1 ? 's' : '' ?>.
            Ce mois-ci : <strong><?= (int) $ce_mois['envois'] ?></strong> envoi<?= $ce_mois['envois'] > 1 ? 's' : '' ?>,
            <strong><?= (int) $ce_mois['personnes'] ?></strong> personne<?= $ce_mois['personnes'] > 1 ? 's' : '' ?> touchée<?= $ce_mois['personnes'] > 1 ? 's' : '' ?>.
          <?php endif; ?>
        </p>
      </div>
    </div>

    <?php if ($historique): ?>
      <div class="tableau" style="margin-top:14px">
        <table>
          <thead>
            <tr>
              <th>Quand</th>
              <th>Le message</th>
              <th>À qui</th>
              <th class="chiffre">Personnes</th>
              <th class="chiffre">Remises</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($historique as $d): $motifs = json_decode((string) ($d['motifs'] ?? ''), true); ?>
            <tr<?= (int) $d['remises'] === 0 ? ' class="pale"' : '' ?>>
              <td class="mono" style="white-space:nowrap">
                <?= e(gmdate('d/m/Y', strtotime((string) $d['cree_le']))) ?>
                <br><span class="aide"><?= e(gmdate('H:i', strtotime((string) $d['cree_le']))) ?> UTC</span>
              </td>
              <td>
                <b><?= e((string) $d['titre']) ?></b>
                <?php if ($d['corps']): ?>
                  <br><span class="aide"><?= e(mb_strimwidth((string) $d['corps'], 0, 90, '…')) ?></span>
                <?php endif; ?>
                <?php if ($equipe && $d['auteur_nom']): ?>
                  <br><span class="aide">par <?= e((string) $d['auteur_nom']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?= e(PUSH_SEGMENTS[$d['segment']] ?? ($d['segment'] === 'mes-invites'
                    ? 'Les invités de mes campagnes' : (string) $d['segment'])) ?>
                <br><span class="aide"><?= (int) $d['abonnements'] ?> abonnement<?= $d['abonnements'] > 1 ? 's' : '' ?> visé<?= $d['abonnements'] > 1 ? 's' : '' ?></span>
              </td>
              <td class="mono chiffre"><strong><?= (int) $d['personnes'] ?></strong></td>
              <td class="mono chiffre">
                <?= (int) $d['remises'] ?>
                <?php if ((int) $d['echecs']): ?>
                  <br><span class="aide" style="color:var(--orange)" title="<?= e($motifs ? implode(' · ', array_map(
                        fn($m, $n) => $n . ' × ' . $m, array_keys($motifs), array_values($motifs))) : '') ?>">
                    <?= (int) $d['echecs'] ?> échec<?= $d['echecs'] > 1 ? 's' : '' ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <p class="aide" style="margin:12px 0 0">
        <strong>Personnes</strong> compte les gens ; <strong>remises</strong> compte les navigateurs —
        le même invité sur son téléphone et sur son poste en fait deux. Ni l’un ni l’autre ne dit que la
        notification a été <em>lue</em> : elle a été acceptée par le service du navigateur, qui la
        remettra quand l’appareil sera rallumé.
      </p>

      <?php if ($pages > 1): ?>
        <div class="rangee" style="justify-content:center;gap:12px;margin-top:16px;align-items:center">
          <?php if ($page_n > 1): ?>
            <a class="bouton fant petit" href="<?= e($vers($page_n - 1)) ?>">← Plus récents</a>
          <?php endif; ?>
          <span class="aide">Page <?= (int) $page_n ?> sur <?= (int) $pages ?></span>
          <?php if ($page_n < $pages): ?>
            <a class="bouton fant petit" href="<?= e($vers($page_n + 1)) ?>">Plus anciens →</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
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
