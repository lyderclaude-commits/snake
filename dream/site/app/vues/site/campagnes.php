<?php
/** Nos Campagnes & Réalisations | Live Y Dream — Agence de Communication Lomé — converti depuis campagnes.html. */
$titre = 'Nos Campagnes & Réalisations | Live Y Dream — Agence de Communication Lomé';
$description = 'Découvrez les campagnes et réalisations de Live Y Dream : stratégie digitale, production vidéo, événementiel, identité visuelle pour Woodin, Groupe KIK, Kramel et plus.';
ob_start(); ?>
<style>

/* ===== EDIT ===== */
.section-label:before {
    display: none !important;
}
.cg-cat {
    font-family: 'DM Sans', serif !important;
}
.hero-right-badge {
    font-family: 'DM Sans', serif !important;
}
.hero-badge {
    display: none !important;
}
.hero {
    padding-top: 0px !important;
}
.ticker {
    display: none !important;
}
.hero-headline {
    font-size: clamp(52px, 7.5vw, 65px) !important;
}
.hero-right-title {
    padding-bottom: 30px !important;
}
.hero-stats {
    margin-top: 36px !important;
}
.hero-left {
    padding-bottom: 16px !important;
}
.hero-right-corner {
    top: 90px !important;
}
:root {
    --navy: #000000 !important;
    --bg-card: #FFFFFF !important;
    --gold: #ffffff !important;
    --gold-dark: #ffffff !important;
    --font-serif: 'DM Sans', sans-serif !important;
}
.toto-noir {
    color: #ffffff !important;
}
.s-tag {
    border: 1px solid #000000 !important;
    color: #000000 !important;
}
.section-label {
    color: #000000 !important;
}
.section-label::before {
    background: #000000 !important;
}
.section-title em {
    color: #000000 !important;
    font-style: normal !important;
}
.section-title {
    font-size: clamp(32px, 4.5vw, 40px) !important;
}
.cta-big-title span {
    color: #000000 !important;
}
.problem-cta:hover { 
    background: #000000 !important;
    color: #ffffff !important;
    border: 1px solid #ffffff !important;
    transform: translateY(-2px); 
}
.prob-card:hover { 
    background: #000000 !important;
    border-color: #ffffff !important; 
}
.testi-card:hover { 
    background: #000000 !important;
    border-color: #ffffff !important; 
}
.hero-img-overlay {
    background: linear-gradient(135deg, rgb(0 0 0 / 70%) 0%, rgba(10, 22, 40, 0.3) 100%) !important;
}
.hero-right-corner {
    border: 1px solid rgb(255 255 255 / 40%) !important;
}
.nav-logo-accent {
    color: #000000 !important;
}
.section-title {
    font-family: 'DM sans' !important;
}
.hero-tagline {
    line-height: 1.5 !important;
}
.case-modal {
    background: rgb(0 0 0 / 87%) !important;
}
.btn-gold {
    border: 2px solid #ffffff !important;
}
.btn-light {
    background: #25D366 !important;
}

/* ===== EDIT ===== */

