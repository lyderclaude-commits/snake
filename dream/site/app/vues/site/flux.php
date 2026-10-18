<?php
/** Le flux RSS du blog : les vingt derniers articles. */
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title><?= e(reglage('nom_site')) ?> — Blog</title>
  <link><?= e(url_absolue('blog')) ?></link>
  <atom:link href="<?= e(url_absolue('blog/flux.xml')) ?>" rel="self" type="application/rss+xml"/>
  <description><?= e(reglage('meta_description')) ?></description>
  <language>fr-FR</language>
<?php foreach (articles_publies(1, '', '', '', 20)['articles'] as $a): ?>
  <item>
    <title><?= e($a['titre']) ?></title>
    <link><?= e(url_absolue('blog/' . rawurlencode($a['slug']))) ?></link>
    <guid isPermaLink="true"><?= e(url_absolue('blog/' . rawurlencode($a['slug']))) ?></guid>
    <pubDate><?= e(date(DATE_RSS, strtotime((string) $a['publie_le']) ?: time())) ?></pubDate>
    <?php if ($a['categorie'] !== ''): ?><category><?= e($a['categorie']) ?></category><?php endif; ?>
    <description><?= e(article_extrait($a, 400)) ?></description>
  </item>
<?php endforeach; ?>
</channel>
</rss>
