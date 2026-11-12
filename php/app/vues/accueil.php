<?php
/**
 * L'accueil, repris de la page d'accueil de wakabileguide.com.
 *
 * La page du guide est devenue la porte du site fusionné : qui arrive
 * cherche quoi sortir ce soir, pas un logiciel. Boost se présente donc
 * au MILIEU de ce parcours — la section qui porte son nom, posée après
 * les trois étapes — au lieu d'ouvrir la page.
 *
 * Le balisage est celui de la maquette validée, à trois choses près,
 * toutes nécessaires à la fusion :
 *
 *   - la navigation par script (`onclick="navigate(.)"`) est devenue
 *     de vrais liens, qui s'ouvrent, se copient et se partagent ;
 *   - les icônes chargées chez un tiers sont dessinées ici
 *     (`icone_guide()`), comme sur les autres pages du guide ;
 *   - les adresses des magasins passent par `APPLICATION_URL` et
 *     `APP_STORE_URL`, écrites une fois dans `app/vitrine.php`.
 *
 * La mise en page vient de `public/guide.css`, servie à cette page et
 * aux autres pages du guide — jamais en même temps que celle de Boost.
 * Voir `app/vitrine.php` (`page_du_guide`).
 *
 * Le menu de navigation et le pied de page ne sont pas ici : ce sont
 * ceux du site, posés par le gabarit commun. L'invitation aux
 * notifications non plus — elle est rendue par le layout, et habillée
 * dans la feuille du guide.
 */
/**
 * Trois articles du blog, lus dans la base.
 *
 * La maquette portait trois cartes écrites en dur ; elles restent en
 * repli si la rédaction n'a encore rien publié — une section « Le
 * blog » vide est pire que pas de blog.
 */
$articles = blog_fusionne(1, 3)['liste'];
?>