.cmp-intro { padding: 60px var(--gutter) 40px; background: var(--bg); }
.ci-inner { max-width: var(--max-w); margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 40px; flex-wrap: wrap; }
.filter-bar { display: flex; gap: 4px; flex-wrap: wrap; }
.filter-btn { padding: 10px 20px; background: var(--bg-card); border: 1.5px solid var(--border); color: var(--text-soft); font-size: 12px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; cursor: pointer; transition: all var(--transition); font-family: var(--font-body); }
.filter-btn.active, .filter-btn:hover { background: var(--navy); color: #fff; border-color: var(--navy); }

.cmp-grid-section { padding: 20px var(--gutter) 80px; background: var(--bg); }
.cg-inner { max-width: var(--max-w); margin: 0 auto; }
.campaign-grid { display: grid; grid-template-columns: repeat(12,1fr); gap: 12px; }
.cg-item { position: relative; overflow: hidden; cursor: pointer; background: var(--navy); }
.cg-item.cg-feat { grid-column: span 7; grid-row: span 2; }
.cg-item.cg-med { grid-column: span 5; }
.cg-item.cg-sm { grid-column: span 4; }
.cg-item img { width: 100%; height: 100%; object-fit: cover; min-height: 240px; display: block; transition: transform 0.6s ease; opacity: 0.7; }
.cg-item:hover img { transform: scale(1.05); opacity: 0.5; }
.cg-overlay { position: absolute; inset: 0; background: linear-gradient(0deg, rgba(10,22,40,0.95) 0%, rgba(10,22,40,0.2) 60%, transparent 100%); display: flex; flex-direction: column; justify-content: flex-end; padding: 28px; }
.cg-cat { display: inline-block; background: var(--gold); color: var(--navy); font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; padding: 4px 10px; margin-bottom: 10px; align-self: flex-start; }
.cg-title { font-family: var(--font-serif); font-size: clamp(16px,2.2vw,24px); color: #fff; font-weight: 700; line-height: 1.2; margin-bottom: 6px; }
.cg-client { font-size: 12px; color: var(--gold); font-weight: 600; letter-spacing: 0.5px; }
.cg-year { font-size: 12px; color: rgba(255,255,255,0.4); margin-left: 12px; }
.cg-item.hidden { display: none; }

/* Case study / detail */
.case-modal { position: fixed; inset: 0; background: rgba(10,22,40,0.95); z-index: 2000; overflow-y: auto; display: none; }
.case-modal.open { display: block; }
.case-modal-inner { max-width: 1100px; margin: 40px auto; background: var(--bg-card); border: 1px solid var(--border); position: relative; }
.case-modal-close { position: absolute; top: 20px; right: 20px; width: 44px; height: 44px; background: var(--navy); color: #fff; border: none; font-size: 18px; cursor: pointer; z-index: 10; transition: all var(--transition); }
.case-modal-close:hover { background: var(--gold); color: var(--navy); }
.case-hero { width: 100%; height: 420px; object-fit: cover; display: block; }
.case-body { display: grid; grid-template-columns: 3fr 2fr; gap: 0; }
.case-content { padding: 48px; border-right: 1px solid var(--border); }
.case-sidebar { padding: 48px; background: var(--bg-soft); }
.case-cat { display: inline-block; background: var(--gold); color: var(--navy); font-size: 10px; font-weight: 700; letter-spacing: 2px; padding: 4px 10px; margin-bottom: 16px; }
.case-title { font-family: var(--font-serif); font-size: 32px; font-weight: 700; color: var(--navy); margin-bottom: 8px; line-height: 1.2; }
.case-client-line { font-size: 14px; color: var(--gold-dark); font-weight: 600; letter-spacing: 1px; margin-bottom: 32px; }
.case-desc { font-family: var(--font-serif); font-size: 16px; color: var(--text-mid); line-height: 1.8; margin-bottom: 24px; }
.case-tags-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 24px; }
.case-tag { padding: 6px 14px; border: 1px solid var(--border-gold); color: var(--gold-dark); font-size: 12px; font-weight: 600; }
.case-results-title { font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--text-soft); margin-bottom: 20px; }
.case-results-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2px; }
.case-result { background: var(--bg-card); border: 1px solid var(--border); padding: 20px; text-align: center; }
.case-result-num { font-family: var(--font-display); font-size: 36px; color: var(--navy); line-height: 1; }
.case-result-lbl { font-size: 11px; color: var(--text-soft); letter-spacing: 0.5px; margin-top: 4px; }
.case-cta { margin-top: 32px; }

/* Featured projects */
.featured-section { padding: 80px var(--gutter); background: var(--bg-soft); }
.fs-inner { max-width: var(--max-w); margin: 0 auto; }

@media (max-width:1024px) {
  .campaign-grid { grid-template-columns: 1fr 1fr; }
  .cg-item.cg-feat, .cg-item.cg-med, .cg-item.cg-sm { grid-column: span 1; grid-row: span 1; }
  .case-body { grid-template-columns: 1fr; }
  .case-content { border-right: none; border-bottom: 1px solid var(--border); }
}
@media (max-width:768px) {
  .campaign-grid { grid-template-columns: 1fr; }
  .ci-inner { flex-direction: column; align-items: flex-start; }
}

