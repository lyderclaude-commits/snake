<?php
$titre = 'Page introuvable | ' . reglage('nom_site');
$noindex = true;
?>
<section class="page-404">
  <div class="section-label" style="justify-content:center">Erreur 404</div>
  <h1 class="section-title">Cette page n’existe pas<br/><em>ou plus</em></h1>
  <p>Le lien est peut-être ancien. Les articles de l’ancien site sont désormais sur le blog.</p>
  <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap">
    <a href="<?= url('') ?>" class="btn btn-gold">Retour à l’accueil</a>
    <a href="<?= url('blog') ?>" class="btn btn-outline">Voir le blog</a>
  </div>
</section>
