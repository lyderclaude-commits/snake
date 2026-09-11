<?php
/**
 * L'identité qui figure sur chaque facture.
 *
 * Saisie une fois, RECOPIÉE dans chaque document à l'émission. C'est la
 * même règle que pour le nom du client et le montant, et pour la même
 * raison : déménager l'an prochain ne doit pas réécrire les factures de
 * cette année.
 *
 * Les champs sont volontairement libres plutôt que contraints : les
 * mentions obligatoires ne sont pas les mêmes au Togo, au Bénin et en Côte
 * d'Ivoire, et un formulaire qui impose un format de RCCM togolais devient
 * un mur le jour où l'on facture à Abidjan.
 */
$message = $message ?? null;
$erreur = $erreur ?? null;
$r = $reglages;
$taux = (int) $r['fact_tva'];
?>
<div class="contenu etroit-large">
  <p class="fil">
    <a class="retour" href="<?= e(url('?p=facturation')) ?>"><i>←</i>La facturation</a>
  </p>

  <section class="entete">
    <h1>Identité de facturation</h1>
    <p>Ce que porteront vos factures. Une facture sans RCCM ni NIF se fait refuser par la
    comptabilité de votre client, et il vous rappelle.</p>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <form method="post" action="<?= e(url('?p=reglages-facturation')) ?>">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

    <div class="carte" style="margin-bottom:16px">
      <h3 style="margin:0 0 14px">Qui émet</h3>
      <div class="champ">
        <label for="fact_raison">Raison sociale</label>
        <input id="fact_raison" name="fact_raison" type="text" value="<?= e((string) $r['fact_raison']) ?>">
      </div>
      <div class="grille g2">
        <div class="champ">
          <label for="fact_forme">Forme juridique</label>
          <input id="fact_forme" name="fact_forme" type="text"
                 value="<?= e((string) $r['fact_forme']) ?>" placeholder="SARL au capital de">
        </div>
        <div class="champ">
          <label for="fact_capital">Capital</label>
          <input id="fact_capital" name="fact_capital" type="text"
                 value="<?= e((string) $r['fact_capital']) ?>" placeholder="1 000 000 F CFA">
        </div>
      </div>
      <div class="champ">
        <label for="fact_adresse">Adresse</label>
        <input id="fact_adresse" name="fact_adresse" type="text" value="<?= e((string) $r['fact_adresse']) ?>">
      </div>
      <div class="grille g2">
        <div class="champ">
          <label for="fact_telephone">Téléphone</label>
          <input id="fact_telephone" name="fact_telephone" type="text"
                 value="<?= e((string) $r['fact_telephone']) ?>" placeholder="+228 90 00 00 00">
        </div>
        <div class="champ">
          <label for="fact_courriel">Adresse e-mail de facturation</label>
          <input id="fact_courriel" name="fact_courriel" type="text"
                 value="<?= e((string) $r['fact_courriel']) ?>">
        </div>
      </div>
    </div>

    <div class="carte" style="margin-bottom:16px">
      <h3 style="margin:0 0 4px">Les numéros qu’une comptabilité cherche</h3>
      <p class="aide" style="margin:0 0 14px">Ce sont eux qui font la différence entre un reçu et une
      facture. Laissez vide ce qui ne s’applique pas à vous.</p>
      <div class="grille g2">
        <div class="champ">
          <label for="fact_rccm">RCCM</label>
          <input id="fact_rccm" name="fact_rccm" type="text"
                 value="<?= e((string) $r['fact_rccm']) ?>" placeholder="TG-LOM-2024-B-1234">
        </div>
        <div class="champ">
          <label for="fact_nif">NIF</label>
          <input id="fact_nif" name="fact_nif" type="text"
                 value="<?= e((string) $r['fact_nif']) ?>" placeholder="1000123456">
        </div>
      </div>
    </div>

    <div class="carte" style="margin-bottom:16px">
      <h3 style="margin:0 0 4px">La TVA</h3>
      <p class="aide" style="margin:0 0 14px">
        <strong>Vos prix sont TTC</strong> : les 12 000 F d’une offre Croissance sont ce que le client
        règle. La facture remonte donc au hors taxes depuis ce montant, et écrit la taxe par
        différence pour que l’addition tombe juste au franc près.
      </p>
      <div class="grille g2">
        <div class="champ">
          <label for="fact_tva">Taux, en pourcentage</label>
          <input id="fact_tva" name="fact_tva" type="text" inputmode="decimal"
                 value="<?= e(rtrim(rtrim(number_format($taux / 100, 2, ',', ''), '0'), ',')) ?>"
                 placeholder="18">
          <p class="aide">Mettez <strong>0</strong> si vous n’êtes pas assujetti : les deux lignes
          disparaissent de la facture et la mention ci-dessous les remplace.</p>
        </div>
        <div class="champ">
          <label for="fact_devise">Devise affichée</label>
          <input id="fact_devise" name="fact_devise" type="text" value="<?= e((string) $r['fact_devise']) ?>">
        </div>
      </div>
      <div class="champ">
        <label for="fact_mention_tva">Mention quand le taux est à zéro</label>
        <input id="fact_mention_tva" name="fact_mention_tva" type="text"
               value="<?= e((string) $r['fact_mention_tva']) ?>">
      </div>

      <?php
      /**
       * L'exemple chiffré, sur le prix réel d'une offre.
       *
       * Un taux saisi de travers ne se voit pas dans un champ : il se voit
       * sur une facture, trois semaines plus tard, chez le client.
       */
      $ex = facture_montants((int) (FORMULES['croissance']['prix'] ?? 12000), $taux);
      ?>
      <div class="msg ok" style="margin:4px 0 0">
        <strong>Sur une offre Croissance à <?= e(montant_fr($ex['ttc'], (string) $r['fact_devise'])) ?> :</strong>
        <p style="margin:.35em 0 0">
          <?php if ($taux > 0): ?>
            hors taxes <?= e(montant_fr($ex['ht'])) ?>, TVA
            <?= e(rtrim(rtrim(number_format($taux / 100, 2, ',', ' '), '0'), ',')) ?> %
            <?= e(montant_fr($ex['tva'])) ?>, total
            <?= e(montant_fr($ex['ttc'], (string) $r['fact_devise'])) ?>.
          <?php else: ?>
            un seul montant, <?= e(montant_fr($ex['ttc'], (string) $r['fact_devise'])) ?>,
            et la mention « <?= e((string) $r['fact_mention_tva']) ?> ».
          <?php endif; ?>
        </p>
      </div>
    </div>

    <div class="carte" style="margin-bottom:16px">
      <h3 style="margin:0 0 4px">Le pied de page</h3>
      <p class="aide" style="margin:0 0 12px">Les mentions écrites en petit, en bas de chaque
      facture. L’identité légale y est ajoutée automatiquement devant.</p>
      <div class="champ">
        <label for="fact_pied" class="sr">Mentions</label>
        <textarea id="fact_pied" name="fact_pied" rows="4"><?= e((string) $r['fact_pied']) ?></textarea>
      </div>
    </div>

    <div class="rangee" style="gap:10px">
      <button class="bouton" type="submit">Enregistrer</button>
      <a class="bouton fant" href="<?= e(url('?p=facture-exemple')) ?>">Voir un exemple de facture</a>
    </div>
  </form>
</div>
