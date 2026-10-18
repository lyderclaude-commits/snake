<?php $titre = 'Mon profil'; ?>
<div class="entete"><div><h1>Mon profil</h1><p><?= e(ROLES[$me['role']] ?? $me['role']) ?> · compte créé le <?= e(date_fr($me['cree_le'])) ?>.</p></div></div>
<?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>
<form method="post" action="<?= url('admin/profil') ?>" class="pile" style="max-width:640px">
  <?= csrf_champ() ?>
  <div class="carte">
    <h2>Identité</h2><p class="aide">Le nom apparaît comme auteur de vos articles.</p>
    <div class="champ"><label for="nom">Nom</label><input id="nom" name="nom" required value="<?= e($me['nom']) ?>"></div>
    <div class="champ" style="margin:0"><label for="email">E-mail de connexion</label><input id="email" name="email" type="email" required value="<?= e($me['email']) ?>"></div>
  </div>
  <div class="carte">
    <h2>Mot de passe</h2><p class="aide">Laissez vide pour ne pas le changer.</p>
    <div class="champ"><label for="actuel">Mot de passe actuel</label><input id="actuel" name="actuel" type="password" autocomplete="current-password"></div>
    <div class="champs">
      <div class="champ"><label for="mdp">Nouveau</label><input id="mdp" name="mdp" type="password" minlength="10" autocomplete="new-password"></div>
      <div class="champ"><label for="mdp2">Une seconde fois</label><input id="mdp2" name="mdp2" type="password" minlength="10" autocomplete="new-password"></div>
    </div>
  </div>
  <div><button class="bouton" type="submit">Enregistrer</button></div>
</form>