/* ============================================================
   ===== RESPONSIVE OVERRIDES (ajout uniquement) ==============
   Aucune règle existante n'est supprimée ou modifiée.
   Améliore la lisibilité tablette + mobile pour campagnes.html
   ============================================================ */

/* === TABLETTE & DESSOUS (≤ 1024px) === */
@media (max-width: 1024px) {
  /* Page header */
  .page-header {
    padding-left: 24px !important;
    padding-right: 24px !important;
  }
  .page-header-inner { padding: 80px 0 60px !important; }

  /* Intro + filter bar : padding latéral réduit */
  .cmp-intro,
  .cmp-grid-section {
    padding-left: 24px !important;
    padding-right: 24px !important;
  }
  .ci-inner { gap: 24px !important; }

  /* Modal : margins moins agressives, deux colonnes -> une */
  .case-modal-inner { margin: 24px 18px !important; }
  .case-hero { height: 340px !important; }
  .case-content { padding: 36px !important; }
  .case-sidebar { padding: 36px !important; }
  .case-title { font-size: 28px !important; }
}

/* === MOBILE LARGE (≤ 768px) === */
@media (max-width: 768px) {
  /* PAGE HEADER */
  .page-header { padding-left: 18px !important; padding-right: 18px !important; }
  .page-header-inner { padding: 60px 0 44px !important; }
  .page-header .section-title {
    font-size: clamp(28px, 7vw, 36px) !important;
    line-height: 1.15 !important;
  }
  .page-header-breadcrumb { font-size: 12px !important; margin-top: 16px !important; }

  /* INTRO */
  .cmp-intro {
    padding-top: 40px !important;
    padding-bottom: 24px !important;
    padding-left: 18px !important;
    padding-right: 18px !important;
  }
  .ci-inner { gap: 20px !important; }
  .ci-inner > div:first-child p { font-size: 14px !important; line-height: 1.6 !important; }

  /* FILTER BAR : scroll horizontal pour éviter le wrap moche */
  .filter-bar {
    width: 100%;
    overflow-x: auto;
    flex-wrap: nowrap !important;
    gap: 6px !important;
    padding-bottom: 4px;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
  }
  .filter-bar::-webkit-scrollbar { height: 3px; }
  .filter-bar::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
  .filter-btn {
    padding: 8px 14px !important;
    font-size: 11px !important;
    letter-spacing: 1px !important;
    white-space: nowrap;
    flex-shrink: 0;
  }

  /* GRID SECTION */
  .cmp-grid-section {
    padding-top: 16px !important;
    padding-bottom: 56px !important;
    padding-left: 18px !important;
    padding-right: 18px !important;
  }
  .campaign-grid { gap: 10px !important; }
  .cg-item img { min-height: 220px !important; }
  .cg-overlay { padding: 20px !important; }
  .cg-cat { font-size: 9px !important; padding: 3px 8px !important; margin-bottom: 8px !important; }
  .cg-title { font-size: 17px !important; line-height: 1.25 !important; }
  .cg-client { font-size: 11px !important; }
  .cg-year { font-size: 11px !important; margin-left: 8px !important; }

  /* MODAL : reformatée pour mobile */
  .case-modal-inner { margin: 14px 12px !important; }
  .case-modal-close {
    width: 38px !important;
    height: 38px !important;
    font-size: 16px !important;
    top: 12px !important;
    right: 12px !important;
  }
  .case-hero { height: 220px !important; }
  .case-content { padding: 24px 20px !important; border-right: none !important; border-bottom: 1px solid var(--border) !important; }
  .case-sidebar { padding: 24px 20px !important; }
  .case-cat { font-size: 9px !important; margin-bottom: 12px !important; }
  .case-title { font-size: 22px !important; line-height: 1.2 !important; }
  .case-client-line { font-size: 12px !important; margin-bottom: 20px !important; letter-spacing: 0.5px !important; }
  .case-desc { font-size: 14px !important; line-height: 1.7 !important; margin-bottom: 18px !important; }
  .case-tags-list { gap: 6px !important; margin-top: 16px !important; }
  .case-tag { padding: 5px 11px !important; font-size: 11px !important; }
  .case-results-title { font-size: 10px !important; margin-bottom: 16px !important; }
  .case-results-grid { gap: 1px !important; }
  .case-result { padding: 16px 12px !important; }
  .case-result-num { font-size: 26px !important; }
  .case-result-lbl { font-size: 10px !important; line-height: 1.3 !important; }
  .case-cta .btn { font-size: 12px !important; padding: 12px 20px !important; }

  /* CTA FINAL (navy) — section avec styles inline */
  section[style*="background:var(--navy)"][style*="text-align:center"] {
    padding-left: 18px !important;
    padding-right: 18px !important;
    padding-top: 56px !important;
    padding-bottom: 56px !important;
  }
  section[style*="background:var(--navy)"][style*="text-align:center"] .section-title {
    font-size: clamp(24px, 7vw, 30px) !important;
    line-height: 1.2 !important;
  }
  section[style*="background:var(--navy)"][style*="text-align:center"] p {
    font-size: 14px !important;
    line-height: 1.6 !important;
    margin-bottom: 28px !important;
  }
  section[style*="background:var(--navy)"][style*="text-align:center"] .btn {
    width: 100% !important;
    justify-content: center;
  }
  section[style*="background:var(--navy)"][style*="text-align:center"] > div > div[style*="display:flex"] {
    gap: 12px !important;
    flex-direction: column !important;
  }
}

