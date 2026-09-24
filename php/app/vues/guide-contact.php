<?php
/**
 * Contact · Wakabi — page venue de wakabileguide.com.
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
      <div class="breadcrumb"><a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">›</span><span>Contact</span></div>
      <h1>Contactez-nous</h1>
      <p>Notre équipe vous répond sous 24h. Partenariats, support, presse, on gère tout.</p>
    </div></div>
  </div>
  <section class="section">
    <div class="container">
      <div class="contact-grid">
        <div class="fade-up">
          <div class="tag">Restons en contact</div>
          <h3>Une question ? Un projet ? Écrivez-nous.</h3>
          <p style="color:var(--text2);margin:14px 0 28px;line-height:1.8;">Que vous souhaitiez rejoindre la plateforme, discuter d'un partenariat stratégique ou simplement en savoir plus, notre équipe vous répond sous 24h.</p>
          <div class="contact-links">
            <a href="mailto:contact@wakabileguide.com" class="contact-link">contact@wakabileguide.com</a>
            <a href="https://www.facebook.com/wakabileguide" target="_blank" class="contact-link">Facebook — Wakabi le Guide</a>
            <a href="https://www.instagram.com/wakabileguide" target="_blank" class="contact-link">Instagram — @wakabileguide</a>
            <a href="https://www.tiktok.com/@wakabileguide" target="_blank" class="contact-link">TikTok — @wakabileguide</a>
            <a href="https://www.youtube.com/@WakabiLeGuideDesBonsCoins" target="_blank" class="contact-link">YouTube — Wakabi Le Guide</a>
          </div>
        </div>
        <div class="fade-up fade-up-d2">
          <form class="contact-form" id="contactForm" onsubmit="handleContact(event)">
            <div class="form-row">
              <div class="form-group"><label>Prénom / Nom</label><input type="text" name="name" placeholder="Kofi Mensah" required></div>
              <div class="form-group"><label>Email</label><input type="email" name="email" placeholder="kofi@exemple.com" required></div>
            </div>
            <div class="form-group">
              <label>Objet</label>
              <select name="subject" required><option value="" disabled selected>Choisir un objet</option><option>Devenir partenaire</option><option>Partenariat stratégique / Sponsoring</option><option>Support technique</option><option>Presse & Médias</option><option>Autre demande</option></select>
            </div>
            <div class="form-row">
              <div class="form-group"><label>Ville</label><input type="text" name="city" placeholder="Lomé, Togo"></div>
              <div class="form-group"><label>Téléphone (optionnel)</label><input type="tel" name="phone" placeholder="+228 XX XX XX XX"></div>
            </div>
            <div class="form-group"><label>Message</label><textarea name="message" placeholder="Décrivez votre projet..." required></textarea></div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;">Envoyer le message</button>
            <div class="form-success" id="contactSuccess">✓ Message envoyé ! Notre équipe vous répond sous 24h.</div>
          </form>
        </div>
      </div>
    </div>
  </section>
</div>


<!-- ================================================================
     PAGE : CONFIDENTIALITÉ
     ================================================================ -->
<template id="footerTemplate">
