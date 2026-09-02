<?php
/** Mon profil : mon identité, mon mot de passe, mon départ. */
$erreur = $erreur ?? null;
$message = $message ?? null;
$verifie = email_verifie($me);
?>
<div class="contenu">
  <section class="entete">
    <h1>Mon profil</h1>
    <p><?= e(role_libelle($me['role'])) ?><?php if ($me['role'] === 'partenaire'): ?>
      · offre <?= e(formule_libelle($me['formule'] ?? null)) ?><?php endif; ?>
      · compte créé le <?= e(gmdate('d/m/Y', strtotime((string) $me['cree_le']))) ?></p>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <?php if (verification_exigee() && !$verifie): ?>
    <div class="msg err" style="margin-bottom:16px">
      <strong>Votre adresse n’est pas confirmée</strong>
      <p style="margin:.35em 0 .7em">Un lien vous attend à <?= e($me['email']) ?>.</p>
      <form method="post" action="<?= e(url('?p=renvoyer-verification')) ?>" style="margin:0">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <button class="bouton petit" type="submit">M’envoyer un nouveau lien</button>
      </form>
    </div>
  <?php endif; ?>

  <!-- ---------- identité ---------- -->
  <div class="carte">
    <h3 style="margin:0 0 4px">Mes informations</h3>
    <p class="aide" style="margin:0 0 16px">Elles servent à vous joindre, et à signer vos
    campagnes. Rien de tout cela n’est public.</p>

    <form method="post" action="<?= e(url('?p=profil-identite')) ?>">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <div class="grille g2">
        <div class="champ">
          <label for="p-nom">Nom et prénom</label>
          <input id="p-nom" name="nom" type="text" required maxlength="120" value="<?= e($me['nom']) ?>">
        </div>
        <div class="champ">
          <label for="p-email">Adresse e-mail</label>
          <input id="p-email" name="email" type="email" required value="<?= e($me['email']) ?>">
          <p class="aide">
            <?php if ($verifie): ?>
              Confirmée. En changer redemandera une confirmation.
            <?php else: ?>
              Non confirmée pour l’instant.
            <?php endif; ?>
          </p>
        </div>
        <div class="champ">
          <label for="p-organisation">Structure <span style="font-weight:400">(facultatif)</span></label>
          <input id="p-organisation" name="organisation" type="text" maxlength="120"
                 value="<?= e((string) ($me['organisation'] ?? '')) ?>">
        </div>
        <div class="champ">
          <label for="p-ville">Ville</label>
          <select id="p-ville" name="ville">
            <option value="">Non renseignée</option>
            <?php foreach (['lome' => 'Lomé', 'cotonou' => 'Cotonou', 'abidjan' => 'Abidjan'] as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= ($me['ville'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="champ" style="grid-column:1/-1">
          <label for="p-telephone">Téléphone <span style="font-weight:400">(facultatif)</span></label>
          <input id="p-telephone" name="telephone" type="tel" maxlength="25" placeholder="+228 90 00 00 00"
                 value="<?= e((string) ($me['telephone'] ?? '')) ?>">
          <p class="aide">Pour qu’on puisse vous joindre vite le jour d’un événement.</p>
        </div>
      </div>
      <button class="bouton" type="submit" style="margin-top:14px">Enregistrer</button>
    </form>
  </div>

  <!-- ---------- mot de passe ---------- -->
  <div class="carte" style="margin-top:16px">
    <h3 style="margin:0 0 4px">Mon mot de passe</h3>
    <p class="aide" style="margin:0 0 16px">L’actuel est demandé : un poste laissé ouvert
    ne doit pas suffire à s’approprier un compte.</p>

    <form method="post" action="<?= e(url('?p=profil-motdepasse')) ?>">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <div class="grille g2">
        <div class="champ">
          <label for="p-actuel">Mot de passe actuel</label>
          <input id="p-actuel" name="actuel" type="password" required autocomplete="current-password">
        </div>
        <div class="champ">
          <label for="p-nouveau">Nouveau mot de passe</label>
          <input id="p-nouveau" name="nouveau" type="password" required minlength="8" autocomplete="new-password">
          <p class="aide">Huit caractères au minimum.</p>
        </div>
      </div>
      <button class="bouton fant" type="submit" style="margin-top:14px">Changer le mot de passe</button>
    </form>
  </div>

  <!-- ---------- notifications ---------- -->
  <?php require RACINE . '/app/vues/partiels/push-abonnement.php'; ?>

  <!-- ---------- partir ---------- -->
  <?php if (!$interne): ?>
    <details class="carte" style="margin-top:16px">
      <summary style="cursor:pointer;font-weight:700">Supprimer mon compte</summary>
      <div style="margin-top:14px">
        <p class="aide" style="margin:0 0 4px"><strong>Ce qui s’en va :</strong> votre compte,
        vos liens courts, vos notifications et vos Koris.</p>
        <p class="aide" style="margin:0 0 14px"><strong>Ce qui reste :</strong>
        <?php if ($me['role'] === 'partenaire' && $campagnes > 0): ?>
          vos <?= (int) $campagnes ?> campagne(s) publiée(s) et les présences déjà scannées.
          Les badges que vos invités ont téléchargés portent leur QR : les effacer les casserait
          tous. Vos campagnes restent au catalogue, sans propriétaire, et l’équipe décide de la suite.
        <?php else: ?>
          les présences déjà scannées, qui sont l’historique d’événements réels.
        <?php endif; ?>
        </p>

        <form method="post" action="<?= e(url('?p=profil-supprimer')) ?>">
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <div class="champ">
            <label for="p-confirmation">Recopiez <code><?= e($me['email']) ?></code> pour confirmer</label>
            <input id="p-confirmation" name="confirmation" type="text" required autocomplete="off">
          </div>
          <button class="bouton danger" type="submit">Supprimer définitivement</button>
        </form>
      </div>
    </details>
  <?php endif; ?>
</div>
