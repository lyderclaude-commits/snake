<?php
/** Le tableau de bord : ce qui attend, ce qu'on a touché, où aller. Même plan que celui de Wakabi. */
$titre = 'Tableau de bord';
$classe_main = 'contenu bord';
$onglet = ($_GET['vue'] ?? '') === 'chiffres' ? 'chiffres' : 'faire';
$articles_on = module('articles');
$messages_on = module('messages');
$nonlus = $messages_on ? array_map('message_decoder', tous('SELECT * FROM messages WHERE lu = 0 ORDER BY id LIMIT 8')) : [];
$brouillons = $articles_on ? tous("SELECT id, titre, auteur, modifie_le FROM articles WHERE statut = 'brouillon' ORDER BY modifie_le DESC LIMIT 5") : [];
$a_relire = module('traduction') ? (int) valeur("SELECT COUNT(*) FROM articles WHERE traduction_auto = 1 AND trim(titre_en) <> ''") : 0;
$a_faire = count($nonlus) + count($brouillons) + ($a_relire ? 1 : 0);
$recents = array_merge(
    $articles_on ? array_map(fn($r) => $r + ['genre' => 'Article', 'classe' => 'article', 'lien' => 'admin/articles/' . $r['id']], tous('SELECT id, titre, modifie_le quand, statut FROM articles ORDER BY modifie_le DESC LIMIT 4')) : [],
    array_map(fn($x) => ['titre' => element_titre($x), 'quand' => $x['modifie_le'], 'classe' => 'campagne', 'lien' => 'admin/' . $x['collection'] . '/' . $x['id'],
        'genre' => mb_strtoupper(mb_substr(collection($x['collection'])['element'], 0, 1)) . mb_substr(collection($x['collection'])['element'], 1)],
        collections() ? array_map('element_decoder', tous('SELECT * FROM elements WHERE collection IN (' . implode(',', array_fill(0, count(collections()), '?')) . ') ORDER BY modifie_le DESC LIMIT 4', array_keys(collections()))) : []),
);
usort($recents, fn($a, $b) => strcmp((string) $b['quand'], (string) $a['quand']));
$recents = array_slice($recents, 0, 4);
$n = [
    'articles' => $articles_on ? (int) valeur("SELECT COUNT(*) FROM articles WHERE statut = 'publie'") : 0,
    'brouillons' => $articles_on ? (int) valeur("SELECT COUNT(*) FROM articles WHERE statut = 'brouillon'") : 0,
    'medias' => (int) valeur('SELECT COUNT(*) FROM medias'),
    'messages' => $messages_on ? (int) valeur('SELECT COUNT(*) FROM messages') : 0,
    'lectures' => $articles_on ? (int) valeur('SELECT COALESCE(SUM(lectures), 0) FROM articles') : 0,
    'messages7' => $messages_on ? (int) valeur('SELECT COUNT(*) FROM messages WHERE cree_le >= ?', [maintenant(time() - 7 * 86400)]) : 0,
    'articles30' => $articles_on ? (int) valeur("SELECT COUNT(*) FROM articles WHERE statut = 'publie' AND publie_le >= ?", [maintenant(time() - 30 * 86400)]) : 0,
];
?>
<div class="bord-barre">
  <div class="bord-qui">
    <h1>Tableau de bord</h1>
    <p><?= e($me['nom']) ?> · <?= e(ROLES[$me['role']] ?? $me['role']) ?></p>
  </div>
  <form class="bord-chercher" action="<?= url('admin/recherche') ?>" method="get" role="search">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
    <input type="search" name="q" placeholder="Chercher dans tout le site" aria-label="Rechercher" id="bord-q">
    <kbd>/</kbd>
  </form>
  <details class="deroulant bord-nouveau">
    <summary>+ Nouveau <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" style="width:15px;height:15px"><polyline points="6 9 12 15 18 9"></polyline></svg></summary>
    <div class="volet">
      <?php if ($articles_on): ?><a href="<?= url('admin/articles/nouveau') ?>">Article<small>Pour le blog</small></a><?php endif; ?>
      <?php foreach (collections() as $c): ?><a href="<?= url('admin/' . $c['nom'] . '/nouveau') ?>"><?= e(mb_strtoupper(mb_substr($c['element'], 0, 1)) . mb_substr($c['element'], 1)) ?><small><?= e($c['aide_menu'] !== '' ? $c['aide_menu'] : $c['titre']) ?></small></a><?php endforeach; ?>
      <a href="<?= url('admin/medias') ?>">Image<small>Dans la médiathèque</small></a>
    </div>
  </details>
</div>

<nav class="bord-onglets">
  <a href="<?= url('admin') ?>"<?= $onglet === 'faire' ? ' aria-current="page"' : '' ?>>À faire<?php if ($a_faire): ?> <span class="bord-compte"><?= $a_faire ?></span><?php endif; ?></a>
  <a href="<?= url('admin?vue=chiffres') ?>"<?= $onglet === 'chiffres' ? ' aria-current="page"' : '' ?>>Les chiffres</a>
</nav>

