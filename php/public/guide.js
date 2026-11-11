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

/*
 * ------------------------------------------------------------------ *
 *  Le badge « En direct depuis … » du hero.
 *
 *  La ville écrite dans la vue n'est qu'un repli : une fois la page
 *  affichée, on demande à un service de géolocalisation par IP d'où
 *  vient le visiteur, et on écrit sa ville à la place. Sans autorisation
 *  du navigateur — la géolocalisation précise réclame un accord qu'on
 *  ne demande pas dès la porte —, sans bloquer le rendu, et sans rien
 *  casser si le service ne répond pas : le repli reste en place.
 *
 *  Le passage par le service confie l'adresse IP du visiteur à un
 *  tiers ; le résultat garde en sessionStorage, on ne le paie qu'une
 *  fois par onglet.
 * ------------------------------------------------------------------ */
(function () {
  'use strict';

  var cible = document.getElementById('wk-geo');
  if (!cible || !window.fetch) { return; }

  var memo = null;
  try { memo = sessionStorage.getItem('wk-geo'); } catch (e) { /* stockage fermé */ }
  if (memo) { cible.textContent = memo; return; }

  /* geojs.io nomme les pays en anglais ; la petite table remet les
     plus probables en français, et le nom brut passe sinon — « Togo »
     ou « Lomé » s'écrivent pareil dans les deux langues. */
  var PAYS_FR = {
    'Ivory Coast': 'Côte d’Ivoire',
    'Cameroon': 'Cameroun',
    'Senegal': 'Sénégal',
    'Benin': 'Bénin',
    'Guinea': 'Guinée',
    'Morocco': 'Maroc',
    'Spain': 'Espagne',
    'Germany': 'Allemagne',
    'Belgium': 'Belgique',
    'United States': 'États-Unis',
    'United Kingdom': 'Royaume-Uni',
    'Italy': 'Italie'
  };

  var ecrit = function (ville, pays) {
    var texte = ville + ', ' + (PAYS_FR[pays] || pays);
    cible.textContent = texte;
    try { sessionStorage.setItem('wk-geo', texte); } catch (e) { /* rien */ }
  };

  /* Premier service : ipwho.is, qui répond en français. Il est borné en
     nombre de requêtes ; quand il refuse (429) ou bafouille, on retente
     avec geojs.io, plus généreux mais anglophone. */
  fetch('https://ipwho.is/?language=fr')
    .then(function (reponse) { return reponse.json(); })
    .then(function (donnees) {
      if (!donnees || donnees.success === false || !donnees.city || !donnees.country) {
        throw new Error('ipwho.is sans ville');
      }
      ecrit(donnees.city, donnees.country);
    })
    .catch(function () {
      fetch('https://get.geojs.io/v1/ip/geo.json')
        .then(function (reponse) { return reponse.json(); })
        .then(function (donnees) {
          if (donnees && donnees.city && donnees.country) { ecrit(donnees.city, donnees.country); }
        })
        .catch(function () { /* le repli « Lomé, Togo » reste affiché */ });
    });
})();

/* ------------------------------------------------------------------ */
/* Le guide : recherche, filtres, bascule liste / grille               */
/* ------------------------------------------------------------------ */

/* La bascule permute les deux vues SUR PLACE : le serveur rend les
   deux dans la zone `.guide-zone` voisine, l'une cachée, et JavaScript
   les échange sans recharger la page. L'adresse est mise à jour en
   même temps : un copier-coller garde la vue choisie. Sans JavaScript,
   les liens rechargent la page comme avant - rien ne casse. */
(function () {
  document.addEventListener('click', function (e) {
    var cible = e.target;
    if (!cible || !cible.closest) { return; }
    var lien = cible.closest('.guide-bascule a');
    if (!lien) { return; }
    var bascule = lien.closest('.guide-bascule');
    var zone = bascule ? bascule.closest('.guide-zone') : null;
    if (!zone) { return; }
    e.preventDefault();
    var vue = lien.getAttribute('data-vue');
    zone.querySelectorAll('.vue-liste, .vue-grille').forEach(function (bloc) {
      bloc.hidden = !bloc.classList.contains('vue-' + vue);
    });
    bascule.querySelectorAll('a').forEach(function (a) {
      a.classList.toggle('actif', a === lien);
    });
    if (window.history && history.replaceState) {
      history.replaceState(null, '', lien.getAttribute('href'));
    }
  });
})();

/* La recherche : à la soumission, les champs vides ne partent pas dans
   l'adresse - `?p=agenda&q=zouglou`, pas une rafale de filtres vides.
   Et changer un sélecteur relance la recherche tout seul. */
(function () {
  document.querySelectorAll('.guide-recherche').forEach(function (form) {
    form.addEventListener('submit', function () {
      form.querySelectorAll('input[type="search"], select').forEach(function (el) {
        if (!el.value) { el.disabled = true; }
      });
    });
    form.querySelectorAll('select').forEach(function (sel) {
      /* Sans choix, le sélecteur se lit comme un placeholder : gris.
         Dès qu'on choisit, il passe en texte normal. */
      var maj = function () { sel.classList.toggle('vide', !sel.value); };
      maj();
      sel.addEventListener('change', function () { maj(); form.submit(); });
    });
  });
})();
/* ---- galerie de la fiche lieu : visionneuse ---- */
(function () {
  var gal = document.querySelector('.fiche-galerie');
  if (!gal) return;
  var photos = Array.prototype.slice.call(gal.querySelectorAll('img'));
  if (!photos.length) return;
  var i = 0;
  var boite = document.createElement('div');
  boite.className = 'fiche-lumiere';
  boite.hidden = true;
  boite.innerHTML =
    '<button type="button" class="fiche-lumiere-ferme" aria-label="Fermer">&times;</button>' +
    '<button type="button" class="fiche-lumiere-avant" aria-label="Photo pr\u00e9c\u00e9dente">&#8592;</button>' +
    '<img src="" alt="">' +
    '<button type="button" class="fiche-lumiere-apres" aria-label="Photo suivante">&#8594;</button>' +
    '<p class="fiche-lumiere-legende"></p>';
  document.body.appendChild(boite);
  var img = boite.querySelector('img');
  var legende = boite.querySelector('.fiche-lumiere-legende');
  function montre(n) {
    i = (n + photos.length) % photos.length;
    img.src = photos[i].src;
    img.alt = photos[i].alt;
    legende.textContent = photos[i].alt + ' \u2014 ' + (i + 1) + ' / ' + photos.length;
  }
  function ouvre(n) {
    montre(n);
    boite.hidden = false;
    document.body.style.overflow = 'hidden';
    boite.querySelector('.fiche-lumiere-ferme').focus();
  }
  function ferme() {
    boite.hidden = true;
    document.body.style.overflow = '';
  }
  photos.forEach(function (p, n) {
    p.closest('.fiche-photo').addEventListener('click', function () { ouvre(n); });
  });
  boite.querySelector('.fiche-lumiere-ferme').addEventListener('click', ferme);
  boite.querySelector('.fiche-lumiere-avant').addEventListener('click', function () { montre(i - 1); });
  boite.querySelector('.fiche-lumiere-apres').addEventListener('click', function () { montre(i + 1); });
  boite.addEventListener('click', function (e) { if (e.target === boite) ferme(); });
  document.addEventListener('keydown', function (e) {
    if (boite.hidden) return;
    if (e.key === 'Escape') ferme();
    if (e.key === 'ArrowLeft') montre(i - 1);
    if (e.key === 'ArrowRight') montre(i + 1);
  });
})();