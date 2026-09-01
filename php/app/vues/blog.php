<?php
/** Le blog : la liste, lisible par tout le monde. */
?>
<div class="contenu">
  <section class="entete">
    <h1>Le blog</h1>
    <p>Ce qu’on apprend en remplissant des salles à Lomé, Cotonou et Abidjan.
    Des cas réels, des chiffres, et ce qui n’a pas marché.</p>
  </section>

  <?php if (!$liste): ?>
    <div class="carte">
      <p style="margin:0;color:var(--text2)">Rien de publié pour l’instant. Revenez bientôt —
      ou <a href="<?= e(url('?p=decors')) ?>">allez voir les décors</a> en attendant.</p>
    </div>
  <?php else: ?>
    <div class="grille g3">
      <?php foreach ($liste as $a): ?>
        <a class="vignette article" href="<?= e(url('?p=blog&a=' . urlencode($a['slug']))) ?>">
          <?php if ($a['couverture']):
              $_im = image_reduite($a['couverture'], 320); ?>
            <img src="<?= e($_im['src']) ?>"
                 <?= $_im['srcset'] ? 'srcset="' . e($_im['srcset']) . '" sizes="(max-width:700px) 92vw, 320px"' : '' ?>
                 <?= $_im['largeur'] ? 'width="' . $_im['largeur'] . '" height="' . $_im['hauteur'] . '"' : '' ?>
                 alt="" loading="lazy" decoding="async">
          <?php endif; ?>
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
          <a class="bouton fant petit" href="<?= e(url('?p=blog&n=' . ($page_n - 1))) ?>">← Plus récents</a>
        <?php endif; ?>
        <span class="aide">Page <?= (int) $page_n ?> sur <?= (int) $pages ?></span>
        <?php if ($page_n < $pages): ?>
          <a class="bouton fant petit" href="<?= e(url('?p=blog&n=' . ($page_n + 1))) ?>">Plus anciens →</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
