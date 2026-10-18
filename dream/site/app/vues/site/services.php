<?php
/** Services — Community Management, Vidéo, Design, Événementiel | Live Y Dream Lomé — converti depuis services.html. */
$titre = 'Services — Community Management, Vidéo, Design, Événementiel | Live Y Dream Lomé';
$description = 'Découvrez tous les services de Live Y Dream : community management, production vidéo, conception graphique, événementiel, régie publicitaire et abonnements PME à Lomé, Togo.';
ob_start(); ?>
<style>

/* ===== EDIT ===== */

html,body{
    max-width:100% !important;
    overflow-x:hidden !important;
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
.ss-deliverable-check {
    background: #ffffff !important;
    border: 1px solid rgb(0 0 0) !important;
    color: #000000 !important;
}
.ss-price-label {
    color: #000000 !important;
}
.si-card-num {
    color: #000000 !important;
}
.ss-pricing {
    background: #ffffff !important;
    border:1px solid #000000 !important;
}
.btn-gold:hover { 
    background: #ffffff !important;
    border-color: #000000 !important; 
    color : #000000 !important;
}
.btn-gold{ 
    border: 1px solid #ffffff !important; 
}
.ps-num {
    color: rgb(255 255 255) !important;
}
.ps-step:hover { 
    background: #000000 !important;
    border-color: #ffffff !important; 
}
.section-label:before {
    display: none !important;
}
.ss-img-tag {
    font-family: 'DM Sans', serif !important;
}

/* ===== EDIT ===== */

.services-intro { padding: clamp(60px,8vw,100px) var(--gutter); background: var(--bg); }
.si-inner { max-width: var(--max-w); margin: 0 auto; display: grid; grid-template-columns: 5fr 7fr; gap: 80px; align-items: center; }
.si-stat-row { display: flex; gap: 32px; margin-top: 32px; }
.si-stat { text-align: center; }
.si-stat-num { font-family: var(--font-display); font-size: 48px; color: var(--navy); line-height: 1; }
.si-stat-lbl { font-size: 11px; color: var(--text-soft); letter-spacing: 1.5px; text-transform: uppercase; margin-top: 4px; }
.si-right { display: grid; grid-template-columns: 1fr 1fr; gap: 2px; }
.si-card { background: var(--bg-card); border: 1px solid var(--border); padding: 28px; transition: all var(--transition); }
.si-card:hover { border-color: var(--gold); box-shadow: var(--shadow-md); }
.si-card-num { font-family: var(--font-display); font-size: 36px; color: var(--gold-dark); line-height: 1; margin-bottom: 8px; }
.si-card p { font-size: 13px; color: var(--text-soft); line-height: 1.6; }

.service-section { padding: clamp(60px,8vw,100px) var(--gutter); }
.service-section:nth-child(even) { background: var(--bg-soft); }
.service-section:nth-child(odd) { background: var(--bg-card); }
.ss-inner { max-width: var(--max-w); margin: 0 auto; }
.ss-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center; }
.ss-grid.reverse { }
.ss-visual { position: relative; }
.ss-img { width: 100%; aspect-ratio: 4/3; object-fit: cover; border-radius: var(--radius); }
.ss-img-tag { position: absolute; bottom: -16px; left: 20px; background: var(--navy); color: #fff; padding: 12px 20px; font-family: var(--font-display); font-size: 14px; letter-spacing: 1.5px; }
.ss-content {}
.ss-deliverables { margin: 24px 0; }
.ss-deliverable { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border); }
.ss-deliverable:last-child { border-bottom: none; }
.ss-deliverable-check { width: 20px; height: 20px; background: var(--gold-bg); border: 1px solid var(--border-gold); display: flex; align-items: center; justify-content: center; font-size: 11px; color: var(--gold-dark); flex-shrink: 0; margin-top: 2px; }
.ss-deliverable-text h5 { font-size: 14px; font-weight: 700; color: var(--navy); margin-bottom: 3px; }
.ss-deliverable-text p { font-size: 13px; color: var(--text-soft); }
.ss-pricing { background: var(--gold-bg); border: 1px solid var(--border-gold); padding: 24px; margin-top: 28px; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; }
.ss-price-label { font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--gold-dark); }
.ss-price-val { font-family: var(--font-display); font-size: 28px; color: var(--navy); }

