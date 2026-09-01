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
      <p style="margin:.35em 0 0">Il manque OpenSSL ou <code>hash_hkdf</code>. Ce sont des
      extensions PHP standard : votre hébergeur les active sur demande.</p>
    </div>
  <?php endif; ?>

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
