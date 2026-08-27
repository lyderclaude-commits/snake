<div class="contenu">
  <section class="entete">
    <h1><?= $modifie ? 'Modifier le décor' : 'Nouveau décor' ?></h1>
    <?php if ($modifie): ?>
      <p><?= e($modifie['titre']) ?>
        <span class="pastille <?= e($modifie['statut']) ?>"><?= e(statut_libelle($modifie['statut'])) ?></span>
      </p>
      <p class="aide">L’adresse du décor (<code>/<?= e($modifie['slug']) ?></code>) ne change pas :
      elle vit dans des liens déjà partagés et dans les QR des badges déjà téléchargés.</p>
    <?php else: ?>
      <p>Choisissez une disposition, téléversez votre cadre, décrivez la campagne.
      Les positions du texte et de la zone photo sont déjà réglées.</p>
    <?php endif; ?>
  </section>

  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="grille g2" style="align-items:start">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
    <input type="hidden" name="cadre_url" value="<?= e($valeurs['cadre_url']) ?>">
    <?php if ($modifie): ?><input type="hidden" name="id" value="<?= e($modifie['id']) ?>"><?php endif; ?>

    <div class="carte">
      <h3 style="margin-bottom:14px">1 · La disposition</h3>
      <div class="champ">
        <label for="disposition">Gabarit</label>
        <select id="disposition" name="disposition">
          <?php foreach (dispositions() as $l): ?>
            <option value="<?= e($l['id']) ?>" <?= $valeurs['disposition'] === $l['id'] ? 'selected' : '' ?>>
              <?= e($l['nom']) ?> : <?= e($l['aide']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <h3 style="margin:20px 0 14px">2 · Votre cadre</h3>
      <div class="champ">
        <label for="cadre">Fichier PNG ou WebP à fond transparent</label>
        <input id="cadre" name="cadre" type="file" accept="image/png,image/webp">
        <?php if ($modifie && $valeurs['cadre_url']): ?>
          <p class="aide">Un cadre est déjà en place. N’en choisissez un que pour le remplacer.</p>
        <?php endif; ?>
        <p class="aide">2 Mo maximum. La photo de l’invité apparaîtra derrière : laissez donc
        le centre vide. Le SVG est refusé pour raison de sécurité.</p>
      </div>
      <?php if ($valeurs['cadre_url']): ?>
        <div class="msg ok" style="margin:0">Cadre téléversé : il survivra à une erreur de saisie.</div>
      <?php endif; ?>
    </div>

    <div class="carte">
      <h3 style="margin-bottom:14px">3 · La campagne</h3>
      <div class="champ"><label for="titre">Titre</label>
        <input id="titre" name="titre" type="text" required value="<?= e($valeurs['titre']) ?>"></div>
      <div class="champ"><label for="sous_titre">Sous-titre</label>
        <input id="sous_titre" name="sous_titre" type="text" value="<?= e($valeurs['sous_titre']) ?>"></div>
      <div class="champ"><label for="accroche">Accroche sur le badge</label>
        <input id="accroche" name="accroche" type="text" required value="<?= e($valeurs['accroche']) ?>"></div>
      <div class="champ"><label for="champ_libelle">Libellé du champ à remplir</label>
        <input id="champ_libelle" name="champ_libelle" type="text" value="<?= e($valeurs['champ_libelle']) ?>"></div>
      <div class="champ"><label for="ville">Ville</label>
        <select id="ville" name="ville">
          <?php foreach (['all' => 'Toutes', 'lome' => 'Lomé', 'cotonou' => 'Cotonou', 'abidjan' => 'Abidjan'] as $k => $v): ?>
            <option value="<?= e($k) ?>" <?= $valeurs['ville'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="champ"><label for="redirection">Page de destination après téléchargement</label>
        <input id="redirection" name="redirection" type="url" required value="<?= e($valeurs['redirection']) ?>">
        <p class="aide">Doit pointer vers un domaine Wakabi
        (<?= e(implode(', ', WAKABI_DOMAINES)) ?>) ou l’un de leurs sous-domaines.</p></div>
      <div class="champ"><label for="expire_le">Expiration <span style="font-weight:400">(facultatif)</span></label>
        <input id="expire_le" name="expire_le" type="date" value="<?= e($valeurs['expire_le']) ?>"></div>

      <button class="bouton" type="submit" style="width:100%;justify-content:center">
        <?= $modifie ? 'Enregistrer les modifications' : 'Créer et enregistrer' ?>
      </button>
    </div>
  </form>

  <div class="carte plate" style="margin-top:18px">
    <h3 style="margin-bottom:10px">Ce que la relecture vérifie</h3>
    <ul style="color:var(--text2);margin:0;padding-left:1.1em;font-size:.92rem">
      <li>La zone photo reste visible : un cadre opaque est refusé</li>
      <li>Les textes tiennent dans le cadre</li>
      <li>Aucun texte sous le filigrane ni sous le QR</li>
      <li>Format et poids du cadre soutenables en 3G</li>
      <li>La redirection pointe vers un domaine Wakabi</li>
    </ul>
  </div>
</div>
