/**
 * L'apparition au défilement des pages venues du guide.
 *
 * Deux lignes de rien, et une règle : le contenu se lit SANS ce fichier.
 * C'est lui qui pose `html.js`, et c'est cette classe qui autorise la
 * feuille à masquer les blocs avant leur entrée à l'écran. Le site
 * d'origine faisait l'inverse — les blocs naissaient invisibles — et une
 * page servie à un robot, à un lecteur d'articles ou à un navigateur dont
 * le script a échoué était blanche, sans un mot pour le dire.
 *
 * La classe est posée tout de suite, avant le premier rendu, pour qu'on
 * ne voie pas les blocs apparaître puis disparaître.
 */
(function () {
  'use strict';

  var racine = document.documentElement;
  var doux = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Sans IntersectionObserver, on ne masque rien : un navigateur ancien
     doit voir la page, pas un écran vide. */
  if (doux || !('IntersectionObserver' in window)) {
    return;
  }
  racine.className += (racine.className ? ' ' : '') + 'js';

  var demarrer = function () {
    var blocs = document.querySelectorAll('.fade-up');
    if (!blocs.length) { return; }

    var oeil = new IntersectionObserver(function (entrees) {
      entrees.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add('visible');
          oeil.unobserve(e.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

    Array.prototype.forEach.call(blocs, function (b) { oeil.observe(b); });

    /**
     * Le filet : au bout de trois secondes, tout se montre.
     *
     * Un observateur qui ne se déclenche jamais — page trop courte pour
     * défiler, onglet ouvert en arrière-plan, impression — laisserait le
     * contenu masqué pour toujours. Le défaut serait rare, invisible en
     * recette, et total pour celui qui le rencontre.
     */
    window.setTimeout(function () {
      Array.prototype.forEach.call(document.querySelectorAll('.fade-up:not(.visible)'),
        function (b) { b.classList.add('visible'); });
    }, 3000);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', demarrer);
  } else {
    demarrer();
  }
})();
