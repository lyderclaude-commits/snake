<?php
/**
 * Ce qu'on voit après avoir cliqué le lien reçu par courriel.
 *
 * Trois issues, et une seule était traitée jusqu'ici :
 *
 *  - **confirmée à l'instant** — le cas nominal ;
 *  - **déjà confirmée** — un antivirus de messagerie a suivi le lien avant
 *    la personne, ou celle-ci a tapé deux fois. C'est une RÉUSSITE, et
 *    l'annoncer comme une panne était le vrai défaut : l'adresse était
 *    bien confirmée pendant que l'écran disait le contraire ;
 *  - **lien remplacé ou périmé** — le seul échec réel, et il ne doit pas
 *    finir en mur. On y demande une adresse et l'on renvoie un lien, sans
 *    exiger de session : quelqu'un qui n'arrive pas à confirmer son adresse
 *    est justement quelqu'un qui n'a peut-être jamais pu ouvrir son compte.
 */
$ok = $resultat['ok'];
$deja = !empty($resultat['deja']);
$demande = $demande ?? null;
$moi = utilisateur_courant();
?>
<div class="contenu etroit-large">
  <section class="entete">
    <h1><?= $ok ? ($deja ? 'Adresse déjà confirmée' : 'Adresse confirmée') : 'Ce lien n’a pas fonctionné' ?></h1>
    <p><?= e($resultat['message']) ?></p>
  </section>

  <?php if ($demande): ?>
    <div class="msg ok" role="status"><?= e($demande) ?></div>
  <?php endif; ?>

  <div class="carte">
    <?php if ($ok): ?>
      <p style="margin:0 0 14px">
        <?php if ($deja): ?>
          Un lien de confirmation peut être ouvert plusieurs fois : les filtres de certaines
          messageries le suivent avant vous pour vérifier où il mène. Que ce soit vous ou eux
          qui l’ayez ouvert en premier ne change rien — <strong>l’adresse est confirmée</strong>.
        <?php else: ?>
          Vous pouvez maintenant soumettre vos décors à la relecture. L’équipe s’engage à
          répondre sous 24 heures ouvrées, et sa décision vous parviendra à cette adresse.
        <?php endif; ?>
      </p>
      <?php
      /* Un visiteur non connecté va à la connexion, pas à `?p=compte` qui
         l'y renverrait de toute façon — un aller-retour visible pour rien. */
      ?>
      <a class="bouton" href="<?= e(url($moi ? accueil_de($moi) : '?p=connexion')) ?>">
        <?= $moi ? 'Aller à mon espace' : 'Se connecter' ?>
      </a>

    <?php else: ?>
      <p style="margin:0 0 16px">Un lien de confirmation vaut <?= VERIF_HEURES ?> heures, et
      demander un lien neuf annule les précédents — c’est <strong>le dernier reçu</strong> qui
      compte. Donnez votre adresse, il en repart un tout de suite.</p>

      <form method="post" action="<?= e(url('?p=verif-demander')) ?>">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <div class="champ">
          <label for="v-email">Votre adresse e-mail</label>
          <input id="v-email" name="email" type="email" required autocomplete="email"
                 value="<?= e((string) ($moi['email'] ?? '')) ?>"
                 placeholder="vous@exemple.tg">
        </div>
        <button class="bouton" type="submit">M’envoyer un lien neuf</button>
      </form>

      <p class="aide" style="margin:16px 0 0">
        Regardez aussi vos <strong>indésirables</strong> : c’est là qu’atterrit le plus souvent
        un premier message d’un expéditeur inconnu.
      </p>
    <?php endif; ?>
  </div>
</div>
