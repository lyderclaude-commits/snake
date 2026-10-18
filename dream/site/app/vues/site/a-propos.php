<?php
/** À Propos — L'histoire de Live Y Dream, Agence de Communication à Lomé — converti depuis a-propos.html. */
$titre = "À Propos — L'histoire de Live Y Dream, Agence de Communication à Lomé";
$description = 'Depuis 2013, Live Y Dream construit les marques qui dominent. Découvrez notre histoire, notre équipe et notre vision panafricaine.';
ob_start(); ?>
<style>

/* ===== EDIT ===== */

html,body{
    max-width:100% !important;
    overflow-x:hidden !important;
}
.section-label:before {
    display: none !important;
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
.val-card h5 {
    color: #000000 !important;
}
.team-role {
    color: #000000 !important;
}

/* ===== EDIT ===== */

.story { padding: clamp(60px,8vw,100px) var(--gutter); background: var(--bg); }
.story-inner { max-width: var(--max-w); margin: 0 auto; display: grid; grid-template-columns: 5fr 7fr; gap: 80px; align-items: center; }
.story-img-wrap { position: relative; }
.story-img { width: 100%; aspect-ratio: 4/5; object-fit: cover; }
.story-badge { position: absolute; bottom: -24px; right: -24px; width: 140px; height: 140px; background: var(--navy); display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 16px; }
.story-badge-num { font-family: var(--font-display); font-size: 48px; color: var(--gold); line-height: 1; }
.story-badge-txt { font-size: 10px; color: rgba(255,255,255,0.7); letter-spacing: 1.5px; text-transform: uppercase; margin-top: 4px; }
.story-content p { font-family: var(--font-serif); font-size: 16px; color: var(--text-mid); line-height: 1.85; margin-bottom: 18px; }
.story-content p strong { color: var(--navy); }
.story-values { display: grid; grid-template-columns: 1fr 1fr; gap: 2px; margin: 32px 0; }
.val-card { background: var(--bg-soft); border: 1px solid var(--border); padding: 24px; transition: all var(--transition); }
.val-card:hover { border-color: var(--gold); }
.val-card h5 { font-family: var(--font-serif); font-size: 16px; color: var(--gold-dark); margin-bottom: 8px; font-weight: 700; }
.val-card p { font-size: 13px; color: var(--text-soft); line-height: 1.6; }

.milestones { padding: clamp(60px,8vw,100px) var(--gutter); background: var(--navy); }
.ms-inner { max-width: var(--max-w); margin: 0 auto; }
.milestones .section-title { color: #fff; margin-bottom: 52px; }
.milestones .section-label { color: var(--gold); }
.milestones .section-label::before { background: var(--gold); }
.timeline { position: relative; padding-left: 48px; }
.timeline::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 1px; background: rgba(201,168,76,0.3); }
.tl-item { position: relative; margin-bottom: 48px; }
.tl-item::before { content: ''; position: absolute; left: -52px; top: 4px; width: 10px; height: 10px; background: var(--gold); border-radius: 50%; }
.tl-year { font-family: var(--font-display); font-size: 18px; color: var(--gold); margin-bottom: 8px; }
.tl-title { font-family: var(--font-serif); font-size: 18px; color: #fff; font-weight: 700; margin-bottom: 6px; }
.tl-desc { font-size: 14px; color: rgba(255,255,255,0.55); line-height: 1.7; }

.team-section { padding: clamp(60px,8vw,100px) var(--gutter); background: var(--bg-soft); }
.ts-inner { max-width: var(--max-w); margin: 0 auto; }
.team-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 2px; margin-top: 52px; }
.team-card { background: var(--bg-card); border: 1px solid var(--border); overflow: hidden; transition: all var(--transition); }
.team-card:hover { border-color: var(--border-gold); box-shadow: var(--shadow-md); transform: translateY(-4px); }
.team-photo { width: 100%; aspect-ratio: 1; object-fit: cover; background: linear-gradient(135deg,var(--navy-light),var(--navy-mid)); display: flex; align-items: center; justify-content: center; font-family: var(--font-display); font-size: 56px; color: rgba(255,255,255,0.2); }
.team-info { padding: 20px; }
.team-name { font-family: var(--font-serif); font-size: 17px; font-weight: 700; color: var(--navy); margin-bottom: 4px; }
.team-role { font-size: 12px; color: var(--gold-dark); font-weight: 600; letter-spacing: 1px; text-transform: uppercase; }
.team-dept { font-size: 12px; color: var(--text-soft); margin-top: 6px; }

.impact { padding: clamp(60px,8vw,100px) var(--gutter); background: var(--bg-card); }
.impact-inner { max-width: var(--max-w); margin: 0 auto; display: grid; grid-template-columns: repeat(4,1fr); gap: 2px; }
.impact-card { padding: 44px 28px; text-align: center; border: 1px solid var(--border); background: var(--bg); transition: all var(--transition); }
.impact-card:hover { border-color: var(--gold); box-shadow: var(--shadow-md); }
.impact-num { font-family: var(--font-display); font-size: 64px; color: var(--navy); line-height: 1; }
.impact-lbl { font-size: 11px; color: var(--text-soft); letter-spacing: 2px; text-transform: uppercase; margin-top: 8px; }

@media (max-width:1024px) { .story-inner { grid-template-columns: 1fr; gap: 48px; } .team-grid { grid-template-columns: 1fr 1fr; } .impact-inner { grid-template-columns: 1fr 1fr; } }
@media (max-width:768px) { .story-values { grid-template-columns: 1fr; } .team-grid { grid-template-columns: 1fr 1fr; } .story-badge { right: 0; bottom: -16px; width: 110px; height: 110px; } }

/* ============================================================
   ===== RESPONSIVE OVERRIDES (ajout uniquement) ==============
   Aucune règle existante n'est supprimée ou modifiée.
   Améliore la lisibilité tablette + mobile pour a-propos.html
   ============================================================ */

/* === TABLETTE & DESSOUS (≤ 1024px) === */
@media (max-width: 1024px) {
  /* Page header */
  .page-header {
    padding-left: 24px !important;
    padding-right: 24px !important;
  }
  .page-header-inner { padding: 80px 0 60px !important; }

  /* Sections : padding latéral réduit */
  .story,
  .impact,
  .milestones,
  .team-section {
    padding-left: 24px !important;
    padding-right: 24px !important;
  }

  /* Story : grid déjà 1fr à 1024px, on resserre */
  .story-inner { gap: 36px !important; }
  .story-badge {
    width: 120px !important; height: 120px !important;
    right: 16px !important; bottom: -20px !important;
    padding: 14px !important;
  }
  .story-badge-num { font-size: 40px !important; }

  /* Team : 2 colonnes à 1024px (au lieu de 4) — ajustements internes */
  .team-grid { gap: 1px !important; margin-top: 40px !important; }
  .team-photo { font-size: 48px !important; }

  /* Impact */
  .impact-card { padding: 36px 24px !important; }
  .impact-num { font-size: 56px !important; }

  /* Milestones */
  .timeline { padding-left: 40px !important; }
  .tl-item::before { left: -44px !important; }
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

  /* SECTIONS : padding général */
  .story,
  .impact,
  .milestones,
  .team-section {
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

  /* STORY */
  .story-inner { gap: 28px !important; }
  .story-img { aspect-ratio: 4/3 !important; }
  .story-badge {
    width: 96px !important; height: 96px !important;
    right: 12px !important; bottom: -16px !important;
    padding: 10px !important;
  }
  .story-badge-num { font-size: 30px !important; }
  .story-badge-txt { font-size: 8.5px !important; letter-spacing: 1px !important; }
  .story-content p { font-size: 14.5px !important; line-height: 1.7 !important; margin-bottom: 14px !important; }

  /* Story values : garder 2 colonnes serrées au lieu de tomber en 1 */
  .story-values {
    grid-template-columns: 1fr 1fr !important;
    gap: 1px !important;
    margin: 24px 0 !important;
  }
  .val-card { padding: 18px 16px !important; }
  .val-card h5 { font-size: 14px !important; margin-bottom: 6px !important; }
  .val-card p { font-size: 12px !important; line-height: 1.5 !important; }

  /* IMPACT : 2 colonnes à 1024px, restent 2 à 768px */
  .impact-inner { gap: 1px !important; }
  .impact-card { padding: 28px 18px !important; }
  .impact-num { font-size: 38px !important; }
  .impact-lbl { font-size: 10px !important; letter-spacing: 1.2px !important; margin-top: 6px !important; line-height: 1.3 !important; }

  /* MILESTONES : timeline plus compacte */
  .milestones .section-title { margin-bottom: 32px !important; }
  .timeline { padding-left: 28px !important; }
  .timeline::before { width: 1px !important; }
  .tl-item { margin-bottom: 32px !important; }
  .tl-item::before {
    left: -32px !important;
    width: 8px !important; height: 8px !important;
    top: 3px !important;
  }
  .tl-year { font-size: 15px !important; margin-bottom: 5px !important; }
  .tl-title { font-size: 15px !important; margin-bottom: 5px !important; line-height: 1.3 !important; }
  .tl-desc { font-size: 13px !important; line-height: 1.6 !important; }

  /* TEAM 
  .team-section .section-title { margin-bottom: 4px; }
  .team-grid { margin-top: 32px !important; gap: 1px !important; }
  .team-info { padding: 14px !important; }
  .team-name { font-size: 15px !important; }
  .team-role { font-size: 10.5px !important; letter-spacing: 0.8px !important; }
  .team-dept { font-size: 11px !important; margin-top: 4px !important; }
  .team-photo { font-size: 38px !important; }
*/
  /* CTA bas : section avec style inline */
  section[style*="border-top: 1px solid rgb(0 0 0)"][style*="text-align:center"] {
    padding-left: 18px !important;
    padding-right: 18px !important;
    padding-top: 52px !important;
    padding-bottom: 52px !important;
  }
  section[style*="border-top: 1px solid rgb(0 0 0)"][style*="text-align:center"] .btn {
    width: 100% !important;
    justify-content: center;
  }
}

/* === MOBILE PETIT (≤ 480px) === */
@media (max-width: 480px) {
  .page-header-inner { padding: 44px 0 32px !important; }
  .page-header .section-title { font-size: clamp(24px, 7.5vw, 30px) !important; }

  /* Sections padding minimum */
  .story,
  .impact,
  .milestones,
  .team-section {
    padding-left: 14px !important;
    padding-right: 14px !important;
    padding-top: 44px !important;
    padding-bottom: 44px !important;
  }
  .section-title { font-size: clamp(22px, 7vw, 28px) !important; }

  /* Story */
  .story-badge {
    width: 84px !important; height: 84px !important;
    right: 8px !important; bottom: -14px !important;
    padding: 8px !important;
  }
  .story-badge-num { font-size: 26px !important; }
  .story-badge-txt { font-size: 8px !important; line-height: 1.1 !important; }

  /* Story values : repasser en 1 colonne sur très petit */
  .story-values { grid-template-columns: 1fr !important; }

  /* Impact : 2 colonnes mais plus serrées */
  .impact-card { padding: 22px 14px !important; }
  .impact-num { font-size: 32px !important; }
  .impact-lbl { font-size: 9px !important; }

  /* Timeline encore plus serrée */
  .timeline { padding-left: 24px !important; }
  .tl-item::before { left: -28px !important; width: 7px !important; height: 7px !important; }
  .tl-item { margin-bottom: 26px !important; }

  /* Team : team-grid passe en 1 colonne ? Non — garder 2 mais réduire */
  .team-info { padding: 12px !important; }
  .team-name { font-size: 14px !important; }
  .team-photo { font-size: 32px !important; }
}

/* === ULTRA PETIT (≤ 360px) === */
@media (max-width: 360px) {
  .page-header .section-title { font-size: 22px !important; }
  .section-title { font-size: 22px !important; }
  .story-badge {
    width: 72px !important; height: 72px !important;
    right: 6px !important; bottom: -12px !important;
  }
  .story-badge-num { font-size: 22px !important; }
  .impact-num { font-size: 28px !important; }
  .tl-year { font-size: 14px !important; }
  .tl-title { font-size: 14px !important; }
  .tl-desc { font-size: 12.5px !important; }
  .team-grid { grid-template-columns: 1fr !important; }
}
</style>
<?php $tete = ob_get_clean(); ?>
<header class="page-header">
  <div class="page-header-grid"></div>
  <div class="page-header-inner">
    <div class="section-label" style="color:#ffffff !important">Notre Histoire</div>
    <h1 class="section-title">Bâtis pour faire gagner les autres</h1>
    <div class="page-header-breadcrumb"><a href="<?= url('') ?>">Accueil</a><span>/</span><span>À Propos</span></div>
  </div>
</header>

<!-- STORY -->
<section class="story" id="histoire">
  <div class="story-inner">
    <div class="story-img-wrap reveal-l">
      <img src="<?= actif('assets/images/site/a-propos.jpg') ?>" alt="Équipe Live Y Dream" class="story-img"/>
      <div class="story-badge">
        <div class="story-badge-num">10+</div>
        <div class="story-badge-txt">Ans au service de vos rêves</div>
      </div>
    </div>
    <div class="story-content reveal-r">
      <div class="section-label">Depuis 2013</div>
      <h2 class="section-title">Une décennie à<br/><em>construire l'excellence</em></h2>
      <p>Fondée en 2013 à Lomé, Live Y Dream est née d'une conviction simple : <strong>les meilleures entreprises africaines méritent une communication à la hauteur de leurs ambitions.</strong></p>
      <p>En 10 ans, nous avons accompagné plus de 200 marques, des PME locales aux multinationales comme UNICEF, UBA et Ecobank, avec une seule obsession : <strong>des résultats concrets et mesurables.</strong></p>
      <p>Aujourd'hui, Live Y Dream c'est une équipe de passionnés, un studio de production équipé, une présence dans 4 pays (Togo, Bénin, Gabon, Mali) et une vision panafricaine qui ne fait que commencer.</p>
      <div class="story-values">
        <div class="val-card"><h5>Excellence</h5><p>Chaque livrable doit dépasser les attentes. C'est notre standard, pas notre exception.</p></div>
        <div class="val-card"><h5>Résultats</h5><p>Chaque action doit générer un impact mesurable. On ne fait pas de la com pour faire de la com.</p></div>
        <div class="val-card"><h5>Innovation</h5><p>L'Afrique évolue vite. Nous évoluons encore plus vite pour vous garder en avance.</p></div>
        <div class="val-card"><h5>Loyauté</h5><p>Vos intérêts passent avant les nôtres. Votre succès est notre succès.</p></div>
      </div>
      <a href="<?= url('contact') ?>" class="btn btn-gold">Travaillons ensemble</a>
    </div>
  </div>
</section>

<!-- IMPACT -->
<section class="impact">
  <div class="impact-inner reveal">
    <div class="impact-card"><?= compteur(reglage('stat2'), 'impact-num') ?><div class="impact-lbl">Marques accompagnées</div></div>
    <div class="impact-card"><?= compteur(reglage('stat1'), 'impact-num') ?><div class="impact-lbl">Années d'expérience</div></div>
    <div class="impact-card"><?= compteur(reglage('stat3'), 'impact-num') ?><div class="impact-lbl">Pays couverts</div></div>
    <div class="impact-card"><?= compteur(reglage('stat4'), 'impact-num') ?><div class="impact-lbl">Experts dans l'équipe</div></div>
  </div>
</section>

<!-- MILESTONES -->
<section class="milestones">
  <div class="ms-inner">
    <div class="section-label" style="color:#ffffff !important"> Notre parcours</div>
    <h2 class="section-title">10 ans de jalons historiques</h2>
    <div class="timeline reveal">
      <div class="tl-item"><div class="tl-year">2013</div><div class="tl-title">Fondation de Live Y Dream</div><div class="tl-desc">Naissance de l'agence à Lomé avec une vision claire : créer l'agence de communication de référence en Afrique de l'Ouest.</div></div>
      <div class="tl-item"><div class="tl-year">2015</div><div class="tl-title">Premiers grands comptes</div><div class="tl-desc">Signature des premiers contrats avec des marques nationales majeures. L'agence prouve sa capacité à délivrer à grande échelle.</div></div>
      <div class="tl-item"><div class="tl-year">2018</div><div class="tl-title">Lomé By Night, Premier concept propriétaire</div><div class="tl-desc">Création et production du premier concept événementiel interne de l'agence. Plus de 1000 participants. La preuve que nous créons de la valeur au-delà des services.</div></div>
      <div class="tl-item"><div class="tl-year">2019</div><div class="tl-title">Battle Arena & Expansion régionale</div><div class="tl-desc">Lancement de Battle Arena et ouverture des premiers bureaux au Bénin. L'agence commence son expansion panafricaine.</div></div>
      <div class="tl-item"><div class="tl-year">2022</div><div class="tl-title">Partenariats institutionnels</div><div class="tl-desc">Signature de contrats avec UNICEF, GIZ et d'autres organisations internationales. La qualité de LYD reconnue au niveau mondial.</div></div>
      <div class="tl-item"><div class="tl-year">2023</div><div class="tl-title">10 ans, Nouveau chapitre</div><div class="tl-desc">Célébration d'une décennie. Lancement de Wakabi (tourisme) et restructuration vers un groupe panafricain. Les 10 prochaines années commencent.</div></div>
    </div>
  </div>
</section>

<!-- TEAM 
<section class="team-section" id="equipe">
  <div class="ts-inner">
    <div class="section-label">Notre équipe</div>
    <h2 class="section-title">Les Dreameurs qui<br/><em>font la magie</em></h2>
    <div class="team-grid reveal">
      <div class="team-card"><div class="team-photo">U</div><div class="team-info"><div class="team-name">Ulrich</div><div class="team-role">Fondateur & CEO</div><div class="team-dept">Direction Générale</div></div></div>
      <div class="team-card"><div class="team-photo">A</div><div class="team-info"><div class="team-name">Annah</div><div class="team-role">CM Senior</div><div class="team-dept">Community Management</div></div></div>
      <div class="team-card"><div class="team-photo">C</div><div class="team-info"><div class="team-name">Charlotte</div><div class="team-role">CM Senior</div><div class="team-dept">Community Management</div></div></div>
      <div class="team-card"><div class="team-photo">M</div><div class="team-info"><div class="team-name">Manuella</div><div class="team-role">CM Senior</div><div class="team-dept">Content & Strategy</div></div></div>
      <div class="team-card"><div class="team-photo">G</div><div class="team-info"><div class="team-name">Grace</div><div class="team-role">Commerciale</div><div class="team-dept">Business Development</div></div></div>
      <div class="team-card"><div class="team-photo">N</div><div class="team-info"><div class="team-name">Nabila</div><div class="team-role">Commerciale & TikTok</div><div class="team-dept">Sales & Content</div></div></div>
      <div class="team-card"><div class="team-photo">H</div><div class="team-info"><div class="team-name">Huget</div><div class="team-role">CM Junior</div><div class="team-dept">Community Management</div></div></div>
      <div class="team-card" style="background:var(--navy);border-color:var(--navy)">
        <div class="team-photo" style="background:rgba(201,168,76,0.1)">+</div>
        <div class="team-info"><div class="team-name" style="color:var(--gold)">Rejoignez-nous</div><div class="team-role" style="color:rgba(255,255,255,0.5)">Poste ouvert</div><div class="team-dept"><a href="<?= url('contact') ?>" style="color:var(--gold);text-decoration:underline;font-size:13px">Voir les offres</a></div></div>
      </div>
    </div>
  </div>
</section>
-->
<!-- CTA -->
<section style="padding:clamp(60px,8vw,100px) var(--gutter);background: #ffffff !important;border-top: 1px solid rgb(0 0 0) !important;text-align:center">
  <div class="reveal" style="max-width:600px;margin:0 auto">
    <div class="section-label" style="justify-content:center">Écrivons notre histoire ensemble</div>
    <h2 class="section-title" style="margin-bottom:16px">Vous êtes notre<br/><em>prochain succès</em></h2>
    <p class="section-sub" style="margin-bottom:32px">Chaque grande marque a eu besoin d'un partenaire qui la croit. Soyons ce partenaire pour vous.</p>
    <a href="<?= url('contact') ?>" class="btn btn-gold">Démarrons ensemble</a>
  </div>
</section>
