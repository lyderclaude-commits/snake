<?php
/** Demander un lien pour reprendre la main sur son compte. */
$fait = $fait ?? false;
?>
<div class="etroit">
  <h1 style="margin-bottom:.4em">Mot de passe oublié</h1>

  <?php if ($fait): ?>
    <div class="msg ok" role="status">
      <strong>Si un compte existe à cette adresse, le lien vient de partir.</strong>
      <p style="margin:.4em 0 0">Regardez votre boîte — et les indésirables, où les messages
      d’un domaine récent atterrissent souvent. Le lien vaut <?= OUBLI_HEURES ?> heures et ne
      sert qu’une fois.</p>
    </div>
    <p class="aide" style="text-align:center;margin-top:16px">
      <a href="<?= e(url('?p=connexion')) ?>">Revenir à la connexion</a>
    </p>
  <?php else: ?>
    <p class="aide" style="margin:0 0 16px">Indiquez l’adresse de votre compte. Nous vous
    enverrons un lien pour en choisir un nouveau.</p>

    <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

    <form method="post" class="carte">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <div class="champ">
        <label for="email">Adresse e-mail</label>
        <input id="email" name="email" type="email" required autocomplete="email"
               autofocus value="<?= e($valeurs['email']) ?>">
      </div>
      <button class="bouton" type="submit" style="width:100%;justify-content:center">
        M’envoyer le lien
      </button>
    </form>

    <p class="aide" style="text-align:center;margin-top:14px">
      <a href="<?= e(url('?p=connexion')) ?>">Revenir à la connexion</a>
    </p>
  <?php endif; ?>
</div>
