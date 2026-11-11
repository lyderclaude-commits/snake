<?php
/**
 * Le tableau de bord, onglet « A faire » — refondu.
 *
 * L'ancien montage empilait une file, quatre cartes de reprise, seize
 * pastilles en vrac et une bande de chiffres : chaque bloc disait une
 * chose utile, mais aucun ne se lisait d'un coup d'oeil, et les
 * variations « +100 % » posées à côté d'un zéro ne voulaient rien dire.
 *
 * L'ordre choisi est celui d'une matinée :
 *   - les QUATRE chiffres d'action, chacun avec sa variation DITE
 *     (« en hausse », « stable », « nouveau ») et non crue ;
 *   - ce qui attend une décision, à côté de là où l'on s'était arrêté ;
 *   - la semaine, avec son graphique de vraies données journalières.
 *
 * Rien n'a disparu : la cinquième mesure (les vues) vit dans le
 * récapitulatif du bas, et chaque carte des chiffres mène à sa section.
 */
$fr = fn(int $n) => number_format($n, 0, ',', ' ');
$onglet = 'faire';

/* Les quatre indicateurs d'action, dans l'ordre de la lecture. */
$kpi = [];
foreach (['badges', 'telechargements', 'campagnes', 'comptes'] as $_cle) {
    if (isset($semaine[$_cle])) {
        $kpi[$_cle] = $semaine[$_cle];
    }
}

/* La variation, dite en français : « +100 % » à côté d'un zéro ne
   renseignait personne, et « nouveau » sans couleur se lisait comme
   une alerte. Chaque cas a son mot et sa teinte. */

$icone_kpi = [
    'badges' => 'ticket',
    'telechargements' => 'telechargement',
    'campagnes' => 'avion',
    'comptes' => 'crowd',
];

/* Chaque carte mène à sa section : les badges au catalogue, les
   téléchargements aux rapports, les campagnes à la régie, les comptes
   aux comptes. */
$lien_kpi = [
    'badges' => '?p=catalogue',
    'telechargements' => '?p=rapports',
    'campagnes' => '?p=regie',
    'comptes' => '?p=comptes',
];

/* Une icone du bord : le jeu du guide d'abord, le jeu de l'atelier
   ensuite. Les deux vivent sur la meme grille de 24 px, et un nom
   absent d'une carte ne doit pas laisser un trou. */
$icone_bord = static fn(string $nom, int $t = 18): string => icone_guide($nom, $t) ?: icone($nom);
$dire_variation = static function (array $k): array {
    $v = $k['variation'];
    if ($v === null) {
        return ['nouveau', 'neutre'];
    }
    if ($v > 0) {
        return ['▲ ' . $v . ' % vs semaine dernière', 'haut'];
    }
    if ($v < 0) {
        return ['▼ ' . abs($v) . ' % vs semaine dernière', 'bas'];
    }
    return ['stable vs semaine dernière', 'neutre'];
};


/* L'icone d'un type d'objet du journal. Un type inconnu tombe sur un
   sablier : il existe, il est vieux, et rien ne casse. */
$icone_type = [
    'decor' => 'ticket',
    'article' => 'affiche',
    'compte' => 'user',
    'campagne' => 'avion',
    'lien' => 'lien',
    'liste' => 'carnet',
    'offre' => 'recompense',
    'rapport' => 'analyse',
    'reglage' => 'reglage', 'reglages' => 'reglage',
    'sauvegarde' => 'horloge',
    'contact' => 'enveloppe',
];


/* Le graphique de la semaine : de VRAIES données journalières, pas un
   dessin. La dernière colonne est aujourd'hui, et se peint plus foncée. */
$_max_jour = 1;
foreach ($serie as $_s) {
    $_max_jour = max($_max_jour, (int) $_s['n']);
}
$_jours_fr = ['Mon' => 'lun', 'Tue' => 'mar', 'Wed' => 'mer', 'Thu' => 'jeu',
              'Fri' => 'ven', 'Sat' => 'sam', 'Sun' => 'dim'];
$_total_semaine = array_sum(array_column($serie, 'n'));
$_i_jour = 0;
$_n_jours = count($serie);
?>
<div class="contenu bord">
<?php require __DIR__ . '/partiels/bord-barre.php'; ?>