<!-- ---------- le hero ---------- -->
<section class="hero">
  <div class="hero-bg-dots"></div>
  <div class="hero-blue-blob"></div>
  <div class="container">
    <div class="hero-inner">
      <div class="hero-content">
        <div class="hero-eyebrow"><div class="hero-dot"></div><span>En direct depuis <strong id="wk-geo">Lomé, Togo</strong></span></div>
        <h1 class="hero-title fade-up">
          Ton prochain<br>
          <span class="accent">bon coin</span><br>
          <span class="stroke">t'attend</span>
        </h1>
        <p class="hero-sub fade-up fade-up-d1">Wakabi transforme chaque sortie en expérience inoubliable. Restos, sorties, événements, hébergements tout ce qu'il faut sur ta ville, en une seule app.</p>
        <div class="store-btns fade-up fade-up-d2">
          <a href="<?= e(APPLICATION_URL) ?>" target="_blank" rel="noopener" class="store-btn">
            <span class="store-btn-icon"><svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20" aria-hidden="true"><path d="M4 3.2v17.6c0 .5.5.8.9.6l12.4-8.8c.4-.3.4-.9 0-1.2L4.9 2.6c-.4-.2-.9.1-.9.6Z"/></svg></span>
            <div class="store-btn-text"><small>Disponible sur</small><strong>Google Play</strong></div>
          </a>
          <?php if (APP_STORE_URL !== ''): ?>
            <a href="<?= e(APP_STORE_URL) ?>" target="_blank" rel="noopener" class="store-btn secondary">
              <span class="store-btn-icon"><svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20" aria-hidden="true"><path d="M16.2 12.6c0-2 1.6-3 1.7-3-.9-1.4-2.4-1.6-2.9-1.6-1.2-.1-2.4.7-3 .7-.6 0-1.6-.7-2.6-.7-1.3 0-2.6.8-3.3 2-1.4 2.4-.4 6 1 8 .7 1 1.5 2 2.5 2 1 0 1.4-.6 2.6-.6s1.5.6 2.6.6 1.7-.9 2.4-1.9c.7-1.1 1-2.2 1-2.2s-1.9-.8-2-3.3ZM14.4 6.3c.5-.7.9-1.6.8-2.6-.8 0-1.8.5-2.4 1.2-.5.6-1 1.6-.8 2.5.9.1 1.8-.4 2.4-1.1Z"/></svg></span>
              <div class="store-btn-text"><small>Disponible sur</small><strong>App Store</strong></div>
            </a>
          <?php else: ?>
            <div class="store-btn secondary" title="Bientôt disponible">
              <span class="store-btn-icon"><svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20" aria-hidden="true"><path d="M16.2 12.6c0-2 1.6-3 1.7-3-.9-1.4-2.4-1.6-2.9-1.6-1.2-.1-2.4.7-3 .7-.6 0-1.6-.7-2.6-.7-1.3 0-2.6.8-3.3 2-1.4 2.4-.4 6 1 8 .7 1 1.5 2 2.5 2 1 0 1.4-.6 2.6-.6s1.5.6 2.6.6 1.7-.9 2.4-1.9c.7-1.1 1-2.2 1-2.2s-1.9-.8-2-3.3ZM14.4 6.3c.5-.7.9-1.6.8-2.6-.8 0-1.8.5-2.4 1.2-.5.6-1 1.6-.8 2.5.9.1 1.8-.4 2.4-1.1Z"/></svg></span>
              <div class="store-btn-text"><small>Bientôt sur</small><strong>App Store</strong></div>
            </div>
          <?php endif; ?>
        </div>
        <div class="hero-trust fade-up fade-up-d3" style="margin-top:32px;">
          <div class="trust-item"><span class="trust-icon">⭐</span> 4.7 sur Google Play</div>
          <div class="trust-item" style="border-left:1px solid var(--border);padding-left:20px;"><span class="trust-icon">👥</span> 10 000+ utilisateurs</div>
          <div class="trust-item" style="border-left:1px solid var(--border);padding-left:20px;"><span class="trust-icon">🏪</span> 340+ partenaires</div>
        </div>
      </div>
      <div class="hero-visual fade-up fade-up-d2">
        <div class="hero-float-card hfc-left">
          <div class="hfc-label">Ce soir chez toi</div>
          <div class="hfc-val">12 <span>events</span></div>
          <div class="hfc-sub">Concerts &amp; Soirées</div>
        </div>
        <div class="hero-phone-wrap">
          <img src="https://admin.wakabileguide.com/wp-content/uploads/2026/05/MOKE-1.png" alt="L'application Wakabi sur un téléphone" loading="eager">
        </div>
        <div class="hero-float-card hfc-right">
          <div class="hfc-label">Bon plan</div>
          <div class="hfc-val"><span>-30%</span></div>
          <div class="hfc-sub">Chez nos partenaires</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ---------- le bandeau qui défile ---------- -->
<div class="marquee-bar">
  <div class="marquee-track">
    <span class="marquee-item"><span class="marquee-sep"></span><strong>Gastronomie</strong> - Les meilleures tables</span>
    <span class="marquee-item"><span class="marquee-sep"></span><strong>Événements</strong> - Concerts, soirées, festivals</span>
    <span class="marquee-item"><span class="marquee-sep"></span><strong>Hébergements</strong> - Hôtels &amp; maisons d'hôtes</span>
    <span class="marquee-item"><span class="marquee-sep"></span><strong>Carte Wakabi</strong> - Réductions exclusives</span>
    <span class="marquee-item"><span class="marquee-sep"></span><strong>Kori</strong> - Ta monnaie locale virtuelle</span>
    <span class="marquee-item"><span class="marquee-sep"></span><strong>6 pays</strong> - UEMOA en 24 mois</span>
    <span class="marquee-item"><span class="marquee-sep"></span><strong>Gastronomie</strong> - Les meilleures tables</span>
    <span class="marquee-item"><span class="marquee-sep"></span><strong>Événements</strong> - Concerts, soirées, festivals</span>
    <span class="marquee-item"><span class="marquee-sep"></span><strong>Hébergements</strong> - Hôtels &amp; maisons d'hôtes</span>
    <span class="marquee-item"><span class="marquee-sep"></span><strong>Carte Wakabi</strong> - Réductions exclusives</span>
    <span class="marquee-item"><span class="marquee-sep"></span><strong>Kori</strong> - Ta monnaie locale virtuelle</span>
    <span class="marquee-item"><span class="marquee-sep"></span><strong>6 pays</strong> - UEMOA en 24 mois</span>
  </div>
