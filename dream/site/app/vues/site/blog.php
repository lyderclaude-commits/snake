<?php
/** Blog & Ressources — Marketing Digital, Communication | Live Y Dream — converti depuis blog.html. */
$titre = $titre ?? 'Blog & Ressources — Marketing Digital, Communication | Live Y Dream';
$description = 'Conseils, stratégies et ressources sur le marketing digital et la communication au Togo. Articles rédigés par les experts de Live Y Dream.';
ob_start(); ?>
<style>
.blog-layout { padding: clamp(60px,8vw,100px) var(--gutter); background: var(--bg); }
.bl-inner { max-width: var(--max-w); margin: 0 auto; display: grid; grid-template-columns: 7fr 3fr; gap: 60px; }
.blog-grid { display: grid; grid-template-columns: 1fr; gap: 32px; }
.article-card { background: var(--bg-card); border: 1px solid var(--border); display: grid; grid-template-columns: 260px 1fr; overflow: hidden; transition: all var(--transition); cursor: pointer; }
.article-card:hover { border-color: var(--border-gold); box-shadow: var(--shadow-lg); transform: translateY(-3px); }
.article-card.featured-post { grid-template-columns: 1fr; grid-row: auto; }
.article-img { width: 100%; height: 100%; min-height: 180px; object-fit: cover; }
.article-card.featured-post .article-img { height: 320px; }
.article-body { padding: 32px; display: flex; flex-direction: column; }
.article-meta { display: flex; align-items: center; gap: 16px; margin-bottom: 12px; flex-wrap: wrap; }
.article-cat { background: var(--gold-bg); color: var(--gold-dark); font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; padding: 4px 10px; border: 1px solid var(--border-gold); }
.article-date { font-size: 12px; color: var(--text-soft); }
.article-read { font-size: 12px; color: var(--text-soft); }
.article-title { font-family: var(--font-serif); font-size: clamp(17px,2vw,22px); font-weight: 700; color: var(--navy); line-height: 1.3; margin-bottom: 10px; }
.article-excerpt { font-size: 14px; color: var(--text-soft); line-height: 1.7; flex: 1; }
.article-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border); }
.article-author { display: flex; align-items: center; gap: 10px; }
.article-av { width: 32px; height: 32px; border-radius: 50%; background: var(--navy); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #fff; }
.article-author-name { font-size: 13px; font-weight: 600; color: var(--navy); }
.article-link { font-size: 12px; font-weight: 700; color: var(--gold-dark); letter-spacing: 1px; text-transform: uppercase; transition: gap var(--transition); display: flex; align-items: center; gap: 6px; }
.article-card:hover .article-link { gap: 10px; }

/* Sidebar */
.blog-sidebar {}
.sidebar-widget { background: var(--bg-card); border: 1px solid var(--border); padding: 28px; margin-bottom: 24px; }
.sw-title { font-size: 11px; font-weight: 700; letter-spacing: 2.5px; text-transform: uppercase; color: var(--navy); margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid var(--gold); display: inline-block; }
.sw-cats { display: flex; flex-direction: column; gap: 8px; }
.sw-cat { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border); cursor: pointer; transition: color var(--transition); font-size: 14px; color: var(--text-mid); }
.sw-cat:hover { color: var(--gold-dark); }
.sw-cat-count { background: var(--bg-soft); padding: 2px 8px; font-size: 11px; font-weight: 700; color: var(--text-soft); }
.sw-tags { display: flex; flex-wrap: wrap; gap: 8px; }
.sw-tag { padding: 6px 12px; border: 1px solid var(--border); font-size: 12px; color: var(--text-soft); cursor: pointer; transition: all var(--transition); }
.sw-tag:hover { border-color: var(--gold); color: var(--gold-dark); }
.sw-cta { background: var(--navy); padding: 32px; text-align: center; }
.sw-cta h4 { font-family: var(--font-serif); font-size: 20px; color: #fff; margin-bottom: 10px; }
.sw-cta p { font-size: 13px; color: rgba(255,255,255,0.6); line-height: 1.6; margin-bottom: 20px; }

/* Article modal */
.article-modal { position: fixed; inset: 0; background: rgba(10,22,40,0.97); z-index: 2000; overflow-y: auto; display: none; }
.article-modal.open { display: block; }
.am-inner { max-width: 780px; margin: 0 auto; background: var(--bg-card); position: relative; margin-top: 40px; margin-bottom: 40px; }
.am-close { position: fixed; top: 20px; right: 20px; width: 44px; height: 44px; background: var(--navy); color: #fff; border: none; font-size: 18px; cursor: pointer; z-index: 10; transition: all var(--transition); }
.am-close:hover { background: var(--gold); color: var(--navy); }
.am-hero { width: 100%; height: 380px; object-fit: cover; display: block; }
.am-body { padding: clamp(32px,5vw,60px); }
.am-cat { display: inline-block; background: var(--gold-bg); color: var(--gold-dark); font-size: 10px; font-weight: 700; letter-spacing: 2px; padding: 4px 12px; border: 1px solid var(--border-gold); margin-bottom: 16px; }
.am-title { font-family: var(--font-serif); font-size: clamp(24px,4vw,40px); font-weight: 900; color: var(--navy); line-height: 1.15; margin-bottom: 16px; }
.am-meta { display: flex; align-items: center; gap: 16px; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid var(--border); font-size: 13px; color: var(--text-soft); }
.am-content { font-family: var(--font-serif); font-size: 17px; color: var(--text-mid); line-height: 1.85; }
.am-content h3 { font-size: 22px; color: var(--navy); margin: 32px 0 12px; font-weight: 700; }
.am-content p { margin-bottom: 18px; }
.am-content strong { color: var(--navy); font-weight: 700; }
.am-cta { background: var(--gold-bg); border: 1px solid var(--border-gold); padding: 32px; margin-top: 40px; text-align: center; }
.am-cta h4 { font-family: var(--font-serif); font-size: 20px; color: var(--navy); margin-bottom: 8px; }
.am-cta p { font-size: 14px; color: var(--text-soft); margin-bottom: 16px; }

@media (max-width:1024px) { .bl-inner { grid-template-columns: 1fr; } .blog-sidebar { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; } }
@media (max-width:768px) { .article-card { grid-template-columns: 1fr; } .blog-sidebar { grid-template-columns: 1fr; } }

/* Ajouts : liens de cartes, filtres actifs, pagination, recherche */
a.article-card { color: inherit; }
.article-card.featured-post { grid-template-columns: 1fr; }
.sw-cat { text-decoration: none; }
.sw-cat.on, .sw-tag.on { color: var(--gold-dark); border-color: var(--gold); }
.sw-tag { text-decoration: none; display: inline-block; }
.blog-filtre { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 8px; font-size: 14px; color: var(--text-soft); }
.blog-filtre a { color: var(--gold-dark); font-weight: 700; }
.blog-pages { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
.blog-pages a, .blog-pages span { min-width: 44px; height: 44px; padding: 0 14px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border); background: var(--bg-card); font-size: 13px; font-weight: 700; color: var(--navy); }
.blog-pages a:hover { border-color: var(--gold); color: var(--gold-dark); }
.blog-pages .ici { background: var(--navy); color: #fff; border-color: var(--navy); }
.sw-search { display: flex; gap: 0; }
.sw-search input { flex: 1; min-width: 0; padding: 12px 14px; border: 1px solid var(--border); font-size: 14px; background: var(--bg); }
.sw-search button { padding: 0 16px; border: 0; background: var(--navy); color: #fff; font-weight: 700; }
.article-lang { font-size: 10px; font-weight: 700; letter-spacing: 1.5px; padding: 3px 7px; border: 1px solid var(--border); color: var(--text-soft); }
.blog-vide {background: var(--bg-card); border: 1px solid var(--border); padding: 48px 32px; text-align: center; color: var(--text-soft); }
</style>
<?php $tete = ob_get_clean(); ?>
<header class="page-header">
  <div class="page-header-grid"></div>
  <div class="page-header-inner">
    <div class="section-label">Blog & Ressources</div>
    <h1 class="section-title">Conseils & stratégies<br/><em>pour vos marques</em></h1>
    <div class="page-header-breadcrumb"><a href="<?= url('') ?>">Accueil</a><span>/</span><span>Blog</span></div>
  </div>
</header>

<div class="blog-layout">
  <div class="bl-inner">
    <div class="blog-grid" id="articleGrid">
      <?php if ($categorie !== '' || $tag !== '' || $recherche !== ''): ?>
        <div class="blog-filtre">
          <span><?= e(trim(t('%s article(s)', (int) $liste['total'])
              . ($categorie !== '' ? ' ' . t('dans « %s »', t($categorie)) : '')
              . ($tag !== '' ? ' ' . t('avec le tag « %s »', $tag) : '')
              . ($recherche !== '' ? ' ' . t('pour « %s »', $recherche) : ''))) ?></span>
          <a href="<?= url('blog') ?>">Tout afficher</a>
        </div>
      <?php endif; ?>

      <?php foreach ($liste['articles'] as $i => $a): ?>
      <a class="article-card<?= $i === 0 && $liste['page'] === 1 && $categorie . $tag . $recherche === '' ? ' featured-post' : '' ?>" href="<?= url('blog/' . rawurlencode($a['slug'])) ?>">
        <?php $vf = langue() === 'en' && !article_traduit($a); ?>
        <img src="<?= e(article_image($a)) ?>" alt="<?= e(article_l($a, 'titre')) ?>" class="article-img" loading="<?= $i < 2 ? 'eager' : 'lazy' ?>"/>
        <div class="article-body">
          <div class="article-meta">
            <?php if ($a['categorie'] !== ''): ?><span class="article-cat"><?= e(t($a['categorie'])) ?></span><?php endif; ?>
            <span class="article-date"><?= e(date_lang($a['publie_le'])) ?></span>
            <span class="article-read"><?= e(temps_lecture(article_l($a, 'contenu'))) ?></span>
            <?php if ($vf): ?><span class="article-lang" title="<?= e(t('En français')) ?>">FR</span><?php endif; ?>
          </div>
          <h2 class="article-title"<?= $vf ? ' lang="fr"' : '' ?>><?= e(article_l($a, 'titre')) ?></h2>
          <p class="article-excerpt"><?= e(article_extrait($a)) ?></p>
          <div class="article-footer">
            <div class="article-author"><div class="article-av"><?= e(initiales($a['auteur'] ?: 'L')) ?></div><div class="article-author-name"><?= e($a['auteur']) ?></div></div>
            <span class="article-link">Lire l'article →</span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>

      <?php if (!$liste['articles']): ?>
        <div class="blog-vide">Aucun article <?= $categorie . $tag . $recherche !== '' ? 'ne correspond à cette recherche' : 'publié pour l’instant' ?>.</div>
      <?php endif; ?>

      <?php if ($liste['pages'] > 1): ?>
        <nav class="blog-pages" aria-label="Pages du blog">
          <?php for ($n = 1; $n <= $liste['pages']; $n++):
              $q = array_filter(['categorie' => $categorie, 'tag' => $tag, 'q' => $recherche, 'page' => $n > 1 ? $n : null]); ?>
            <?php if ($n === $liste['page']): ?><span class="ici" aria-current="page"><?= $n ?></span>
            <?php else: ?><a href="<?= url('blog' . ($q ? '?' . http_build_query($q) : '')) ?>"><?= $n ?></a><?php endif; ?>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>
    </div>

    <aside class="blog-sidebar">
      <div class="sidebar-widget">
        <div class="sw-title">Rechercher</div>
        <form class="sw-search" action="<?= url('blog') ?>" method="get" role="search">
          <input type="search" name="q" value="<?= e($recherche) ?>" placeholder="Un mot, un sujet…" aria-label="Rechercher dans le blog"/>
          <button type="submit">OK</button>
        </form>
      </div>
      <?php if ($categories): ?>
      <div class="sidebar-widget">
        <div class="sw-title">Catégories</div>
        <div class="sw-cats" id="swCats">
          <?php foreach ($categories as $c): ?>
            <a class="sw-cat<?= $c['categorie'] === $categorie ? ' on' : '' ?>" href="<?= url('blog?categorie=' . rawurlencode($c['categorie'])) ?>"><span><?= e($c['categorie']) ?></span><span class="sw-cat-count"><?= (int) $c['n'] ?></span></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($tags): ?>
      <div class="sidebar-widget">
        <div class="sw-title">Tags</div>
        <div class="sw-tags" id="swTags">
          <?php foreach ($tags as $t): ?>
            <a class="sw-tag<?= $t === $tag ? ' on' : '' ?>" href="<?= url('blog?tag=' . rawurlencode($t)) ?>"><?= e($t) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <div class="sw-cta">
        <h4>Audit Gratuit</h4>
        <p>Faites auditer votre communication par nos experts. Sans engagement, réponse en 24h.</p>
        <a href="<?= url('contact') ?>" class="btn btn-gold" style="width:100%;justify-content:center">Demander →</a>
      </div>
    </aside>
  </div>
</div>