/* === MOBILE PETIT (≤ 480px) === */
@media (max-width: 480px) {
  .page-header-inner { padding: 44px 0 32px !important; }
  .page-header .section-title { font-size: clamp(24px, 7.5vw, 30px) !important; }

  .cmp-intro,
  .cmp-grid-section {
    padding-left: 14px !important;
    padding-right: 14px !important;
  }

  /* Filter buttons encore plus compacts */
  .filter-btn {
    padding: 7px 12px !important;
    font-size: 10px !important;
    letter-spacing: 0.8px !important;
  }

  /* Grid items plus serrés */
  .cg-item img { min-height: 200px !important; }
  .cg-overlay { padding: 16px !important; }
  .cg-title { font-size: 15px !important; }

  /* Modal : encore plus compact */
  .case-modal-inner { margin: 10px 8px !important; }
  .case-hero { height: 180px !important; }
  .case-content { padding: 22px 16px !important; }
  .case-sidebar { padding: 22px 16px !important; }
  .case-title { font-size: 20px !important; }
  .case-desc { font-size: 13.5px !important; }
  .case-result-num { font-size: 24px !important; }

  /* CTA final padding plus serré */
  section[style*="background:var(--navy)"][style*="text-align:center"] {
    padding-left: 14px !important;
    padding-right: 14px !important;
  }
}

/* === ULTRA PETIT (≤ 360px) === */
@media (max-width: 360px) {
  .page-header .section-title { font-size: 22px !important; }
  .filter-btn { padding: 6px 10px !important; font-size: 9.5px !important; }
  .cg-title { font-size: 14px !important; }
  .case-title { font-size: 18px !important; }
  .case-result-num { font-size: 22px !important; }
  .case-hero { height: 160px !important; }
}
</style>
<?php $tete = ob_get_clean(); ?>
<header class="page-header">
  <div class="page-header-grid"></div>
  <div class="page-header-inner">
    <div class="section-label" style="color: #ffffff !important;">Nos Réalisations</div>
    <h1 class="section-title">Campagnes qui ont<br/>changé la donne</h1>
    <div class="page-header-breadcrumb"><a href="<?= url('') ?>">Accueil</a><span>/</span><span>Campagnes</span></div>
  </div>
</header>

<!-- FILTER + INTRO -->
<div class="cmp-intro">
  <div class="ci-inner">
    <div>
      <p style="font-size:15px;color:var(--text-soft);max-width:500px">Chaque projet est une histoire de transformation. Découvrez comment nous avons aidé nos clients à dominer leur marché.</p>
    </div>
    <div class="filter-bar" id="filterBar">
      <button class="filter-btn active" data-filter="all">Tout voir</button>
      <button class="filter-btn" data-filter="digital">Digital</button>
      <button class="filter-btn" data-filter="video">Vidéo & Photo</button>
      <button class="filter-btn" data-filter="event">Événementiel</button>
      <button class="filter-btn" data-filter="brand">Identité</button>
    </div>
  </div>
