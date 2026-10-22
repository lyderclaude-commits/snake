<?php $titre = 'Import WordPress'; ?>
<div class="entete">
  <div><h1>Import WordPress</h1><p>Rapatrie les articles de l’ancien site — textes, catégories, tags, auteurs, dates — et <b>toutes leurs images</b>, copiées sur ce serveur. Les anciennes adresses des articles redirigent ensuite vers les nouvelles.</p></div>
</div>

<?php if (!$curl): ?><div class="msg attention">L’extension cURL est absente de l’hébergement : l’import passe par un mode de repli plus lent. Si des erreurs apparaissent, activez cURL dans le panneau PHP de l’hébergeur.</div><?php endif; ?>

<div class="grille g3" style="margin-bottom:20px">
  <div class="stat p"><b data-n-importes><?= (int) $importes ?></b><span>Articles déjà importés</span></div>
  <div class="stat o"><b data-n-exterieures><?= (int) $exterieures ?></b><span>Images encore hébergées ailleurs</span></div>
  <div class="stat"><b style="font-size:1.05rem;line-height:1.5"><?= $dernier ? e(il_y_a($dernier)) : 'jamais' ?></b><span>Dernier import</span></div>
</div>

<div class="carte" data-import>
  <h2>1. Les articles</h2>
  <p class="aide">Par lots de dix, pour ne jamais dépasser le temps qu’un hébergement accorde à une page. Laissez cet onglet ouvert jusqu’à la fin.</p>
  <div class="champ"><label for="racine">Adresse de l’API WordPress</label>
    <input id="racine" name="racine" type="url" value="<?= e($racine) ?>" placeholder="https://ancien-site.com/wp-json/wp/v2">
    <p class="aide">À faire <b>avant</b> de faire pointer le domaine vers ce nouveau site : une fois le domaine basculé, l’ancien WordPress ne répondra plus à cette adresse (sauf s’il a été déplacé sur un sous-domaine — indiquez alors celui-ci).</p></div>
  <label class="case" style="margin-bottom:14px"><input type="checkbox" data-ecraser> Écraser aussi les articles retouchés ici depuis le dernier import</label>
  <div class="rangee">
    <button class="bouton fant" type="button" data-sonder>Tester la connexion</button>
    <button class="bouton" type="button" data-lancer>Importer les articles</button>
  </div>
  <div data-etat-import class="aide" style="margin-top:12px" role="status"></div>
  <div class="progression" hidden data-barre><i></i></div>
  <pre class="journal" data-journal></pre>
</div>

<div class="carte" style="margin-top:20px" data-images>
  <h2>2. Les images restantes</h2>
  <p class="aide">Les images qu’un article ou un élément du site va encore chercher sur un autre site (les photos de démonstration Unsplash, une image qui n’a pas pu être lue pendant l’import). Elles sont téléchargées puis servies d’ici.</p>
  <div class="rangee">
    <button class="bouton" type="button" data-rapatrier>Rapatrier les images</button>
    <label class="case"><input type="checkbox" data-reprendre> Réessayer aussi celles qui ont échoué</label>
  </div>
  <div data-etat-images class="aide" style="margin-top:12px" role="status"></div>
  <pre class="journal" data-journal-images></pre>
</div>

<div class="carte plate" style="margin-top:20px">
  <h2>Ensuite</h2>
  <ol class="etapes" style="margin-top:12px">
    <li><span><b>Relisez quelques articles</b> sur <a href="<?= url((string) def('articles.chemin', 'blog')) ?>" target="_blank">le blog</a> : mise en page, images, liens.</span></li>
    <li><span><b>Basculez le domaine</b> vers ce site. Les liens de l’ancien WordPress (<code>/mon-article/</code>, <code>/2024/03/mon-article/</code>, <code>?p=123</code>) redirigent tout seuls vers <code>/<?= e((string) def('articles.chemin', 'blog')) ?>/mon-article</code>.</span></li>
    <li><span><b>Déclarez le plan du site</b> dans Google Search Console : <code><?= e(url_absolue('sitemap.xml')) ?></code></span></li>
  </ol>
</div>
