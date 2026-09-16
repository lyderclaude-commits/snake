<?php
/**
 * Le sondage, tel que le voit l'invité.
 *
 * Une page publique, sans compte, sans menu, sans rien d'autre à faire que
 * répondre. Trois questions, un bouton. Tout ce qu'on ajouterait ici ferait
 * baisser le taux de réponse, y compris une invitation à créer un compte.
 */
?>
<div class="contenu etroit">

  <?php if (!$ctx): ?>

    <section class="carte" style="text-align:center;padding:36px 22px">
      <h1 style="margin:0 0 10px">Ce sondage n’est plus ouvert</h1>
      <p class="aide">Le lien a peut-être expiré, ou l’organisateur a clos son sondage.
      Rien n’est perdu : votre badge, lui, reste valide.</p>
      <p style="margin-top:18px"><a class="bouton fant" href="<?= e(url('')) ?>">Retour à l’accueil</a></p>
    </section>

  <?php elseif ($ctx['deja'] || $envoye): ?>

    <section class="carte" style="text-align:center;padding:36px 22px">
      <h1 style="margin:0 0 10px">Merci</h1>
      <p class="aide">Votre réponse est enregistrée. Une seule par personne :
      c’est ce qui fait que la moyenne veut dire quelque chose.</p>
      <p style="margin-top:18px">
        <a class="bouton fant"
           href="<?= e(url('?p=decor&slug=' . rawurlencode((string) $ctx['decor']['slug']))) ?>">Revoir le décor</a>
      </p>
    </section>

  <?php else: ?>

    <section class="carte">
      <h1 style="margin:0 0 4px"><?= e((string) $ctx['decor']['titre']) ?></h1>
      <p class="aide" style="margin:0 0 20px">Trois questions, et c’est tout.</p>

      <?php if ($erreur): ?><div class="msg err"><?= e($erreur) ?></div><?php endif; ?>

      <form method="post" action="<?= e(url('?p=sondage&j='
            . rawurlencode((string) $ctx['envoi']['jeton']))) ?>">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

        <fieldset class="sond-bloc">
          <legend>Comment avez-vous trouvé la soirée ?</legend>
          <div class="sond-notes">
            <?php foreach ([1, 2, 3, 4, 5] as $n): ?>
              <label class="sond-note">
                <input type="radio" name="note" value="<?= $n ?>"
                       <?= (int) $valeurs['note'] === $n ? 'checked' : '' ?> required>
                <span><?= $n ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="aide">1, c’était mauvais. 5, c’était excellent.</p>
        </fieldset>

        <fieldset class="sond-bloc">
          <legend>Reviendrez-vous l’an prochain ?</legend>
          <div class="sond-ouinon">
            <?php foreach (['oui' => 'Oui', 'non' => 'Non'] as $cle => $lib): ?>
              <label class="sond-note">
                <input type="radio" name="revient" value="<?= e($cle) ?>"
                       <?= $valeurs['revient'] === $cle ? 'checked' : '' ?>>
                <span><?= e($lib) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <div class="champ">
          <label for="s-mot">Un mot pour l’organisateur ?</label>
          <textarea id="s-mot" name="mot" rows="3" maxlength="600"
                    placeholder="Facultatif"><?= e((string) $valeurs['mot']) ?></textarea>
        </div>

        <button class="bouton" type="submit">Envoyer ma réponse</button>

        <p class="aide" style="margin-top:14px">
          Votre réponse n’est pas signée : l’organisateur voit les notes, pas qui a mis quoi.
        </p>
      </form>
    </section>

  <?php endif; ?>

</div>
