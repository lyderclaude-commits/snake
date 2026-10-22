<?php $titre = 'Premier compte'; ?>
<div class="etroit" style="max-width:520px">
  <a class="marque grand" href="<?= url('') ?>"><?= marque_logo() ?></a>
  <div class="carte">
    <h1>Bienvenue</h1>
    <p class="aide" style="margin:0 0 18px">Le site est installé. Créez le compte administrateur : c’est lui qui publie, règle le site et crée les autres comptes.</p>
    <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>
    <form method="post" action="<?= url('admin/premier-compte') ?>">
      <?= csrf_champ() ?>
      <div class="champ"><label for="nom">Votre nom</label><input id="nom" name="nom" required value="<?= e($v['nom'] ?? '') ?>" autofocus></div>
      <div class="champ"><label for="email">E-mail</label><input id="email" name="email" type="email" autocomplete="username" required value="<?= e($v['email'] ?? '') ?>"></div>
      <div class="champs">
        <div class="champ"><label for="mdp">Mot de passe</label><input id="mdp" name="mdp" type="password" autocomplete="new-password" minlength="10" required></div>
        <div class="champ"><label for="mdp2">Une seconde fois</label><input id="mdp2" name="mdp2" type="password" autocomplete="new-password" minlength="10" required></div>
      </div>
      <p class="aide" style="margin:-4px 0 16px">10 caractères au moins.</p>
      <button class="bouton large" type="submit">Créer le compte et continuer</button>
    </form>
  </div>
</div>
