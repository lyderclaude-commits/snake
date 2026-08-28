<?php
/** Les liens courts : en créer un, voir les clics, en supprimer. */
$illimite = $max < 0;
$plein = !$illimite && $max > 0 && $utilises >= $max;
$ferme = $max === 0;
$total = 0;
foreach ($liste as $l) {
    $total += (int) $l['clics'];
}
?>
<div class="contenu">
  <section class="entete">
    <h1>Liens courts</h1>
    <p>Une adresse courte à mettre sur une affiche ou dans un message, et le nombre de
    personnes qui l’ont réellement suivie.</p>
  </section>

  <?php if (!empty($_GET['ok'])): ?><div class="msg ok" role="status"><?= e($_GET['ok']) ?></div><?php endif; ?>
  <?php if (!empty($_GET['err'])): ?><div class="msg err" role="alert"><?= e($_GET['err']) ?></div><?php endif; ?>

  <div class="grille g4" style="margin-bottom:18px">
    <div class="stat p"><b><?= $utilises ?><?= $illimite ? '' : ' / ' . $max ?></b><span>liens</span></div>
    <div class="stat o"><b><?= $total ?></b><span>clics au total</span></div>
  </div>

  <?php if ($ferme): ?>
    <div class="carte">
      <h3 style="margin:0 0 6px">Les liens courts arrivent avec l’offre Impact</h3>
      <p class="aide" style="margin:0 0 14px">Votre offre
      <?= e(formule_libelle($me['formule'] ?? null)) ?> n’en comprend pas. Impact en donne 20,
      Croissance 100, Mouvement sans limite — avec le compte des clics pour chacun.</p>
      <a class="bouton" href="<?= e(url('#tarifs')) ?>">Voir les offres</a>
    </div>
  <?php else: ?>
    <div class="carte">
      <h3 style="margin:0 0 4px">Créer un lien</h3>
      <p class="aide" style="margin:0 0 16px">L’adresse peut mener n’importe où : votre billetterie,
      votre page Facebook, une fiche Wakabi. Le lien, lui, reste court et se compte.</p>

      <?php if ($plein): ?>
        <div class="msg err" style="margin:0">
          <strong>Vos <?= $max ?> liens sont pris.</strong>
          <p style="margin:.35em 0 0">Supprimez-en un pour en créer un autre, ou passez à
          l’offre supérieure.</p>
        </div>
      <?php else: ?>
        <form method="post" action="<?= e(url('?p=creer-lien')) ?>">
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <div class="grille g2">
            <div class="champ" style="grid-column:1/-1">
              <label for="cible">Adresse de destination</label>
              <input id="cible" name="cible" type="url" required placeholder="https://…">
            </div>
            <div class="champ">
              <label for="titre-lien">À quoi il sert <span style="font-weight:400">(pour vous)</span></label>
              <input id="titre-lien" name="titre" type="text" placeholder="Affiche du 12 septembre">
            </div>
            <div class="champ">
              <label for="decor_id">Campagne liée <span style="font-weight:400">(facultatif)</span></label>
              <select id="decor_id" name="decor_id">
                <option value="">Aucune</option>
                <?php foreach ($campagnes as $c): ?>
                  <option value="<?= e($c['id']) ?>"><?= e($c['titre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <button class="bouton" type="submit" style="margin-top:12px">Créer le lien</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($liste): ?>
    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 10px">Vos liens</h3>
      <?php foreach ($liste as $l): ?>
        <div style="border-top:1px solid var(--border);padding:12px 0">
          <div class="rangee" style="justify-content:space-between;gap:12px;align-items:baseline">
            <div style="min-width:0">
              <a class="mono" href="<?= e(lien_court_url((string) $l['code'])) ?>"
                 style="font-weight:700"><?= e(lien_court_url((string) $l['code'])) ?></a>
              <?php if ($l['titre']): ?>
                <span class="aide" style="margin-left:8px"><?= e($l['titre']) ?></span>
              <?php endif; ?>
              <p class="aide" style="margin:4px 0 0;overflow-wrap:anywhere">
                → <?= e($l['cible']) ?>
                <?php if ($l['decor_titre']): ?> · campagne <?= e($l['decor_titre']) ?><?php endif; ?>
              </p>
            </div>
            <div class="rangee" style="gap:10px;flex-wrap:nowrap;align-items:baseline">
              <span><b style="font-size:1.1rem"><?= (int) $l['clics'] ?></b>
                <span class="aide">clic<?= (int) $l['clics'] > 1 ? 's' : '' ?></span></span>
              <form method="post" action="<?= e(url('?p=supprimer-lien')) ?>" style="margin:0">
                <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                <input type="hidden" name="code" value="<?= e($l['code']) ?>">
                <button class="bouton fant petit" type="submit">Supprimer</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <p class="aide" style="margin:14px 0 0">Supprimer un lien le rend introuvable : les
      affiches déjà imprimées ne mèneront plus nulle part.</p>
    </div>
  <?php endif; ?>
</div>
