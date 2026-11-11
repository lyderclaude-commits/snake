<?php
/**
 * La barre de recherche du guide, avec ses sélecteurs.
 *
 * Un formulaire GET des plus simples : le texte part dans `q`, les
 * sélecteurs portent leurs filtres, ce qui reste voyage en champs
 * cachés. Un formulaire GET REMPLACE la requête de l'action, donc tout
 * ce qui doit survivre à la soumission doit être un champ, pas un
 * morceau de l'adresse.
 *
 * `guide.js` retire les champs vides à la soumission — l'adresse reste
 * propre, `?p=agenda&q=zouglou` et non `?p=agenda&periode=&ville=&q=`
 * — et relance la recherche quand on change un sélecteur.
 *
 * Variables attendues :
 *   $recherche_page        la page visée ('agenda', 'sortir-lome'...)
 *   $recherche_q           le texte déjà tapé
 *   $recherche_params      les filtres sans sélecteur, en champs cachés
 *   $recherche_selects     les sélecteurs : nom, libelle, valeur, options
 *   $recherche_placeholder la phrase d'attente du champ
 */
?>
<form class="guide-recherche" method="get" action="<?= e(base_url()) ?>/">
  <input type="hidden" name="p" value="<?= e($recherche_page) ?>">
  <?php foreach (($recherche_params ?? []) as $_cle => $_valeur): ?>
    <?php if ($_valeur === '' || $_valeur === null) { continue; } ?>
    <input type="hidden" name="<?= e((string) $_cle) ?>" value="<?= e((string) $_valeur) ?>">
  <?php endforeach; ?>
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"></circle><line x1="16.5" y1="16.5" x2="21" y2="21"></line></svg>
  <input type="search" name="q" value="<?= e($recherche_q ?? '') ?>" placeholder="<?= e($recherche_placeholder ?? 'Chercher...') ?>" aria-label="Rechercher">
  <?php foreach (($recherche_selects ?? []) as $_s): ?>
    <select name="<?= e($_s['nom']) ?>" aria-label="<?= e($_s['libelle']) ?>">
      <?php foreach ($_s['options'] as $_cle => $_libelle): ?>
        <option value="<?= e((string) $_cle) ?>"<?= (string) ($_s['valeur'] ?? '') === (string) $_cle ? ' selected' : '' ?>><?= e($_libelle) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endforeach; ?>
  <button class="btn btn-primary" type="submit">Chercher</button>
</form>
