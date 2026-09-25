<?php
/**
 * Le tableau de bord, onglet « Les chiffres ».
 *
 * TOUT ce que l'ancien écran montrait est ici, au caractère près : les
 * cinq indicateurs de la semaine, la boucle sur trente jours, la
 * répartition des offres, l'histogramme des téléchargements, les derniers
 * inscrits et le tableau des décors qui portent. Rien n'a été supprimé,
 * tout a été déplacé — le tableau de bord cesse d'être un rapport, et le
 * rapport a sa page.
 */
$fr = fn(int $n) => number_format($n, 0, ',', ' ');
$onglet = 'chiffres';
?>
<div class="contenu bord">
<?php require __DIR__ . '/partiels/bord-barre.php'; ?>

<p class="aide">
  <?= $fr($stats['publies']) ?> décors en ligne · <?= $fr($stats['comptes']) ?> comptes ·
  <?= $fr($stats['koris']) ?> Koris distribués
</p>

<!-- ---------- les sept derniers jours ---------- -->
<h2 class="titre-bloc">Les 7 derniers jours</h2>
<div class="grille kpis">
  <?php foreach ($semaine as $k): ?>
    <div class="stat">
      <b><?= $fr((int) $k['valeur']) ?></b>
      <span><?= e($k['titre']) ?></span>
      <?php
      $v = $k['variation'];
      $classe = $v === null ? 'plat' : ($v > 0 ? 'haut' : ($v < 0 ? 'bas' : 'plat'));
      $texte = $v === null ? 'nouveau' : ($v > 0 ? '+' . $v . ' %' : $v . ' %');
      ?>
      <span class="delta <?= $classe ?>" title="Semaine précédente : <?= (int) $k['avant'] ?>"><?= e($texte) ?></span>
    </div>
  <?php endforeach; ?>
</div>

<!-- ---------- le produit : la boucle, et qui arrive ---------- -->
<?php
/* Deux cartes de MÊME hauteur par rangée. « Les offres » et « Derniers
   inscrits » côte à côte laissaient un vide d'un demi-écran : l'une
   compte quatre lignes, l'autre six. On apparie les blocs par leur
   forme autant que par leur sujet — un tableau troué se lit comme un
   tableau cassé. */
?>
<div class="grille g2" style="align-items:start">
  <div class="carte">
    <h3>La boucle, sur 30 jours</h3>
    <p class="aide" style="margin-bottom:14px">De la page vue à la personne présente dans la salle.</p>
    <?php foreach ($boucle as $m): ?>
      <div class="marche">
        <div class="haut">
          <span><?= e($m['nom']) ?></span>
          <b><?= $fr((int) $m['n']) ?></b>
        </div>
        <div class="rail"><i style="width:<?= max(1.5, round($m['part'] * 100, 1)) ?>%"></i></div>
        <?php if ($m['passage'] !== null): ?>
          <span class="taux"><?= round($m['passage'] * 100) ?> % de l’étape précédente</span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="carte">
    <div class="rangee" style="justify-content:space-between">
      <h3 style="margin:0">Derniers inscrits</h3>
      <?php if (droit($me, 'comptes')): ?>
        <a class="aide" href="<?= e(url('?p=comptes')) ?>">Tout voir</a>
      <?php endif; ?>
    </div>
    <?php $pluriel = fn(int $n, string $mot) => $n . ' ' . $mot . ($n > 1 ? 's' : ''); ?>
    <p class="aide" style="margin:6px 0 12px">
      <?= e($pluriel((int) ($roles['partenaire'] ?? 0), 'organisateur')) ?> ·
      <?= e($pluriel((int) ($roles['participant'] ?? 0), 'participant')) ?> ·
      <?= e($pluriel((int) ($roles['equipe'] ?? 0), 'membre')) ?> de l’équipe
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
  </div>
</div>

<!-- ---------- l'argent et le rythme ---------- -->
<div class="grille g2" style="align-items:start">
  <div class="carte">
    <div class="rangee" style="justify-content:space-between">
      <h3 style="margin:0">Les offres</h3>
      <?php if (droit($me, 'reglages')): ?>
        <a class="aide" href="<?= e(url('?p=offres')) ?>">Modifier les offres</a>
      <?php endif; ?>
    </div>
    <p class="aide" style="margin:6px 0 14px">Hors comptes de l’équipe.</p>
    <?php
    $total_formules = max(1, array_sum($formules));
    foreach (formules() as $cle => $f):
        $n = $formules[$cle] ?? 0;
    ?>
      <div class="marche">
        <div class="haut">
          <span><?= e($f['nom']) ?>
            <span class="aide"><?= $f['prix'] ? $fr($f['prix']) . ' FCFA/mois' : 'gratuit' ?></span>
          </span>
          <b><?= (int) $n ?></b>
        </div>
        <div class="rail"><i style="width:<?= round($n / $total_formules * 100, 1) ?>%"></i></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="carte">
    <h3>Téléchargements, 14 derniers jours</h3>
    <?php $max = max(1, max(array_column($serie, 'n'))); ?>
    <div class="histo">
      <?php foreach ($serie as $j): ?>
        <div style="height:<?= (int) round($j['n'] / $max * 100) ?>%"
             title="<?= e($j['jour']) ?> : <?= (int) $j['n'] ?>"></div>
      <?php endforeach; ?>
    </div>
    <p class="aide" style="margin-top:8px">
      <?= $fr(array_sum(array_column($serie, 'n'))) ?> sur la période, pointe à <?= $fr($max) ?> en un jour.
    </p>
    <?php if ($stats['badges'] > 0): ?>
      <p class="aide" style="margin-top:10px;border-top:1px solid var(--border);padding-top:10px">
        <strong><?= (int) round($stats['presences'] / max(1, $stats['badges']) * 100) ?> %</strong>
        des badges émis depuis toujours ont été scannés à une entrée
        (<?= $fr($stats['presences']) ?> sur <?= $fr($stats['badges']) ?>).
      </p>
    <?php endif; ?>
  </div>
</div>

<!-- ---------- les décors qui portent ---------- -->
<div class="carte plate" style="padding:0;overflow:hidden">
  <div class="rangee" style="justify-content:space-between;padding:16px 18px 12px">
    <h3 style="margin:0">Les décors qui portent</h3>
    <a class="aide" href="<?= e(url('?p=catalogue')) ?>">Le catalogue complet</a>
  </div>
  <div class="tableau" style="border:0;border-radius:0">
    <table>
      <thead>
        <tr>
          <th>Décor</th><th>Auteur</th>
          <th class="chiffre">Vues</th><th class="chiffre">Badges</th>
          <th class="chiffre">Téléch.</th><th class="chiffre">Présence</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($tetes as $d): ?>
        <tr>
          <td>
            <a href="<?= e(url('?p=modifier&id=' . urlencode($d['id']))) ?>"><b><?= e($d['titre']) ?></b></a>
            <br><span class="aide">/<?= e($d['slug']) ?></span>
          </td>
          <td><?= e($d['auteur_nom'] ?: 'Équipe Wakabi') ?></td>
          <td class="mono chiffre"><?= $fr((int) $d['vues']) ?></td>
          <td class="mono chiffre"><?= $fr((int) $d['badges']) ?></td>
          <td class="mono chiffre"><?= $fr((int) $d['telechargements']) ?></td>
          <td class="mono chiffre">
            <?= (int) $d['presences'] ?><?= $d['badges']
              ? ' <span class="aide">(' . round($d['presences'] / $d['badges'] * 100) . ' %)</span>'
              : '' ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$tetes): ?>
        <tr><td colspan="6" class="aide">Aucun décor publié pour l’instant.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>
