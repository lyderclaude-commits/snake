<?php
/**
 * La fin d'une liste de vitrine : ce que le site ne montre PAS.
 *
 * Le guide s'arrête à sept lieux et six événements. Une liste qui
 * s'arrête sans rien dire laisse croire qu'elle est complète, et c'est
 * l'inverse de ce qu'on vend : elle se ferme donc sur la porte de
 * l'application, qui, elle, montre tout.
 *
 * Deux variables l'habillent, toutes deux facultatives :
 *   $plus_titre   le titre
 *   $plus_texte   la ligne en dessous
 *
 * Elles sont EFFACÉES après usage. Ce fichier est inclus plusieurs fois
 * dans une même page, et une variable qui survit habillerait le bloc
 * suivant avec le texte du précédent — un bug qui ne se voit qu'à la
 * relecture, et jamais au moment où on l'écrit.
 */
$_t = $plus_titre ?? 'Il y en a bien plus dans l’application';
$_p = $plus_texte ?? 'Le site en montre un aperçu. Tous les lieux, tous les événements, '
    . 'ville par ville et à jour, vivent dans l’application Wakabi.';
unset($plus_titre, $plus_texte);
?>
<div class="encart-ville">
  <h3><?= e($_t) ?></h3>
  <p><?= e($_p) ?></p>
  <a class="btn btn-primary btn-lg" href="<?= e(APPLICATION_URL) ?>"<?= sortie_externe(APPLICATION_URL) ?>>Ouvrir l’application</a>
</div>
