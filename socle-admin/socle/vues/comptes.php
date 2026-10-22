<?php $titre = 'Comptes'; ?>
<div class="entete"><div><h1>Comptes</h1><p><?= count($liste) ?> compte(s). Un <b>éditeur</b> écrit les contenus et lit les messages ; un <b>administrateur</b> règle en plus le site, les comptes et l’import.</p></div></div>
<?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>
<details class="carte" style="padding:0;margin-bottom:22px"<?= $erreur ? ' open' : '' ?>>
  <summary class="rangee" style="padding:16px 20px;cursor:pointer;list-style:none"><span class="bouton">+ Nouveau compte</span><span class="aide" style="margin:0">Pour quelqu’un de l’équipe qui doit publier.</span></summary>
  <form method="post" action="<?= url('admin/comptes') ?>" style="padding:4px 20px 20px;border-top:1px solid var(--border)">
    <?= csrf_champ() ?><input type="hidden" name="action" value="creer">
    <div class="champs" style="margin-top:16px">
      <div class="champ"><label for="n-nom">Nom</label><input id="n-nom" name="nom" required value="<?= e($v['nom'] ?? '') ?>"></div>
      <div class="champ"><label for="n-email">E-mail</label><input id="n-email" name="email" type="email" required value="<?= e($v['email'] ?? '') ?>"></div>
      <div class="champ"><label for="n-mdp">Mot de passe</label><input id="n-mdp" name="mdp" type="text" minlength="10" required autocomplete="off"><p class="aide">10 caractères au moins. À transmettre en main propre.</p></div>
      <div class="champ"><label for="n-role">Rôle</label><select id="n-role" name="role"><?php foreach (ROLES as $k => $l): ?><option value="<?= e($k) ?>"<?= ($v['role'] ?? 'editeur') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    </div>
    <button class="bouton" type="submit">Créer le compte</button>
  </form>
</details>
<div class="tableau">
  <table>
    <thead><tr><th>Compte</th><th>Rôle</th><th>Dernière visite</th><th>État</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($liste as $u): $moi = (int) $u['id'] === (int) $me['id']; ?>
      <tr>
        <td><b><?= e($u['nom']) ?></b><span class="sous"><?= e($u['email']) ?></span></td>
        <td>
          <?php if ($moi): ?><span class="pastille publie"><?= e(ROLES[$u['role']] ?? $u['role']) ?></span> <span class="aide" style="display:inline">votre compte</span>
          <?php else: ?>
            <form class="rangee" style="gap:6px;flex-wrap:nowrap" method="post" action="<?= url('admin/comptes') ?>"><?= csrf_champ() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
              <select name="role" style="width:auto" aria-label="Rôle de <?= e($u['nom']) ?>"><?php foreach (ROLES as $k => $l): ?><option value="<?= e($k) ?>"<?= $u['role'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
              <button class="bouton fant petit" type="submit">OK</button></form>
          <?php endif; ?>
        </td>
        <td class="aide"><?= e(il_y_a($u['vu_le'])) ?></td>
        <td><?= (int) $u['actif'] ? '<span class="pastille publie">Actif</span>' : '<span class="pastille inactif">Suspendu</span>' ?></td>
        <td class="actions">
          <?php if (!$moi): ?>
            <form class="enligne" method="post" action="<?= url('admin/comptes') ?>" data-confirmer="Générer un nouveau mot de passe pour <?= e($u['nom']) ?> ?"><?= csrf_champ() ?><input type="hidden" name="action" value="mdp"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="bouton fant petit" type="submit">Nouveau mot de passe</button></form>
            <form class="enligne" method="post" action="<?= url('admin/comptes') ?>"><?= csrf_champ() ?><input type="hidden" name="action" value="actif"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="bouton fant petit" type="submit"><?= (int) $u['actif'] ? 'Suspendre' : 'Réactiver' ?></button></form>
            <form class="enligne" method="post" action="<?= url('admin/comptes') ?>" data-confirmer="Supprimer le compte de <?= e($u['nom']) ?> ?"><?= csrf_champ() ?><input type="hidden" name="action" value="supprimer"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="bouton danger fant petit" type="submit">Supprimer</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="aide" style="margin-top:12px">Suspendre coupe l’accès sans rien effacer. Personne ne peut se suspendre ni se rétrograder soi-même : le site ne doit jamais se retrouver sans administrateur.</p>