</div>

<!-- ---------- les chiffres ---------- -->
<?php
/**
 * Les chiffres de l'application, écrits en dur.
 *
 * Ce sont ceux de L'APPLICATION, pas ceux de Boost : dix mille personnes
 * qui sortent, et non dix mille organisateurs. Les deux premiers sont
 * des planchers — c'est ce que le « + » dit —, le troisième un objectif
 * annoncé. Ils ne s'animent pas au défilement : le guide n'anime rien,
 * et un chiffre qui compte à chaque visite annonce une mesure qui bouge
 * sans raison.
 */
?>
<section class="stats-row">
  <div class="container">
    <div class="stats-grid fade-up">
      <div class="stat-block"><div class="stat-num">10 000+</div><div class="stat-label">Utilisateurs actifs</div></div>
      <div class="stat-block"><div class="stat-num">2 340+</div><div class="stat-label">Partenaires référencés</div></div>
      <div class="stat-block"><div class="stat-num">6</div><div class="stat-label">Pays cibles UEMOA</div></div>
    </div>
  </div>
</section>

<!-- ---------- ce que l'app contient ---------- -->
<section class="section" id="features" style="background:var(--bg2);">
  <div class="container">
    <div style="text-align:center;margin-bottom:54px;" class="fade-up">
      <div class="tag">Ce que tu vas trouver</div>
      <h2 class="headline">Une app. <span>Un univers entier</span></h2>
      <p class="subhead" style="margin:14px auto 0;text-align:center;">Tout ce dont tu as besoin pour vivre ta ville à 100%.</p>
    </div>
    <div class="features-grid">
      <div class="feat-card fade-up"><div class="feat-ico-nu"><?= icone_guide('calendar', 32) ?></div><div class="feat-title">Sorties &amp; événements</div><div class="feat-desc">Concerts, soirées, festivals, expos culturelles. Ne rate plus jamais un moment qui te parle.</div><div class="feat-count">200+ <span>events/mois</span></div></div>
      <div class="feat-card fade-up fade-up-d1"><div class="feat-ico-nu"><?= icone_guide('cocktail', 32) ?></div><div class="feat-title">Gastronomie &amp; Bars</div><div class="feat-desc">Saveurs locales, restaurants tendance, bars branchés et cocktails signature à découvrir.</div><div class="feat-count">650+ <span>adresses</span></div></div>
      <div class="feat-card fade-up fade-up-d2"><div class="feat-ico-nu"><?= icone_guide('hotel', 32) ?></div><div class="feat-title">Hébergements</div><div class="feat-desc">Hôtels, maisons d'hôtes et logements cosy où confort, style et ambiance se rencontrent.</div><div class="feat-count">295+ <span>établissements</span></div></div>
      <div class="feat-card fade-up fade-up-d3"><div class="feat-ico-nu"><?= icone_guide('monde', 32) ?></div><div class="feat-title">Culture &amp; Découvertes</div><div class="feat-desc">Traditions, street culture, coins cachés à explorer pour vivre ta ville autrement.</div><div class="feat-count">100+ <span>lieux uniques</span></div></div>
    </div>
  </div>
</section>

