<div class="contenu">
  <section class="entete">
    <h1>Les Badges</h1>
    <p>Choisissez-en un, ajoutez votre photo, partagez. Sans compte, en trente secondes.</p>
  </section>

  <?php
  /* La barre de recherche du catalogue, avec ses filtres. On se souvient
     rarement d'un titre exact, mais très bien de « Lomé », d'un mot lu sur
     le badge, ou du fait que LE badge qu'on cherche est le plus partagé de
     sa ville. Le formulaire voyage tout entier dans l'adresse : une
     combinaison de filtres est une adresse que l'on peut coller à un
     collègue, et qu'un moteur de recherche sait parcourir. */
  ?>
  <form method="get" action="<?= e(url('?p=decors')) ?>" class="catalogue-recherche">
    <?= permaliens_champ_page('decors') ?>
    <div class="catalogue-champ">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"></circle>
        <line x1="16" y1="16" x2="21" y2="21"></line></svg>
      <input type="search" name="q" value="<?= e($cherche) ?>"
             placeholder="Chercher un badge, une ville, un mot lu dessus"
             aria-label="Chercher un badge">
    </div>
    <div class="catalogue-filtres">
      <select name="ville" aria-label="Filtrer par ville">
        <option value="" <?= $ville === '' ? 'selected' : '' ?>>Toutes les villes</option>
        <option value="lome" <?= $ville === 'lome' ? 'selected' : '' ?>>Lomé</option>
        <option value="cotonou" <?= $ville === 'cotonou' ? 'selected' : '' ?>>Cotonou</option>
        <option value="abidjan" <?= $ville === 'abidjan' ? 'selected' : '' ?>>Abidjan</option>
      </select>
      <select name="tri" aria-label="Trier les badges">
        <option value="" <?= $tri === '' ? 'selected' : '' ?>>Plus récents</option>
        <option value="populaires" <?= $tri === 'populaires' ? 'selected' : '' ?>>Les plus partagés</option>
        <option value="az" <?= $tri === 'az' ? 'selected' : '' ?>>A à Z</option>
      </select>
      <button class="bouton" type="submit">Chercher</button>
      <?php if ($cherche !== '' || $ville !== '' || $tri !== ''): ?>
        <a class="bouton fant" href="<?= e(url('?p=decors')) ?>">Effacer</a>
      <?php endif; ?>
    </div>
  </form>

  <?php
  /* Le résumé du filtre se lit d'un coup : combien, et à quoi la grille a
     répondu. Un « 12 badges » sec laisse deviner ; le résumé raconte. */
  if ($cherche !== '' || $ville !== '' || $tri !== ''):
      $_res = $total . ' badge' . ($total > 1 ? 's' : '');
      if ($ville !== '') {
          $_res .= ' à ' . ['lome' => 'Lomé', 'cotonou' => 'Cotonou', 'abidjan' => 'Abidjan'][$ville];
      }
      if ($cherche !== '') {
          $_res .= ' pour « ' . e($cherche) . ' »';
      }
  ?>
    <p class="aide" style="margin:0 0 16px"><?= $_res ?>.</p>
  <?php endif; ?>

  <?php if (!$liste): ?>
    <div class="carte">
      <p style="margin:0;color:var(--text2)">
        <?php if ($cherche !== '' || $ville !== ''): ?>
          Aucun badge ne correspond.
          <a href="<?= e(url('?p=decors')) ?>">Voir tout le catalogue</a>.
        <?php else: ?>
          Aucun badge publié pour l’instant.
        <?php endif; ?>
      </p>
    </div>
  <?php else: ?>
    <div class="grille g3">
      <?php foreach ($liste as $d): ?>
        <a class="vignette" href="<?= e(url('?p=decor&slug=' . urlencode($d['slug']))) ?>">
          <?php $_im = image_reduite($d['cadre_url'] ?: url('public/cadres/bon-plan.png'), 320); ?>
          <img src="<?= e($_im['src']) ?>"
               <?= $_im['srcset'] ? 'srcset="' . e($_im['srcset']) . '" sizes="(max-width:700px) 92vw, 320px"' : '' ?>
               <?= $_im['largeur'] ? 'width="' . $_im['largeur'] . '" height="' . $_im['hauteur'] . '"' : '' ?>
               alt="" loading="lazy" decoding="async">
          <div class="bas">
            <?php /* Le titre, puis ce que le badge a vécu : les compteurs de
               vues et de téléchargements, lus sur les mêmes sous-requêtes
               que le rapport. Le sous-titre (« Samedi, 21 h ») reste hors
               de la vignette — sur la grille, il donnait des cartes de
               hauteurs inégales ; un compteur, lui, tient sur une ligne. */ ?>
            <b><?= e($d['titre']) ?></b>
            <span><?= (int) $d['vues'] ?> vue<?= (int) $d['vues'] > 1 ? 's' : '' ?>
              · <?= (int) $d['telechargements'] ?> téléchargement<?= (int) $d['telechargements'] > 1 ? 's' : '' ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <?php
    /* La pagination par page entière, pas par défilement infini : une page
       numérotée a une adresse, elle se partage et un moteur sait la
       parcourir. Les filtres voyagent avec leur page, sinon changer de
       page effacerait la recherche que l'on vient de composer. */
    $_suite = ($cherche !== '' ? '&q=' . urlencode($cherche) : '')
            . ($ville !== '' ? '&ville=' . urlencode($ville) : '')
            . ($tri !== '' ? '&tri=' . urlencode($tri) : '');
    ?>
    <?php if ($pages > 1): ?>
      <nav class="rangee" style="margin-top:22px;gap:10px;justify-content:center" aria-label="Pages">
        <?php if ($page_n > 1): ?>
          <a class="bouton fant petit" href="<?= e(url('?p=decors&n=' . ($page_n - 1) . $_suite)) ?>">← Plus récents</a>
        <?php endif; ?>
        <span class="aide">Page <?= (int) $page_n ?> sur <?= (int) $pages ?></span>
        <?php if ($page_n < $pages): ?>
          <a class="bouton fant petit" href="<?= e(url('?p=decors&n=' . ($page_n + 1) . $_suite)) ?>">Plus anciens →</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
