/**
 * Les menus se ferment quand on va voir ailleurs.
 *
 * Tous les menus du site sont des `<details>`, et c'est délibéré : ils
 * s'ouvrent au clic et au clavier sans une ligne de script, et restent
 * ouvrables si celui-ci ne charge pas. Mais un `<details>` ne sait pas se
 * refermer : il reste ouvert tant qu'on ne reclique pas exactement sur son
 * bouton. On pouvait donc laisser derrière soi deux volets dépliés, l'un
 * par-dessus la page, l'autre par-dessus le premier.
 *
 * Trois règles, et rien de plus :
 *   — un clic hors d'un menu ouvert le ferme ;
 *   — ouvrir un menu ferme les autres ;
 *   — Échap ferme celui qui est ouvert, et rend le clavier à son bouton.
 *
 * Ce fichier ne touche QUE les menus, nommés un par un. Les `<details>` de
 * contenu — les questions fréquentes, les blocs qu'on déplie pour lire —
 * n'ont rien à voir là-dedans : se refermer parce qu'on a cliqué à côté
 * serait pour eux une panne, pas un service.
 */
(function () {
  'use strict';

  var MENUS = 'details.wk-grp, details.wk-compte, details.wk-burger,'
            + ' details.deroulant, details.menu';

  var ouverts = function () {
    var out = [];
    var tous = document.querySelectorAll(MENUS);
    for (var i = 0; i < tous.length; i++) {
      if (tous[i].open) { out.push(tous[i]); }
    }
    return out;
  };

  /**
   * Le menu du haut, au-dessus de 901 px, n'est pas un menu déroulant.
   *
   * Sa feuille le déplie en permanence et cache son bouton : le fermer
   * n'aurait aucun effet visible, mais son attribut `open` sauterait à
   * chaque clic sur la page, pour rien.
   */
  var estDeroulant = function (d) {
    if (!d.classList.contains('menu')) { return true; }
    var s = d.querySelector(':scope > summary');
    return !!(s && s.offsetParent !== null);
  };

  document.addEventListener('click', function (e) {
    var liste = ouverts();
    for (var i = 0; i < liste.length; i++) {
      if (estDeroulant(liste[i]) && !liste[i].contains(e.target)) {
        liste[i].open = false;
      }
    }
  });

  /* `toggle` ne remonte pas : on l'écoute à la descente. */
  document.addEventListener('toggle', function (e) {
    var d = e.target;
    if (!d || !d.open || !d.matches || !d.matches(MENUS)) { return; }
    var liste = ouverts();
    for (var i = 0; i < liste.length; i++) {
      /* Un menu DANS un autre — le groupe « Boost » du volet mobile —
         reste ouvert : le fermer refermerait ce qu'on vient d'ouvrir. */
      if (liste[i] !== d && !liste[i].contains(d) && !d.contains(liste[i])) {
        liste[i].open = false;
      }
    }
  }, true);

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    var liste = ouverts();
    for (var i = liste.length - 1; i >= 0; i--) {
      if (!estDeroulant(liste[i])) { continue; }
      liste[i].open = false;
      /* Le clavier revient sur le bouton qui l'a ouvert, et non au début
         de la page : sans cela, Échap fait tout recommencer. */
      var s = liste[i].querySelector(':scope > summary');
      if (s && typeof s.focus === 'function') { s.focus(); }
      return;
    }
  });
})();
