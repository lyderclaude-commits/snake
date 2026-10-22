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

<?php if (module('push')):
$appareils = tous('SELECT * FROM push_abonnements WHERE utilisateur_id = ? ORDER BY cree_le DESC', [$me['id']]);
$manque = array_keys(array_filter(push_pre_requis(), fn($v) => !$v));
?>
<div class="carte" style="max-width:640px;margin-top:18px" id="notifications" data-push>
  <h2>Notifications</h2>
  <p class="aide">Une notification sur votre téléphone ou votre ordinateur à chaque nouvelle demande de contact — même site fermé. À activer sur chaque appareil, dans le navigateur où vous voulez la recevoir.</p>
  <?php if ($manque): ?>
    <div class="msg attention">L’hébergement ne fournit pas : <b><?= e(implode(', ', $manque)) ?></b>. Les notifications ne peuvent pas partir tant que ces fonctions manquent — demandez-les à l’hébergeur.</div>
  <?php endif; ?>
  <div class="msg info" data-push-etat hidden></div>
  <div class="rangee">
    <button class="bouton" type="button" data-push-activer hidden>Activer sur cet appareil</button>
    <button class="bouton fant" type="button" data-push-couper hidden>Désactiver sur cet appareil</button>
    <?php if ($appareils): ?>
      <form method="post" action="<?= url('admin/push/tester') ?>"><?= csrf_champ() ?><button class="bouton fant" type="submit">Envoyer une notification de test</button></form>
    <?php endif; ?>
  </div>
  <p class="aide" data-push-ios hidden>Sur iPhone et iPad : ajoutez d’abord le site à l’écran d’accueil (Partager → « Sur l’écran d’accueil »), puis ouvrez-le depuis cette icône pour activer les notifications.</p>
  <?php if ($appareils): ?>
    <ul class="appareils">
      <?php foreach ($appareils as $ap): ?>
        <li>
          <span><b><?= e($ap['appareil'] ?: 'Appareil') ?></b>
            <span class="aide" style="display:block">depuis le <?= e(date_fr($ap['cree_le'])) ?><?= $ap['dernier_envoi'] ? ' · dernier envoi ' . e(il_y_a($ap['dernier_envoi'])) . ' : ' . e($ap['dernier_resultat']) : '' ?></span></span>
          <form method="post" action="<?= url('admin/push/desabonner') ?>"><?= csrf_champ() ?><input type="hidden" name="id" value="<?= (int) $ap['id'] ?>"><button class="bouton danger fant petit" type="submit">Retirer</button></form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="aide" style="margin-top:12px">Aucun appareil abonné pour l’instant.</p>
  <?php endif; ?>
</div>
<?php endif; ?>
