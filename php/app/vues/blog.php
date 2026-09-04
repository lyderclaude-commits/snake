<?php
/** Le blog : la liste, lisible par tout le monde. */
?>
<div class="contenu">
  <section class="entete">
    <h1>Le blog</h1>
    <p>Ce qu’on apprend en remplissant des salles à Lomé, Cotonou et Abidjan.
    Des cas réels, des chiffres, et ce qui n’a pas marché.</p>
  </section>

  <?php
  /* La recherche porte sur le titre, le chapô et le texte : on se souvient
     rarement d'un titre, mais très bien d'un mot lu dedans. */
  ?>
  <form method="get" action="<?= e(url('?p=blog')) ?>" class="rangee chercher chercher-comptes">
    <input type="hidden" name="p" value="blog">
    <input type="search" name="q" value="<?= e($cherche) ?>" placeholder="Chercher dans le blog"
           aria-label="Chercher dans le blog">
    <button class="bouton fant petit" type="submit">Chercher</button>
    <?php if ($cherche !== ''): ?>
      <a class="bouton fant petit" href="<?= e(url('?p=blog')) ?>">Tout voir</a>
    <?php endif; ?>
  </form>

  <?php if ($cherche !== ''): ?>
    <p class="aide" style="margin:0 0 16px"><?= (int) $total ?>
      article<?= $total > 1 ? 's' : '' ?> pour « <?= e($cherche) ?> ».</p>
  <?php endif; ?>

  <?php if (!$liste): ?>
    <div class="carte">
      <p style="margin:0;color:var(--text2)">
        <?php if ($cherche !== ''): ?>
          Aucun article ne parle de « <?= e($cherche) ?> ».
          <a href="<?= e(url('?p=blog')) ?>">Voir tout le blog</a>.
        <?php else: ?>
          Rien de publié pour l’instant. Revenez bientôt —
          ou <a href="<?= e(url('?p=decors')) ?>">allez voir les décors</a> en attendant.
        <?php endif; ?>
      </p>
    </div>
  <?php else: ?>
    <div class="grille g3">
      <?php foreach ($liste as $a): ?>
        <a class="vignette article" href="<?= e(url('?p=blog&a=' . urlencode($a['slug']))) ?>">
          <?php
          /* Toujours une image : sa couverture, à défaut le cadre du décor
             qu'il cite, à défaut la vignette de la maison. Une carte sans
             image au milieu de deux qui en ont ne se lit pas comme « pas
             d'image » mais comme « carte cassée ». */
          $_im = image_reduite(illustration_article($a), 320);
          ?>
          <img src="<?= e($_im['src']) ?>"
               <?= $_im['srcset'] ? 'srcset="' . e($_im['srcset']) . '" sizes="(max-width:700px) 92vw, 320px"' : '' ?>
               <?= $_im['largeur'] ? 'width="' . $_im['largeur'] . '" height="' . $_im['hauteur'] . '"' : '' ?>
               alt="" loading="lazy" decoding="async">
          <div class="bas">
            <b><?= e($a['titre']) ?></b>
            <span><?= e(gmdate('d/m/Y', strtotime((string) $a['publie_le']))) ?>
              · <?= texte_minutes((string) $a['corps']) ?> min de lecture</span>
            <span class="chapo"><?= e($a['chapo'] ?: texte_extrait((string) $a['corps'], 120)) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
      <nav class="rangee" style="margin-top:22px;gap:10px;justify-content:center" aria-label="Pages">
        <?php if ($page_n > 1): ?>
          <a class="bouton fant petit" href="<?= e(url('?p=blog&n=' . ($page_n - 1) . ($cherche ? '&q=' . urlencode($cherche) : ''))) ?>">← Plus récents</a>
        <?php endif; ?>
        <span class="aide">Page <?= (int) $page_n ?> sur <?= (int) $pages ?></span>
        <?php if ($page_n < $pages): ?>
          <a class="bouton fant petit" href="<?= e(url('?p=blog&n=' . ($page_n + 1) . ($cherche ? '&q=' . urlencode($cherche) : ''))) ?>">Plus anciens →</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