.abonnement-plans { display: grid; grid-template-columns: repeat(3,1fr); gap: 2px; margin-top: 48px; }
.plan-card { background: var(--bg-card); border: 1px solid var(--border); padding: 44px 32px; text-align: center; position: relative; transition: all var(--transition); }
.plan-card.featured { background: var(--navy); border-color: var(--navy); }
.plan-card.featured * { color: rgba(255,255,255,0.85); }
.plan-recommended { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: var(--gold); color: var(--navy); font-size: 10px; font-weight: 700; letter-spacing: 2px; padding: 4px 16px; text-transform: uppercase; white-space: nowrap; }
.plan-name { font-size: 12px; font-weight: 700; letter-spacing: 2.5px; text-transform: uppercase; color: var(--text-soft); margin-bottom: 12px; }
.plan-price { font-family: var(--font-display); font-size: 56px; color: var(--navy); line-height: 1; margin-bottom: 4px; }
.plan-card.featured .plan-price { color: var(--gold); }
.plan-period { font-size: 13px; color: var(--text-soft); margin-bottom: 32px; }
.plan-features { text-align: left; list-style: none; margin-bottom: 32px; }
.plan-features li { padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 14px; color: var(--text-mid); display: flex; align-items: center; gap: 10px; }
.plan-card.featured .plan-features li { border-bottom-color: rgba(255,255,255,0.1); }
.plan-features li::before { content: '✓'; color: var(--gold-dark); font-weight: 700; font-size: 12px; flex-shrink: 0; }

