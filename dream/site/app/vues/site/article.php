<?php
/**
 * Un article, sur sa propre page.
 *
 * Le site livré ouvrait les articles dans une fenêtre par-dessus le blog :
 * aucune adresse à partager, rien qu'un moteur de recherche puisse lire.
 * La mise en forme de cette fenêtre (`am-*`) est reprise telle quelle.
 */
$titre = $a['titre'] . ' | ' . reglage('nom_site');
$description = article_extrait($a, 160);
ob_start(); ?>
<style>
.am-page { background: var(--bg); padding: 120px var(--gutter) clamp(60px,8vw,100px); }
.am-inner { max-width: 820px; margin: 0 auto; background: var(--bg-card); border: 1px solid var(--border); }
.am-hero { width: 100%; max-height: 460px; object-fit: cover; display: block; }
.am-body { padding: clamp(28px,5vw,60px); }
.am-fil { font-size: 12px; color: var(--text-soft); margin-bottom: 18px; display: flex; gap: 8px; flex-wrap: wrap; }
.am-fil a { color: var(--gold-dark); font-weight: 600; }
.am-cat { display: inline-block; background: var(--gold-bg); color: var(--gold-dark); font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; padding: 4px 12px; border: 1px solid var(--border-gold); margin-bottom: 16px; }
.am-title { font-family: var(--font-serif); font-size: clamp(26px,4vw,42px); font-weight: 900; color: var(--navy); line-height: 1.15; margin-bottom: 16px; }
.am-meta { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid var(--border); font-size: 13px; color: var(--text-soft); }
.am-content { font-family: var(--font-serif); font-size: 17px; color: var(--text-mid); line-height: 1.85; overflow-wrap: anywhere; }
.am-content h2 { font-size: 26px; color: var(--navy); margin: 36px 0 14px; font-weight: 700; line-height: 1.25; }
.am-content h3, .am-content h4 { font-size: 21px; color: var(--navy); margin: 30px 0 12px; font-weight: 700; line-height: 1.3; }
.am-content p { margin-bottom: 18px; }
.am-content a { color: var(--gold-dark); text-decoration: underline; text-underline-offset: 3px; }
.am-content strong, .am-content b { color: var(--navy); font-weight: 700; }
.am-content ul, .am-content ol { margin: 0 0 18px 1.3em; }
.am-content ul { list-style: disc; }
.am-content ol { list-style: decimal; }
.am-content li { margin-bottom: 6px; }
.am-content blockquote { border-left: 3px solid var(--gold); padding: 6px 0 6px 20px; margin: 24px 0; font-style: italic; color: var(--navy); }
.am-content img { margin: 24px auto; height: auto; }
.am-content figure { margin: 24px 0; }
.am-content figure img { margin: 0 auto; }
.am-content figcaption { font-size: 13px; color: var(--text-soft); text-align: center; margin-top: 8px; }
.am-content iframe { width: 100%; aspect-ratio: 16/9; height: auto; border: 0; margin: 24px 0; }
.am-content table { width: 100%; border-collapse: collapse; margin: 24px 0; font-size: 15px; }
.am-content th, .am-content td { border: 1px solid var(--border); padding: 8px 10px; text-align: left; }
.am-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 32px; }
.am-tags a { padding: 6px 12px; border: 1px solid var(--border); font-size: 12px; color: var(--text-soft); }
.am-tags a:hover { border-color: var(--gold); color: var(--gold-dark); }
.am-partage { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 28px; font-size: 12px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--text-soft); }
.am-partage a { padding: 8px 14px; border: 1px solid var(--border); color: var(--navy); letter-spacing: 1px; }
.am-partage a:hover { background: var(--navy); color: #fff; }
.am-cta { background: var(--gold-bg); border: 1px solid var(--border-gold); padding: 32px; margin-top: 40px; text-align: center; }
.am-cta h4 { font-family: var(--font-serif); font-size: 20px; color: var(--navy); margin-bottom: 8px; }
.am-cta p { font-size: 14px; color: var(--text-soft); margin-bottom: 16px; }
.am-suite { max-width: 820px; margin: 40px auto 0; }
.am-suite h2 { font-size: 11px; font-weight: 700; letter-spacing: 2.5px; text-transform: uppercase; color: var(--navy); margin-bottom: 16px; }
.am-suite-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap: 16px; }
.am-suite a { background: var(--bg-card); border: 1px solid var(--border); display: block; transition: all var(--transition); }
.am-suite a:hover { border-color: var(--border-gold); box-shadow: var(--shadow-md); transform: translateY(-2px); }
.am-suite img { width: 100%; aspect-ratio: 16/10; object-fit: cover; }
.am-suite b { display: block; padding: 14px 16px 4px; font-size: 15px; line-height: 1.35; color: var(--navy); }
.am-suite span { display: block; padding: 0 16px 14px; font-size: 12px; color: var(--text-soft); }
.am-brouillon { max-width: 820px; margin: 0 auto 16px; background: #FFF7ED; border: 1px solid #FDBA74; color: #9A3412; padding: 12px 16px; font-size: 14px; }
</style>
<?php $tete = ob_get_clean(); ?>
<article class="am-page">
  <?php if ($a['statut'] !== 'publie' || $a['publie_le'] > maintenant()): ?>
    <div class="am-brouillon"><strong>Aperçu.</strong> Cet article n’est pas en ligne : seuls les membres connectés le voient.</div>
  <?php endif; ?>
  <div class="am-inner">
    <img src="<?= e(article_image($a)) ?>" alt="<?= e($a['titre']) ?>" class="am-hero"/>
    <div class="am-body">
      <nav class="am-fil" aria-label="Fil d’Ariane"><a href="<?= url('') ?>">Accueil</a><span>/</span><a href="<?= url('blog') ?>">Blog</a></nav>
      <?php if ($a['categorie'] !== ''): ?>
        <a class="am-cat" href="<?= url('blog?categorie=' . rawurlencode($a['categorie'])) ?>"><?= e($a['categorie']) ?></a>
      <?php endif; ?>
      <h1 class="am-title"><?= e($a['titre']) ?></h1>
      <div class="am-meta">
        <time datetime="<?= e(date('c', strtotime((string) $a['publie_le']) ?: time())) ?>"><?= e(date_fr($a['publie_le'])) ?></time>
        <span>·</span><span><?= e(temps_lecture($a['contenu'])) ?> de lecture</span>
        <?php if ($a['auteur'] !== ''): ?><span>·</span><span><?= e($a['auteur']) ?></span><?php endif; ?>
      </div>
      <div class="am-content"><?= contenu_afficher($a['contenu']) ?></div>

      <?php if ($tags_article = json_liste($a['tags'])): ?>
        <div class="am-tags">
          <?php foreach ($tags_article as $t): ?><a href="<?= url('blog?tag=' . rawurlencode($t)) ?>"><?= e($t) ?></a><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php $adresse = rawurlencode(url_absolue('blog/' . $a['slug'])); ?>
      <div class="am-partage">Partager
        <a href="https://wa.me/?text=<?= rawurlencode($a['titre']) ?>%20<?= $adresse ?>" target="_blank" rel="noopener">WhatsApp</a>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $adresse ?>" target="_blank" rel="noopener">Facebook</a>
        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $adresse ?>" target="_blank" rel="noopener">LinkedIn</a>
      </div>

      <div class="am-cta">
        <h4>Vous avez aimé cet article ?</h4>
        <p>Nos experts peuvent vous aider à appliquer ces stratégies pour votre entreprise.</p>
        <a href="<?= url('contact') ?>" class="btn btn-gold">Obtenir un accompagnement →</a>
      </div>
    </div>
  </div>

  <?php if ($autres): ?>
  <section class="am-suite">
    <h2>À lire aussi</h2>
    <div class="am-suite-grid">
      <?php foreach ($autres as $o): ?>
        <a href="<?= url('blog/' . rawurlencode($o['slug'])) ?>">
          <img src="<?= e(article_image($o)) ?>" alt="" loading="lazy"/>
          <b><?= e($o['titre']) ?></b>
          <span><?= e(date_fr($o['publie_le'])) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</article>
