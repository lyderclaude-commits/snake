<?php
/**
 * « Cette page vient avec une autre offre. »
 *
 * Un écran plutôt qu'un 403 : le compte a le droit d'être ici, c'est son
 * abonnement qui ne couvre pas encore la fonction. Le dire, dire laquelle
 * la couvre, et laisser repartir — un refus sec ferait croire à une panne.
 */
$debloque = $debloque ?? null;
?>
<div class="contenu">
  <section class="entete">
    <h1><?= e($quoi) ?></h1>
    <p><?= e($aide) ?></p>
  </section>

  <div class="carte">
    <p style="margin:0 0 4px"><strong>Votre offre
      <?= e(formule_libelle($me['formule'] ?? null)) ?> ne comprend pas cette fonction.</strong></p>
    <?php if ($debloque): ?>
      <p class="aide" style="margin:0 0 16px">Elle arrive avec l’offre
      <strong><?= e(formule_libelle($debloque)) ?></strong>.</p>
    <?php else: ?>
      <p class="aide" style="margin:0 0 16px">Écrivez-nous pour savoir comment y accéder.</p>
    <?php endif; ?>
    <div class="rangee" style="gap:10px">
      <a class="bouton" href="<?= e(url('?p=accueil#offres')) ?>">Voir les offres</a>
      <a class="bouton fant" href="<?= e(url('?p=partenaire')) ?>">Revenir à mes campagnes</a>
    </div>
  </div>
</div>