.process-section { padding: clamp(60px,8vw,100px) var(--gutter); background: var(--navy); }
.ps-inner { max-width: var(--max-w); margin: 0 auto; }
.process-section .section-label { color: var(--gold); }
.process-section .section-label::before { background: var(--gold); }
.process-section .section-title { color: #fff; }
.process-steps { display: grid; grid-template-columns: repeat(4,1fr); gap: 2px; margin-top: 52px; }
.ps-step { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); padding: 40px 28px; position: relative; transition: all var(--transition); }
.ps-step:hover { background: rgba(201,168,76,0.07); border-color: rgba(201,168,76,0.3); }
.ps-num { font-family: var(--font-display); font-size: 72px; color: rgba(201,168,76,0.1); line-height: 0.9; margin-bottom: 20px; }
.ps-step h4 { font-size: 17px; font-weight: 700; color: #fff; margin-bottom: 10px; font-family: var(--font-serif); }
.ps-step p { font-size: 13px; color: rgba(255,255,255,0.55); line-height: 1.7; }

@media (max-width:1024px) {
  .si-inner { grid-template-columns: 1fr; gap: 40px; }
  .si-right { grid-template-columns: 1fr 1fr; }
  .ss-grid { grid-template-columns: 1fr; gap: 40px; }
  .abonnement-plans { grid-template-columns: 1fr; }
  .process-steps { grid-template-columns: 1fr 1fr; }
}
@media (max-width:768px) {
  .si-stat-row { flex-direction: column; gap: 16px; }
  .si-right { grid-template-columns: 1fr; }
  .process-steps { grid-template-columns: 1fr; }
}

/* ============================================================
   ===== RESPONSIVE OVERRIDES (ajout uniquement) ==============
   Aucune règle existante n'est supprimée ou modifiée.
   Améliore la lisibilité tablette + mobile pour services.html
   ============================================================ */

/* === TABLETTE & DESSOUS (≤ 1024px) === */
@media (max-width: 1024px) {
  /* Page header */
  .page-header {
    padding-left: 24px !important;
    padding-right: 24px !important;
  }
  .page-header-inner { padding: 80px 0 60px !important; }

  /* Sections — padding latéral réduit */
  .services-intro,
  .service-section,
  .process-section {
    padding-left: 24px !important;
    padding-right: 24px !important;
  }

  /* Intro : grid déjà 1fr, on resserre le gap */
  .si-inner { gap: 32px !important; }

  /* Services sections : gap réduit */
  .ss-grid { gap: 40px !important; }

  /* Pricing block : déjà flex-wrap, on resserre */
  .ss-pricing { padding: 20px !important; gap: 14px !important; }
  .ss-price-val { font-size: 24px !important; }

  /* Process : déjà 2x2 à 1024px, on resserre */
  .process-steps { gap: 1px !important; margin-top: 36px !important; }
  .ps-step { padding: 32px 24px !important; }
  .ps-num { font-size: 60px !important; margin-bottom: 14px !important; }
}

/* === MOBILE LARGE (≤ 768px) === */
@media (max-width: 768px) {
  /* PAGE HEADER */
  .page-header-inner { padding: 60px 0 44px !important; }
  .page-header .section-title {
    font-size: clamp(28px, 7vw, 36px) !important;
    line-height: 1.15 !important;
  }
  .page-header-breadcrumb { font-size: 12px !important; margin-top: 16px !important; }

  /* SECTIONS : padding général */
  .services-intro,
  .service-section,
  .process-section {
    padding-top: 56px !important;
    padding-bottom: 56px !important;
    padding-left: 18px !important;
    padding-right: 18px !important;
  }

  /* SECTION TITLES */
  .section-title {
    font-size: clamp(26px, 7vw, 32px) !important;
    line-height: 1.15 !important;
  }
  .section-sub { font-size: 14px !important; line-height: 1.6 !important; }
  .section-label { font-size: 11px !important; }

  /* INTRO : passer si-right en 2 colonnes (au lieu de 1) pour densité */
  .si-inner { gap: 28px !important; }
  .si-right {
    grid-template-columns: 1fr 1fr !important;
    gap: 1px !important;
  }
  .si-card { padding: 20px 16px !important; }
  .si-card-num { font-size: 28px !important; margin-bottom: 6px !important; }
  .si-card h4 { font-size: 13px !important; }
  .si-card p { font-size: 12px !important; line-height: 1.45 !important; }

  /* Stats intro : redevenir grid 3 colonnes serrées au lieu de column */
  .si-stat-row {
    display: grid !important;
    grid-template-columns: repeat(3, 1fr) !important;
    flex-direction: initial !important;
    gap: 12px !important;
    margin-top: 24px !important;
  }
  .si-stat-num { font-size: 32px !important; }
  .si-stat-lbl { font-size: 10px !important; letter-spacing: 1px !important; }

  /* SERVICE SECTIONS */
  .ss-grid { gap: 28px !important; }
  .ss-img { aspect-ratio: 16/10 !important; }
  .ss-img-tag {
    font-size: 12px !important;
    padding: 9px 14px !important;
    bottom: -12px !important;
    left: 14px !important;
  }

  /* Deliverables */
  .ss-deliverables { margin: 18px 0 !important; }
  .ss-deliverable { padding: 10px 0 !important; gap: 10px !important; }
  .ss-deliverable-check {
    width: 18px !important;
    height: 18px !important;
    font-size: 10px !important;
  }
  .ss-deliverable-text h5 { font-size: 13px !important; }
  .ss-deliverable-text p { font-size: 12.5px !important; line-height: 1.5 !important; }

  /* Pricing : passer en colonne pour mobile */
  .ss-pricing {
    flex-direction: column !important;
    align-items: stretch !important;
    text-align: center;
    padding: 18px !important;
    margin-top: 22px !important;
  }
  .ss-pricing > div { text-align: center; }
  .ss-price-val { font-size: 22px !important; }
  .ss-pricing .btn { width: 100% !important; justify-content: center; }

  /* ABONNEMENT PLANS */
  .abonnement-plans { margin-top: 32px !important; gap: 1px !important; }
  .plan-card { padding: 32px 22px !important; }
  .plan-price { font-size: 44px !important; }
  .plan-period { font-size: 12px !important; margin-bottom: 24px !important; }
  .plan-features { margin-bottom: 24px !important; }
  .plan-features li { padding: 7px 0 !important; font-size: 13px !important; gap: 8px !important; }
  .plan-recommended { font-size: 9px !important; padding: 3px 12px !important; top: -10px !important; }
  .plan-name { font-size: 11px !important; }

  /* PROCESS */
  .process-steps { margin-top: 32px !important; }
  .ps-step { padding: 28px 22px !important; }
  .ps-num { font-size: 48px !important; margin-bottom: 12px !important; }
  .ps-step h4 { font-size: 15px !important; margin-bottom: 8px !important; }
  .ps-step p { font-size: 12.5px !important; line-height: 1.6 !important; }

  /* CTA final : padding et boutons */
  section[style*="background:var(--gold-bg)"] {
    padding-left: 18px !important;
    padding-right: 18px !important;
    padding-top: 52px !important;
    padding-bottom: 52px !important;
  }
  section[style*="background:var(--gold-bg)"] .btn {
    width: 100% !important;
    justify-content: center;
  }
}

/* === MOBILE PETIT (≤ 480px) === */
@media (max-width: 480px) {
  .page-header { padding-left: 16px !important; padding-right: 16px !important; }
  .page-header-inner { padding: 48px 0 36px !important; }
  .page-header .section-title { font-size: clamp(24px, 7.5vw, 30px) !important; }

  /* Sections padding minimum */
  .services-intro,
  .service-section,
  .process-section {
    padding-left: 14px !important;
    padding-right: 14px !important;
    padding-top: 44px !important;
    padding-bottom: 44px !important;
  }

  .section-title { font-size: clamp(22px, 7vw, 28px) !important; }

  /* INTRO : si-right en 1 seule colonne pour très petit */
  .si-right { grid-template-columns: 1fr !important; }
  .si-card { padding: 18px 16px !important; }

  /* Stats intro plus serrées */
  .si-stat-num { font-size: 26px !important; }
  .si-stat-lbl { font-size: 9px !important; }

  /* SS sections plus compactes */
  .ss-img-tag { font-size: 11px !important; padding: 7px 12px !important; }
  .ss-price-val { font-size: 20px !important; }

  /* Plans cards plus compactes */
  .plan-card { padding: 28px 18px !important; }
  .plan-price { font-size: 38px !important; }
  .plan-features li { font-size: 12.5px !important; }

  /* Process */
  .ps-num { font-size: 42px !important; }
  .ps-step { padding: 24px 18px !important; }
}

/* === ULTRA PETIT (≤ 360px) === */
@media (max-width: 360px) {
  .section-title { font-size: 22px !important; }
  .page-header .section-title { font-size: 22px !important; }
  .si-stat-num { font-size: 22px !important; }
  .plan-price { font-size: 34px !important; }
  .ss-price-val { font-size: 18px !important; }
  .ps-num { font-size: 38px !important; }
}
</style>
<?php $tete = ob_get_clean(); ?>
<header class="page-header">
  <div class="page-header-grid"></div>
  <div class="page-header-inner">
    <div class="section-label" style="color: #ffffff !important;">Nos Services</div>
    <h1 class="section-title">Un écosystème complet<br/>pour votre croissance</h1>
    <div class="page-header-breadcrumb">
      <a href="<?= url('') ?>">Accueil</a><span>/</span><span>Services</span>
    </div>
  </div>
</header>

<!-- INTRO -->
<section class="services-intro">
  <div class="si-inner">
    <div class="reveal-l">
      <div class="section-label">Notre philosophie</div>
      <h2 class="section-title">Nous ne vendons pas<br/><em>du graphisme</em></h2>
      <p class="section-sub">Nous vendons des nouveaux clients, de la visibilité et de la crédibilité, les trois moteurs de toute entreprise qui gagne en Afrique de l'Ouest.</p>
      <div class="si-stat-row">
        <div class="si-stat"><div class="si-stat-num">200+</div><div class="si-stat-lbl">Marques</div></div>
        <div class="si-stat"><div class="si-stat-num">10+</div><div class="si-stat-lbl">Années</div></div>
        <div class="si-stat"><div class="si-stat-num">4</div><div class="si-stat-lbl">Pays</div></div>
      </div>
    </div>
    <div class="si-right reveal">
      <div class="si-card"><div class="si-card-num">01</div><h4 style="font-family:var(--font-serif);font-size:15px;color:var(--navy);margin-bottom:8px">Community Management</h4><p>Stratégie, création et gestion de vos réseaux sociaux.</p></div>
      <div class="si-card"><div class="si-card-num">02</div><h4 style="font-family:var(--font-serif);font-size:15px;color:var(--navy);margin-bottom:8px">Production Vidéo & Photo</h4><p>Studio broadcast, direction artistique, post-production.</p></div>
      <div class="si-card"><div class="si-card-num">03</div><h4 style="font-family:var(--font-serif);font-size:15px;color:var(--navy);margin-bottom:8px">Conception Graphique</h4><p>Identité visuelle, logo, charte graphique, packaging.</p></div>
      <div class="si-card"><div class="si-card-num">04</div><h4 style="font-family:var(--font-serif);font-size:15px;color:var(--navy);margin-bottom:8px">Événementiel</h4><p>Organisation et communication de vos événements.</p></div>
      <div class="si-card"><div class="si-card-num">05</div><h4 style="font-family:var(--font-serif);font-size:15px;color:var(--navy);margin-bottom:8px">Régie Publicitaire</h4><p>Affichage, Facebook Ads, Google Ads, billboards.</p></div>
      <div class="si-card"><div class="si-card-num">★</div><h4 style="font-family:var(--font-serif);font-size:15px;color:#000000 !important; margin-bottom:8px">Abonnement PME</h4><p>Tout-en-un à partir de 200€/mois.</p></div>
    </div>
  </div>
</section>

<!-- SERVICE 1: COMMUNITY MANAGEMENT -->
<section class="service-section" id="community">
  <div class="ss-inner">
    <div class="ss-grid">
      <div class="ss-visual reveal-l">
        <img src="<?= actif('assets/images/site/strategie.jpg') ?>" alt="Community Management" class="ss-img"/>
        <div class="ss-img-tag">Community Management</div>
      </div>
      <div class="ss-content reveal-r">
        <h2 class="section-title">Stratégie & <em>Community Management</em></h2>
        <p class="section-sub" style="font-size:15px">Vos réseaux sociaux transformés en machines à attirer des clients. Chaque publication, chaque interaction est pensée pour convertir.</p>
        <div class="ss-deliverables">
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Stratégie de contenu mensuelle</h5><p>Calendrier éditorial complet, thématiques, angles de communication</p></div></div>
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Création de contenus quotidiens</h5><p>Posts, stories, reels, carrousels, créés par nos experts certifiés</p></div></div>
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Modération & engagement</h5><p>Réponse aux commentaires et messages, gestion de la communauté</p></div></div>
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Reporting mensuel</h5><p>Rapport détaillé avec KPIs, analyses et recommandations</p></div></div>
        </div>
        <div class="ss-pricing">
          <div><div class="ss-price-label">À partir de</div><div class="ss-price-val">75 000 FCFA/mois</div></div>
          <a href="<?= url('contact') ?>" class="btn btn-gold">Demander un devis</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- SERVICE 2: VIDEO -->
<section class="service-section" id="video">
  <div class="ss-inner">
    <div class="ss-grid">
      <div class="ss-content reveal-l">
        <h2 class="section-title">Production <em>Vidéo & Photo</em></h2>
        <p class="section-sub" style="font-size:15px">Des images qui arrêtent le scroll et des vidéos qui font vendre. Studio équipé broadcast, équipe créative, direction artistique maîtrisée.</p>
        <div class="ss-deliverables">
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Spots publicitaires TV & réseaux</h5><p>Production complète : script, tournage, montage, étalonnage</p></div></div>
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Shooting produit & corporate</h5><p>Photos professionnelles pour vos supports de communication</p></div></div>
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Reels & contenus courts</h5><p>Formats optimisés pour Instagram, TikTok et Facebook</p></div></div>
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Motion design</h5><p>Animations et vidéos infographiques pour vos présentations</p></div></div>
        </div>
        <div class="ss-pricing">
          <div><div class="ss-price-label">Sur devis selon projet</div><div class="ss-price-val">Dès 100€</div></div>
          <a href="<?= url('contact') ?>" class="btn btn-gold">Obtenir un devis</a>
        </div>
      </div>
      <div class="ss-visual reveal-r">
        <img src="<?= actif('assets/images/site/production.jpg') ?>" alt="Production Vidéo" class="ss-img"/>
        <div class="ss-img-tag">Studio Broadcast</div>
      </div>
    </div>
  </div>
</section>

<!-- SERVICE 3: GRAPHISME -->
<section class="service-section" id="graphisme">
  <div class="ss-inner">
    <div class="ss-grid">
      <div class="ss-visual reveal-l">
        <img src="<?= actif('assets/images/site/conception.jpg') ?>" alt="Conception Graphique" class="ss-img"/>
        <div class="ss-img-tag">Identité Visuelle</div>
      </div>
      <div class="ss-content reveal-r">
        <h2 class="section-title">Conception Graphique<br/>& <em>Identité Visuelle</em></h2>
        <p class="section-sub" style="font-size:15px">Un logo que vos clients reconnaissent au premier coup d'œil, une identité qui inspire confiance et fait la différence avec vos concurrents.</p>
        <div class="ss-deliverables">
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Création de logo</h5><p>3 propositions créatives, 2 tours de modifications inclus</p></div></div>
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Charte graphique complète</h5><p>Couleurs, typographies, règles d'usage, exemples d'application</p></div></div>
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Supports imprimés</h5><p>Cartes de visite, flyers, affiches, roll-up, packaging</p></div></div>
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Habillage digital</h5><p>Templates réseaux sociaux, bannières web, présentations</p></div></div>
        </div>
        <div class="ss-pricing">
          <div><div class="ss-price-label">Logo complet à partir de</div><div class="ss-price-val">50€</div></div>
          <a href="<?= url('contact') ?>" class="btn btn-gold">Demander un devis</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- SERVICE 4: ÉVÉNEMENTIEL -->
<section class="service-section" id="evenement">
  <div class="ss-inner">
    <div class="ss-grid">
      <div class="ss-content reveal-l">
        <h2 class="section-title">Organisation<br/><em>Événementielle</em></h2>
        <p class="section-sub" style="font-size:15px">Vos événements transformés en expériences mémorables. De la conception au jour J, nous gérons tout pour que vous soyez présent à 100%.</p>
        <div class="ss-deliverables">
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Conception scénographique</h5><p>Direction artistique, décoration, ambiance visuelle complète</p></div></div>
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Communication événement</h5><p>Campagne digitale, invitations, affiches, relations presse</p></div></div>
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Coordination jour J</h5><p>Équipe dédiée sur place, logistique, gestion des imprévus</p></div></div>
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Couverture audiovisuelle</h5><p>Photo et vidéo live, montage post-événement, restitution</p></div></div>
        </div>
        <div class="ss-pricing">
          <div><div class="ss-price-label">Sur devis selon envergure</div><div class="ss-price-val">Dès 300€</div></div>
          <a href="<?= url('contact') ?>" class="btn btn-gold">Obtenir un devis</a>
        </div>
      </div>
      <div class="ss-visual reveal-r">
        <img src="<?= actif('assets/images/site/organisation.jpg') ?>" alt="Événementiel" class="ss-img"/>
        <div class="ss-img-tag">Activation de Marque</div>
      </div>
    </div>
  </div>
</section>

<!-- SERVICE 5: RÉGIE -->
<section class="service-section" id="regie">
  <div class="ss-inner">
    <div class="ss-grid">
      <div class="ss-visual reveal-l">
        <img src="<?= actif('assets/images/site/regie.jpg') ?>" alt="Régie Publicitaire" class="ss-img"/>
        <div class="ss-img-tag">Régie Publicitaire</div>
      </div>
      <div class="ss-content reveal-r">
        <h2 class="section-title">Régie Publicitaire<br/>& <em>Affichage</em></h2>
        <p class="section-sub" style="font-size:15px">Panneaux, affichage digital, pub Facebook/Google, nous gérons vos investissements publicitaires pour maximiser chaque FCFA dépensé.</p>
        <div class="ss-deliverables">
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Affichage extérieur & billboards</h5><p>Sélection des emplacements, création des visuels, gestion des droits</p></div></div>
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Facebook & Instagram Ads</h5><p>Ciblage précis, création des publicités, optimisation quotidienne</p></div></div>
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Google Ads & SEA</h5><p>Campagnes de recherche et display, mots-clés optimisés</p></div></div>
          <div class="ss-deliverable"><div class="ss-deliverable-check">✓</div><div class="ss-deliverable-text"><h5>Suivi & optimisation</h5><p>Reporting détaillé, ajustements en temps réel, recommandations</p></div></div>
        </div>
        <div class="ss-pricing">
          <div><div class="ss-price-label">Gestion à partir de</div><div class="ss-price-val">100€/mois</div></div>
          <a href="<?= url('contact') ?>" class="btn btn-gold">Demander un devis</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- SERVICE 6: ABONNEMENT -->
<section class="service-section" id="abonnement" style="background:var(--bg)!important">
  <div class="ss-inner">
    <div style="text-align:center;max-width:680px;margin:0 auto" class="reveal">
      <div class="section-label" style="justify-content:center">Service phare</div>
      <h2 class="section-title">Abonnement Communication<br/><em>Pour PME</em></h2>
      <p class="section-sub">La solution tout-en-un pour les PME qui veulent une présence professionnelle sans gérer une équipe. Visibilité garantie, résultats mesurés, budget maîtrisé.</p>
    </div>
    <div class="abonnement-plans reveal">
      <div class="plan-card">
        <div class="plan-name">Starter</div>
        <div class="plan-price">100€</div>
        <div class="plan-period">FCFA par mois</div>
        <ul class="plan-features">
          <li>1 réseau social géré</li>
          <li>12 publications/mois</li>
          <li>Création des visuels</li>
          <li>Modération basique</li>
          <li>Rapport mensuel</li>
        </ul>
        <a href="<?= url('contact') ?>" class="btn btn-outline" style="width:100%;justify-content:center">Choisir Starter</a>
      </div>
      <div class="plan-card featured" style="background:var(--navy);border-color:var(--navy)">
        <div class="plan-recommended">Recommandé</div>
        <div class="plan-name" style="color:var(--gold)">Pro</div>
        <div class="plan-price" style="color:var(--gold)">250€</div>
        <div class="plan-period" style="color:rgba(255,255,255,0.5)">FCFA par mois</div>
        <ul class="plan-features" style="border-color:rgba(255,255,255,0.1)">
          <li>3 réseaux sociaux</li>
          <li>20 publications/mois</li>
          <li>Shooting mensuel inclus</li>
          <li>Modération 7j/7</li>
          <li>Stories & Reels</li>
          <li>Rapport détaillé</li>
        </ul>
        <a href="<?= url('contact') ?>" class="btn btn-gold" style="width:100%;justify-content:center">Choisir Pro</a>
      </div>
      <div class="plan-card">
        <div class="plan-name">Premium</div>
        <div class="plan-price">500€</div>
        <div class="plan-period">FCFA par mois</div>
        <ul class="plan-features">
          <li>Tous réseaux sociaux</li>
          <li>30+ publications/mois</li>
          <li>2 shootings/mois</li>
          <li>1 vidéo/mois</li>
          <li>Publicités incluses</li>
          <li>Stratégie trimestrielle</li>
          <li>Accès prioritaire</li>
        </ul>
        <a href="<?= url('contact') ?>" class="btn btn-outline" style="width:100%;justify-content:center">Choisir Premium</a>
      </div>
    </div>
  </div>
</section>

<!-- PROCESS -->
<section class="process-section">
  <div class="ps-inner">
    <div class="section-label" style="color: #ffffff !important;"> Comment ça marche</div>
    <h2 class="section-title">De la vision à la victoire</h2>
    <div class="process-steps reveal">
      <div class="ps-step"><div class="ps-num">01</div><h4>Audit & Diagnostic</h4><p>On analyse votre situation actuelle, vos concurrents et votre marché. Résultat : une vision claire de ce qui bloque votre croissance.</p></div>
      <div class="ps-step"><div class="ps-num">02</div><h4>Stratégie Sur-Mesure</h4><p>On conçoit votre plan de communication personnalisé avec des objectifs mesurables et un calendrier précis.</p></div>
      <div class="ps-step"><div class="ps-num">03</div><h4>Création & Exécution</h4><p>Notre équipe créative donne vie à votre stratégie. Contenus, visuels, vidéos, tout dans les délais convenus.</p></div>
      <div class="ps-step"><div class="ps-num">04</div><h4>Mesure & Optimisation</h4><p>On mesure chaque résultat et on optimise en continu pour maximiser votre retour sur investissement.</p></div>
    </div>
  </div>
</section>

<!-- CTA -->
<section style="padding:clamp(60px,8vw,100px) var(--gutter);background:var(--gold-bg);text-align:center;border-top:1px solid var(--border-gold)">
  <div class="reveal" style="max-width:600px;margin:0 auto">
    <div class="section-label" style="justify-content:center">Prêt à commencer ?</div>
    <h2 class="section-title" style="margin-bottom:16px">Demandez votre<br/><em>devis gratuit</em></h2>
    <p class="section-sub">Réponse garantie sous 24h. L'audit est gratuit, sans engagement.</p>
    <div style="display:flex;gap:16px;justify-content:center;margin-top:32px;flex-wrap:wrap">
      <a href="<?= url('contact') ?>" class="btn btn-gold">Demander un devis</a>
      <a href="<?= e(wa_lien()) ?>" class="btn btn-wa" target="_blank">WhatsApp direct</a>
    </div>
  </div>
</section>
