<?php $titre = 'Connexion'; ?>
<div class="etroit">
  <a class="marque" href="<?= url('') ?>"><span class="logo-lyd" style="font-size:2.1rem">LIVE<span>Y</span>DREAM</span></a>
  <div class="carte">
    <h1>Connexion</h1>
    <p class="aide" style="margin:0 0 18px">L’espace d’administration du site.</p>
    <?php foreach (flashes() as [$_t, $_m]): ?><div class="msg <?= e($_t) ?>"><?= e($_m) ?></div><?php endforeach; ?>
    <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>
    <form method="post" action="<?= url('admin/connexion') ?>">
      <?= csrf_champ() ?>
      <input type="hidden" name="suite" value="<?= e((string) $suite) ?>">
      <div class="champ"><label for="email">E-mail</label><input id="email" name="email" type="email" autocomplete="username" required value="<?= e((string) $email) ?>" autofocus></div>
      <div class="champ"><label for="mdp">Mot de passe</label><input id="mdp" name="mdp" type="password" autocomplete="current-password" required></div>
      <button class="bouton large" type="submit">Se connecter</button>
    </form>
  </div>
  <p class="aide" style="text-align:center;margin-top:16px"><a href="<?= url('') ?>">← Retour au site</a></p>
</div>
