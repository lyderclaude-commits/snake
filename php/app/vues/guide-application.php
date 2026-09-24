<?php
/**
 * L’Application Wakabi — page venue de wakabileguide.com.
 *
 * Le balisage est celui du site d'origine, à trois choses près, toutes
 * nécessaires à la fusion :
 *
 *   · la navigation par script (`onclick="navigate(…)"`) est devenue de
 *     vrais liens, qui s'ouvrent dans un onglet, se copient et se
 *     partagent — ce qu'un `onclick` ne sait pas faire ;
 *   · les icônes chargées chez un tiers sont dessinées ici
 *     (`icone_guide()`, `drapeau()`), pour ne pas payer trente-quatre
 *     allers-retours par page sur une connexion 3G ;
 *   · l'adresse du magasin d'applications passe par `APPLICATION_URL`.
 *
 * La mise en page vient de `public/guide.css`, servie à cette page SEULE
 * — jamais en même temps que celle de Boost. Voir `app/vitrine.php`.
 */
?>
<div class="page-header">
    <div class="container"><div class="page-header-inner">
      <div class="breadcrumb"><a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">›</span><span>L'Application</span></div>
      <h1>L'Application Wakabi</h1>
      <p>Tout ce qu'il faut savoir sur l'expérience utilisateur, les fonctionnalités et la technologie derrière Wakabi.</p>
    </div></div>
  </div>
  <section class="section">
    <div class="container">
      <div class="app-showcase">
        <div class="fade-up">
          <div class="tag">L'expérience utilisateur</div>
          <h2 class="headline">Simple. Rapide. <span>Addictif.</span></h2>
          <p style="color:var(--text2);margin-bottom:16px;line-height:1.8;">Wakabi a été conçue pour la réalité africaine : mobile first, légère, optimisée pour les connexions 3G et les smartphones d'entrée de gamme. En quelques secondes, tu sais où aller, ce qui se passe et combien tu vas économiser. Ton prochain bon coin est à portée de doigt.</p>
          <div class="feature-pills">
            <span class="pill"><span class="pill-dot"></span>Géolocalisation</span>
            <span class="pill"><span class="pill-dot"></span>Filtres intelligents</span>
            <span class="pill"><span class="pill-dot"></span>Agenda events</span>
            <span class="pill"><span class="pill-dot"></span>QR Code</span>
            <span class="pill"><span class="pill-dot"></span>Notifications live</span>
            <span class="pill"><span class="pill-dot"></span>Avis communauté</span>
            <span class="pill"><span class="pill-dot"></span>Mode hors ligne</span>
          </div>
          <a href="<?= e(APPLICATION_URL) ?>" target="_blank" class="btn btn-primary btn-lg">Télécharger gratuitement</a>
        </div>
        <div class="phones-wrap fade-up fade-up-d2">
          <div class="phone-side phone-left"><img src="https://admin.wakabileguide.com/wp-content/uploads/2026/05/MOKE-2.png" alt="App screen 2" loading="lazy" style="height:360px;"></div>
          <div class="phone-main"><img src="https://admin.wakabileguide.com/wp-content/uploads/2026/05/MOKE.png" alt="App screen main" loading="lazy" style="height:420px;"></div>
          <div class="phone-side phone-right"><img src="https://admin.wakabileguide.com/wp-content/uploads/2026/05/mockup-4tel-1024x1024-1.png" alt="App mockup" loading="lazy" style="height:360px;"></div>
        </div>
      </div>
    </div>
  </section>
  <section class="section" style="background:var(--bg2);">
    <div class="container">
      <div style="text-align:center;margin-bottom:50px;" class="fade-up">
        <h2 class="headline">4 niveaux <span>d'explorateur</span></h2>
      </div>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;" class="fade-up">
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--r-xl);padding:28px;text-align:center;">
          <div style="font-size:36px;margin-bottom:12px;"><?= icone_guide('compass', 32) ?></div>
          <div style="font-family:var(--font-display);font-size:17px;font-weight:700;margin-bottom:6px;">Découvreur</div>
          <div style="font-size:13px;color:var(--text3);margin-bottom:14px;">0 – 200 Points</div>
          <div style="font-size:13px;color:var(--text2);">Accès aux offres basiques.</div>
        </div>
        <div style="background:var(--bg);border:2px solid var(--blue-300);border-radius:var(--r-xl);padding:28px;text-align:center;">
          <div style="font-size:36px;margin-bottom:12px;"><?= icone_guide('search', 32) ?></div>
          <div style="font-family:var(--font-display);font-size:17px;font-weight:700;margin-bottom:6px;color:var(--primary);">Explorateur</div>
          <div style="font-size:13px;color:var(--text3);margin-bottom:14px;">201 – 500 Points</div>
          <div style="font-size:13px;color:var(--text2);">Réductions améliorées. Accès prioritaire events.</div>
        </div>
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--r-xl);padding:28px;text-align:center;">
          <div style="font-size:36px;margin-bottom:12px;"><?= icone_guide('user', 32) ?></div>
          <div style="font-family:var(--font-display);font-size:17px;font-weight:700;margin-bottom:6px;">Insider</div>
          <div style="font-size:13px;color:var(--text3);margin-bottom:14px;">501 – 1 000 Points</div>
          <div style="font-size:13px;color:var(--text2);">Accès VIP, invitations exclusives, cashback.</div>
        </div>
        <div style="background:linear-gradient(135deg,var(--blue-800),var(--blue-600));border-radius:var(--r-xl);padding:28px;text-align:center;color:#fff;">
          <div style="font-size:36px;margin-bottom:12px;"><?= icone_guide('recompense', 32) ?></div>
          <div style="font-family:var(--font-display);font-size:17px;font-weight:700;margin-bottom:6px;">Legend</div>
          <div style="font-size:13px;opacity:0.6;margin-bottom:14px;">1 000+ Points</div>
          <div style="font-size:13px;opacity:0.85;">Tous les avantages + expériences uniques réservées.</div>
        </div>
      </div>
    </div>
  </section>
  <section class="section" style="background:var(--bg2);">
    <div class="container">
      <div style="text-align:center;margin-bottom:54px;" class="fade-up">
        <div class="tag">Fonctionnalités</div>
        <h2 class="headline">Tout ce dont tu as <span>besoin</span></h2>
      </div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;" class="fade-up">
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--r-xl);padding:28px;">
          <div style="font-size:32px;margin-bottom:14px;"><?= icone_guide('map-marker', 32) ?></div>
          <div style="font-family:var(--font-display);font-size:17px;font-weight:700;margin-bottom:8px;">Carte interactive</div>
          <p style="font-size:13px;color:var(--text2);line-height:1.7;">Visualise tous les partenaires autour de toi en temps réel. Filtre par catégorie, note ou distance.</p>
        </div>
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--r-xl);padding:28px;">
          <div style="font-size:32px;margin-bottom:14px;"><?= icone_guide('calendar', 32) ?></div>
          <div style="font-family:var(--font-display);font-size:17px;font-weight:700;margin-bottom:8px;">Agenda des events</div>
          <p style="font-size:13px;color:var(--text2);line-height:1.7;">Tous les événements à venir dans ta ville. Rappels automatiques, partage avec amis.</p>
        </div>
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--r-xl);padding:28px;">
          <div style="font-size:32px;margin-bottom:14px;"><?= icone_guide('ticket', 32) ?></div>
          <div style="font-family:var(--font-display);font-size:17px;font-weight:700;margin-bottom:8px;">Billetterie intégrée</div>
          <p style="font-size:13px;color:var(--text2);line-height:1.7;">Achète tes billets directement dans l'app. Bientôt disponible pour les concerts et événements.</p>
        </div>
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--r-xl);padding:28px;">
          <div style="font-size:32px;margin-bottom:14px;"><?= icone_guide('crowd', 32) ?></div>
          <div style="font-family:var(--font-display);font-size:17px;font-weight:700;margin-bottom:8px;">Avis communauté</div>
          <p style="font-size:13px;color:var(--text2);line-height:1.7;">Notes et avis vérifiés par la communauté Wakabi. Fais confiance à tes pairs.</p>
        </div>
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--r-xl);padding:28px;">
          <div style="font-size:32px;margin-bottom:14px;"><?= icone_guide('push-notifications', 32) ?></div>
          <div style="font-family:var(--font-display);font-size:17px;font-weight:700;margin-bottom:8px;">Notifications push</div>
          <p style="font-size:13px;color:var(--text2);line-height:1.7;">Alertes personnalisées pour les nouveaux bons plans, événements à venir et offres flash.</p>
        </div>
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--r-xl);padding:28px;">
          <div style="font-size:32px;margin-bottom:14px;"><?= icone_guide('assistance', 32) ?></div>
          <div style="font-family:var(--font-display);font-size:17px;font-weight:700;margin-bottom:8px;">Assistance </div>
          <p style="font-size:13px;color:var(--text2);line-height:1.7;">Une assistante plein temps avec notre equipe qui connaît ta ville, tes goûts et planifie tes soirées avec toi.</p>
        </div>
      </div>
    </div>
  </section>
  <!-- Footer inclus via JS -->
</div>


<!-- ================================================================
     PAGE : KORI
     ================================================================ -->
<template id="footerTemplate">
