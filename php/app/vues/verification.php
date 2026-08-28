<?php
/** Ce qu'on voit après avoir cliqué le lien reçu par courriel. */
$ok = $resultat['ok'];
$moi = utilisateur_courant();
?>
<div class="contenu">
  <section class="entete">
    <h1><?= $ok ? 'Adresse confirmée' : 'Ce lien n’a pas fonctionné' ?></h1>
    <p><?= e($resultat['message']) ?></p>
  </section>

  <div class="carte">
    <?php if ($ok): ?>
      <p style="margin:0 0 14px">Vous pouvez maintenant soumettre vos décors à la relecture.
      L’équipe s’engage à répondre sous 24 heures ouvrées, et sa décision vous parviendra
      à cette adresse.</p>
      <a class="bouton" href="<?= e(url($moi && $moi['role'] === 'partenaire' ? '?p=partenaire' : '?p=compte')) ?>">
        Aller à mon espace
      </a>
    <?php else: ?>
      <p style="margin:0 0 14px">Un lien de confirmation ne sert qu’une fois et vaut
      <?= VERIF_HEURES ?> heures. Si vous en avez demandé un nouveau, c’est le dernier reçu
      qui compte — les précédents s’annulent.</p>
      <?php if ($moi && !email_verifie($moi)): ?>
        <form method="post" action="<?= e(url('?p=renvoyer-verification')) ?>">
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <button class="bouton" type="submit">M’envoyer un nouveau lien</button>
        </form>
      <?php elseif (!$moi): ?>
        <a class="bouton" href="<?= e(url('?p=connexion')) ?>">Se connecter pour en redemander un</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
