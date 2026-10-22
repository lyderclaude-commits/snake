<article class="article" data-lecture="<?= e(url('blog/' . rawurlencode($a['slug']) . '/vue')) ?>">
  <?php if ($a['statut'] !== 'publie'): ?><p class="brouillon">Aperçu d’un brouillon : invisible du public.</p><?php endif; ?>
  <h1><?= e($a['titre']) ?></h1>
  <p class="meta"><?= e(date_fr($a['publie_le'])) ?><?= $a['auteur'] !== '' ? ' · ' . e($a['auteur']) : '' ?> · <?= e(temps_lecture($a['contenu'])) ?></p>
  <?php if ($a['image'] !== ''): ?><img class="couverture" src="<?= e(image_url($a['image'])) ?>" alt=""><?php endif; ?>
  <div class="texte"><?= contenu_afficher($a['contenu']) ?></div>
</article>
<script>
  // Une lecture comptée une fois par onglet, quand la page est vraiment affichée (voir article_lecture()).
  (function () {
    var el = document.querySelector('[data-lecture]'), cle = 'lu:' + location.pathname;
    try { if (sessionStorage.getItem(cle)) return; sessionStorage.setItem(cle, '1'); } catch (e) {}
    setTimeout(function () { navigator.sendBeacon && navigator.sendBeacon(el.dataset.lecture); }, 1200);
  })();
</script>