<!-- ---------- trois étapes ---------- -->
<section class="section">
  <div class="container">
    <div style="text-align:center;margin-bottom:54px;" class="fade-up">
      <div class="tag">Simple comme bonjour</div>
      <h2 class="headline">En <span>3 étapes,</span> c'est plié</h2>
    </div>
    <div class="steps-grid">
      <div class="step-card fade-up"><div class="step-num">1</div><div class="step-title">Télécharge l'app</div><div class="step-desc">Gratuite, sans engagement. 2 minutes pour créer ton profil et activer ton compte Wakabi.</div></div>
      <div class="step-card fade-up fade-up-d1"><div class="step-num">2</div><div class="step-title">Explore ta ville</div><div class="step-desc">Restos, sorties, événements filtrés selon tes goûts, ta localisation et ton budget.</div></div>
      <div class="step-card fade-up fade-up-d2"><div class="step-num">3</div><div class="step-title">Profite &amp; économise</div><div class="step-desc">Présente toi, accumule des points et débloque des réductions exclusives.</div></div>
    </div>
  </div>
</section>

<!-- ---------- la section Boost ---------- -->
<?php
/**
 * Boost, au milieu du parcours du guide.
 *
 * Ici change la personne : le guide tutoie — il parle à qui sort —,
 * Boost vouvoie — il parle à qui organise. La section reprend donc la
 * voix de la vitrine, et ses deux boutons mènent aux portes du
 * produit : le Studio, puis les offres.
 */
?>
<section class="section boost-sec">
  <div class="container">
    <div class="boost-grid">
      <div class="boost-content">
        <div class="boost-eyebrow"><b>NOUVEAU</b><span>La suite marketing événementielle de Wakabi</span></div>
        <h2 class="headline">Faites de chaque invitation,<br><span style="color:var(--primary);">une présence</span></h2>
        <p class="boost-p">Badges viraux, WhatsApp, Push, Telegram, liens traçables. <strong>Wakabi Boost</strong> transforme chaque contact en présence réelle, et chaque présence en client fidèle grâce au <strong>QR Code qui rapporte</strong>.</p>
        <div class="boost-ctas">
          <a class="boost-btn-primary" href="<?= e(url('?p=creer')) ?>">Créer mon badge gratuit <span aria-hidden="true">&#8594;</span></a>
          <a class="boost-btn-ghost" href="<?= e(url('?p=tarifs')) ?>">Voir les offres</a>
        </div>
        <div class="boost-checks">
          <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" width="15" height="15" aria-hidden="true"><polyline points="4 12.5 9.5 18 20 6.5"></polyline></svg> Sans carte bancaire</span>
          <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" width="15" height="15" aria-hidden="true"><polyline points="4 12.5 9.5 18 20 6.5"></polyline></svg> Gratuit pour démarrer</span>
          <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" width="15" height="15" aria-hidden="true"><polyline points="4 12.5 9.5 18 20 6.5"></polyline></svg> Prêt en 2 minutes</span>
        </div>
      </div>
      <div class="boost-visual">
        <div class="boost-float boost-float-l"><b>98&#160;%</b><span>taux de lecture</span></div>
        <div class="boost-phone">
          <div class="boost-phone-in">
            <div class="boost-phone-head"><span class="boost-phone-logo">W</span><span class="boost-phone-bars"><i></i><i></i><i></i></span></div>
            <div class="boost-phone-t"><b>J'y serai à Lomé</b><small>Festival des Divinités Noires</small></div>
            <div class="boost-badge">
              <div class="boost-badge-ava"><i></i></div>
              <div class="boost-badge-name">J'Y SERAI</div>
              <div class="boost-badge-qr"></div>
            </div>
            <div class="boost-photo"><small>1. Votre photo</small><span>Changer de photo</span></div>
          </div>
        </div>
        <div class="boost-float boost-float-r"><b>+40&#160;%</b><span>présence réelle</span></div>
      </div>
    </div>
  </div>
</section>

