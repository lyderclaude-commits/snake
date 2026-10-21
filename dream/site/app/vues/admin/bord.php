<?php
/** Le tableau de bord : ce qui attend, ce qu'on a touché, où aller. Même plan que celui de Wakabi. */
$titre = 'Tableau de bord';
$classe_main = 'contenu bord';
$onglet = ($_GET['vue'] ?? '') === 'chiffres' ? 'chiffres' : 'faire';
$nonlus = tous('SELECT id, nom, besoin, cree_le FROM messages WHERE lu = 0 ORDER BY id LIMIT 8');
$brouillons = tous("SELECT id, titre, auteur, modifie_le FROM articles WHERE statut = 'brouillon' ORDER BY modifie_le DESC LIMIT 5");
$a_relire = (int) valeur("SELECT COUNT(*) FROM articles WHERE traduction_auto = 1 AND trim(titre_en) <> ''");
$a_faire = count($nonlus) + count($brouillons) + ($a_relire ? 1 : 0);
$recents = array_merge(
    array_map(fn($r) => $r + ['genre' => 'article', 'lien' => 'admin/articles/' . $r['id']], tous('SELECT id, titre, modifie_le quand, statut FROM articles ORDER BY modifie_le DESC LIMIT 4')),
    array_map(fn($r) => $r + ['genre' => 'campagne', 'lien' => 'admin/campagnes/' . $r['id']], tous('SELECT id, titre, modifie_le quand FROM campagnes ORDER BY modifie_le DESC LIMIT 4')),
);
usort($recents, fn($a, $b) => strcmp((string) $b['quand'], (string) $a['quand']));
$recents = array_slice($recents, 0, 4);
$n = [
    'articles' => (int) valeur("SELECT COUNT(*) FROM articles WHERE statut = 'publie'"),
    'brouillons' => (int) valeur("SELECT COUNT(*) FROM articles WHERE statut = 'brouillon'"),
    'campagnes' => (int) valeur('SELECT COUNT(*) FROM campagnes WHERE publie = 1'),
    'medias' => (int) valeur('SELECT COUNT(*) FROM medias'),
    'messages' => (int) valeur('SELECT COUNT(*) FROM messages'),
    'lectures' => (int) valeur('SELECT COALESCE(SUM(lectures), 0) FROM articles'),
    'messages7' => (int) valeur('SELECT COUNT(*) FROM messages WHERE cree_le >= ?', [maintenant(time() - 7 * 86400)]),
    'articles30' => (int) valeur("SELECT COUNT(*) FROM articles WHERE statut = 'publie' AND publie_le >= ?", [maintenant(time() - 30 * 86400)]),
    'comptes' => (int) valeur('SELECT COUNT(*) FROM utilisateurs'),
];
?>
<div class="bord-barre">
  <div class="bord-qui">
    <h1>Tableau de bord</h1>
    <p><?= e($me['nom']) ?> · <?= e(ROLES[$me['role']] ?? $me['role']) ?></p>
  </div>
  <form class="bord-chercher" action="<?= url('admin/recherche') ?>" method="get" role="search">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
    <input type="search" name="q" placeholder="Chercher un article, une campagne, un message" aria-label="Rechercher" id="bord-q">
    <kbd>/</kbd>
  </form>
  <details class="deroulant bord-nouveau">
    <summary>+ Nouveau <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" style="width:15px;height:15px"><polyline points="6 9 12 15 18 9"></polyline></svg></summary>
    <div class="volet">
      <a href="<?= url('admin/articles/nouveau') ?>">Article<small>Pour le blog</small></a>
      <a href="<?= url('admin/campagnes/nouveau') ?>">Campagne<small>Pour le portfolio</small></a>
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
      <span class="bord-titre"><?= e($m['nom']) ?><?= $m['besoin'] !== '' ? ' — ' . e($m['besoin']) : '' ?></span>
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
    <p class="aide bord-vide">Rien n’attend. Aucune demande de contact non lue, aucun brouillon en cours.</p>
  <?php endif; ?>
  <?php if ($n['articles'] === 0 && est_admin($me)): ?>
    <p class="bord-calme">Le blog est vide : <a href="<?= url('admin/import') ?>">importez les articles de l’ancien site WordPress</a>.</p>
  <?php endif; ?>
</div>

<?php if ($recents): ?>
<div class="bord-tete"><h2>Reprendre</h2><span class="aide">ce qui a été touché en dernier</span></div>
<div class="grille g4 bord-reprendre">
  <?php foreach ($recents as $r): ?>
    <a class="carte" href="<?= url($r['lien']) ?>">
      <span class="bord-genre <?= e($r['genre']) ?>"><?= $r['genre'] === 'article' ? 'Article' : 'Campagne' ?></span>
      <b><?= e($r['titre']) ?></b>
      <span class="aide"><?= isset($r['statut']) && $r['statut'] === 'brouillon' ? 'brouillon · ' : '' ?>modifié <?= e(il_y_a($r['quand'])) ?></span>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="bord-tete"><h2>Où aller</h2><span class="aide">seulement ce que votre rôle ouvre</span></div>
<div class="bord-pastilles">
  <a href="<?= url('admin/articles') ?>">Articles <span><?= $n['articles'] + $n['brouillons'] ?></span></a>
  <a href="<?= url('admin/campagnes') ?>">Campagnes <span><?= $n['campagnes'] ?></span></a>
  <a href="<?= url('admin/services') ?>">Services <span><?= (int) valeur('SELECT COUNT(*) FROM services') ?></span></a>
  <a href="<?= url('admin/temoignages') ?>">Témoignages <span><?= (int) valeur('SELECT COUNT(*) FROM temoignages') ?></span></a>
  <a href="<?= url('admin/clients') ?>">Logos clients <span><?= (int) valeur('SELECT COUNT(*) FROM clients') ?></span></a>
  <a href="<?= url('admin/medias') ?>">Médiathèque <span><?= $n['medias'] ?></span></a>
  <a href="<?= url('admin/messages') ?>">Messages <span><?= $n['messages'] ?></span></a>
  <?php if (est_admin($me)): ?>
    <a href="<?= url('admin/import') ?>">Import WordPress</a>
    <a href="<?= url('admin/reglages') ?>">Réglages</a>
    <a href="<?= url('admin/comptes') ?>">Comptes <span><?= $n['comptes'] ?></span></a>
  <?php endif; ?>
  <a href="<?= url('admin/profil') ?>">Mon profil</a>
</div>
<?php else: ?>
<div class="grille g4">
  <a class="stat p" href="<?= url('admin/articles?statut=publie') ?>"><b><?= $n['articles'] ?></b><span>Articles en ligne</span></a>
  <a class="stat" href="<?= url('admin/articles?statut=brouillon') ?>"><b><?= $n['brouillons'] ?></b><span>Brouillons</span></a>
  <a class="stat v" href="<?= url('admin/campagnes') ?>"><b><?= $n['campagnes'] ?></b><span>Campagnes publiées</span></a>
  <a class="stat o" href="<?= url('admin/messages') ?>"><b><?= $n['messages7'] ?></b><span>Demandes ces 7 jours</span></a>
  <div class="stat"><b><?= $n['lectures'] ?></b><span>Lectures d’articles</span></div>
  <div class="stat"><b><?= $n['articles30'] ?></b><span>Articles publiés en 30 jours</span></div>
  <a class="stat" href="<?= url('admin/medias') ?>"><b><?= $n['medias'] ?></b><span>Images hébergées</span></a>
  <div class="stat"><b><?= $n['messages'] ?></b><span>Demandes au total</span></div>
</div>
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
