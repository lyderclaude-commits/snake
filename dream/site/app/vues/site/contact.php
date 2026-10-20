<?php
/** Contact — Demandez votre Devis Gratuit | Live Y Dream Lomé, Togo — converti depuis contact.html. */
$titre = 'Contact — Demandez votre Devis Gratuit | Live Y Dream Lomé, Togo';
$description = 'Contactez Live Y Dream pour votre projet de communication. Audit gratuit, réponse sous 24h. Tél: +228 70 08 77 77. Lomé, Togo.';
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
.page-header {
    
}
.cd-icon {
    width: 48px;
    height: 48px;
    background: #ffffff00 !important;
    border: 0px !important;
}

/* ===== EDIT ===== */

.contact-layout { padding: clamp(60px,8vw,100px) var(--gutter); background: var(--bg); }
.cl-inner { max-width: var(--max-w); margin: 0 auto; display: grid; grid-template-columns: 5fr 7fr; gap: 80px; }
.contact-info-col {}
.ci-intro { font-family: var(--font-serif); font-size: 16px; color: var(--text-soft); line-height: 1.8; margin-bottom: 40px; }
.contact-detail { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 24px; }
.cd-icon { width: 48px; height: 48px; background: var(--gold-bg); border: 1px solid var(--border-gold); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 20px; }
.cd-text strong { display: block; font-size: 13px; font-weight: 700; color: var(--navy); margin-bottom: 3px; }
.cd-text span { font-size: 14px; color: var(--text-soft); }
.cd-text a { color: var(--text-soft); transition: color var(--transition); }
.cd-text a:hover { color: var(--gold-dark); }

.hours-table { background: var(--bg-soft); border: 1px solid var(--border); padding: 24px; margin-top: 32px; }
.ht-title { font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--navy); margin-bottom: 16px; }
.ht-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 14px; }
.ht-row:last-child { border-bottom: none; }
.ht-day { color: var(--text-mid); }
.ht-time { color: var(--navy); font-weight: 600; }