<?php if ($onglet === 'faire'): ?>
<div class="carte bord-file">
  <div class="bord-tete"><h2>À traiter</h2><span class="aide">du plus ancien au plus récent</span></div>
  <?php foreach ($nonlus as $m): ?>
    <div class="bord-ligne">
      <span class="bord-genre message">Message</span>
      <span class="bord-titre"><?= e($m['nom']) ?><?= message_sujet($m) !== '' ? ' — ' . e(message_sujet($m)) : '' ?></span>
      <span class="bord-age"><?= e(il_y_a($m['cree_le'])) ?></span>
      <a class="bouton petit" href="<?= url('admin/messages/' . $m['id']) ?>">Lire</a>
    </div>
  <?php endforeach; ?>
  <?php foreach ($brouillons as $b): ?>
    <div class="bord-ligne">
      <span class="bord-genre article">Brouillon</span>
      <span class="bord-titre"><?= e($b['titre']) ?></span>
      <span class="bord-auteur"><?= e($b['auteur']) ?></span>
      <span class="bord-age"><?= e(il_y_a($b['modifie_le'])) ?></span>
      <a class="bouton petit fant" href="<?= url('admin/articles/' . $b['id']) ?>">Reprendre</a>
    </div>
  <?php endforeach; ?>
  <?php if ($a_relire): ?>
    <div class="bord-ligne">
      <span class="bord-genre article">Anglais</span>
      <span class="bord-titre"><?= $a_relire ?> traduction(s) automatique(s) DeepL à relire</span>
      <a class="bouton petit fant" href="<?= url('admin/articles?statut=auto') ?>">Voir</a>
    </div>
  <?php endif; ?>
  <?php if (!$a_faire): ?>
    <p class="aide bord-vide">Rien n’attend.<?= $messages_on ? ' Aucune demande de contact non lue' . ($articles_on ? ', aucun brouillon en cours.' : '.') : ($articles_on ? ' Aucun brouillon en cours.' : '') ?></p>
  <?php endif; ?>
  <?php if ($articles_on && module('wordpress') && $n['articles'] === 0 && est_admin($me)): ?>
    <p class="bord-calme">Le blog est vide : <a href="<?= url('admin/import') ?>">importez les articles de l’ancien site WordPress</a>.</p>
  <?php endif; ?>
</div>

<?php if ($recents): ?>
<div class="bord-tete"><h2>Reprendre</h2><span class="aide">ce qui a été touché en dernier</span></div>
<div class="grille g4 bord-reprendre">
  <?php foreach ($recents as $r): ?>
    <a class="carte" href="<?= url($r['lien']) ?>">
      <span class="bord-genre <?= e($r['classe']) ?>"><?= e($r['genre']) ?></span>
      <b><?= e($r['titre']) ?></b>
      <span class="aide"><?= isset($r['statut']) && $r['statut'] === 'brouillon' ? 'brouillon · ' : '' ?>modifié <?= e(il_y_a($r['quand'])) ?></span>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="bord-tete"><h2>Où aller</h2><span class="aide">seulement ce que votre rôle ouvre</span></div>
<div class="bord-pastilles">
  <?php foreach (admin_menu($me) as $liens): foreach ($liens as [$r, $t]):
      $compte = match (true) {
          $r === 'admin/articles' => $n['articles'] + $n['brouillons'],
          $r === 'admin/medias' => $n['medias'],
          $r === 'admin/comptes' => (int) valeur('SELECT COUNT(*) FROM utilisateurs'),
          collection(substr($r, 6)) !== null => elements_compter(substr($r, 6)),
          default => null,
      }; ?>
    <a href="<?= url($r) ?>"><?= e($t) ?><?php if ($compte !== null): ?> <span><?= $compte ?></span><?php endif; ?></a>
    <?php if ($r === 'admin/medias' && $messages_on): ?><a href="<?= url('admin/messages') ?>">Messages <span><?= $n['messages'] ?></span></a><?php endif; ?>
  <?php endforeach; endforeach; ?>
  <a href="<?= url('admin/profil') ?>">Mon profil</a>
</div>
<?php else: ?>
<div class="grille g4">
  <?php if ($articles_on): ?>
    <a class="stat p" href="<?= url('admin/articles?statut=publie') ?>"><b><?= $n['articles'] ?></b><span>Articles en ligne</span></a>
    <a class="stat" href="<?= url('admin/articles?statut=brouillon') ?>"><b><?= $n['brouillons'] ?></b><span>Brouillons</span></a>
  <?php endif; ?>
  <?php foreach (collections() as $c): ?>
    <a class="stat v" href="<?= url('admin/' . $c['nom']) ?>"><b><?= elements_compter($c['nom'], true) ?></b><span><?= e($c['titre']) ?> en ligne</span></a>
  <?php endforeach; ?>
  <?php if ($messages_on): ?><a class="stat o" href="<?= url('admin/messages') ?>"><b><?= $n['messages7'] ?></b><span>Demandes ces 7 jours</span></a><?php endif; ?>
  <?php if ($articles_on): ?>
    <div class="stat"><b><?= $n['lectures'] ?></b><span>Lectures d’articles</span></div>
    <div class="stat"><b><?= $n['articles30'] ?></b><span>Articles publiés en 30 jours</span></div>
  <?php endif; ?>
  <a class="stat" href="<?= url('admin/medias') ?>"><b><?= $n['medias'] ?></b><span>Images hébergées</span></a>
  <?php if ($messages_on): ?><div class="stat"><b><?= $n['messages'] ?></b><span>Demandes au total</span></div><?php endif; ?>
</div>
<?php if ($articles_on): ?>
<div class="bord-tete"><h2>Les plus lus</h2></div>
<div class="tableau">
  <table>
    <thead><tr><th>Article</th><th>Publié</th><th class="chiffre">Lectures</th></tr></thead>
    <tbody>
    <?php foreach (tous("SELECT id, titre, publie_le, lectures FROM articles WHERE statut = 'publie' ORDER BY lectures DESC, publie_le DESC LIMIT 8") as $a): ?>
      <tr><td><a class="titre-ligne" href="<?= url('admin/articles/' . $a['id']) ?>"><?= e($a['titre']) ?></a></td><td><?= e(date_fr($a['publie_le'])) ?></td><td class="chiffre"><?= (int) $a['lectures'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php endif; ?>
