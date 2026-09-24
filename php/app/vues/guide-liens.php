<?php
/**
 * Liens courts — page de présentation, côté vitrine.
 *
 * Celle-ci a une chance que les deux autres n'ont pas : le composeur est
 * DÉJÀ public. On peut donc faire mieux qu'expliquer — on donne le champ
 * tout de suite, et le compte ne se demande qu'au moment de créer le
 * lien. Le mur reste à la fin, comme partout ailleurs dans le produit.
 */
?>
<div class="page-header">
  <div class="container"><div class="page-header-inner">
    <div class="breadcrumb">
      <a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">›</span>
      <span>Boost · Liens courts</span>
    </div>
    <h1>Une adresse courte, et ce qu’elle rapporte</h1>
    <p>Sur une affiche, dans un statut WhatsApp, à la radio : une adresse qu’on retient,
    et le nombre de personnes qui l’ont réellement suivie.</p>
  </div></div>
</div>

<section class="section">
  <div class="container">

    <?php $ligne = 'liens_courts'; $quoi = 'les liens courts'; $pluriel = true;
          require __DIR__ . '/partiels/offre-ligne.php'; ?>

    <div style="text-align:center;margin-bottom:46px" class="fade-up">
      <div class="tag">Essayez tout de suite</div>
      <h2 class="headline">Composez le vôtre, <span>le compte vient après</span></h2>
      <p style="color:var(--text2);max-width:62ch;margin:14px auto 0;line-height:1.8">
        Pas de formulaire d’inscription avant d’avoir vu de quoi il s’agit. Vous composez
        votre lien, et c’est seulement pour le créer qu’on vous demande qui vous êtes.
      </p>
      <a class="btn btn-primary btn-lg" style="margin-top:22px"
         href="<?= e(url('?p=lien-court')) ?>">Composer un lien</a>
    </div>

    <div class="why-grid fade-up">
      <div class="why-card">
        <div class="why-icon"><?= icone_guide('map-marker', 32) ?></div>
        <div class="why-title">Une adresse qui tient sur une affiche</div>
        <div class="why-desc">Six caractères après le domaine. Assez court pour se recopier
        à la main depuis une affiche collée sur un mur, ce qu’une adresse de billetterie
        de quatre-vingts caractères ne permet pas.</div>
      </div>
      <div class="why-card">
        <div class="why-icon"><?= icone_guide('croissance', 32) ?></div>
        <div class="why-title">Le compte des clics</div>
        <div class="why-desc">Combien de personnes ont suivi le lien, et quand. C’est
        souvent la seule mesure disponible quand on colle une affiche : le reste se devine,
        celle-là se lit.</div>
      </div>
      <div class="why-card">
        <div class="why-icon"><?= icone_guide('confiance', 32) ?></div>
        <div class="why-title">Rattaché à une campagne</div>
        <div class="why-desc">Un lien peut appartenir à l’une de vos campagnes : ses clics
        entrent alors dans son rapport, à côté des vues, des badges et des présences
        scannées à l’entrée.</div>
      </div>
    </div>

    <div class="fade-up" style="text-align:center;margin-top:52px">
      <p style="color:var(--text2);font-size:14px">
        Déjà un compte ? <a href="<?= e(url('?p=liens')) ?>">Ouvrir mes liens</a>
      </p>
    </div>

  </div>
</section>