.contact-form-col {}
.contact-form { background: var(--bg-card); border: 1px solid var(--border); padding: 48px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-submit-btn { width: 100%; padding: 18px; background: var(--navy); color: #fff; border: none; font-size: 14px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; cursor: pointer; font-family: var(--font-body); transition: all var(--transition); display: flex; align-items: center; justify-content: center; gap: 10px; }
.form-submit-btn:hover { background: var(--gold); color: var(--navy); }
.form-note { font-size: 12px; color: var(--text-soft); text-align: center; margin-top: 12px; }

.wa-cta { background: var(--navy); padding: 48px var(--gutter); text-align: center; }
.wa-cta-inner { max-width: 640px; margin: 0 auto; }
.wa-cta h3 { font-family: var(--font-serif); font-size: 28px; color: #fff; margin-bottom: 12px; }
.wa-cta p { color: rgba(255,255,255,0.6); font-size: 15px; margin-bottom: 28px; }
.wa-cta-btn { display: inline-flex; align-items: center; gap: 12px; background: #25D366; color: #fff; padding: 18px 40px; font-weight: 700; font-size: 15px; letter-spacing: 1px; text-transform: uppercase; text-decoration: none; transition: all var(--transition); }
.wa-cta-btn:hover { background: #1da851; transform: translateY(-2px); box-shadow: 0 8px 32px rgba(37,211,102,0.4); }
.online-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(37,211,102,0.1); border: 1px solid rgba(37,211,102,0.3); padding: 8px 16px; border-radius: 100px; font-size: 12px; color: #25D366; font-weight: 600; margin-bottom: 24px; }
.online-dot { width: 6px; height: 6px; background: #25D366; border-radius: 50%; animation: pulse 2s infinite; }

/* Success state */
.form-success { display: none; text-align: center; padding: 40px; }
.form-success.show { display: block; }
.fs-icon { font-size: 56px; margin-bottom: 20px; }
.fs-title { font-family: var(--font-serif); font-size: 28px; color: var(--navy); margin-bottom: 10px; }
.fs-sub { color: var(--text-soft); font-size: 16px; }

@media (max-width:1024px) { .cl-inner { grid-template-columns: 1fr; gap: 48px; } }
@media (max-width:768px) { .form-row { grid-template-columns: 1fr; } .contact-form { padding: 28px; } }

/* ============================================================
   ===== RESPONSIVE OVERRIDES (ajout uniquement) ==============
   Aucune règle existante n'est supprimée ou modifiée.
   Améliore la lisibilité tablette + mobile pour contact.html
   ============================================================ */

/* === TABLETTE & DESSOUS (≤ 1024px) === */
@media (max-width: 1024px) {
  /* Page header */
  .page-header {
    padding-left: 24px !important;
    padding-right: 24px !important;
  }
  .page-header-inner { padding: 80px 0 60px !important; }

  /* WA CTA */
  .wa-cta {
    padding-left: 24px !important;
    padding-right: 24px !important;
    padding-top: 40px !important;
    padding-bottom: 40px !important;
  }

  /* Contact layout */
  .contact-layout {
    padding-left: 24px !important;
    padding-right: 24px !important;
  }
  .cl-inner { gap: 36px !important; }
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

  /* WA CTA */
  .wa-cta {
    padding-left: 18px !important;
    padding-right: 18px !important;
    padding-top: 36px !important;
    padding-bottom: 36px !important;
  }
  .wa-cta h3 {
    font-size: clamp(20px, 5.5vw, 24px) !important;
    line-height: 1.25 !important;
    margin-bottom: 10px !important;
  }
  .wa-cta p {
    font-size: 13.5px !important;
    line-height: 1.6 !important;
    margin-bottom: 22px !important;
  }
  .online-badge {
    font-size: 11px !important;
    padding: 6px 14px !important;
    margin-bottom: 18px !important;
  }
  .wa-cta-btn {
    width: 100% !important;
    justify-content: center;
    padding: 16px 24px !important;
    font-size: 13px !important;
    letter-spacing: 0.8px !important;
    box-sizing: border-box;
  }
  .wa-cta-btn svg { width: 18px !important; height: 18px !important; flex-shrink: 0; }

  /* CONTACT LAYOUT : padding général */
  .contact-layout {
    padding-top: 48px !important;
    padding-bottom: 48px !important;
    padding-left: 18px !important;
    padding-right: 18px !important;
  }
  .cl-inner { gap: 32px !important; }

  /* SECTION TITLES */
  .section-title {
    font-size: clamp(26px, 7vw, 32px) !important;
    line-height: 1.15 !important;
  }
  .section-label { font-size: 11px !important; }

  /* CONTACT INFO */
  .ci-intro {
    font-size: 14.5px !important;
    line-height: 1.65 !important;
    margin-bottom: 28px !important;
  }
  .contact-detail {
    gap: 12px !important;
    margin-bottom: 18px !important;
  }
  .cd-icon {
    width: 40px !important;
    height: 40px !important;
  }
  .cd-icon img {
    width: 32px !important;
    height: 32px !important;
  }
  .cd-text strong { font-size: 12px !important; margin-bottom: 2px !important; }
  .cd-text span { font-size: 13.5px !important; line-height: 1.5 !important; }
  .cd-text a { font-size: 13.5px !important; }

  /* HOURS TABLE */
  .hours-table {
    padding: 18px !important;
    margin-top: 24px !important;
  }
  .ht-title { font-size: 10.5px !important; margin-bottom: 12px !important; }
  .ht-row { padding: 7px 0 !important; font-size: 13px !important; }

  /* CONTACT FORM */
  .contact-form { padding: 22px !important; }
  .contact-form > h3 {
    font-size: 20px !important;
    margin-bottom: 6px !important;
  }
  .contact-form > p {
    font-size: 12.5px !important;
    margin-bottom: 22px !important;
    line-height: 1.5 !important;
  }
  .form-row { gap: 12px !important; }

  /* Submit button */
  .form-submit-btn {
    padding: 15px !important;
    font-size: 12.5px !important;
    letter-spacing: 1.5px !important;
  }
  .form-note {
    font-size: 11px !important;
    margin-top: 10px !important;
    line-height: 1.4 !important;
  }

  /* Form success */
  .form-success { padding: 28px 16px !important; }
  .fs-icon { font-size: 44px !important; margin-bottom: 14px !important; }
  .fs-title { font-size: 22px !important; }
  .fs-sub { font-size: 14px !important; line-height: 1.55 !important; }
}

/* === MOBILE PETIT (≤ 480px) === */
@media (max-width: 480px) {
  .page-header { padding-left: 16px !important; padding-right: 16px !important; }
  .page-header-inner { padding: 44px 0 32px !important; }
  .page-header .section-title { font-size: clamp(24px, 7.5vw, 30px) !important; }

  /* WA CTA */
  .wa-cta {
    padding-left: 14px !important;
    padding-right: 14px !important;
    padding-top: 32px !important;
    padding-bottom: 32px !important;
  }
  .wa-cta h3 { font-size: clamp(18px, 6vw, 22px) !important; }
  .wa-cta p { font-size: 13px !important; margin-bottom: 18px !important; }
  .wa-cta-btn {
    padding: 14px 18px !important;
    font-size: 12px !important;
    gap: 8px !important;
  }
  .wa-cta-btn svg { width: 16px !important; height: 16px !important; }

  /* CONTACT LAYOUT */
  .contact-layout {
    padding-left: 14px !important;
    padding-right: 14px !important;
    padding-top: 40px !important;
    padding-bottom: 40px !important;
  }
  .section-title { font-size: clamp(22px, 7vw, 28px) !important; }

  /* Contact details */
  .cd-icon { width: 36px !important; height: 36px !important; }
  .cd-icon img { width: 28px !important; height: 28px !important; }
  .cd-text strong { font-size: 11.5px !important; }
  .cd-text span, .cd-text a { font-size: 13px !important; }

  /* Hours table */
  .hours-table { padding: 16px !important; }
  .ht-row { font-size: 12.5px !important; }

  /* Form */
  .contact-form { padding: 18px !important; }
  .contact-form > h3 { font-size: 18px !important; }
}

/* === ULTRA PETIT (≤ 360px) === */
@media (max-width: 360px) {
  .page-header .section-title { font-size: 22px !important; }
  .section-title { font-size: 22px !important; }
  .wa-cta h3 { font-size: 18px !important; }
  .wa-cta-btn { font-size: 11.5px !important; padding: 13px 14px !important; }
  .cd-icon { width: 34px !important; height: 34px !important; }
  .cd-icon img { width: 26px !important; height: 26px !important; }
  .ht-row { font-size: 12px !important; }
  .contact-form { padding: 16px !important; }
  .form-submit-btn { padding: 14px !important; font-size: 12px !important; }
}
.form-erreur { background: #FEF2F2; border: 1px solid #FCA5A5; color: #991B1B; padding: 12px 16px; font-size: 14px; margin-bottom: 20px; }
.form-piege { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
</style>
<?php $tete = ob_get_clean(); ?>
<header class="page-header">
  <div class="page-header-grid"></div>
  <div class="page-header-inner">
    <div class="section-label" style="color: #ffffff !important;">Travaillons ensemble</div>
    <h1 class="section-title">Parlons de votre projet</h1>
    <div class="page-header-breadcrumb"><a href="<?= url('') ?>">Accueil</a><span>/</span><span>Contact</span></div>
  </div>
</header>

<!-- WA FAST CTA -->
<div class="wa-cta">
  <div class="wa-cta-inner">
    <h3>Réponse garantie en moins d'1 heure</h3>
    <p>Pour une réponse immédiate, contactez-nous directement sur WhatsApp. Notre équipe est disponible 7j/7.</p>
    <a href="<?= e(wa_lien(t('Bonjour Live Y Dream, je souhaite discuter d\'un projet.'))) ?>" class="wa-cta-btn" target="_blank" rel="noopener">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
      Écrire sur WhatsApp
    </a>
  </div>
</div>

<!-- CONTACT LAYOUT -->
<div class="contact-layout">
  <div class="cl-inner">
    <div class="reveal-l">
      <div class="section-label">Nous trouver</div>
      <h2 class="section-title" style="font-size:clamp(28px,4vw,44px)">Nos coordonnées</h2>
      <p class="ci-intro">Dites-nous ce que vous voulez accomplir. Nous vous répondrons avec un plan d'action concret et un devis transparent sous 24h.</p>
      <div class="contact-detail">
        <div class="cd-icon"><?= icone('lieu') ?></div>
        <div class="cd-text"><strong>Notre bureau</strong><span><?= e(reglage('adresse')) ?><br/><?= e(reglage_l('adresse2')) ?></span></div>
      </div>
      <div class="contact-detail">
        <div class="cd-icon"><?= icone('telephone') ?></div>
        <div class="cd-text"><strong>Téléphone & WhatsApp</strong><span><a href="<?= e(tel_lien(reglage('telephone'))) ?>"><?= e(reglage('telephone')) ?></a><?php if (reglage('telephone2') !== ''): ?><br/><a href="<?= e(tel_lien(reglage('telephone2'))) ?>"><?= e(reglage('telephone2')) ?></a><?php endif; ?></span></div>
      </div>
      <div class="contact-detail">
        <div class="cd-icon"><?= icone('courriel') ?></div>
        <div class="cd-text"><strong>Email</strong><span><a href="mailto:<?= e(reglage('email')) ?>"><?= e(reglage('email')) ?></a><?php if (reglage('email2') !== ''): ?><br/><a href="mailto:<?= e(reglage('email2')) ?>"><?= e(reglage('email2')) ?></a><?php endif; ?></span></div>
      </div>
      <div class="hours-table">
        <div class="ht-title">Horaires d'ouverture</div>
        <div class="ht-row"><span class="ht-day">Lundi — Vendredi</span><span class="ht-time"><?= e(reglage_l('horaires_semaine')) ?></span></div>
        <div class="ht-row"><span class="ht-day">Samedi</span><span class="ht-time"><?= e(reglage_l('horaires_samedi')) ?></span></div>
        <div class="ht-row"><span class="ht-day">Dimanche</span><span class="ht-time"><?= e(reglage_l('horaires_dimanche')) ?></span></div>
      </div>
    </div>
    <div class="reveal-r">
      <div class="contact-form">
        <h3 style="font-family:var(--font-serif);font-size:24px;color:var(--navy);margin-bottom:8px">Envoyer un message</h3>
        <p style="font-size:13px;color:var(--text-soft);margin-bottom:32px">Remplissez le formulaire et nous vous répondons sous 24h.</p>
        <?php if ($erreur): ?><div class="form-erreur" role="alert"><?= e($erreur) ?></div><?php endif; ?>
        <form id="contactForm" method="post" action="<?= url('contact') ?>"<?= $envoye ? ' style="display:none"' : '' ?>>
          <?= csrf_champ() ?>
          <div class="form-piege" aria-hidden="true"><label>Laissez ce champ vide<input type="text" name="site_web" tabindex="-1" autocomplete="off"/></label></div>
          <div class="form-row">
            <div class="form-group"><label class="form-label" for="c-nom">Prénom & Nom *</label><input id="c-nom" name="nom" type="text" class="form-input" placeholder="Jean Kouassi" required maxlength="120" value="<?= e($v['nom'] ?? '') ?>"/></div>
            <div class="form-group"><label class="form-label" for="c-ent">Entreprise</label><input id="c-ent" name="entreprise" type="text" class="form-input" placeholder="Votre entreprise" maxlength="120" value="<?= e($v['entreprise'] ?? '') ?>"/></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label" for="c-mail">Email *</label><input id="c-mail" name="email" type="email" class="form-input" placeholder="vous@entreprise.com" required maxlength="160" value="<?= e($v['email'] ?? '') ?>"/></div>
            <div class="form-group"><label class="form-label" for="c-wa">WhatsApp *</label><input id="c-wa" name="whatsapp" type="tel" class="form-input" placeholder="+228 XX XX XX XX" required maxlength="40" value="<?= e($v['whatsapp'] ?? '') ?>"/></div>
          </div>
          <div class="form-group">
            <label class="form-label" for="c-besoin">Votre besoin</label>
            <select id="c-besoin" name="besoin" class="form-input form-select">
              <option value="">Choisissez un service</option>
              <?php foreach (BESOINS as $o): ?><option<?= ($v['besoin'] ?? '') === $o ? ' selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="c-budget">Budget estimé</label>
            <select id="c-budget" name="budget" class="form-input form-select">
              <option value="">Fourchette de budget</option>
              <?php foreach (BUDGETS as $o): ?><option<?= ($v['budget'] ?? '') === $o ? ' selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="c-msg">Décrivez votre projet</label>
            <textarea id="c-msg" name="message" class="form-input form-textarea" maxlength="5000" placeholder="Parlez-nous de votre entreprise, vos objectifs et ce que vous souhaitez accomplir…"><?= e($v['message'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="form-submit-btn"><span>Envoyer ma demande</span></button>
          <p class="form-note">🔒 Vos informations restent strictement confidentielles. Réponse garantie sous 24h.</p>
        </form>
        <div class="form-success<?= $envoye ? ' show' : '' ?>" id="formSuccess" role="status">
          <div class="fs-icon">✅</div>
          <div class="fs-title">Message envoyé !</div>
          <p class="fs-sub">Nous vous répondrons sous 24h. En attendant, vous pouvez nous joindre directement sur WhatsApp.</p>
          <a href="<?= e(wa_lien()) ?>" class="btn btn-wa" style="margin-top:20px" target="_blank">WhatsApp direct →</a>
        </div>
      </div>
    </div>
  </div>
</div>