<!-- ---------- les quatre chiffres d'action ---------- -->
<div class="bord-kpis">
  <?php foreach ($kpi as $_cle => $k):
        [$mot, $ton] = $dire_variation($k);
        if ($_cle === 'campagnes' && $k['variation'] === null) {
            /* Un stock ne varie pas « vs semaine dernière » : il est là. */
            $mot = 'en cours';
        } ?>
  <a class="bord-kpi <?= e($_cle) ?>" href="<?= e(url($lien_kpi[$_cle] ?? '?p=admin&vue=chiffres')) ?>">
    <span class="bord-kpi-corps">
      <span class="bord-kpi-nom"><?= e($k['titre']) ?></span>
      <span class="bord-kpi-valeur"><?= $fr((int) $k['valeur']) ?></span>
      <span class="bord-kpi-variation <?= $ton ?>"><?= e($mot) ?></span>
    </span>
    <span class="bord-kpi-tuile"><?= $icone_bord($icone_kpi[$_cle] ?? 'ticket', 22) ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- ---------- à faire, à côté de reprendre ---------- -->
<div class="bord-deux<?= $reprendre ? '' : ' bord-deux-seul' ?>">
  <section class="carte bord-file">
    <div class="bord-tete">
      <h2>A traiter</h2>
      <span class="aide">du plus vieux au plus récent</span>
    </div>

    <?php if (!$a_faire): ?>
      <p class="aide bord-vide" role="status">Rien n'attend de décision. Aucune relecture en file.</p>
    <?php else: ?>
      <table class="bord-table">
        <thead>
          <tr><th>Type</th><th>Titre</th><th>Auteur</th><th>Ancienneté</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($a_faire as $l): ?>
          <tr>
            <td><span class="bord-genre <?= e($l['ton']) ?>"><?= e($l['genre']) ?></span></td>
            <td class="bord-t-td"><?= e($l['titre']) ?></td>
            <td class="bord-t-auteur"><?= e($l['qui']) ?></td>
            <td><span class="bord-age<?= $l['depuis'] !== '' && bord_urgent($l['depuis']) ? ' vieux' : '' ?>"><?= e($l['depuis'] !== '' ? bord_depuis($l['depuis']) : '') ?></span></td>
            <td class="bord-t-action"><a class="bouton petit<?= $l['depuis'] !== '' && bord_urgent($l['depuis']) ? '' : ' fant' ?>" href="<?= e(url($l['lien'])) ?>"><?= e($l['action']) ?></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if ($sans_urgence): ?>
      <p class="bord-calme">Sans urgence :
        <?php foreach ($sans_urgence as $i => [$ou, $n, $quoi]): ?>
          <?= $i ? ' · ' : '' ?><a href="<?= e(url($ou)) ?>"><?= (int) $n ?> <?= e($quoi) ?></a>
        <?php endforeach; ?>
      </p>
    <?php endif; ?>
  </section>

  <?php if ($reprendre): ?>
  <section class="carte bord-reprendre-carte">
    <div class="bord-tete">
      <h2>Reprendre</h2>
      <span class="aide">ce que vous avez touché en dernier</span>
    </div>
    <?php foreach ($reprendre as $r): ?>
      <a class="bord-reprendre-ligne" href="<?= e(url($r['lien'])) ?>">
        <span class="bord-reprendre-icone"><?= $icone_bord($icone_type[$r['ton']] ?? 'horloge') ?></span>
        <span class="bord-reprendre-quoi">
          <b><?= e($r['titre']) ?></b>
          <small><?= e($r['genre']) ?> · <?= e($r['quoi']) ?> · <?= e(bord_depuis($r['quand'])) ?></small>
        </span>
        <span class="bord-chev" aria-hidden="true">›</span>
      </a>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
</div>