</div>

<!-- GRID -->
<div class="cmp-grid-section">
  <div class="cg-inner">
    <div class="campaign-grid" id="campaignGrid">
      <?php foreach ($campagnes as $i => $c): ?>
      <a class="cg-item <?= ['cg-feat', 'cg-med', 'cg-sm', 'cg-sm', 'cg-sm', 'cg-med'][$i % 6] ?>" id="campagne-<?= (int) $c['id'] ?>" href="#campagne-<?= (int) $c['id'] ?>" data-id="<?= (int) $c['id'] ?>">
        <img src="<?= e(image_url($c['image'])) ?>" alt="<?= e($c['titre']) ?>" loading="lazy"/>
        <div class="cg-overlay">
          <span class="cg-cat"><?= e(categorie_campagne($c['categorie'])) ?></span>
          <div class="cg-title"><?= e($c['titre']) ?></div>
          <div><span class="cg-client"><?= e($c['client']) ?></span><span class="cg-year"><?= e($c['annee']) ?></span></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <div id="emptyState" style="display:none;text-align:center;padding:80px 20px;color:var(--text-soft)">
      <div style="font-size:48px;margin-bottom:16px">🔍</div>
      <p>Aucune campagne dans cette catégorie pour l'instant.</p>
    </div>
  </div>
</div>

<!-- CTA -->
<section style="padding:clamp(60px,8vw,100px) var(--gutter);background:var(--navy);text-align:center;border-bottom: 1px solid #ffffff !important;">
  <div class="reveal" style="max-width:640px;margin:0 auto">
    <div class="section-label" style="color:#ffffff !important;justify-content:center"><span></span>Votre projet ici ?</div>
    <h2 class="section-title" style="color:#fff;margin-bottom:20px">Prêt à créer votre<br/>prochaine grande campagne ?</h2>
    <p style="color:rgba(255,255,255,0.6);font-size:16px;font-family:var(--font-serif);line-height:1.7;margin-bottom:36px">UNICEF, Woodin, UBA, Ecobank nous ont fait confiance. Pourquoi pas vous ?</p>
    <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap">
      <a href="<?= url('contact') ?>" class="btn btn-gold">Discuter de mon projet</a>
      <a href="<?= e(wa_lien()) ?>" class="btn btn-light" target="_blank">WhatsApp direct</a>
    </div>
  </div>
</section>

<!-- CASE STUDY MODAL -->
<div class="case-modal" id="caseModal">
  <div class="case-modal-inner">
    <button class="case-modal-close" type="button" data-fermer aria-label="Fermer">✕</button>
    <img id="caseImg" src="" alt="" class="case-hero"/>
    <div class="case-body">
      <div class="case-content">
        <div id="caseCat" class="case-cat">Catégorie</div>
        <h2 id="caseTitle" class="case-title">Titre</h2>
        <div id="caseClient" class="case-client-line">Client • Année</div>
        <p id="caseDesc" class="case-desc">Description…</p>
        <div id="caseTags" class="case-tags-list"></div>
      </div>
      <div class="case-sidebar">
        <div class="case-results-title">Résultats obtenus</div>
        <div class="case-results-grid" id="caseResults"></div>
        <div class="case-cta">
          <a href="<?= url('contact') ?>" class="btn btn-gold" style="width:100%;justify-content:center;margin-top:12px">Je veux un projet similaire</a>
        </div>
      </div>
    </div>
  </div>
</div>
<script type="application/json" id="donneesCampagnes"><?= json_encode(array_map(fn($c) => [
    'id' => (int) $c['id'], 'titre' => $c['titre'], 'client' => $c['client'], 'categorie' => $c['categorie'],
    'libelle' => categorie_campagne($c['categorie']), 'annee' => $c['annee'], 'description' => $c['description'],
    'image' => image_url($c['image']), 'tags' => $c['tags'], 'resultats' => $c['resultats'],
], $campagnes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
