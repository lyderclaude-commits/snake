<?php
/**
 * Devenir partenaire Wakabi — page venue de wakabileguide.com.
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
      <div class="breadcrumb"><a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">›</span><span>Partenaires</span></div>
      <h1>Devenez <br>Partenaire Wakabi</h1>
      <p>Rejoignez 2340+ établissements qui font confiance à Wakabi pour booster leur visibilité locale.</p>
    </div></div>
  </div>
  <section class="section partner-section">
    <div class="container">
      <div style="text-align:center;margin-bottom:56px;" class="fade-up">
        <div class="tag">Pourquoi rejoindre Wakabi</div>
        <h2 class="headline">Connectez-vous à <span>des milliers</span> de clients locaux</h2>
        <p class="subhead" style="margin:14px auto;text-align:center;">Wakabi vous connecte directement avec des milliers de clients prêts à découvrir votre établissement. Zéro commission sur vos ventes.</p>
      </div>
      <div class="why-grid fade-up">
        <div class="why-card"><div class="why-icon"><?= icone_guide('visibilite', 32) ?></div><div class="why-title">Visibilité maximale</div><div class="why-desc">Votre établissement présenté à tous les utilisateurs dans votre zone. Profil complet, photos, horaires — tout ce dont vos clients ont besoin.</div><div class="why-metric">+68% <span>de visibilité moyenne</span></div></div>
        <div class="why-card"><div class="why-icon"><?= icone_guide('pourcentage', 32) ?></div><div class="why-title">0% de commission</div><div class="why-desc">Contrairement aux autres plateformes, Wakabi ne prend aucune commission sur vos ventes. Abonnement fixe, revenus 100% conservés.</div><div class="why-metric">100% <span>des revenus conservés</span></div></div>
        <div class="why-card"><div class="why-icon"><?= icone_guide('analyse', 32) ?></div><div class="why-title">Analytics en temps réel</div><div class="why-desc">Statistiques : vues du profil, clics, scans de Qr Code, avis clients. Optimisez avec des données concrètes.</div><div class="why-metric">Temps réel <span>données & insights</span></div></div>
      </div>
      <div style="text-align:center;margin-bottom:44px;" class="fade-up">
        <div class="tag">Offres partenaires</div>
        <h2 class="headline">Choisissez votre <span>formule</span></h2>
      </div>
      <div class="pricing-grid fade-up">
        <div class="pricing-card">
          <div class="pricing-tier">Starter</div><div class="pricing-name">Basic</div>
          <div class="pricing-price"><span class="price-curr">XOF</span><span class="price-val">0</span></div>
          <div class="price-period">Gratuit pour toujours</div>
          <div class="pricing-desc">Pour tester et commencer à vous faire connaître.</div>
          <div class="pricing-divider"></div>
          <div class="pricing-features">
            <div class="pf-item"><span class="pf-check">✓</span>Profil référencé dans l'annuaire</div>
            <div class="pf-item"><span class="pf-check">✓</span>Informations de base</div>
            <div class="pf-item"><span class="pf-check">✓</span>Réception d'avis clients</div>
            <div class="pf-item pf-dim"><span class="pf-check">—</span>Galerie photos complète</div>
            <div class="pf-item pf-dim"><span class="pf-check">—</span>Mise en avant résultats</div>
            <div class="pf-item pf-dim"><span class="pf-check">—</span>Intégration Carte Wakabi</div>
          </div>
          <button class="btn btn-outline" style="width:100%;justify-content:center;" href="<?= e(url('?p=contact')) ?>">Commencer gratuitement</button>
        </div>
        <div class="pricing-card featured">
          <div class="featured-label">🤍 Le plus populaire</div>
          <div class="pricing-name">Pro</div>
          <div class="pricing-price"><span class="price-curr">XOF</span><span class="price-val">9 900</span></div>
          <div class="price-period">par an · sans engagement</div>
          <div class="pricing-desc">Pour les établissements qui veulent dominer leur quartier.</div>
          <div class="pricing-divider"></div>
          <div class="pricing-features">
            <div class="pf-item"><span class="pf-check">✓</span>Tout ce qui est dans Basic</div>
            <div class="pf-item"><span class="pf-check">✓</span>Galerie photos (20 photos)</div>
            <div class="pf-item"><span class="pf-check">✓</span>Mise en avant dans les résultats</div>
            <div class="pf-item"><span class="pf-check">✓</span>Notifications push audience</div>
          </div>
          <button class="btn btn-white" style="width:100%;justify-content:center;" href="<?= e(url('?p=contact')) ?>">Devenir partenaire Pro</button>
        </div>
        <div class="pricing-card">
          <div class="pricing-tier">Enterprise</div><div class="pricing-name">Premium</div>
          <div class="pricing-price"><span class="price-curr">XOF</span><span class="price-val">99 000</span></div>
          <div class="price-period">a vie · accompagnement inclus</div>
          <div class="pricing-desc">Pour les marques et chaînes qui veulent dominer le marché.</div>
          <div class="pricing-divider"></div>
          <div class="pricing-features">
            <div class="pf-item"><span class="pf-check">✓</span>Tout ce qui est dans Pro</div>
            <div class="pf-item"><span class="pf-check">✓</span>Placement publicitaire ciblé</div>
            <div class="pf-item"><span class="pf-check">✓</span>Article sponsorisé blog Wakabi</div>
            <div class="pf-item"><span class="pf-check">✓</span>Co-branding réseaux sociaux</div>
            <div class="pf-item"><span class="pf-check">✓</span>Campagnes personnalisées</div>
            <div class="pf-item"><span class="pf-check">✓</span>Account manager dédié</div>
          </div>
          <button class="btn btn-outline" style="width:100%;justify-content:center;border-color:var(--primary);color:var(--primary);" href="<?= e(url('?p=contact')) ?>">Nous contacter</button>
        </div>
      </div>
    </div>
  </section>
  <!-- FAQ -->
  <section class="section" style="background:var(--bg2);">
    <div class="container" style="max-width:740px;">
      <div style="text-align:center;margin-bottom:48px;" class="fade-up">
        <div class="tag">Questions fréquentes</div>
        <h2 class="headline">Tout ce que vous <span>voulez savoir</span></h2>
      </div>
      <div class="faq-list fade-up" id="faqList">
        <div class="faq-item"><div class="faq-q">Comment rejoindre la plateforme Wakabi ?<span>+</span></div><div class="faq-a">Il vous suffit de remplir le formulaire de contact en choisissant l'objet "Devenir partenaire". Notre équipe vous contacte sous 24h pour vous guider dans le processus d'inscription et la configuration de votre profil.</div></div>
        <div class="faq-item"><div class="faq-q">Est-ce que Wakabi prend une commission sur mes ventes ?<span>+</span></div><div class="faq-a">Non, jamais. Wakabi fonctionne sur un modèle d'abonnement. Vous payez un forfait fixe et conservez 100% de vos revenus. Aucune commission, aucune surprise.</div></div>
        <div class="faq-item"><div class="faq-q">Combien d'utilisateurs Wakabi compte actuellement ?<span>+</span></div><div class="faq-a">Wakabi compte plus de 10 000 utilisateurs actifs, avec une croissance mensuelle de 15 à 20%. L'expansion vers Cotonou est en cours, ce qui augmentera significativement la base d'utilisateurs.</div></div>
        <div class="faq-item"><div class="faq-q">Puis-je modifier mes informations à tout moment ?<span>+</span></div><div class="faq-a">Oui, absolument. Les partenaires Pro et Premium ont accès à un dashboard complet pour modifier leurs informations, photos, horaires et offres en temps réel, directement depuis leur interface partenaire.</div></div>
        <div class="faq-item"><div class="faq-q">Comment fonctionne le Qr Code pour mon établissement ?<span>+</span></div><div class="faq-a">Vous recevez un QR Code unique à afficher dans votre établissement. Quand un client scanne votre QR code avec l'app Wakabi, il reçoit des points et bénéficie de vos offres actives. Vous voyez toutes les statistiques dans votre dashboard.</div></div>
      </div>
    </div>
  </section>
  <!-- Testimonials partenaires -->
  <div class="testimonial-band">
    <div class="container">
      <div style="text-align:center;margin-bottom:44px;"><p style="font-size:12px;text-transform:uppercase;letter-spacing:0.1em;color:rgba(255,255,255,0.4);font-weight:700;margin-bottom:12px;">Ce que disent nos partenaires</p><h2 style="font-family:var(--font-display);font-size:clamp(1.8rem,4vw,2.8rem);font-weight:800;color:#fff;letter-spacing:-0.03em;">Ils nous font confiance</h2></div>
      <div class="partner-testimonials">
        <div class="pt-card"><p class="pt-quote">"Depuis que nous sommes sur Wakabi, notre fréquentation le week-end a augmenté de 40%. Les clients viennent en citant l'app. C'est un canal marketing qui fonctionne vraiment."</p><div class="pt-name">Kofi Mensah</div><div class="pt-role">Gérant, Le Grill de Lomé</div></div>
        <div class="pt-card"><p class="pt-quote">"Le rapport qualité-prix est imbattable. Pour 15 000 XOF par mois, j'ai une visibilité que je n'aurais jamais pu acheter sur les réseaux sociaux seul."</p><div class="pt-name">Aïcha Traoré</div><div class="pt-role">Propriétaire, Boutique Mode Abidjan</div></div>
        <div class="pt-card"><p class="pt-quote">"L'équipe Wakabi est réactive et professionnelle. Le dashboard analytics m'aide à comprendre mes clients et à adapter mon offre. Je recommande vivement."</p><div class="pt-name">Emmanuel Agbo</div><div class="pt-role">Directeur, Hôtel Cotonou</div></div>
      </div>
    </div>
  </div>
</div>


<!-- ================================================================
     PAGE : VILLES
     ================================================================ -->
<template id="footerTemplate">
