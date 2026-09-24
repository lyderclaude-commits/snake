<?php
/**
 * À propos de Wakabi — page venue de wakabileguide.com.
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
      <div class="breadcrumb"><a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">›</span><span>À Propos</span></div>
      <h1>À Propos de Wakabi</h1>
      <p>L'histoire et la mission qui fait vivre Wakabi au quotidien.</p>
    </div></div>
  </div>
  <section class="section">
    <div class="container">
      <div class="about-grid">
        <div class="fade-up">
          <div class="tag">Notre histoire</div>
          <h2 class="headline">Né à Cotonou pour <span>l'Afrique entière</span></h2><br/>
          <p style="color:var(--text2);margin-bottom:16px;line-height:1.85;">Wakabi est né d'une frustration simple : malgré la richesse culturelle et gastronomique de L'Afrique, il était impossible de trouver facilement les meilleurs endroits sans passer des heures sur Instagram, WhatsApp et à demander à ses amis.</p>
          <p style="color:var(--text2);margin-bottom:16px;line-height:1.85;">En 2022, les fondateurs décident de créer le guide local que l'Afrique francophone méritait : intelligent, mobile-first, ancré dans les réalités locales et conçu pour les jeunes urbains qui veulent vivre leur ville à fond.</p>
          <p style="color:var(--text2);line-height:1.85;">Le nom Wakabi vient du concept "Le Guide" l'ami qui connaît tout, qui te montre les bons coins et qui ne te déçoit jamais. C'est exactement ce que nous voulons être pour chaque utilisateur.</p>
        </div>
        <div class="fade-up fade-up-d2">
          <img src="https://v3.1.wakabileguide.com/wp-content/uploads/2026/02/mockup-4tel-1024x1024.png">
          <!--<div style="background:linear-gradient(135deg,var(--blue-50),var(--blue-100));border-radius:var(--r-2xl);padding:48px;aspect-ratio:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:16px;">
            <div style="font-size:60px;margin-bottom:8px;">🌍</div>
            <div style="font-family:var(--font-display);font-size:1.2rem;font-weight:800;color:var(--blue-800);text-align:center;">Fondé en 2024<br>à Lomé, Togo</div>
            <div style="font-size:14px;color:var(--text2);text-align:center;max-width:220px;line-height:1.6;">Avec l'ambition de devenir le guide de référence de l'Afrique francophone</div>
          </div>-->
        </div>
      </div>
    </div>
  </section>
  <section class="section lequipe" style="background:var(--bg2);">
    <div class="container">
      <div style="text-align:center;margin-bottom:52px;" class="fade-up">
        <div class="tag">L'équipe</div>
        <h2 class="headline">Ceux qui font <span>Wakabi.</span></h2>
      </div>
      <div class="team-grid fade-up">
        <div class="team-card"><div class="team-av" style="background:var(--blue-100);color:var(--blue-700);">UL</div><div class="team-name">Ulrich</div><div class="team-role">CEO & Co-fondateur</div></div>
        <div class="team-card"><div class="team-av" style="background:#FEF3C7;color:#92400E;">LA</div><div class="team-name">Laeticia</div><div class="team-role">Co-fondatrice & Digital Lead</div></div>
        <div class="team-card"><div class="team-av" style="background:#D1FAE5;color:#065F46;">EG</div><div class="team-name">Elmak/Godwin</div><div class="team-role">CTO</div></div>
        <div class="team-card"><div class="team-av" style="background:#F3E8FF;color:#6B21A8;">AP</div><div class="team-name">APush & Amen</div><div class="team-role">R&D & Créatif</div></div>
      </div>
    </div>
  </section>
  <section class="section unedeux">
    <div class="container">
      <div style="text-align:center;margin-bottom:52px;" class="fade-up">
        <div class="tag">Nos valeurs</div>
        <h2 class="headline">Ce en quoi <span>on croit</span></h2>
      </div>
      <div class="values-grid fade-up">
        <div class="value-card"><div class="value-icon"><?= icone_guide('boutique', 32) ?></div><div class="value-title">Local d'abord</div><div class="value-desc">Nous construisons pour l'Afrique, par l'Afrique. Chaque décision produit et marketing est pensée pour les réalités locales.</div></div>
        <div class="value-card"><div class="value-icon"><?= icone_guide('equipe', 32) ?></div><div class="value-title">Communauté avant tout</div><div class="value-desc">Wakabi n'est pas juste une app — c'est une communauté de gens qui aiment leur ville et veulent la faire découvrir aux autres.</div></div>
        <div class="value-card"><div class="value-icon"><?= icone_guide('creativite', 32) ?></div><div class="value-title">Innovation africaine</div><div class="value-desc">On ne copie pas les modèles occidentaux. On crée des solutions qui correspondent à nos contextes, nos usages et nos ambitions.</div></div>
        <div class="value-card"><div class="value-icon"><?= icone_guide('telephone', 32) ?></div><div class="value-title">Mobile-first</div><div class="value-desc">L'Afrique est mobile. Tout ce que nous créons est pensé pour fonctionner parfaitement sur smartphone, avec n'importe quelle connexion.</div></div>
        <div class="value-card"><div class="value-icon"><?= icone_guide('confiance', 32) ?></div><div class="value-title">Confiance & transparence</div><div class="value-desc">Aucune commission cachée pour nos partenaires, aucune vente de données pour nos utilisateurs. Notre modèle est clair et honnête.</div></div>
        <div class="value-card"><div class="value-icon"><?= icone_guide('croissance', 32) ?></div><div class="value-title">Impact mesurable</div><div class="value-desc">Chaque feature que nous lançons doit avoir un impact réel et mesurable sur la vie de nos utilisateurs et le business de nos partenaires.</div></div>
      </div>
    </div>
  </section>
</div>


<!-- ================================================================
     PAGE : CONTACT
     ================================================================ -->
<template id="footerTemplate">
