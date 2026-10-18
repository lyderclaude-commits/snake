<?php
/** Mentions légales et confidentialité : le texte se règle dans l'administration. */
$titre = $titre_page . ' | ' . reglage('nom_site');
$noindex = false;
?>
<section class="page-legale">
  <div class="pl-inner">
    <h1><?= e($titre_page) ?></h1>
    <?php if (trim($texte) !== ''): ?>
      <?= contenu_afficher($texte) ?>
    <?php else: ?>
      <?= $defaut ?>
    <?php endif; ?>
  </div>
</section>
