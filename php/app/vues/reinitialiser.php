<?php
/**
 * Choisir un nouveau mot de passe, au bout du lien reçu.
 *
 * Un lien mort ne dit pas « erreur » : il dit ce qui s'est passé — expiré,
 * déjà servi, ou remplacé par une demande plus récente — et il propose
 * d'en redemander un. Un cul-de-sac, ici, renvoie à l'équipe.
 */
?>
<div class="etroit">
  <h1 style="margin-bottom:.4em">Nouveau mot de passe</h1>

  <?php if (!$compte): ?>
    <div class="msg err" role="alert">
      <strong>Ce lien ne fonctionne plus.</strong>
      <p style="margin:.4em 0 0">Il a expiré, il a déjà servi, ou une demande plus récente
      l’a remplacé. Aucun mot de passe n’a été changé.</p>
    </div>
    <p style="text-align:center;margin-top:16px">
      <a class="bouton" href="<?= e(url('?p=oubli')) ?>">Demander un nouveau lien</a>
    </p>
  <?php else: ?>
    <p class="aide" style="margin:0 0 16px">Pour le compte
    <strong><?= e($compte['email']) ?></strong>.</p>

    <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

    <form method="post" class="carte">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <input type="hidden" name="jeton" value="<?= e($jeton) ?>">
      <div class="champ">
        <label for="mdp">Nouveau mot de passe</label>
        <input id="mdp" name="mot_de_passe" type="password" required minlength="8"
               autocomplete="new-password" autofocus>
        <p class="aide">Huit caractères au minimum. Une phrase dont vous vous souviendrez
        vaut mieux qu’un mot compliqué que vous noterez.</p>
      </div>
      <div class="champ">
        <label for="mdp2">Encore une fois</label>
        <input id="mdp2" name="confirmation" type="password" required minlength="8"
               autocomplete="new-password">
      </div>
      <button class="bouton" type="submit" style="width:100%;justify-content:center">
        Enregistrer et se connecter
      </button>
    </form>
  <?php endif; ?>
</div>