<!-- ---------- nos solutions ---------- -->
<?php
/**
 * Les six canaux de Boost, dans les cartes du guide.
 *
 * Les icônes sont celles de Boost (`icone()`, app/icones.php) — ce
 * sont les mêmes que sur la vitrine, dessinées ici et non chargées
 * chez un tiers. Trois cartes portent un bouton, et ce sont les seules
 * portes : les trois autres décrivent des canaux qui se branchent
 * depuis un compte.
 */
?>
<section class="section" style="background:var(--bg);">
  <div class="container">
    <div style="text-align:center;margin-bottom:54px;">
      <div class="tag">Nos solutions</div>
      <h2 class="headline">Nos outils vous permettent<br><span style="color:var(--primary);">de remplir vos événements</span></h2>
      <p class="subhead" style="margin:14px auto 0;text-align:center;">Là où vos invités sont déjà, pas là où vous espérez qu'ils aillent.</p>
    </div>
    <div class="features-grid trois">
      <div class="feat-card">
        <div class="feat-ico"><?= icone('cloche') ?></div>
        <div class="feat-title">Notifications Push</div>
        <div class="feat-desc">Notifiez vos abonnés directement sur leur navigateur, sans application à installer.</div>
        <div class="feat-note">Coût d'envoi : zéro</div>
      </div>
      <div class="feat-card">
        <div class="feat-ico"><?= icone('avion') ?></div>
        <div class="feat-title">Telegram</div>
        <div class="feat-desc">Créez des canaux de diffusion illimités et sécurisés. Idéal pour fédérer une communauté fidèle.</div>
        <div class="feat-note">Canaux illimités</div>
      </div>
      <div class="feat-card">
        <div class="feat-ico"><?= icone('message') ?></div>
        <div class="feat-title">WhatsApp &amp; Rappels</div>
        <div class="feat-desc">Invitations, rappels automatiques J-1 et H-2, chatbots. Vos messages lus à 98 %, jamais dans les spams.</div>
        <div class="feat-note">À partir de 1 FCFA/message</div>
      </div>
      <div class="feat-card feat-cta">
        <div class="feat-ico"><?= icone('lien') ?></div>
        <div class="feat-title">Liens courts</div>
        <div class="feat-desc">Une adresse courte à mettre sur une affiche, et le nombre de personnes qui l'ont réellement suivie.</div>
        <div class="feat-note">wkb.tg · wkb.bj · wkb.sn</div>
        <a class="bouton-solution" href="<?= e(url('?p=lien-court')) ?>">Raccourcir un lien</a>
      </div>
      <div class="feat-card feat-cta">
        <div class="feat-ico"><?= icone('studio') ?></div>
        <div class="feat-title">Badge « J'y serai »</div>
        <div class="feat-desc">Composez le badge de votre événement : déposez le vôtre, ou partez de zéro.</div>
        <div class="feat-note">Sans compte pour commencer</div>
        <a class="bouton-solution" href="<?= e(url('?p=creer')) ?>">Réaliser un badge</a>
      </div>
      <div class="feat-card feat-cta">
        <div class="feat-ico"><?= icone('coche') ?></div>
        <div class="feat-title">Générer un badge</div>
        <div class="feat-desc">Choisissez un badge publié, ajoutez votre photo, partagez. C'est ce que font vos invités.</div>
        <div class="feat-note">Sans compte, en 30 secondes</div>
        <a class="bouton-solution" href="<?= e(url('?p=decors')) ?>">Voir les badges</a>
      </div>
    </div>
  </div>
</section>