<!-- ---------- les badges qui portent, et qui arrive ---------- -->
<?php $pluriel = fn(int $n, string $mot) => $n . ' ' . $mot . ($n > 1 ? 's' : ''); ?>
<div class="bord-deux">
  <section class="carte bord-file">
    <div class="bord-tete">
      <h2>Les badges qui portent</h2>
      <a href="<?= e(url('?p=catalogue')) ?>">Le catalogue complet</a>
    </div>
    <table class="bord-table">
      <thead>
        <tr><th>Badge</th><th>Auteur</th>
            <th class="bord-t-n">Vues</th><th class="bord-t-n">Badges</th>
            <th class="bord-t-n">Téléch.</th><th class="bord-t-n">Présence</th></tr>
      </thead>
      <tbody>
      <?php foreach ($tetes as $d): ?>
        <tr>
          <td class="bord-t-badge">
            <a class="bord-t-lien" href="<?= e(url('?p=modifier&id=' . urlencode($d['id']))) ?>"><?= e($d['titre']) ?></a>
            <span class="aide">/<?= e($d['slug']) ?></span>
          </td>
          <td class="bord-t-auteur"><?= e($d['auteur_nom'] ?: 'Équipe Wakabi') ?></td>
          <td class="bord-t-n"><?= $fr((int) $d['vues']) ?></td>
          <td class="bord-t-n"><?= $fr((int) $d['badges']) ?></td>
          <td class="bord-t-n"><?= $fr((int) $d['telechargements']) ?></td>
          <td class="bord-t-n"><?= (int) $d['presences'] ?><?= $d['badges']
            ? ' <span class="aide">(' . round($d['presences'] / $d['badges'] * 100) . ' %)</span>'
            : '' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$tetes): ?>
        <tr><td colspan="6" class="aide">Aucun badge publié pour l'instant.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="carte bord-inscrits">
    <div class="bord-tete">
      <h2>Derniers inscrits</h2>
      <?php if (droit($me, 'comptes')): ?>
        <a href="<?= e(url('?p=comptes')) ?>">Tout voir</a>
      <?php endif; ?>
    </div>
    <p class="bord-inscrits-sous">
      <?= e($pluriel((int) ($roles['partenaire'] ?? 0), 'Partenaire')) ?> ·
      <?= e($pluriel((int) ($roles['participant'] ?? 0), 'Utilisateur')) ?> ·
      <?= e($pluriel((int) ($roles['equipe'] ?? 0), 'membre')) ?> de l'équipe
    </p>
    <ul class="liste-comptes">
      <?php foreach ($nouveaux as $c): ?>
        <li>
          <span>
            <b><?= e($c['nom']) ?></b>
            <span class="aide"><?= e($c['email']) ?></span>
          </span>
          <?php /* Un compte interne se distingue au premier coup d'œil :
                   dans une liste de nouveaux inscrits, c'est le seul qui
                   ne devrait pas y être par accident. */ ?>
          <span class="pastille <?= interne($c) ? 'publie' : 'brouillon' ?>"><?= e(role_libelle($c['role'])) ?></span>
          <?php if (!interne($c)): ?>
            <span class="pastille formule"><?= e(formule_libelle($c['formule'] ?? null)) ?></span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>

<!-- ---------- la semaine : graphique et récapitulatif ---------- -->
<a class="carte plate bord-activite" href="<?= e(url('?p=rapports')) ?>">
  <span class="bord-activite-graph">
    <span class="bord-activite-tete">
      <span class="bord-semaine-titre">7 derniers jours</span>
      <span class="bord-semaine-tout">Tout voir ›</span>
    </span>
    <?php if ($_total_semaine === 0): ?>
      <span class="bord-histo-vide" role="status">Aucune activité sur la période : le graphique
      se remplira avec les téléchargements de badges.</span>
    <?php else: ?>
    <span class="bord-histo">
      <?php foreach ($serie as $_s): $_i_jour++; ?>
        <?php
        $_h = (int) round((int) $_s['n'] / $_max_jour * 100);
        $_label = $_jours_fr[gmdate('D', (int) strtotime($_s['jour']))] ?? '';
        ?>
        <span class="bord-col">
          <i style="height:<?= max(6, $_h) ?>%"<?= $_i_jour === $_n_jours ? ' class="aujourdhui"' : '' ?>></i>
          <small><?= e($_label) ?></small>
        </span>
      <?php endforeach; ?>
    </span>
    <?php endif; ?>
  </span>
  <span class="bord-activite-resume">
    <span class="bord-resume-titre">Cette semaine en un coup d’œil</span>
    <span class="bord-resume-sous">Répartition des indicateurs de la semaine</span>
    <?php $_barre_max = 1;
          foreach ($semaine as $_k) { $_barre_max = max($_barre_max, (int) $_k['valeur']); }
          $_barre_ton = ['badges' => '', 'telechargements' => 'violet', 'campagnes' => 'vert',
                         'vues' => 'orange', 'comptes' => '']; ?>
    <?php foreach ($semaine as $_cle2 => $k): ?>
      <span class="bord-resume-barre">
        <span class="bord-barre-l"><span><?= e($k['titre']) ?></span><span><?= $fr((int) $k['valeur']) ?></span></span>
        <span class="bord-barre-f"><i class="<?= $_barre_ton[$_cle2] ?? '' ?>" style="width:<?= max(3, (int) round((int) $k['valeur'] / $_barre_max * 100)) ?>%"></i></span>
      </span>
    <?php endforeach; ?>
  </span>
</a>
</div>
