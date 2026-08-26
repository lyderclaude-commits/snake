<div class="etroit">
  <h1 style="margin-bottom:.4em">Connexion</h1>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>
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
    Pas encore de compte ? <a href="<?= e(url('?p=inscription')) ?>">En créer un</a>
  </p>
</div>