<!-- ---------- l'appel final de l'application ---------- -->
<section class="section" style="background:linear-gradient(135deg,var(--blue-900),var(--blue-600));text-align:center;position:relative;overflow:hidden;">
  <div style="position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,0.04) 1px,transparent 1px);background-size:24px 24px;"></div>
  <div class="container" style="position:relative;z-index:2;">
    <div class="fade-up">
      <h2 style="font-family:var(--font-display);font-size:clamp(2.2rem,5vw,3.8rem);font-weight:800;color:#fff;margin-bottom:16px;letter-spacing:-0.03em;line-height:1.1;">Télécharge Wakabi<br>Vis ta ville à 100%</h2>
      <p style="color:rgba(255,255,255,0.7);font-size:1.1rem;margin-bottom:36px;max-width:480px;margin-left:auto;margin-right:auto;">Rejoins des milliers d'explorateurs qui ne ratent plus jamais les bons plans de leur ville.</p>
      <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
        <a href="<?= e(APPLICATION_URL) ?>" target="_blank" rel="noopener" class="btn btn-white btn-lg">Télécharger sur Google Play</a>
        <a href="<?= e(url('?p=partenaires')) ?>" class="btn btn-lg" style="border:1.5px solid rgba(255,255,255,0.4);color:#fff;">Devenir Partenaire</a>
      </div>
    </div>
  </div>
</section>

<!-- ---------- le Kori ---------- -->
<section class="kori-section">
  <div class="container">
    <div class="kori-grid">
      <div class="kori-visual-card fade-up">
        <div class="kori-logo-text">KORI</div>
        <div class="kori-sub-text">Monnaie Virtuelle Wakabi</div>
        <div class="kori-balance-lbl">Solde disponible</div>
        <div class="kori-balance-val"><sub>₵</sub> 2 450</div>
        <div class="kori-dots"><div class="kori-dot on"></div><div class="kori-dot on"></div><div class="kori-dot on"></div><div class="kori-dot"></div><div class="kori-dot"></div></div>
        <div class="kori-card-label">Carte Wakabi · QR Code Actif</div>
        <div class="kori-perks">
          <div class="kori-perk"><span class="kori-perk-icon">★</span> 50 Koris gagnés ce mois</div>
          <div class="kori-perk"><span class="kori-perk-icon">🎁</span> −20% chez 12 partenaires</div>
          <div class="kori-perk"><span class="kori-perk-icon">🏆</span> Badge "Explorateur Gold"</div>
        </div>
      </div>
      <div class="kori-content fade-up fade-up-d2">
        <div class="tag">Exclusivité Wakabi</div>
        <h2 class="headline">La Carte Wakabi <span>&amp; le Kori.</span></h2>
        <p>Plus tu explores, plus tu gagnes. Chaque visite chez un partenaire te rapporte des Koris, la monnaie virtuelle Wakabi. Utilise-les pour des réductions, des accès VIP et des privilèges exclusifs.</p>
        <div class="kori-benefits">
          <div class="kb-item"><div class="kb-icon">🪙</div><div class="kb-text"><strong>Gagne des Koris à chaque sortie</strong><span>Chaque achat chez un partenaire te crédite automatiquement</span></div></div>
          <div class="kb-item"><div class="kb-icon">💳</div><div class="kb-text"><strong>QR Code unique sur ton téléphone</strong><span>Présente-le chez n'importe quel partenaire pour activer tes avantages</span></div></div>
          <div class="kb-item"><div class="kb-icon">🏅</div><div class="kb-text"><strong>Badges &amp; niveaux d'explorateur</strong><span>Plus tu découvres, plus ton statut et tes privilèges augmentent</span></div></div>
        </div>
        <div style="margin-top:28px;"><a class="btn btn-primary btn-lg" href="<?= e(url('?p=application')) ?>">En savoir plus sur le Kori →</a></div>
      </div>
    </div>
  </div>
</section>

