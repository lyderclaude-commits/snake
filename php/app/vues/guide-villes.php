<?php
/**
 * Nos villes · Wakabi — page venue de wakabileguide.com.
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
      <div class="breadcrumb"><a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">›</span><span>Nos Villes</span></div>
      <h1>Wakabi<br> conquiert l'Afrique francophone</h1>
      <p>Cotonou n'est que le début d'une aventure panafricaine.</p>
    </div></div>
  </div>
  <section class="section">
    <div class="container">
      <div style="text-align:center;margin-bottom:52px;" class="fade-up">
        <div class="tag">Expansion UEMOA</div>
        <h2 class="headline">Notre plan de <span>conquête</span></h2>
      </div>
      <div class="cities-grid fade-up" style="margin-bottom:60px;">
        <div class="city-card live"><div class="city-flag"><?= drapeau('benin') ?></div><div class="city-name">Cotonou</div><div class="city-country">Bénin</div><span class="city-status cs-live">En direct</span></div>
        <div class="city-card live"><div class="city-flag"><?= drapeau('togo') ?></div><div class="city-name">Lomé</div><div class="city-country">Togo</div><span class="city-status cs-live">En direct</span></div>
        <div class="city-card soon"><div class="city-flag"><?= drapeau('ci') ?></div><div class="city-name">Abidjan</div><div class="city-country">Côte d'Ivoire</div><span class="city-status cs-2026">2027</span></div>
        <div class="city-card soon"><div class="city-flag"><?= drapeau('senegal') ?></div><div class="city-name">Dakar</div><div class="city-country">Sénégal</div><span class="city-status cs-2026">2027</span></div>
        <div class="city-card soon"><div class="city-flag"><?= drapeau('gabon') ?></div><div class="city-name">Libreville</div><div class="city-country">Gabon</div><span class="city-status cs-2026">2028</span></div>
        <div class="city-card soon"><div class="city-flag"><?= drapeau('cameroun') ?></div><div class="city-name">Douala</div><div class="city-country">Cameroun</div><span class="city-status cs-2026">2028</span></div>
      </div>
      <div style="background:var(--blue-50);border:1px solid var(--blue-200);border-radius:var(--r-2xl);padding:50px;text-align:center;" class="fade-up">
        <h3 style="font-family:var(--font-display);font-size:1.6rem;font-weight:800;margin-bottom:12px;">Votre ville n'est pas encore listée ?</h3>
        <p style="color:var(--text2);margin-bottom:24px;">Inscrivez-vous à notre liste d'attente et bénéficiez d'un tarif de lancement préférentiel dès l'arrivée de Wakabi chez vous.</p>
        <a class="btn btn-primary btn-lg" href="<?= e(url('?p=contact')) ?>">S'inscrire sur la liste d'attente</a>
      </div>
    </div>
  </section>
</div>


<!-- ================================================================
     PAGE : BLOG (listing)
     ================================================================ -->
<template id="footerTemplate">
