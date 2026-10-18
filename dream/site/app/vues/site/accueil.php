<?php
/** Live Y Dream | Agence de Communication N°1 à Lomé — Marketing Digital, Création, Événementiel — converti depuis index.html. */
$titre = 'Live Y Dream | Agence de Communication N°1 à Lomé — Marketing Digital, Création, Événementiel';
$description = "Depuis 2013, Live Y Dream est l'agence de communication de référence à Lomé, Togo. Marketing digital, création graphique, production vidéo, événementiel, community management.";
ob_start(); ?>
<style>

/* ===== EDIT ===== */

html,body{
    max-width:100% !important;
    overflow-x:hidden !important;
}

@media (max-width: 1024px) {
    .hero {
        grid-template-columns: 1fr;
        margin-top: 45px;
    }
    .hero-right-corner {
        top: 40px !important;
        right: 40px;
    }
}
@media (max-width: 768px) {
    .hero-stats {
        flex-direction: initial !important;
        gap: 20px;
        margin-top: 20px !important;
        margin-bottom: 30px !important;
    }
    .hero-right-corner {
        top: 40px !important;
        right: 40px;
    }
}

.cta-banner
 {
    background: #ffffff !important;
    border-top: 1px solid rgb(0 0 0) !important;
    border-bottom: 1px solid rgb(0 0 0) !important;
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
.hero-right-badge {
    font-family: 'DM Sans', serif !important;
}
.section-label:before {
    display: none !important;
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

/* ===== EDIT ===== */

/* ===== HOME SPECIFIC ===== */
.hero {
  min-height: 100vh; padding-top: 76px;
  display: grid; grid-template-columns: 1fr 1fr;
  align-items: center; gap: 0;
  background: var(--bg);
  position: relative; overflow: hidden;
}
.hero::before {
  content: ''; position: absolute; top: -200px; right: -200px;
  width: 700px; height: 700px; border-radius: 50%;
  background: radial-gradient(circle, rgba(201,168,76,0.08) 0%, transparent 70%);
  pointer-events: none;
}
.hero-left { padding: clamp(60px,8vw,120px) var(--gutter); padding-right: 40px; padding-left: clamp(20px,6vw,100px); }
.hero-badge {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--gold-bg); border: 1px solid var(--border-gold);
  padding: 7px 16px; margin-bottom: 32px; border-radius: 100px;
  font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;
  color: var(--gold-dark);
  animation: fadeUp 0.8s ease 0.2s both;
}
.badge-dot { width: 6px; height: 6px; background: #25D366; border-radius: 50%; animation: pulse 2s infinite; }

.hero-headline {
  font-family: var(--font-display);
  font-size: clamp(52px,7.5vw,100px);
  line-height: 0.93; letter-spacing: 1px; color: var(--navy);
  animation: fadeUp 0.9s ease 0.4s both;
}
.hero-headline .gold { color: var(--gold-dark); }
.hero-headline .stroke {
  -webkit-text-stroke: 1.5px var(--navy); color: transparent;
}
.hero-tagline {
  font-family: var(--font-serif); font-size: clamp(16px,1.8vw,20px);
  color: var(--text-soft); line-height: 1.75; margin: 24px 0 40px; max-width: 500px;
  animation: fadeUp 0.9s ease 0.6s both;
}
.hero-tagline strong { color: var(--navy); font-style: italic; }
.hero-actions { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; animation: fadeUp 0.9s ease 0.8s both; }
.hero-stats { display: flex; gap: 40px; margin-top: 56px; padding-top: 32px; border-top: 1px solid var(--border); animation: fadeUp 0.9s ease 1s both; }
.stat-num { font-family: var(--font-display); font-size: 44px; color: var(--navy); line-height: 1; }
.stat-label { font-size: 11px; color: var(--text-soft); letter-spacing: 1.5px; text-transform: uppercase; margin-top: 4px; }

.hero-right { height: 100vh; position: relative; background: var(--navy); overflow: hidden; }
.hero-img { width: 100%; height: 100%; object-fit: cover; opacity: 0.6; mix-blend-mode: luminosity; }
.hero-img-overlay { position: absolute; inset: 0; background: linear-gradient(135deg, rgba(10,22,40,0.7) 0%, rgba(10,22,40,0.3) 100%); display: flex; flex-direction: column; align-items: flex-start; justify-content: flex-end; padding: 48px; }
.hero-right-badge { display: inline-block; background: var(--gold); color: var(--navy); font-family: var(--font-display); font-size: 13px; letter-spacing: 2px; padding: 8px 16px; margin-bottom: 20px; }
.hero-right-title { font-family: var(--font-serif); font-size: 28px; color: #fff; font-weight: 700; line-height: 1.3; }
.hero-right-corner { position: absolute; top: 40px; right: 40px; width: 100px; height: 100px; border: 1px solid rgba(201,168,76,0.4); background: rgba(10,22,40,0.6); display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
.hero-right-corner-num { font-family: var(--font-display); font-size: 32px; color: var(--gold); line-height: 1; }
.hero-right-corner-txt { font-size: 9px; color: rgba(255,255,255,0.6); letter-spacing: 1.5px; text-transform: uppercase; margin-top: 4px; }

/* Problem section */
.problem { padding: clamp(80px,10vw,120px) var(--gutter); background: var(--navy); }
.problem-inner { max-width: var(--max-w); margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center; }
.problem .section-label { color: var(--gold); }
.problem .section-label::before { background: var(--gold); }
.problem .section-title { color: #fff; }
.problem .section-sub { color: rgba(255,255,255,0.6); }
.problem-cta { margin-top: 32px; display: inline-flex; align-items: center; gap: 12px; background: var(--gold); color: var(--navy); padding: 16px 32px; font-weight: 700; font-size: 13px; letter-spacing: 1.5px; text-transform: uppercase; transition: all var(--transition); }
.problem-cta:hover { background: var(--gold-light); transform: translateY(-2px); }
.problem-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 2px; }
.prob-card { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); padding: 28px; transition: all var(--transition); }
.prob-card:hover { background: rgba(201,168,76,0.07); border-color: rgba(201,168,76,0.25); }
.prob-icon { font-size: 26px; margin-bottom: 12px; }
.prob-card h4 { font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 8px; }
.prob-card p { font-size: 13px; color: rgba(255,255,255,0.5); line-height: 1.6; }

/* Services preview */
.services-preview { padding: clamp(80px,10vw,120px) var(--gutter); background: var(--bg); }
.services-preview-inner { max-width: var(--max-w); margin: 0 auto; }
.sp-header { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: end; margin-bottom: 60px; }
.services-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2px; }
.service-card {
  background: var(--bg-card); border: 1px solid var(--border);
  padding: 44px 36px; position: relative; overflow: hidden;
  transition: all var(--transition); cursor: pointer; display: flex; flex-direction: column;
}
.service-card:hover { border-color: var(--gold); box-shadow: var(--shadow-lg); transform: translateY(-4px); }
.s-num { font-family: var(--font-display); font-size: 72px; color: rgba(10,22,40,0.04); position: absolute; top: -8px; right: 16px; line-height: 1; pointer-events: none; }
.s-icon { font-size: 32px; margin-bottom: 20px; }
.service-card h3 { font-family: var(--font-serif); font-size: 20px; font-weight: 700; color: var(--navy); margin-bottom: 12px; }
.service-card p { font-size: 14px; color: var(--text-soft); line-height: 1.7; flex: 1; }
.s-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 20px; }
.s-tag { font-size: 10px; padding: 3px 9px; border: 1px solid var(--border-gold); color: var(--gold-dark); letter-spacing: 0.5px; border-radius: 2px; }
.s-link { display: inline-flex; align-items: center; gap: 6px; color: var(--gold-dark); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 20px; transition: gap var(--transition); }
.service-card:hover .s-link { gap: 10px; }
.featured-service { background: var(--navy); border-color: var(--navy); }
.featured-service:hover { border-color: var(--gold); }
.featured-service h3, .featured-service p { color: #fff; }
.featured-service p { color: rgba(255,255,255,0.65); }
.featured-service .s-tag { border-color: rgba(201,168,76,0.4); color: var(--gold); }
.featured-service .s-link { color: var(--gold); }
.featured-service .s-num { color: rgba(255,255,255,0.04); }

/* Portfolio preview */
.portfolio-preview { padding: clamp(80px,10vw,120px) var(--gutter); background: var(--bg-soft); }
.pp-inner { max-width: var(--max-w); margin: 0 auto; }
.pp-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 48px; gap: 24px; flex-wrap: wrap; }
.portfolio-grid { display: grid; grid-template-columns: repeat(12,1fr); gap: 12px; }
.pg-item { position: relative; overflow: hidden; cursor: pointer; border-radius: var(--radius); }
.pg-item.feat { grid-column: span 7; }
.pg-item.sm { grid-column: span 5; }
.pg-item.third { grid-column: span 4; }
.pg-thumb { width: 100%; height: 100%; min-height: 280px; object-fit: cover; display: block; transition: transform 0.6s ease; }
.pg-item:hover .pg-thumb { transform: scale(1.05); }
.pg-overlay { position: absolute; inset: 0; background: linear-gradient(0deg, rgba(10,22,40,0.9) 0%, rgba(10,22,40,0.2) 60%, transparent 100%); display: flex; flex-direction: column; justify-content: flex-end; padding: 28px; transition: opacity var(--transition); }
.pg-cat { display: inline-block; background: var(--gold); color: var(--navy); font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; padding: 4px 10px; margin-bottom: 10px; align-self: flex-start; }
.pg-title { font-family: var(--font-serif); font-size: clamp(16px,2vw,22px); color: #fff; font-weight: 700; line-height: 1.3; }
.pg-client { font-size: 12px; color: var(--gold); font-weight: 600; margin-top: 6px; letter-spacing: 0.5px; }

/* Proof */
.proof { padding: clamp(80px,10vw,120px) var(--gutter); background: var(--bg-card); }
.proof-inner { max-width: var(--max-w); margin: 0 auto; }
.proof-nums { display: grid; grid-template-columns: repeat(4,1fr); gap: 2px; margin-bottom: 72px; }
.proof-num-card { background: var(--bg); border: 1px solid var(--border); padding: 44px 32px; text-align: center; transition: all var(--transition); }
.proof-num-card:hover { border-color: var(--gold); box-shadow: var(--shadow-md); }
.proof-number { font-family: var(--font-display); font-size: 64px; color: var(--navy); line-height: 1; }
.proof-lbl { font-size: 11px; color: var(--text-soft); letter-spacing: 2px; text-transform: uppercase; margin-top: 8px; }
.clients-logos { display: grid; grid-template-columns: repeat(6,1fr); gap: 1px; }
.client-cell { background: var(--bg); border: 1px solid var(--border); padding: 28px 20px; display: flex; align-items: center; justify-content: center; font-family: var(--font-display); font-size: 16px; color: var(--text-soft); letter-spacing: 2px; opacity: 0.6; transition: all var(--transition); }
.client-cell:hover { opacity: 1; border-color: var(--border-gold); color: var(--navy); box-shadow: var(--shadow-sm); }

/* Testimonials */
.testimonials { padding: clamp(80px,10vw,120px) var(--gutter); background: var(--navy); }
.testi-inner { max-width: var(--max-w); margin: 0 auto; }
.testimonials .section-label { color: var(--gold); }
.testimonials .section-label::before { background: var(--gold); }
.testimonials .section-title { color: #fff; }
.testi-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 2px; margin-top: 52px; }
.testi-card { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); padding: 44px; transition: all var(--transition); }
.testi-card:hover { background: rgba(255,255,255,0.07); border-color: rgba(201,168,76,0.3); }
.testi-stars { display: flex; gap: 3px; color: var(--gold); font-size: 14px; margin-bottom: 20px; }
.testi-quote { font-family: var(--font-serif); font-size: clamp(15px,1.5vw,18px); color: rgba(255,255,255,0.85); line-height: 1.85; font-style: italic; }
.testi-author { display: flex; align-items: center; gap: 14px; margin-top: 32px; padding-top: 24px; border-top: 1px solid rgba(255,255,255,0.08); }
.testi-av { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg,var(--gold-dark),var(--navy-light)); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 18px; color: #fff; flex-shrink: 0; }
.testi-name { font-weight: 700; font-size: 14px; color: #fff; }
.testi-role { font-size: 12px; color: var(--gold); margin-top: 2px; }

/* CTA Banner */
.cta-banner { padding: clamp(80px,10vw,120px) var(--gutter); background: var(--gold-bg); border-top: 1px solid var(--border-gold); border-bottom: 1px solid var(--border-gold); text-align: center; }
.cta-banner-inner { max-width: 780px; margin: 0 auto; }
.cta-big-title { font-family: var(--font-display); font-size: clamp(48px,7vw,90px); line-height: 0.95; letter-spacing: 1px; color: var(--navy); margin: 16px 0 24px; }
.cta-big-title span { color: var(--gold-dark); }
.cta-sub { font-family: var(--font-serif); font-size: 18px; color: var(--text-soft); line-height: 1.7; margin-bottom: 40px; }
.cta-actions { display: flex; align-items: center; justify-content: center; gap: 16px; flex-wrap: wrap; }
.urgency { display: flex; align-items: center; gap: 8px; justify-content: center; font-size: 13px; color: var(--text-soft); margin-top: 24px; }
.urgency-dot { width: 8px; height: 8px; background: #25D366; border-radius: 50%; animation: pulse 2s infinite; }

@media (max-width:1024px) {
  .hero { grid-template-columns: 1fr; }
  .hero-right { height: 50vh; }
  .problem-inner { grid-template-columns: 1fr; gap: 48px; }
  .sp-header { grid-template-columns: 1fr; gap: 20px; }
  .services-grid { grid-template-columns: 1fr 1fr; }
  .portfolio-grid { grid-template-columns: 1fr 1fr; }
  .pg-item.feat, .pg-item.sm, .pg-item.third { grid-column: span 1; }
  .proof-nums { grid-template-columns: repeat(2,1fr); }
  .clients-logos { grid-template-columns: repeat(3,1fr); }
  .testi-grid { grid-template-columns: 1fr; }
}
@media (max-width:768px) {
  .hero-stats { flex-direction: column; gap: 20px; }
  .services-grid { grid-template-columns: 1fr; }
  .portfolio-grid { grid-template-columns: 1fr; }
  .pg-item.feat, .pg-item.sm, .pg-item.third { grid-column: span 1; }
  .hero-actions { flex-direction: column; }
  .btn { width: 100%; justify-content: center; }
  .cta-actions { flex-direction: column; }
  .clients-logos { grid-template-columns: repeat(2,1fr); }
}

/* ============================================================
   ===== RESPONSIVE OVERRIDES (ajout uniquement) ==============
   Aucune règle existante n'est supprimée ou modifiée.
   Ce bloc cible uniquement les écrans tablette et mobile
   pour améliorer la lisibilité et la disposition.
   ============================================================ */

/* === TABLETTE & DESSOUS (≤ 1024px) === */
@media (max-width: 1024px) {
  /* Hero : laisser respirer le contenu, image en haut */
  .hero { padding-top: 60px !important; }
  .hero-left {
    padding: 32px 24px 24px !important;
  }
  .hero-right { height: 56vh !important; min-height: 380px; }
  .hero-img-overlay { padding: 32px !important; }
  .hero-right-title { font-size: 22px !important; line-height: 1.35 !important; }
  .hero-right-corner {
    width: 86px !important; height: 86px !important;
    top: 24px !important; right: 24px !important;
  }
  .hero-right-corner-num { font-size: 26px !important; }
  .hero-right-corner-txt { font-size: 8px !important; }

  /* Sections : padding latéral réduit */
  .problem,
  .services-preview,
  .portfolio-preview,
  .proof,
  .testimonials,
  .cta-banner {
    padding-left: 24px !important;
    padding-right: 24px !important;
  }
}

/* === MOBILE LARGE (≤ 768px) === */
@media (max-width: 768px) {
  /* HERO ----------------------------------------- */
  .hero-left {
    padding: 24px 20px 16px !important;
  }
  .hero-headline {
    font-size: clamp(38px, 9vw, 52px) !important;
    line-height: 1 !important;
    letter-spacing: 0.5px !important;
  }
  .hero-tagline {
    font-size: 15px !important;
    line-height: 1.6 !important;
    margin: 18px 0 24px !important;
    max-width: 100% !important;
  }
  .hero-actions { gap: 12px !important; }
  .hero-actions .btn { width: 100% !important; }

  /* HERO STATS : forcer 3 colonnes serrées et compactes */
  .hero-stats {
    display: grid !important;
    grid-template-columns: repeat(3, 1fr) !important;
    gap: 12px !important;
    margin-top: 28px !important;
    padding-top: 22px !important;
  }
  .stat-num { font-size: 30px !important; }
  .stat-label { font-size: 9px !important; letter-spacing: 1px !important; line-height: 1.3 !important; }

  /* HERO RIGHT */
  .hero-right { height: 46vh !important; min-height: 320px; }
  .hero-img-overlay { padding: 24px !important; }
  .hero-right-badge { font-size: 11px !important; padding: 6px 12px !important; margin-bottom: 12px !important; }
  .hero-right-title { font-size: 18px !important; padding-bottom: 8px !important; }
  .hero-right-corner {
    width: 72px !important; height: 72px !important;
    top: 18px !important; right: 18px !important;
  }
  .hero-right-corner-num { font-size: 22px !important; }

  /* SECTIONS ------------------------------------- */
  .problem,
  .services-preview,
  .portfolio-preview,
  .proof,
  .testimonials,
  .cta-banner {
    padding-top: 60px !important;
    padding-bottom: 60px !important;
    padding-left: 18px !important;
    padding-right: 18px !important;
  }

  /* Section titles : déjà clampé à 32-40px, on resserre encore */
  .section-title {
    font-size: clamp(26px, 7vw, 32px) !important;
    line-height: 1.15 !important;
  }
  .section-sub { font-size: 14px !important; line-height: 1.65 !important; }
  .section-label { font-size: 11px !important; }

  /* PROBLEM */
  .problem-inner { gap: 32px !important; }
  .problem-cards { gap: 1px !important; }
  .prob-card { padding: 22px 20px !important; }
  .prob-card h4 { font-size: 13px !important; }
  .prob-card p { font-size: 12.5px !important; line-height: 1.55 !important; }
  .problem-cta { padding: 14px 24px !important; font-size: 12px !important; width: 100%; justify-content: center; }

  /* SERVICES */
  .sp-header { gap: 16px !important; margin-bottom: 36px !important; }
  .service-card { padding: 32px 24px !important; }
  .service-card h3 { font-size: 18px !important; }
  .service-card p { font-size: 13.5px !important; line-height: 1.6 !important; }
  .s-num { font-size: 56px !important; top: -4px !important; right: 12px !important; }
  .s-icon { margin-bottom: 14px !important; }
  .s-icon img { width: 42px !important; height: 42px !important; }
  .s-tags { gap: 5px !important; margin-top: 16px !important; }
  .s-link { font-size: 11px !important; margin-top: 16px !important; }

  /* PORTFOLIO */
  .pp-header { margin-bottom: 32px !important; gap: 16px !important; }
  .portfolio-grid { gap: 10px !important; }
  .pg-thumb { min-height: 220px !important; }
  .pg-overlay { padding: 20px !important; }
  .pg-title { font-size: 16px !important; }

  /* PROOF */
  .proof-nums {
    grid-template-columns: repeat(2, 1fr) !important;
    gap: 1px !important;
    margin-bottom: 48px !important;
  }
  .proof-num-card { padding: 28px 16px !important; }
  .proof-number { font-size: 42px !important; }
  .proof-lbl { font-size: 10px !important; letter-spacing: 1.2px !important; }
  .clients-logos { gap: 1px !important; }
  .client-cell { padding: 20px 12px !important; font-size: 13px !important; letter-spacing: 1.5px !important; }

  /* TESTIMONIALS */
  .testi-card { padding: 28px 22px !important; }
  .testi-quote { font-size: 14px !important; line-height: 1.7 !important; }
  .testi-author { margin-top: 22px !important; padding-top: 18px !important; gap: 12px !important; }
  .testi-av { width: 42px !important; height: 42px !important; font-size: 16px !important; }
  .testi-name { font-size: 13px !important; }
  .testi-role { font-size: 11px !important; }

  /* CTA BANNER */
  .cta-big-title {
    font-size: clamp(38px, 10vw, 56px) !important;
    margin: 12px 0 18px !important;
  }
  .cta-sub { font-size: 15px !important; line-height: 1.6 !important; margin-bottom: 28px !important; }
  .cta-actions { gap: 12px !important; }
  .cta-actions .btn { width: 100% !important; justify-content: center; }
  .urgency { font-size: 12px !important; margin-top: 18px !important; padding: 0 8px; line-height: 1.5; }

  /* TICKER (déjà masqué via display:none plus haut, on confirme) */
  .ticker { display: none !important; }
}

/* === MOBILE PETIT (≤ 480px) === */
@media (max-width: 480px) {
  .hero-left { padding: 20px 16px 12px !important; }
  .hero-headline { font-size: clamp(34px, 10vw, 44px) !important; }
  .hero-tagline { font-size: 14px !important; }

  /* Stats compactés sur très petit écran */
  .stat-num { font-size: 26px !important; }
  .stat-label { font-size: 8px !important; }

  /* Hero right corner réduit encore */
  .hero-right-corner {
    width: 64px !important; height: 64px !important;
    top: 14px !important; right: 14px !important;
  }
  .hero-right-corner-num { font-size: 19px !important; }
  .hero-right-corner-txt { font-size: 7px !important; }
  .hero-right-title { font-size: 16px !important; }
  .hero-right-badge { font-size: 10px !important; padding: 5px 10px !important; }

  /* Sections : padding minimal */
  .problem,
  .services-preview,
  .portfolio-preview,
  .proof,
  .testimonials,
  .cta-banner {
    padding-left: 14px !important;
    padding-right: 14px !important;
    padding-top: 48px !important;
    padding-bottom: 48px !important;
  }

  /* CTA WhatsApp : éviter wrap moche du SVG + texte */
  .btn-wa { font-size: 12px !important; padding: 14px 18px !important; }
  .btn-wa svg { width: 18px !important; height: 18px !important; }

  /* Section titles encore plus serrés */
  .section-title { font-size: clamp(22px, 7vw, 28px) !important; }
  .cta-big-title { font-size: clamp(34px, 11vw, 48px) !important; }

  /* Proof number : éviter débordement */
  .proof-number { font-size: 36px !important; }
  .clients-logos { grid-template-columns: repeat(2, 1fr) !important; }
}

/* === ULTRA PETIT (≤ 360px) === */
@media (max-width: 360px) {
  .hero-headline { font-size: 32px !important; }
  .stat-num { font-size: 22px !important; }
  .stat-label { font-size: 7.5px !important; }
  .section-title { font-size: 22px !important; }
  .cta-big-title { font-size: 32px !important; }
  .proof-number { font-size: 32px !important; }
}
</style>
<?php $tete = ob_get_clean(); ?>
<!-- HERO -->
<section class="hero" id="hero">
  <div class="hero-left">
    <div class="hero-badge"><div class="badge-dot"></div>Agence N°1 à Lomé depuis 2013</div>
    <h1 class="hero-headline" id="heroTitle"><?= texte_marque(reglage('hero_titre')) ?></h1>
    <p class="hero-tagline" id="heroSub"><?= texte_marque(reglage('hero_sous_titre')) ?></p>
    <div class="hero-actions">
      <a href="<?= url('contact') ?>" class="btn btn-gold"><span>Audit gratuit</span> </a>
      <a href="<?= url('campagnes') ?>" class="btn btn-outline">Voir nos campagnes</a>
    </div>
    <div class="hero-stats">
      <div><?= compteur(reglage('stat1'), 'stat-num') ?><div class="stat-label"><?= e(reglage('stat1_libelle')) ?></div></div>
      <div><?= compteur(reglage('stat2'), 'stat-num') ?><div class="stat-label"><?= e(reglage('stat2_libelle')) ?></div></div>
      <div><?= compteur(reglage('stat3'), 'stat-num') ?><div class="stat-label"><?= e(reglage('stat3_libelle')) ?></div></div>
    </div>
  </div>
  <div class="hero-right">
    <img src="<?= actif('assets/images/site/background.jpg') ?>" alt="Équipe Live Y Dream" class="hero-img" id="heroImg"/>
    <div class="hero-img-overlay">
      <div class="hero-right-badge">Communication & Influence</div>
      <div class="hero-right-title">Lomé · Cotonou · Libreville · Bamako</div>
    </div>
    <div class="hero-right-corner">
      <div class="hero-right-corner-num">★</div>
      <div class="hero-right-corner-txt">Agence de référence</div>
    </div>
  </div>
</section>

<!-- TICKER -->
<div class="ticker" aria-hidden="true">
  <div class="ticker-inner">
    <div class="ticker-item">STRATÉGIE DIGITALE <span class="ticker-sep">◆</span> CRÉATION GRAPHIQUE <span class="ticker-sep">◆</span> PRODUCTION VIDÉO <span class="ticker-sep">◆</span> COMMUNITY MANAGEMENT <span class="ticker-sep">◆</span> ÉVÉNEMENTIEL <span class="ticker-sep">◆</span> RÉGIE PUBLICITAIRE <span class="ticker-sep">◆</span> INFLUENCE CULTURELLE <span class="ticker-sep">◆</span></div>
    <div class="ticker-item">STRATÉGIE DIGITALE <span class="ticker-sep">◆</span> CRÉATION GRAPHIQUE <span class="ticker-sep">◆</span> PRODUCTION VIDÉO <span class="ticker-sep">◆</span> COMMUNITY MANAGEMENT <span class="ticker-sep">◆</span> ÉVÉNEMENTIEL <span class="ticker-sep">◆</span> RÉGIE PUBLICITAIRE <span class="ticker-sep">◆</span> INFLUENCE CULTURELLE <span class="ticker-sep">◆</span></div>
  </div>
</div>

<!-- PROBLÈME -->
<section class="problem">
  <div class="problem-inner">
    <div class="reveal-l">
      <div class="section-label" style="color: #ffffff !important;"> Le diagnostic</div>
      <h2 class="section-title">Votre entreprise mérite mieux que l'invisibilité</h2>
      <p class="section-sub">Vous avez un produit de qualité. Vous travaillez dur. Et pourtant vos concurrents captent les clients qui vous reviennent de droit pas parce qu'ils sont meilleurs, mais parce qu'ils sont <strong style="color:#fff">plus visibles.</strong></p>
      <a href="<?= url('contact') ?>" class="problem-cta" style="margin-top:32px;display:inline-flex;align-items:center;gap:10px;">Je veux dominer mon marché </a>
    </div>
    <div class="problem-cards reveal">
      <div class="prob-card"><div class="prob-icon"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAACXBIWXMAAAsTAAALEwEAmpwYAAAERklEQVR4nNWaX4gWVRjGv12zSFIzNSRd3Ci1giiwiy4sL/yf5k1hgiCodKFIVoqIxlJoIkGJsMHeFGUWIWqRFJH/LfEPmhIolqRhQbaVppZCqT95l2e2w9mZ75vZOTO7+1ztnvOe93mfOWfe854zX6XSzQAMAt4AVgN9Kz0VwE7+x6te3yPASuAd4CXg1qKCuBuYDawFtgO/AlcV1Bmgscb4exwR14AnnVnaTEesCBl8HfA08BVwneqoKga4V3Ym/hm13Ql8l+DvhVAiHgP2kw3/AseA94EFNguezx+Buc7/GxL8vAfcEkLEi8B/5Md1LZuH5XcWMFB/PwrckN1PwEbgFXuAuQWIoJnwsHeiCejl8KzTjI8JErgnwsiKxGdAb3EtBuqLEPG4nlzR+MCSSAz/dOBr4G/gAtDizmBaEbcBJykP8z1+S+lxGJlViO0PZeK87UsOv6Xh8cDH6vtNm2NdViEHKB/NmYJMIeLBQIG9Bnyawf4v4PaQQp7PKcD2gib5agDOZRg7K6SQd3OI+MXWtudvNPBHyvFbQgo53AkBrcAy4I4En/cDR1P4ORZSyOmUwf8DbAWeS7O2beMDXq6x1H4PKcTSnY8/gYMq6pZYyW17TSf99wEWJszQlZBC/PK8NZjzjlytHteNkM597Klia2v/WyslYvq+Ud99Vcbv8cmKFNKScMCa6SzDAzE2B51lOSOBq6VMIYucvnoVc/a0I2yKy1bWZunUsdur02V7hQssKlPIRKfPPSFa9plTrf7RzM1TrRRhn9M/sRAhyig+Gpz+HcAPepKpr2+AfjplnrKLCqe9IYavTwghdrhxcTxzxZmNrw7Y7XEuDeH4iJxNq2E3VeXIz8CUvHaOreFIJS+Ay3LWv4adBRbhbF47557McKmSFzpWGgZ0gZCBsrucR0PkzFKkYVUNuykK8iwwOa+dQfe/VTfg1LB3w3mCb5ZxsQz0Bd5yzjJPhXK81Km37NQ2NojjeK6x4kCcS0ITTABOBD/sdOT5SBwn/ANZSJIHNNV2VTq0AP+Dndv7h0L798l2iagpob9BtdYlb1NrS6OqtUYljF0uux2FihDZJJGd91OyRFhlWwtmM8wb299Og+qfULgQkR4S4Rqv3WbC8LkfqPqHAV/IZqPXtyqp/C8MevENV+zjjNMeLacxOiBdVd3UGFMUXvQERhvvuNKEiPxDEW9XoTfUWToXvaV0QRcTbaKiRsdXNEvrSxXh1EHR3ZS9pF/GvAsjYtpckcP1URO9H4NKFyIxM5SOrzmfG9qDl001Ud8rlZuPZ7tEhCNmjRuZ2uwbxt6YvyPYBYWL17tUhEHvx9v+uo9DgqjmIg9qqaFSe30aId64CN1CRC/vXnhbhrGZhBcK3aqjXzg0ZhzbrYQ8oXj2d2JstxJyl9Kn/ZphSI8VYgA+UUzLKj1cyCTFdKqnC6lXDdW2P2QY176nhMBNQbcXf373hqQAAAAASUVORK5CYII=" alt="afraid"></div><h4>Vos concurrents gagnent des clients</h4><p>Pendant que vous dormez, vos rivaux moins compétents mais mieux communiqués signent des contrats.</p></div>
      <div class="prob-card"><div class="prob-icon"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAACXBIWXMAAAsTAAALEwEAmpwYAAAEm0lEQVR4nO2Za4hVVRTHj9P0wslm7KkFGaVODyqLPhSaEkUERhFSSI9pyuwJ9SWtD72hRISISaMXvckgs+hDpT3sReVAYR+yzIoKepDV2IzT218s5r9xtTn33HPnnjv3TMwfDtx7995rrf/e67XPTZIxjGF0AOgAuoHngE+AAT32ebXGOpKyAtgduAHoozp+Aa4HdkvKBGAy0OsMfRlYAEwHxuvp1G9r3Lz1tjYpEYmvZZi5z8wca2ZpLlo7uQzu1CuDXgfaa1jbDqzT2veb6mYMxYRhYyUSwDE2ISMxfCoZixtucIYRIbBT3Qk4KJxYhpzZLgF0NNToCgZ0h8CuMPYx8HeI6iqy1mpaV0ONrqDcaoLhkpSxU4Alet7OQeRSyVrVUKMrKA++Pb3KvFtyEOkMWS8ZaQD9Ut5WAJE2yepPRjmRCZK1NRlp/J9ca7WULyiAyMJmBnu3lK8pgMgrzUy/HSpihlnDJRIVxPakGVArHprF9lqJaDM2ScaipFmwRk+tOGoAc7cYIlGOpjGljbdMNjupArnTptK08REZ29WAtcpEh6nYtenzQhfY4STKQSJys8UuAWTB5iwCdk3KCoYuTF3As7qn9OvZqN+6mpKdGNrpc4Gn5Ne2m9uBLTLuMeC84e4ucBLwJPAV8DPwLjC3SAKHAnfndBXD90aoBvmHA29UkDWoeHoR2KbvTwD71ULgZOB54B8n2HbpMr0VmeDSZ6dcJaRRdAcZV+WEbwf+0PzvVGuOVdLYkLFZzwBHa715SA9wlBfeKvfwQn4DHvzPxOqXo3Aj7EkjowvXZ5pjG7UixIvtNvCC0/8WcKZizrwDf+N0+BM41QTMB75wA98CNwL75D7GHYZe5E5yncjtDewLPO50fASc4NadDfyoMYuT+W7M1j/t1v4A3Cm7baMNvYmLAWszLgZ2qZVARMYSw1an+C/gV/fdXqXur7l7Ao9GL/YOcLLOUuwhGdfaKyg3vpfG+uzLHOAMoKUeAhEZM/AaBbMRScOA20QL6CuDOwITFdwBrwFTIh3jgPvzdOBFkTIfPwdYCrwKbBYJj74Qi8BcuXcge1Ucb+oULGUbfrcM2HAiGQTH6+XdQzLoJeBhR+5N4JCUdXNcTJsLn5aUAcAe0ekMKhZaUojfo2Js+AA4omhj1ssdVgHX2S7ZbpqRGWvMsCOB5Y7EO8C0Ct3y5y7d3gTsXCgJKbKCWQ8GlBx2SiHb407hQyuIhROIlE5T+l6hGvJllHZjbNMuP2CxUuEUNrtTuLXuU1DlPw44MctdCoyZ5e4UrNuYUa9Qy9UXAt9EbnAfcHBh1u/Qd7q64HAKt9VboE3olOhvMqv877kWxJq+e30lrkPXxKjC9xYSC2oNwitR64EucFV3KvCIa+CsubzL+qlh6prn2pBBZb3WuklIeChSKysZqBZ+pTshc7k7bHdz6pikG2OAJYiphRCI0l6uYmOthRrB7a7duDncW1LmtwBXqMsN1fnyrDvMiAI4Xm1GwE/AMv3eqp7r/OjOY/ePA5MyApgZ3RjTYFlwXjIaAMzQnX+D3GiL/oa72t8pxjCGpNz4F31JuPnrXSKdAAAAAElFTkSuQmCC" alt="coin-in-hand"></div><h4>Votre budget pub ne rapporte rien</h4><p>Booster vos réseaux sociaux sans stratégie, c'est jeter de l'argent. Chaque FCFA mal dépensé creuse l'écart.</p></div>
      <div class="prob-card"><div class="prob-icon"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAACXBIWXMAAAsTAAALEwEAmpwYAAADqklEQVR4nO2YWahNURzGt2sWyuwiD5cHkmTIUBThhXTlwZR5TFLkRXkgIl6ELsKD4YFIPPJgjMxThnIVeZAp4zVP96e/+2G12+fcda59ztlHvtq19/qvtff/2+tb/7X3FwT/UYAAagGTgNPAOx1ngWkWCwoFwDZSY3dBkAHGK2GbhblAW6ANMBOoUGxqkHQAp5TsgojYbMXOB0kH8EbJtouINVfsU/CPEPkY/CPSOhckHcA4Z7HPAZoCTYAZzmKfEhQCgK1pyu+ugii/zoY4QTKzWXirzXFywZAoaAAlwHbgIfCd9PgMXABWAi2DpAAYIenUBE+Bnkkg0VnVyXAA6AvcjSq/QF3gjmJrgL06fw10yh+L4GdyO5TMvl+LGChV2wvbAJ2+C9V+R6SsIOxR2/G8FgHgiRLpFmo/qvYNum4JvFTbSKdfC+ces5323sBaYDMwJNsk6gCVwDegdijWXe1fgC7AJiV7RPHBwECdj3UkNgjYr/u6mJFNIvV+VaEU8S2KXwK+6uhmpIEPql491PdQKPF3mpHlun6UUyJUldVjOm8FvHKSKwuNM1zTeikGnqt07wTaO5JM+bKyQiQFMdsrUILtI4gYlqm9g1XBUFkvV5+DeSNC1YxUpJmRShH8LTHFuwKHnXE3jGQ+iZRELNoxbj9go87vAdNVpaxAIFkuMulljUQG0poIrAdWKXYbaOAQaQScCJH9pkKRm88XPIg4fW3zWwq0jhhXX8aEffqvsHKdEwI1IeLCt1/OfDSSRWQbNfXRkkKEv/XREkTk1F/7aM5neR/7uItKELgKnMyUiK/uicNH04PCcMtvVEn2JeKle+Ly0YAlwGMdsRDJRPfE7aP5Ju1JxFv3xO2jxUwkI90Tp4+WByK/dR+rj5YHaWXHP46ZSEa6B/qrypXr7/O9zk1yffNGxFf3VJkX9o9fHczlaZYvIml1b+uHPxuyzUIZMEDkGgK9gNWOeWh92+SciIeDc073uZvO4AM6yhv4ua7Cjk/WiQD9HN2/VyWz3951clgMz3zeskyMBxozr7rOtWX52B9eHbXZAiVFW0WK+zT31L1hVnUknPvab7ah3KfzdXVeDDQ2h8R5aLjtdMT4Ysc7rpDGe0rzUbq/aX+dnkSK7GvAfDafzqMj3lplhAlh18MidG8PMlwxbXvq/ky1uq8JgFHARVUSe9hQ83uBW9K6LbjhEePmK7H7Vnli1X0u4UiqNCu6zxWAy5qtogzGFHnr/j+CZOMHwTWKS5/39j0AAAAASUVORK5CYII=" alt="coach-"></div><h4>Votre expertise reste silencieuse</h4><p>Vous avez de l'expérience et des résultats, mais personne ne le sait. L'invisibilité tue les meilleures entreprises.</p></div>
      <div class="prob-card"><div class="prob-icon"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAACXBIWXMAAAsTAAALEwEAmpwYAAADpklEQVR4nO2aO09UQRTH14hIIhaAEC19FCgCi5GoiSSoFT3hI2jjZ/DRKU8bia9C7QmCr9jamBCFyBKVVisFiWIjC/FnJvu/2cnN7t7X3BUi/2QTlpk5jzlzHnNmM5lt/AcgAJmtArYV2aRIqgCQBW4CO9KRsDTTWuA8cBV4AiyUMMaCxsycc2ZNgBLLWjeeujJi+ABYITq+A/eAzhJ0LwF/rLnpKAN0AC99gs0Cw0C/xhuAXfo06H9mbASY8619ARz38bicmjJAHTAKbIj4KjAItMag1QoMiYbBhjairoIyN1wocRjIiWBeTBsc0G3U5qyL9nvgUAlljM9kkzI7Yznfh1Ln2pG/fRSPJeCUNXYxMU/gNPBTDCaBPS4EL8OrXpHN4IetjIvjZHbH4CGw0wnhyjxrgEfi+Q046MKxc5YlUlfCp8yU5TO7M3EhB/R8IrXjFHDMPkmGobhEOhUO18skrNfAW6DFhdAV5OiSDObTFofAK+3EcJnxGY3nqqDMmHg9i7qwQ3F7tVyeAJqBeTEwIfOAK8HL5BkvaYbPI8D9MOfSWMIKBqlahkLyNbgbpYo1xRxhyo4SltnvQnA/gKPisVKparYXXNCC2cDJVbYMxQ3rDTP5WpxwVw3LUHT6K2Eme0moPwYjv2Wa4wpdCsCAl5zDTDbJD//dYDNYhkJuM1gIM9lz9MYEDFt85f4XoC8uPQ/APtFczgQBWNPk4MgQrIxRwsPnJPQMTL0lWmuZaikiWsYSHvJJoxlFRX6HmexdnpqSMBWtPimTdxGaKR6tpSjO3h6XYZkA4PnMTLWcfTJu+A3hM6ZafuMg/E6EmXy9UtX7L0HEhNiryXOZTQaKx7MnbGTwuoaRe1VpAWjzcohp+IVddCfR9TIFUOhOGoxHvV56F6vYGd4VgCbgl2SK1tsCnmsHRlOTMLwstyTLdJzFHdalP1mbMgGAE5IhH7eQtc+lqWLrnUsZzH8vsCgZBpM26Lz2v2lj1jiVNLhBNy3e7xI16ETwiNqWqI1ZUyUlHovnV7szn5Rwt9WOmUrzmFE4Tp4lDM+Trhl0W5YxbcyulBx70bKEWyV8x8w8r6FIMuao3G9SiF23fMLNcQoIAMMW01V9Pxaz7BhRsjPI6xkvmWNHFKIdeOp735uXlQb0+tSohl+tdj2rsTGrAEQ0jF9Eb1I7VCir11avaREFpgC8ncYzXmxo13vU4JvQjwOW1QNY0985jZkfDJwNXcVuI7P18BdtMYRTDomFuAAAAABJRU5ErkJggg==" alt="time--v1"></div><h4>Vous n'avez pas le temps</h4><p>Gérer votre communication en plus de votre business est impossible. Une com bâclée fait plus de mal que rien.</p></div>
    </div>
  </div>
</section>

<!-- SERVICES -->
<section class="services-preview">
  <div class="services-preview-inner">
    <div class="sp-header">
      <div class="reveal-l">
        <div class="section-label">Ce que nous faisons</div>
        <h2 class="section-title">Un écosystème complet<br/>pour votre croissance</h2>
      </div>
      <div class="reveal-r">
        <p class="section-sub" style="font-size:15px">Nous ne vendons pas du graphisme ou de la vidéo. Nous apportons des <strong>nouveaux clients, de la visibilité et de la crédibilité.</strong></p>
        <a href="<?= url('services') ?>" class="btn btn-outline" style="margin-top:20px">Tous nos services</a>
      </div>
    </div>
    <div class="services-grid reveal">
      <div class="service-card" onclick="location.href='<?= url('services#community') ?>'">
        <div class="s-num">01</div>
        <div class="s-icon"><?= icone('cible') ?></div>
        <h3>Stratégie & Community Management</h3>
        <p>Vos réseaux sociaux transformés en machines à attirer des clients. Contenus, calendriers, interactions, on gère tout.</p>
        <div class="s-tags"><span class="s-tag">Facebook</span><span class="s-tag">Instagram</span><span class="s-tag">TikTok</span><span class="s-tag">LinkedIn</span></div>
        <a href="<?= url('services#community') ?>" class="s-link">En savoir plus →</a>
      </div>
      <div class="service-card" onclick="location.href='<?= url('services#video') ?>'">
        <div class="s-num">02</div>
        <div class="s-icon"><?= icone('photos') ?></div>
        <h3>Production Vidéo & Photo</h3>
        <p>Des images qui arrêtent le scroll et des vidéos qui font vendre. Studio équipé, équipe créative, direction artistique.</p>
        <div class="s-tags"><span class="s-tag">Publicités</span><span class="s-tag">Corporate</span><span class="s-tag">Shooting</span></div>
        <a href="<?= url('services#video') ?>" class="s-link">En savoir plus →</a>
      </div>
      <div class="service-card" onclick="location.href='<?= url('services#graphisme') ?>'">
        <div class="s-num">03</div>
        <div class="s-icon"><?= icone('design') ?></div>
        <h3>Conception Graphique & Identité</h3>
        <p>Un logo reconnaissable, une identité qui inspire confiance et fait la différence avec vos concurrents.</p>
        <div class="s-tags"><span class="s-tag">Logo</span><span class="s-tag">Charte</span><span class="s-tag">Packaging</span></div>
        <a href="<?= url('services#graphisme') ?>" class="s-link">En savoir plus →</a>
      </div>
      <div class="service-card" onclick="location.href='<?= url('services#evenement') ?>'">
        <div class="s-num">04</div>
        <div class="s-icon"><?= icone('evenement') ?></div>
        <h3>Organisation Événementielle</h3>
        <p>Vos événements transformés en expériences mémorables. De la conception au jour J, on gère tout.</p>
        <div class="s-tags"><span class="s-tag">Lancement</span><span class="s-tag">Corporate</span><span class="s-tag">Activation</span></div>
        <a href="<?= url('services#evenement') ?>" class="s-link">En savoir plus →</a>
      </div>
      <div class="service-card" onclick="location.href='<?= url('services#regie') ?>'">
        <div class="s-num">05</div>
        <div class="s-icon"><?= icone('regie') ?></div>
        <h3>Régie Publicitaire & Affichage</h3>
        <p>Panneaux, affichage digital, pub Facebook/Google, chaque FCFA investi maximisé.</p>
        <div class="s-tags"><span class="s-tag">Billboards</span><span class="s-tag">Facebook Ads</span><span class="s-tag">Google Ads</span></div>
        <a href="<?= url('services#regie') ?>" class="s-link">En savoir plus →</a>
      </div>
      <div class="service-card featured-service" onclick="location.href='<?= url('services#abonnement') ?>'">
        <div class="s-num">★</div>
        <div class="s-icon"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAACXBIWXMAAAsTAAALEwEAmpwYAAAEjklEQVR4nO2Ze2hWZRzHT877LeZ0U+lCLTXzQqZ2sUgopSKKQqOSkCAoJdL/lhaJiJRYUIZ/pJZd/ijKKCX6IyovJUbUbCUk2ip0qVnpZHNbNvMjv73fA0/b+573nJ3nbCP2hRf27jzn+31+z+V3e4OgF73oOQCGA/cBLwE7gTqggRxOA0eAL4D1wINAadCTANwMbAFaSIYzwFZgTncbMBX4zJnYv8A3wLPAHcAUYDTQF6gAJgNzgZXAHuCs867t1PSuNuAC4GmtqKEReAEYm5BnFLAaOCWeVmAVUJLd7AVgEPChs5KvAGVBCgAXAi8C58T5MTAkDWckgIHOUToJzAs8ArgT+FP8u4DBPvnd4/S2RP6y8x5kAGAccFw675pu4FngMceNzvJKnt+J2I4bFvkkrnDiwUJvxNGaC6RnuhW+SC3AoSDXYavxgAK6n+vxOh9GDHN2Y1qBMVkZcrUeN9g8fG1xTSqizuv/IP0H0hK9IaJl3maXTH+59DenJaoR0bXeZpdM/wbpV6clqhfRiIgxmdwRcZdpyIm0hlj+Y+jbTYb005DWtIY0ich/uhA/hhka0xL92kYDV3ibXTL9a6T/c1oiy0QN873NLpn+Q9L/SN+vA1YA5UmJqkS0KWJMIiTUD0uGxfr+pL43q0AbFJdovOqE+kI1QlaGACNVPp8N8y1ghsrjsHY5AEyKSxjWIE8FXQg1KQzb8jyb5UR9q1DnxiGcrnrcPNhlWU08T57Vqs/EiELvTRljc7sxDvFGvWC70y+LyTta1rA4JL2XYxR8Vm4bfosK3G6ErdUL7wB9fBsgnXLgW+nsBgbEeKePSmPDq3HL0LCmfs13Gar2URi3bNFG6f+PFnMWQCXwjz6Xxm0QGP7waMBgtYHCBt8ex0vN1uSKej3gLQ1ZHkfU+lmGDzwYMMY8IXBYnOZSN9kl7iTfPeLZUWxgpdMUuKXAmKHAw1rJ0VrtEp39iepArlIZGyakqEN5fWcMCAFcLK66oBCAq4CfNPD9PM9two+rUR0X1ql8T23U1M4DGCLe04Xamuuc87vLrZ+1ys87TsBQDXyl/lSTIrP9/aNtO7AGuNvtUComLAV+L2Z9EO2MDLX5HoZuzY7BWqUsc9So3ttOw47HvCTeDLgSeA44Gncbg8Jcd2nIp/ke7i/C26zoelOe7stFmfZw20HxzVCV7+FMucMGucE6fV+vFRgaEZ2blNrMDjIGuYt+Rsd4rG9yOzIo3bjEK3nHyL5dWq8HvgH01+61XUDzfN5FgjYdu7uGY0VzrRQidsS+k5AdtSW+kk5y8SlMZs2r3uqDN0pwWLsfh2pl0AhP9UoLcLu/GRcXnu+k5+hy7lQNfq9VesqyByo9LwUmyKl06OIAj4hnf+recCeMsR9G71dgdFOUYvikvTGW4ivIompxZpca40ykVEatVbfmgCK7xaa/dXltovXOyv+npDVv6Bhjrn5DZhc+LcilHvuc3fkeeMbuhZXeWpDNMsTwddBTQe4YPWGNuhjH8GDQ00HO5d6mxPNL1TLNchy/WEAELu/uefbif4/zfOq6jb24SJIAAAAASUVORK5CYII=" alt="communication--v1"></div>
        <h3>Abonnement Communication PME</h3>
        <p>La solution tout-en-un pour les PME. Visibilité garantie, résultats mesurés, sans se prendre la tête.</p>
        <div class="s-tags"><span class="s-tag" style="color: #ffffff !important;">Dès 200€/mois</span></div>
        <a href="<?= url('services#abonnement') ?>" class="s-link">Découvrir l'offre</a>
      </div>
    </div>
  </div>
</section>

<!-- PORTFOLIO PREVIEW -->
<section class="portfolio-preview">
  <div class="pp-inner">
    <div class="pp-header reveal">
      <div>
        <div class="section-label">Nos réalisations</div>
        <h2 class="section-title">Des campagnes qui ont<br/><em>changé la donne</em></h2>
      </div>
      <a href="<?= url('campagnes') ?>" class="btn btn-outline">Voir tout le portfolio</a>
    </div>
    <div class="portfolio-grid" id="homePortfolio">
      <?php foreach (array_values($campagnes) as $i => $c): ?>
      <a class="pg-item <?= ['feat', 'sm', 'third', 'third'][$i] ?? 'third' ?>" href="<?= url('campagnes#campagne-' . (int) $c['id']) ?>">
        <img src="<?= e(image_url($c['image'])) ?>" alt="<?= e($c['titre']) ?>" class="pg-thumb" loading="lazy"/>
        <div class="pg-overlay">
          <span class="pg-cat"><?= e(categorie_campagne($c['categorie'])) ?></span>
          <div class="pg-title"><?= e($c['titre']) ?></div>
          <div class="pg-client"><?= e($c['client']) ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- PROOF -->
<section class="proof">
  <div class="proof-inner">
    <div class="proof-nums reveal">
      <div class="proof-num-card"><?= compteur(reglage('stat2'), 'proof-number') ?><div class="proof-lbl">Marques accompagnées</div></div>
      <div class="proof-num-card"><?= compteur(reglage('stat1'), 'proof-number') ?><div class="proof-lbl">Années d'expérience</div></div>
      <div class="proof-num-card"><?= compteur(reglage('stat3'), 'proof-number') ?><div class="proof-lbl">Pays couverts</div></div>
      <div class="proof-num-card"><?= compteur(reglage('stat4'), 'proof-number') ?><div class="proof-lbl">Experts dans l'équipe</div></div>
    </div>
    <div style="text-align:center;margin-bottom:36px" class="reveal">
      <div class="section-label" style="justify-content:center">Ils nous font confiance</div>
    </div>
    <div class="clients-logos reveal">
      <div class="client-cell"><img src="<?= actif('assets/images/clients/unicef-1.svg') ?>" alt="Logo UNICEF"/></div><div class="client-cell">UBA</div><div class="client-cell"><img src="<?= actif('assets/images/clients/Ecobank.svg') ?>" alt="Logo ECOBANK"/></div>
      <div class="client-cell">WOODIN</div><div class="client-cell">GIZ</div><div class="client-cell"><img src="<?= actif('assets/images/clients/MSC.svg') ?>" alt="Logo MSC"/></div>
      <div class="client-cell">GROUPE KIK</div><div class="client-cell">KRYSTAL</div><div class="client-cell">KRAMEL</div>
      <div class="client-cell">MANTA HUB</div><div class="client-cell">ABISCREAM</div><div class="client-cell">LDM</div>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="testimonials">
  <div class="testi-inner">
    <div class="section-label" style="color: #ffffff !important;"> Ce que disent nos clients</div>
    <h2 class="section-title">Des résultats qui<br/>parlent d'eux-mêmes</h2>
    <div class="testi-grid reveal">
      <div class="testi-card"><div class="testi-stars">★★★★★</div><p class="testi-quote">Live Y Dream nous accompagne depuis 3 ans. Ils ont su s'immerger dans notre culture d'entreprise pour nous offrir une identité visuelle incomparable. Leur réactivité est indéniablement leur plus grand atout.</p><div class="testi-author"><div class="testi-av">W</div><div><div class="testi-name">Directeur Marketing</div><div class="testi-role">Woodin Togo</div></div></div></div>
      <div class="testi-card"><div class="testi-stars">★★★★★</div><p class="testi-quote">Les missions confiées à Live Y Dream ont été réussies. Planification rigoureuse, sérieux et créativité ont apporté notre satisfaction. Je les recommande sans aucune hésitation à qui veut développer sa visibilité.</p><div class="testi-author"><div class="testi-av">K</div><div><div class="testi-name">Responsable Communication</div><div class="testi-role">Groupe KIK</div></div></div></div>
      <div class="testi-card"><div class="testi-stars">★★★★★</div><p class="testi-quote">Live Y Dream apporte de bonnes réponses en termes de visibilité. Nos interlocuteurs font preuve d'une excellente capacité d'écoute tant pour la nature de nos besoins que pour la mise en œuvre des actions.</p><div class="testi-author"><div class="testi-av">M</div><div><div class="testi-name">Directrice Générale</div><div class="testi-role">Manta Hub</div></div></div></div>
      <div class="testi-card"><div class="testi-stars">★★★★★</div><p class="testi-quote">Une agence créative, réactive et sympathique qui sait mettre la satisfaction client au cœur de ses préoccupations. À travers les projets réalisés, ils ont su répondre à toutes nos demandes, quelle que soit leur complexité.</p><div class="testi-author"><div class="testi-av">A</div><div><div class="testi-name">Responsable</div><div class="testi-role">Abiscream</div></div></div></div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-banner">
  <div class="cta-banner-inner reveal">
    <div class="section-label" style="justify-content:center">Passez à l'action</div>
    <h2 class="cta-big-title">PRÊT À<br/><span>DOMINER</span><br/>VOTRE MARCHÉ ?</h2>
    <p class="cta-sub">Vos concurrents investissent dans leur communication. Chaque jour que vous attendez est un jour où ils prennent de l'avance. <strong>L'audit est gratuit. Les résultats sont réels.</strong></p>
    <div class="cta-actions">
      <a href="<?= e(wa_lien()) ?>?text=Bonjour%20LYD%2C%20je%20souhaite%20un%20audit%20gratuit." class="btn btn-wa" target="_blank">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="white" style="flex-shrink:0"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        Audit gratuit sur WhatsApp
      </a>
      <a href="<?= url('contact') ?>" class="btn btn-outline">Remplir le formulaire</a>
    </div>
  </div>
</section>
