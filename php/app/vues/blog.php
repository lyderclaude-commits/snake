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
  /* La recherche du blog, à l'identique du catalogue : le champ à gauche
     (la loupe dedans, le bouton DEHORS), ses suggestions dans un
     sélecteur, et le bouton bleu qui ferme la ligne. Le sélecteur ne
     change rien tout seul : il propose une adresse complète, la recherche
     part au choix du visiteur — le même contrat que les réglages du
     catalogue. La rangée se replie d'elle-même sur un écran étroit. */
  ?>
  <form method="get" action="<?= e(url('?p=blog')) ?>" class="catalogue-recherche blogue-recherche">
    <?= permaliens_champ_page('blog') ?>
    <div class="catalogue-champ">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"></circle>
        <line x1="16" y1="16" x2="21" y2="21"></line></svg>
      <input type="search" name="q" value="<?= e($cherche) ?>"
             placeholder="Chercher dans le blog : un conseil, une ville, un chiffre"
             aria-label="Chercher dans le blog">
    </div>
    <select name="suggestion" aria-label="Recherches suggérées"
            onchange="this.form.q.value=this.value;this.form.submit()">
      <option value="">Suggérer</option>
      <?php foreach (['badges', 'campagne', 'Lomé', 'affiche'] as $sug): ?>
        <option value="<?= e($sug) ?>" <?= $cherche === $sug ? 'selected' : '' ?>><?= e($sug) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="bouton" type="submit">Chercher</button>
    <?php if ($cherche !== ''): ?>
      <a class="bouton fant" href="<?= e(url('?p=blog')) ?>">Effacer</a>
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
          Rien de publié pour l’instant. Revenez bientôt,
          ou <a href="<?= e(url('?p=decors')) ?>">allez voir les badges</a> en attendant.
        <?php endif; ?>
      </p>
    </div>
  <?php else: ?>
    <div class="grille g3">
      <?php foreach ($liste as $a): ?>
        <a class="vignette article" href="<?= e(url(url_article($a))) ?>">
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
