<div class="etroit">
  <h1 style="margin-bottom:.4em">Connexion</h1>
  <?php
  /* Un message VENU D'AILLEURS — un compte supprimé, une session expirée.
     Il a sa place ici : c'est l'écran où l'on revient après être parti. */
  $venu = trim((string) ($_GET['ok'] ?? '')); ?>
  <?php if ($venu !== ''): ?><div class="msg ok" role="status"><?= e($venu) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <?php
  /**
   * Le second facteur remplace le formulaire, il ne s'y ajoute pas.
   *
   * Laisser les deux à l'écran laisserait croire qu'on peut recommencer
   * du début — et l'on retaperait son mot de passe au lieu de sortir son
   * téléphone.
   */
  ?>
  <?php if (!empty($en_attente)): ?>
    <form method="post" class="carte">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <p style="margin:0 0 14px">Mot de passe accepté pour
      <strong><?= e((string) $en_attente['email']) ?></strong>. Ouvrez votre application
      d’authentification et saisissez le code à six chiffres.</p>
      <div class="champ">
        <label for="code">Code à six chiffres</label>
        <input id="code" name="code" type="text" required inputmode="numeric" autocomplete="one-time-code"
               pattern="[0-9]{6}" maxlength="6" autofocus
               style="font-family:ui-monospace,monospace;font-size:1.4rem;letter-spacing:.3em;text-align:center">
        <p class="aide">Il change toutes les 30 secondes.</p>
      </div>
      <button class="bouton" type="submit" style="width:100%;justify-content:center">Continuer</button>
    </form>
    <p class="aide" style="text-align:center;margin-top:14px">
      <a href="<?= e(url('?p=connexion&annuler=1')) ?>">Recommencer avec un autre compte</a>
      <br>Téléphone perdu ? Un super-administrateur peut retirer la double authentification
      depuis votre fiche.
    </p>
  <?php else: ?>

  <form method="post" class="carte">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
    <div class="champ">
      <label for="email">Adresse e-mail</label>
      <input id="email" name="email" type="email" required autocomplete="email" value="<?= e($valeurs['email']) ?>">
    </div>
    <div class="champ">
      <label for="mdp">Mot de passe</label>
      <input id="mdp" name="mot_de_passe" type="password" required autocomplete="current-password">
    </div>
    <button class="bouton" type="submit" style="width:100%;justify-content:center">Se connecter</button>
  </form>
  <p class="aide" style="text-align:center;margin-top:14px">
    <a href="<?= e(url('?p=oubli')) ?>">Mot de passe oublié ?</a>
  </p>
  <p class="aide" style="text-align:center;margin-top:6px">
    Pas encore de compte ? <a href="<?= e(url('?p=inscription')) ?>">En créer un</a>
  </p>
  <?php endif; ?>
</div>
