/* Live Y Dream — les comportements du site public.
   Le contenu est rendu par le serveur : ce script ne fait qu'animer.
   Sans lui, toutes les pages restent lisibles et tous les liens marchent. */
(function () {
  'use strict';

  /* ---- menu ---- */
  var toggle = document.getElementById('navToggle');
  var menu = document.getElementById('navMenu');
  var nav = document.getElementById('siteNav');
  function fermerMenu() {
    if (!toggle) return;
    toggle.classList.remove('open');
    menu.classList.remove('open');
    toggle.setAttribute('aria-expanded', 'false');
  }
  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      var ouvert = menu.classList.toggle('open');
      toggle.classList.toggle('open', ouvert);
      toggle.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    });
    menu.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', fermerMenu); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fermerMenu(); });
  }
  if (nav) {
    var surveiller = function () { nav.classList.toggle('scrolled', window.scrollY > 40); };
    window.addEventListener('scroll', surveiller, { passive: true });
    surveiller();
  }

  /* ---- apparitions au défilement ---- */
  var elements = document.querySelectorAll('.reveal, .reveal-l, .reveal-r');
  if ('IntersectionObserver' in window) {
    var obs = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); obs.unobserve(e.target); } });
    }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });
    elements.forEach(function (el) { obs.observe(el); });
  } else {
    elements.forEach(function (el) { el.classList.add('in'); });
  }

  /* ---- compteurs : la valeur est déjà écrite, on la rejoue de 0 ---- */
  function animer(el) {
    var cible = parseInt(el.dataset.count, 10), suffixe = el.dataset.suffix || '', t0 = performance.now();
    function pas(t) {
      var p = Math.min((t - t0) / 1600, 1), e2 = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.floor(e2 * cible) + (p >= 1 ? suffixe : '');
      if (p < 1) requestAnimationFrame(pas);
    }
    requestAnimationFrame(pas);
  }
  var compteurs = document.querySelectorAll('[data-count]');
  if (compteurs.length && 'IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var obsC = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { if (e.isIntersecting) { animer(e.target); obsC.unobserve(e.target); } });
    }, { threshold: 0.3 });
    compteurs.forEach(function (el) { obsC.observe(el); });
  }

  /* ---- ancres en douceur ---- */
  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var id = a.getAttribute('href');
      if (id.length < 2 || id.indexOf('#campagne-') === 0) return;
      var t = document.querySelector(id);
      if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
  });

  /* ---- campagnes : filtres et fiche détaillée ---- */
  var donnees = document.getElementById('donneesCampagnes');
  if (donnees) {
    var campagnes = JSON.parse(donnees.textContent || '[]');
    var grille = document.getElementById('campaignGrid');
    var vide = document.getElementById('emptyState');
    var modal = document.getElementById('caseModal');
    var TAILLES = ['cg-feat', 'cg-med', 'cg-sm', 'cg-sm', 'cg-sm', 'cg-med'];

    var echap = function (s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    };

    var afficher = function (filtre) {
      var liste = filtre === 'all' ? campagnes : campagnes.filter(function (c) { return c.categorie === filtre; });
      vide.style.display = liste.length ? 'none' : 'block';
      grille.innerHTML = liste.map(function (c, i) {
        return '<a class="cg-item ' + TAILLES[i % TAILLES.length] + '" id="campagne-' + c.id + '" href="#campagne-' + c.id + '" data-id="' + c.id + '">'
          + '<img src="' + echap(c.image) + '" alt="' + echap(c.titre) + '" loading="lazy"/>'
          + '<div class="cg-overlay"><span class="cg-cat">' + echap(c.libelle) + '</span>'
          + '<div class="cg-title">' + echap(c.titre) + '</div>'
          + '<div><span class="cg-client">' + echap(c.client) + '</span><span class="cg-year">' + echap(c.annee) + '</span></div></div></a>';
      }).join('');
    };

    var ouvrir = function (id) {
      var c = campagnes.filter(function (x) { return x.id === id; })[0];
      if (!c) return;
      document.getElementById('caseImg').src = c.image;
      document.getElementById('caseImg').alt = c.titre;
      document.getElementById('caseCat').textContent = c.libelle;
      document.getElementById('caseTitle').textContent = c.titre;
      document.getElementById('caseClient').textContent = c.client + ' • ' + c.annee;
      document.getElementById('caseDesc').textContent = c.description;
      document.getElementById('caseTags').innerHTML = (c.tags || []).map(function (t) { return '<span class="case-tag">' + echap(t) + '</span>'; }).join('');
      var r = c.resultats || [], html = '';
      for (var i = 0; i < r.length; i += 2) {
        html += '<div class="case-result"><div class="case-result-num">' + echap(r[i]) + '</div><div class="case-result-lbl">' + echap(r[i + 1] || '') + '</div></div>';
      }
      document.getElementById('caseResults').innerHTML = html;
      modal.classList.add('open');
      document.body.style.overflow = 'hidden';
    };
    var fermer = function () {
      modal.classList.remove('open');
      document.body.style.overflow = '';
      if (location.hash.indexOf('#campagne-') === 0) history.replaceState(null, '', location.pathname + location.search);
    };

    grille.addEventListener('click', function (e) {
      var a = e.target.closest('.cg-item');
      if (!a) return;
      e.preventDefault();
      history.replaceState(null, '', '#campagne-' + a.dataset.id);
      ouvrir(parseInt(a.dataset.id, 10));
    });
    modal.addEventListener('click', function (e) { if (e.target === modal || e.target.closest('[data-fermer]')) fermer(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('open')) fermer(); });
    document.getElementById('filterBar').addEventListener('click', function (e) {
      var b = e.target.closest('.filter-btn');
      if (!b) return;
      document.querySelectorAll('.filter-btn').forEach(function (x) { x.classList.remove('active'); });
      b.classList.add('active');
      afficher(b.dataset.filter);
    });
    // Un lien partagé « /campagnes#campagne-3 » (ou venu de l'accueil) ouvre directement la fiche.
    var depuisAncre = function () {
      var m = /^#campagne-(\d+)$/.exec(location.hash);
      if (m) ouvrir(parseInt(m[1], 10));
    };
    window.addEventListener('hashchange', depuisAncre);
    depuisAncre();
  }

  /* ---- une lecture d'article : un signal par onglet et par article ---- */
  var lecture = document.querySelector('[data-lecture]');
  if (lecture) {
    var cle = 'lyd-lu-' + lecture.dataset.article, deja = false;
    try { deja = sessionStorage.getItem(cle) === '1'; } catch (e) { /* navigation privée : on compte quand même */ }
    if (!deja) {
      // Après une seconde à l'écran : un aller-retour immédiat (clic par erreur) ne compte pas.
      setTimeout(function () {
        var ok = false;
        if (navigator.sendBeacon) ok = navigator.sendBeacon(lecture.dataset.lecture, '');
        if (!ok && window.fetch) fetch(lecture.dataset.lecture, { method: 'POST', keepalive: true, credentials: 'omit' });
        try { sessionStorage.setItem(cle, '1'); } catch (e) { /* idem */ }
      }, 1000);
    }
  }
})();
