<?php
/**
 * Régie — page de présentation, côté vitrine.
 *
 * Même raison que Push : le menu public mène ici, et un visiteur doit
 * pouvoir comprendre ce qu'on vend avant qu'on lui demande un compte.
 */
?>
<div class="page-header">
  <div class="container"><div class="page-header-inner">
    <div class="breadcrumb">
      <a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">›</span>
      <span>Boost · Régie</span>
    </div>
    <h1>Vos invités vous appartiennent</h1>
    <p>Chaque badge généré laisse une adresse. La régie en fait une audience, et cette
    audience reste la vôtre — pas celle d’un réseau social qui décide qui vous voit.</p>
  </div></div>
</div>

<section class="section">
  <div class="container">

    <?php $ligne = 'regie'; $quoi = 'la régie'; $pluriel = false;
          require __DIR__ . '/partiels/offre-ligne.php'; ?>

    <div style="text-align:center;margin-bottom:46px" class="fade-up">
      <div class="tag">De la salle à la boîte aux lettres</div>
      <h2 class="headline">Écrire à ceux qui sont <span>déjà venus</span></h2>
      <p style="color:var(--text2);max-width:62ch;margin:14px auto 0;line-height:1.8">
        Une campagne remplit une salle un soir. Les adresses qu’elle laisse remplissent
        les suivantes. C’est la différence entre une affiche et une audience.
      </p>
    </div>

    <div class="why-grid fade-up">
      <div class="why-card">
        <div class="why-icon"><?= icone_guide('equipe', 32) ?></div>
        <div class="why-title">Des listes qui se tiennent seules</div>
        <div class="why-desc">Vos invités entrent dans le carnet au moment où ils font leur
        badge. Les adresses non confirmées sont écartées des envois : votre réputation
        d’expéditeur ne se brûle pas sur des adresses mortes.</div>
      </div>
      <div class="why-card">
        <div class="why-icon"><?= icone_guide('search', 32) ?></div>
        <div class="why-title">Choisir qui reçoit</div>
        <div class="why-desc">Cinq règles suffisent : la ville, la rubrique, la campagne,
        la date, et « est réellement venu ». Écrire à tout le monde coûte cher et lasse
        vite ; écrire à ceux que ça concerne se remarque.</div>
      </div>
      <div class="why-card">
        <div class="why-icon"><?= icone_guide('analyse', 32) ?></div>
        <div class="why-title">Savoir ce qui est arrivé</div>
        <div class="why-desc">Parti, reçu, ouvert, suivi, échoué. Et surtout : les échecs
        listés, avec leur motif, et relançables — c’est la seule partie qu’on ne voit
        jamais ailleurs, et la seule qui se corrige.</div>
      </div>
    </div>

    <div class="fade-up" style="margin-top:52px">
      <h2 class="headline" style="text-align:center;margin-bottom:26px">Ce qui est compris</h2>
      <div class="pf-list" style="max-width:640px;margin:0 auto">
        <div class="pf-item"><span class="pf-check">✓</span>Un atelier d’écriture à deux colonnes, avec l’aperçu à côté</div>
        <div class="pf-item"><span class="pf-check">✓</span>Le carnet d’adresses, vos listes, vos segments</div>
        <div class="pf-item"><span class="pf-check">✓</span>Le désabonnement en un clic, comme la loi le demande</div>
        <div class="pf-item"><span class="pf-check">✓</span>Votre propre serveur d’envoi, ou le nôtre</div>
        <div class="pf-item"><span class="pf-check">✓</span>La relecture avant envoi : rien ne part par accident</div>
      </div>
    </div>

    <div class="fade-up" style="text-align:center;margin-top:52px">
      <a class="btn btn-primary btn-lg" href="<?= e(url('?p=inscription')) ?>">Créer mon compte</a>
      <p style="color:var(--text2);margin-top:14px;font-size:14px">
        Déjà un compte ? <a href="<?= e(url('?p=regie')) ?>">Ouvrir ma régie</a>
      </p>
    </div>

  </div>
</section>