<!-- ---------- témoignages ---------- -->
<section class="section" style="background:var(--bg2);">
  <div class="container">
    <div style="text-align:center;margin-bottom:52px;" class="fade-up">
      <div class="tag">Ils ont testé</div>
      <h2 class="headline">La communauté <span>parle</span></h2>
    </div>
    <div class="testi-grid">
      <div class="testi-card fade-up"><div class="stars">★★★★★</div><p class="testi-quote">"Grâce à Wakabi, je ne rate plus aucun bon plan du week-end. En quelques minutes, je sais où sortir, quel événement ne pas manquer."</p><div class="testi-author"><div class="av av-b">GK</div><div><div class="testi-name">Gracia K.</div><div class="testi-role">Chargée de com., Lomé</div></div></div></div>
      <div class="testi-card fade-up fade-up-d1"><div class="stars">★★★★★</div><p class="testi-quote">"Wakabi m'a permis de découvrir des endroits que je ne connaissais même pas dans ma propre ville. L'app est simple, claire et super pratique."</p><div class="testi-author"><div class="av av-g">CA</div><div><div class="testi-name">César A.</div><div class="testi-role">Entrepreneur, Lomé</div></div></div></div>
      <div class="testi-card fade-up fade-up-d2"><div class="stars">★★★★★</div><p class="testi-quote">"Avant, je passais des heures sur Instagram à chercher quoi faire. Maintenant, tout est regroupé au même endroit. Mon réflexe week-end."</p><div class="testi-author"><div class="av av-o">CL</div><div><div class="testi-name">Claudia L.</div><div class="testi-role">Étudiante, Univ. de Lomé</div></div></div></div>
      <div class="testi-card fade-up fade-up-d3"><div class="stars">★★★★★</div><p class="testi-quote">"Avec Wakabi, je découvre les concerts et événements sans chercher partout. Je planifie mes sorties avec mes amis facilement."</p><div class="testi-author"><div class="av av-p">CM</div><div><div class="testi-name">Christian M.</div><div class="testi-role">Étudiant, Lomé</div></div></div></div>
    </div>
  </div>
</section>

<!-- ---------- le blog ---------- -->
<?php if ($articles): ?>
<section class="section">
  <div class="container">
    <div class="blog-header-row" style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:44px;">
      <div><div class="tag">Wakabi News</div><h2 class="headline">L'Afrique telle qu'<span>on la vit</span></h2></div>
      <a class="btn btn-outline" href="<?= e(url('?p=blog')) ?>">Voir tous les articles</a>
    </div>
    <div class="blog-grid">
      <?php foreach ($articles as $a): ?>
        <a class="blog-card" href="<?= e(url(url_article($a))) ?>" style="text-decoration:none;color:inherit;display:block;">
          <?php $_im = image_reduite(illustration_article($a), 480); ?>
          <div class="blog-img">
            <img src="<?= e($_im['src']) ?>"
                 <?= $_im['srcset'] ? 'srcset="' . e($_im['srcset']) . '" sizes="(max-width:700px) 92vw, 400px"' : '' ?>
                 <?= $_im['largeur'] ? 'width="' . $_im['largeur'] . '" height="' . $_im['hauteur'] . '"' : '' ?>
                 alt="" loading="lazy" decoding="async">
          </div>
          <div class="blog-body">
            <div class="blog-title"><?= e($a['titre']) ?></div>
            <div class="blog-read-more">Lire <span aria-hidden="true">&#8594;</span></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php else: ?>
