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
          <?php
          /**
           * Les DEUX magasins, côte à côte.
           *
           * L'application se télécharge aujourd'hui depuis Google Play ;
           * la version Apple est en cours, mais le bouton se montre
           * déjà — grisé, non cliquable, avec sa date d'ouverture à
           * venir. Un seul bouton posait la question « et sur iPhone ? »
           * sans y répondre ; un lien mort la répondrait mal.
           *
           * Tout est piloté par `APP_STORE_URL` (`app/vitrine.php`) :
           * le jour où l'adresse existe, ce bloc-ci ne change pas — le
           * `span` grisé devient le vrai lien, tout seul.
           */
          ?>
          <div class="store-btns" style="margin-top:26px">
            <a class="store-btn" href="<?= e(APPLICATION_URL) ?>" target="_blank" rel="noopener">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"
                   style="width:24px;height:24px;flex:none"><polygon points="6,3.5 20,12 6,20.5"></polygon></svg>
              <span class="store-btn-text"><small>Télécharger sur</small><strong>Google&nbsp;Play</strong></span>
            </a>
            <?php if (APP_STORE_URL !== ''): ?>
              <a class="store-btn" href="<?= e(APP_STORE_URL) ?>" target="_blank" rel="noopener">
            <?php else: ?>
              <span class="store-btn est-grise" aria-disabled="true" title="Bientôt disponible sur l’App Store">
            <?php endif; ?>
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"
                   style="width:24px;height:24px;flex:none"><path d="M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.042 3.536-.538 8.77 1.473 11.641.975 1.402 2.135 2.973 3.663 2.916 1.468-.058 2.023-.944 3.799-.944 1.757 0 2.27.944 3.8.915 1.569-.027 2.563-1.42 3.518-2.828 1.114-1.624 1.573-3.198 1.598-3.282-.035-.016-3.086-1.186-3.117-4.666-.024-2.93 2.396-4.336 2.509-4.407-1.375-2.008-3.49-2.245-4.224-2.278-1.903-.145-3.499 1.03-4.398 1.03zM15.63 3.905c.805-.975 1.349-2.332 1.199-3.685-1.161.047-2.567.774-3.402 1.748-.745.86-1.397 2.238-1.222 3.556 1.294.101 2.618-.644 3.425-1.619z"></path></svg>
              <span class="store-btn-text"><small><?php if (APP_STORE_URL !== ''): ?>Télécharger sur l’<?php else: ?>Bientôt sur l’<?php endif; ?></small><strong>App&nbsp;Store</strong></span>
            <?php if (APP_STORE_URL !== ''): ?>
              </a>
            <?php else: ?>
              </span>
            <?php endif; ?>
          </div>
          <?php if (APP_STORE_URL === ''): ?>
            <p style="margin-top:8px;font-size:12px;color:var(--text3);">La version iPhone arrive bientôt.</p>
          <?php endif; ?>
        </div>
        <div class="phones-wrap fade-up fade-up-d2">
          <div class="phone-side phone-left"><img src="https://admin.wakabileguide.com/wp-content/uploads/2026/05/MOKE-2.png"
            alt="L’écran des bons plans, dans l’application Wakabi" width="1080" height="2160"
            loading="lazy" decoding="async" referrerpolicy="no-referrer" style="height:360px;"></div>
          <div class="phone-main"><img src="https://admin.wakabileguide.com/wp-content/uploads/2026/05/MOKE.png"
            alt="L’accueil de l’application Wakabi sur un téléphone" width="1080" height="2160"
            loading="lazy" decoding="async" referrerpolicy="no-referrer" style="height:420px;"></div>
          <div class="phone-side phone-right"><img src="https://admin.wakabileguide.com/wp-content/uploads/2026/05/mockup-4tel-1024x1024-1.png"
            alt="L’application Wakabi sur quatre téléphones" width="1024" height="1024"
            loading="lazy" decoding="async" referrerpolicy="no-referrer" style="height:360px;"></div>
        </div>
      </div>
    </div>
  </section>
  <section class="section" style="background:var(--bg2);">
    <div class="container">
      <div style="text-align:center;margin-bottom:30px;" class="fade-up">
        <h2 class="headline">4 niveaux <span>d'explorateur</span></h2>
      </div>
      <div class="features-grid fade-up">
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
      <div style="text-align:center;margin-bottom:30px;" class="fade-up">
        <div class="tag">Fonctionnalités</div>
        <h2 class="headline">Tout ce dont tu as <span>besoin</span></h2>
      </div>
      <div class="values-grid fade-up">
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
