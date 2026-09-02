<?php
/**
 * Une facture, telle qu'on l'imprime ou qu'on l'envoie.
 *
 * Volontairement austère, et volontairement imprimable : c'est un document
 * qu'un organisateur présente à sa comptabilité ou à son employeur pour se
 * faire rembourser. Les couleurs, les cartes et le menu du produit n'y ont
 * rien à faire — on les retire à l'impression.
 *
 * Ce qui y figure ne change plus : le nom, la structure et le montant ont
 * été recopiés à l'émission. Changer le tarif d'une offre ne doit pas
 * réécrire un document déjà remis.
 */
$total = (int) $f['montant'];
?>
<div class="contenu etroit-large">
  <p class="fil sans-impression">
    <a href="<?= e(url($retour)) ?>">← Retour</a>
    · <button type="button" class="lien-bouton" onclick="window.print()">Imprimer</button>
  </p>

  <article class="facture">
    <header class="facture-tete">
      <div>
        <p class="facture-marque">Wakabi Boost</p>
        <p class="aide" style="margin:2px 0 0">Le guide des sorties · Lomé</p>
      </div>
      <div style="text-align:right">
        <p class="facture-numero"><?= e((string) $f['numero']) ?></p>
        <p class="aide" style="margin:2px 0 0">Émise le <?= e(date_fr((string) $f['cree_le'])) ?></p>
      </div>
    </header>

    <section class="facture-parties">
      <div>
        <p class="facture-etiquette">Facturé à</p>
        <p style="margin:0"><strong><?= e((string) ($f['client_org'] ?: $f['client_nom'])) ?></strong>
        <?php if ($f['client_org'] && $f['client_nom']): ?>
          <br><?= e((string) $f['client_nom']) ?>
        <?php endif; ?></p>
      </div>
      <div>
        <p class="facture-etiquette">Période couverte</p>
        <p style="margin:0"><?= e(date_fr((string) $f['debut_le'])) ?>
        au <?= e(date_fr((string) $f['fin_le'])) ?></p>
      </div>
    </section>

    <div class="tableau">
      <table>
        <thead><tr><th>Désignation</th><th class="chiffre">Montant</th></tr></thead>
        <tbody>
          <tr>
            <td>
              <strong>Offre <?= e(formule_libelle((string) $f['formule'])) ?></strong>
              <br><span class="aide">Abonnement du <?= e(date_fr((string) $f['debut_le'])) ?>
              au <?= e(date_fr((string) $f['fin_le'])) ?></span>
            </td>
            <td class="mono chiffre"><?= number_format($total, 0, ',', ' ') ?> F</td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <th>Total réglé</th>
            <th class="mono chiffre"><?= number_format($total, 0, ',', ' ') ?> F CFA</th>
          </tr>
        </tfoot>
      </table>
    </div>

    <p class="aide" style="margin-top:18px">
      <?php if ($f['reglee_le']): ?>
        Réglée le <?= e(date_fr((string) $f['reglee_le'])) ?>. Ce document vaut reçu.
      <?php else: ?>
        En attente de règlement.
      <?php endif; ?>
      <?php if ($f['note']): ?><br><?= e((string) $f['note']) ?><?php endif; ?>
    </p>
  </article>
</div>