<section class="section">
  <div class="container">
    <div class="blog-header-row" style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:44px;">
      <div><div class="tag">Wakabi News</div><h2 class="headline">L'Afrique telle qu'<span>on la vit</span></h2></div>
      <a class="btn btn-outline" href="<?= e(url('?p=blog')) ?>">Voir tous les articles</a>
    </div>
    <div class="blog-grid">
      <a class="blog-card" href="<?= e(url('?p=blog')) ?>" style="text-decoration:none;color:inherit;display:block;">
        <div class="blog-img" style="background:linear-gradient(135deg,var(--blue-50),var(--blue-100));display:flex;align-items:center;justify-content:center;">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.4" width="44" height="44" aria-hidden="true"><path d="M4 5h16v14H4z" stroke-linejoin="round"></path><path d="M4 9h16M8 5v14" stroke-linecap="round"></path></svg>
        </div>
        <div class="blog-body">
          <div class="blog-title">Soirée au Maquis Akwaba : le guide complet</div>
          <div class="blog-read-more">Lire <span aria-hidden="true">&#8594;</span></div>
        </div>
      </a>
      <a class="blog-card" href="<?= e(url('?p=blog')) ?>" style="text-decoration:none;color:inherit;display:block;">
        <div class="blog-img" style="background:linear-gradient(135deg,var(--blue-50),var(--blue-100));display:flex;align-items:center;justify-content:center;">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.4" width="44" height="44" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle><path d="M12 8v4l3 2" stroke-linecap="round"></path></svg>
        </div>
        <div class="blog-body">
          <div class="blog-title">Remplir un maquis un mardi soir : la méthode</div>
          <div class="blog-read-more">Lire <span aria-hidden="true">&#8594;</span></div>
        </div>
      </a>
      <a class="blog-card" href="<?= e(url('?p=blog')) ?>" style="text-decoration:none;color:inherit;display:block;">
        <div class="blog-img" style="background:linear-gradient(135deg,var(--blue-50),var(--blue-100));display:flex;align-items:center;justify-content:center;">
          <svg viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.4" width="44" height="44" aria-hidden="true"><path d="M12 3l2.5 5.5L20 9l-4 4 1 6-5-3-5 3 1-6-4-4 5.5-.5z" stroke-linejoin="round"></path></svg>
        </div>
        <div class="blog-body">
          <div class="blog-title">Badges viraux : pourquoi ça marche</div>
          <div class="blog-read-more">Lire <span aria-hidden="true">&#8594;</span></div>
        </div>
      </a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ---------- les partenaires ---------- -->
<?php
/**
 * LES LOGOS SONT SERVIS PAR NOUS, et plus par le guide.
 *
 * Ils venaient de `admin.wakabileguide.com` : trois requêtes vers un
 * domaine que ce site ne tient pas, sur des connexions où le mégaoctet
 * se compte, et trois images qui disparaissent le jour où ce domaine
 * bouge, passe en maintenance ou change ses adresses de médias. La
 * règle vaut ici comme elle valait pour les captures de l'application :
 * ce que la page montre, la page le sert.
 *
 * Ils sont aussi RÉDUITS À LEUR TAILLE D'AFFICHAGE. Les originaux font
 * 768 pixels de côté pour paraître à 44 : neuf dixièmes du poids
 * téléchargé ne servaient qu'à être jetés par le navigateur.
 *
 * Un logo dont le fichier manque n'est pas affiché, et la section
 * entière disparaît s'il n'en reste aucun. Une rangée de carrés brisés
 * sous « Ils nous font confiance » dit exactement le contraire de ce
 * qu'elle veut dire — et c'est l'état d'une installation où le
 * rapatriement n'a pas encore été fait.
 */
$_logos = array_values(array_filter([
    ['partenaires/sudcom.webp',    'Sudcom',    44],
    ['partenaires/gnadoe.webp',    'Gnadoe',    36],
    ['partenaires/actu-lome.webp', 'Actu Lomé', 36],
], static fn(array $l): bool => is_file(RACINE . '/public/' . $l[0])));
?>
<?php if ($_logos): ?>
<section class="section-sm" style="background:var(--bg2);">
  <div class="container" style="text-align:center;">
    <p style="font-size:12px;text-transform:uppercase;letter-spacing:0.1em;color:var(--text3);font-weight:700;margin-bottom:28px;">Ils nous font confiance</p>
    <div style="display:flex;gap:32px;align-items:center;justify-content:center;flex-wrap:wrap;opacity:0.5;filter:grayscale(1);">
      <?php foreach ($_logos as [$_f, $_nom, $_h]): ?>
        <img src="<?= e(url('public/' . $_f)) ?>" alt="<?= e($_nom) ?>"
             style="height:<?= (int) $_h ?>px;width:auto;object-fit:contain;"
             loading="lazy" decoding="async">
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
