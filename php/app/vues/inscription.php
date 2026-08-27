<div class="etroit">
  <h1 style="margin-bottom:.4em">Créer un compte</h1>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>
  <?php if (!empty($offre)): ?>
    <div class="msg info">
      <strong>Offre <?= e(formule_libelle($offre)) ?> demandée.</strong>
      Créez votre compte : il démarre en Découverte, et l’équipe Wakabi active votre offre
      dès réception du paiement. Vous n’avancez rien.
    </div>
  <?php endif; ?>

  <form method="post" class="carte">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
    <?php if (!empty($offre)): ?><input type="hidden" name="offre" value="<?= e($offre) ?>"><?php endif; ?>
    <div class="champ">
      <label for="role">Type de compte</label>
      <select id="role" name="role">
        <option value="participant" <?= $valeurs['role'] === 'participant' ? 'selected' : '' ?>>Participant : je crée des badges</option>
        <option value="partenaire" <?= $valeurs['role'] === 'partenaire' ? 'selected' : '' ?>>Organisateur : je crée des campagnes</option>
      </select>
    </div>
    <div class="champ"><label for="nom">Nom</label>
      <input id="nom" name="nom" type="text" required value="<?= e($valeurs['nom']) ?>"></div>
    <div class="champ"><label for="org">Structure <span style="font-weight:400">(organisateurs)</span></label>
      <input id="org" name="organisation" type="text" value="<?= e($valeurs['organisation']) ?>"></div>
    <div class="champ"><label for="email">Adresse e-mail</label>
      <input id="email" name="email" type="email" required autocomplete="email" value="<?= e($valeurs['email']) ?>"></div>
    <div class="champ"><label for="mdp">Mot de passe</label>
      <input id="mdp" name="mot_de_passe" type="password" required minlength="8" autocomplete="new-password">
      <p class="aide">Huit caractères au minimum.</p></div>
    <button class="bouton" type="submit" style="width:100%;justify-content:center">Créer mon compte</button>
  </form>
</div>
