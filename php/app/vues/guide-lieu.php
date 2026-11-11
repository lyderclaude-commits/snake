<?php
/**
 * La fiche d'un lieu.
 *
 * Cliquer un lieu de la liste ouvre CETTE page, comme dans l'application :
 * un héros photo avec le nom et l'adresse, la présentation et les
 * commodités à gauche, une carte « Infos pratiques » (contact, horaires,
 * boutons Appeler et Itinéraire) à droite, puis la carte intégrée.
 * Google Maps n'y est plus qu'un bouton — « Voir sur la carte » — et un
 * encart en fin de page, pas une destination qui emporte le visiteur
 * ailleurs avant qu'il ait lu.
 *
 * Les données viennent de l'API du backend officiel (`app/guide-api.php`).
 * Chaque section manque poliment si l'API ne la décrit pas : un lieu sans
 * horaires n'affiche pas un horaire vide.
 *
 * La carte intégrée est l'iframe publique de Google Maps (`output=embed`) :
 * sans clé, sans facturation, et sans quitter la page.
 */
$ville_label = $li['ville'] === 'cotonou' ? 'Cotonou' : 'Lomé';
$pays = $li['ville'] === 'cotonou' ? 'Bénin' : 'Togo';
$carte = api_carte($li['adresse'] !== '' ? $li['adresse'] . ', ' . $ville_label : $ville_label . ', ' . $pays);
$carte_iframe = 'https://www.google.com/maps?q=' . rawurlencode(($li['adresse'] !== '' ? $li['adresse'] . ', ' : '') . $ville_label . ', ' . $pays) . '&output=embed';
$tel_propre = preg_replace('~[^+0-9]~', '', $li['telephone']);
/* La galerie : les photos de l'API, sinon la photo seule du lieu.
   Le héros profite de la première photo si l'API n'a pas d'image. */
$galerie = $li['photos'];
if (!$galerie && $li['image'] !== '') {
    $galerie = [$li['image']];
}
$heros_image = $li['image'] !== '' ? $li['image'] : ($galerie[0] ?? '');
$paragraphe = fn(string $t): array => array_values(array_filter(array_map('trim', preg_split('~\n{2,}|\r\n{2,}~', $t) ?: []), fn(string $p) => $p !== ''));
?>
<section class="fiche-heros <?= $heros_image === '' ? api_visuel($li['nom']) : '' ?>">
  <?php if ($heros_image !== ''): ?>
    <img src="<?= e($heros_image) ?>" alt="<?= e($li['nom']) ?>">
  <?php endif; ?>
  <div class="fiche-heros-voile"></div>
  <div class="container fiche-heros-contenu">
    <div class="breadcrumb fiche-fil">
      <a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">></span><a href="<?= e(url('?p=' . $ville_page)) ?>">Sortir à <?= e($ville_label) ?></a><span class="breadcrumb-sep">></span><span><?= e($li['nom']) ?></span>
    </div>
    <h1><?= e($li['nom']) ?></h1>
    <div class="fiche-heros-meta">
      <?php if ($li['categorie'] !== ''): ?><span class="tag tag-clair"><?= e($li['categorie']) ?></span><?php endif; ?>
      <?php if ($li['adresse'] !== ''): ?>
        <span class="fiche-ou"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"></path><circle cx="12" cy="10" r="2.6"></circle></svg><?= e($li['adresse']) ?>, <?= e($ville_label) ?></span>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">

    <div class="fiche-grille">

      <div class="fiche-principal">
        <?php if ($li['info'] !== ''): ?>
          <p class="fiche-info">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 8h.01M12 11v5"></path></svg>
            <?= e($li['info']) ?>
          </p>
        <?php endif; ?>

        <?php if ($li['description'] !== ''): ?>
          <div class="fiche-bloc">
            <h2 class="fiche-titre">Présentation</h2>
            <?php foreach ($paragraphe($li['description']) as $_p): ?>
              <p class="fiche-texte"><?= e($_p) ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($li['commodites']): ?>
          <div class="fiche-bloc">
            <h2 class="fiche-titre">Commodités</h2>
            <ul class="fiche-commodites">
              <?php foreach ($li['commodites'] as $c): ?>
                <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg><?= e($c) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if ($galerie): ?>
          <div class="fiche-bloc">
            <h2 class="fiche-titre">Galerie</h2>
            <div class="fiche-galerie">
              <?php foreach ($galerie as $i => $ph): ?>
                <button class="fiche-photo" type="button" aria-label="Agrandir la photo <?= $i + 1 ?> sur <?= count($galerie) ?>">
                  <img src="<?= e($ph) ?>" alt="<?= e($li['nom']) ?> en photo, <?= $i + 1 ?> sur <?= count($galerie) ?>" loading="lazy">
                </button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <aside class="fiche-cote">
        <div class="fiche-infos">
          <h3>Infos pratiques</h3>

          <?php if ($li['adresse'] !== ''): ?>
            <p class="fiche-infos-ligne">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"></path><circle cx="12" cy="10" r="2.6"></circle></svg>
              <span><b><?= e($ville_label) ?>, <?= e($pays) ?></b><br><?= e($li['adresse']) ?></span>
            </p>
          <?php endif; ?>

          <?php if ($li['telephone'] !== ''): ?>
            <p class="fiche-infos-ligne">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
              <a href="tel:<?= e($tel_propre) ?>"><?= e($li['telephone']) ?></a>
            </p>
          <?php endif; ?>

          <div class="fiche-actions">
            <?php if ($li['telephone'] !== ''): ?>
              <a class="btn btn-primary" href="tel:<?= e($tel_propre) ?>">Appeler</a>
            <?php endif; ?>
            <a class="btn btn-outline" href="<?= e($carte) ?>"<?= sortie_externe($carte) ?>>Itinéraire</a>
          </div>

          <?php if ($li['horaires']): ?>
            <hr class="fiche-sep">
            <div class="fiche-horaires">
              <h4>Horaires d'ouverture</h4>
              <?php foreach ($li['horaires'] as $h): ?>
                <div class="fiche-horaires-row">
                  <span><?= e($h['jours']) ?></span>
                  <?php if ($h['heures'] !== ''): ?><b><?= e($h['heures']) ?></b><?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </aside>

    </div>

    <div class="rangee-tete fiche-carte-tete">
      <h2 class="headline headline-sm">Localisation</h2>
      <a class="btn btn-outline btn-sm" href="<?= e($carte) ?>"<?= sortie_externe($carte) ?>>Voir sur la carte</a>
    </div>
    <div class="fiche-carte">
      <iframe src="<?= e($carte_iframe) ?>" title="Carte - <?= e($li['nom']) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
    </div>

    <div class="encart-ville">
      <h3>Vous gérez ce lieu ?</h3>
      <p>Prenez la main sur cette fiche : photos, horaires, événements et billetterie, gratuitement depuis l'application Wakabi.</p>
      <a class="btn btn-primary btn-lg" href="<?= e(url('?p=partenaires')) ?>">Devenir Partenaire</a>
    </div>

  </div>
</section>
