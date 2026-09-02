<?php
/**
 * Le formulaire d'un décor : gabarit, cadre, campagne, apparence.
 *
 * L'apparence n'est pas décorative. C'est ce qui permet à l'équipe comme à
 * un organisateur de prendre un gabarit et d'en faire le leur : déplacer le
 * texte, changer sa couleur, choisir le coin du QR. Ce qu'ils ne peuvent pas
 * faire, c'est retirer le QR, le filigrane ou la zone photo — ce sont eux
 * qui font la différence avec un générateur d'images.
 */
$curseur = function (string $nom, string $libelle, float $min, float $max, float $pas, $valeur, string $aide = '') {
    ?>
    <div class="champ reglage">
      <label for="r-<?= e($nom) ?>"><?= e($libelle) ?>
        <output for="r-<?= e($nom) ?>" id="v-<?= e($nom) ?>"></output>
      </label>
      <input id="r-<?= e($nom) ?>" name="<?= e($nom) ?>" type="range"
             min="<?= $min ?>" max="<?= $max ?>" step="<?= $pas ?>" value="<?= e((string) $valeur) ?>">
      <?php if ($aide !== ''): ?><p class="aide"><?= e($aide) ?></p><?php endif; ?>
    </div>
    <?php
};
$liste = function (string $nom, string $libelle, array $choix, string $valeur, string $aide = '') {
    ?>
    <div class="champ reglage">
      <label for="r-<?= e($nom) ?>"><?= e($libelle) ?></label>
      <select id="r-<?= e($nom) ?>" name="<?= e($nom) ?>">
        <?php foreach ($choix as $k => $v): ?>
          <option value="<?= e((string) $k) ?>" <?= (string) $valeur === (string) $k ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($aide !== ''): ?><p class="aide"><?= e($aide) ?></p><?php endif; ?>
    </div>
    <?php
};
?>
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
      <p>Choisissez un gabarit, donnez-lui votre cadre, décrivez la campagne.
      L’apparence se règle ensuite, et l’aperçu suit chaque geste.</p>
    <?php endif; ?>
  </section>

  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" id="form-decor">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
    <input type="hidden" name="cadre_url" value="<?= e($valeurs['cadre_url']) ?>">
    <?php if ($modifie): ?><input type="hidden" name="id" value="<?= e($modifie['id']) ?>"><?php endif; ?>

    <div class="grille g2" style="align-items:start">
      <div class="carte">
        <h3 style="margin-bottom:14px">1 · Le gabarit</h3>
        <div class="champ">
          <label for="disposition">Format</label>
          <select id="disposition" name="disposition">
            <?php
            $groupes = [
              'Formats Wakabi' => ['bandeau', 'angle', 'story'],
              'Réseaux sociaux' => ['instagram', 'facebook', 'tiktok'],
              'Sur mesure' => ['vierge'],
            ];
            $par_id = [];
            foreach (dispositions() as $d) {
                $par_id[$d['id']] = $d;
            }
            // Filet : une disposition ajoutée sans être rangée dans un groupe
            // apparaît quand même, plutôt que de disparaître du formulaire.
            $rangees = array_merge(...array_values($groupes));
            $orphelines = array_diff(array_keys($par_id), $rangees);
            if ($orphelines) {
                $groupes['Autres'] = array_values($orphelines);
            }
            foreach ($groupes as $titre_groupe => $ids): ?>
              <optgroup label="<?= e($titre_groupe) ?>">
                <?php foreach ($ids as $id): $d = $par_id[$id]; ?>
                  <option value="<?= e($id) ?>" <?= $valeurs['disposition'] === $id ? 'selected' : '' ?>>
                    <?= e($d['nom']) ?> : <?= e($d['aide']) ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
          <p class="aide">Changer de format remet l’apparence aux réglages de ce gabarit.</p>
        </div>

        <h3 style="margin:20px 0 14px">2 · Le cadre</h3>
        <p class="aide si-vierge" style="margin:-8px 0 12px"<?= $valeurs['disposition'] === 'vierge' ? '' : ' hidden' ?>>
          La page blanche n’en demande pas : son décor tient au fond, à la fenêtre photo et au texte.
          Vous pouvez tout de même en ajouter un.
        </p>
        <div class="champ">
          <p class="pas">Votre fichier PNG ou WebP à fond transparent</p>
          <!-- Même traitement que dans le Studio : le libellé natif s'affiche
               dans la langue du navigateur, celui-ci est toujours en français. -->
          <input id="cadre" name="cadre" type="file" accept="image/png,image/webp" class="fichier-natif">
          <label class="bouton fant fichier" for="cadre">
            <?= icone('studio') ?><span class="texte">Choisir un fichier</span>
          </label>
          <?php if ($modifie && $valeurs['cadre_url']): ?>
            <p class="aide">Un cadre est déjà en place. N’en choisissez un que pour le remplacer.</p>
          <?php endif; ?>
          <p class="aide">2 Mo maximum. La photo de l’invité apparaîtra derrière : laissez donc
          le centre vide. Le SVG est refusé pour raison de sécurité.</p>
        </div>

        <?php $fournis = cadres_fournis(); if ($fournis && !$valeurs['cadre_url']): ?>
          <div class="champ">
            <label for="cadre_fourni">…ou partez d’un cadre fourni</label>
            <select id="cadre_fourni" name="cadre_fourni">
              <option value="">Aucun, je téléverse le mien</option>
              <?php foreach ($fournis as $nom => $c): ?>
                <option value="<?= e($nom) ?>" <?= $valeurs['cadre_fourni'] === $nom ? 'selected' : '' ?>>
                  <?= e($c['nom']) ?> · <?= e($c['ratio']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="aide">De quoi essayer un format tout de suite, sans passer par un graphiste.</p>
          </div>
        <?php endif; ?>

        <?php if ($valeurs['cadre_url']): ?>
          <div class="msg ok" style="margin:0">Cadre en place : il survivra à une erreur de saisie.</div>
        <?php endif; ?>
      </div>

      <div class="carte">
        <h3 style="margin-bottom:14px">3 · La campagne</h3>
        <div class="champ"><label for="titre">Titre</label>
          <input id="titre" name="titre" type="text" required value="<?= e($valeurs['titre']) ?>"></div>
        <div class="champ"><label for="sous_titre">Sous-titre</label>
          <input id="sous_titre" name="sous_titre" type="text" value="<?= e($valeurs['sous_titre']) ?>"></div>
        <div class="champ">
          <label for="accroche">Accroche sur le badge <span style="font-weight:400">(facultatif)</span></label>
          <input id="accroche" name="accroche" type="text" value="<?= e($valeurs['accroche']) ?>">
        </div>
        <div class="champ">
          <label for="champ_libelle">Libellé du champ à remplir <span style="font-weight:400">(facultatif)</span></label>
          <input id="champ_libelle" name="champ_libelle" type="text" value="<?= e($valeurs['champ_libelle']) ?>">
          <p class="aide">Laissez les deux vides pour un décor sans aucun texte : le cadre parle tout seul,
          et l’invité n’a rien à saisir.</p>
        </div>
        <?php
        /**
         * « Toutes les villes » est une ligne d'offre, pas une case.
         *
         * Le ciblage multi-villes s'achète à partir de Croissance. On
         * n'enlève pas l'option en silence : elle reste visible, désactivée,
         * avec ce qu'il faut pour l'obtenir — cacher une fonctionnalité
         * empêche de la vendre.
         */
        $peut_cibler = capacite($me, 'ciblage');
        ?>
        <div class="champ"><label for="ville">Ville</label>
          <select id="ville" name="ville">
            <?php foreach (['all' => 'Toutes', 'lome' => 'Lomé', 'cotonou' => 'Cotonou', 'abidjan' => 'Abidjan'] as $k => $v): ?>
              <option value="<?= e($k) ?>"
                      <?= $valeurs['ville'] === $k ? 'selected' : '' ?>
                      <?= $k === 'all' && !$peut_cibler ? 'disabled' : '' ?>>
                <?= e($v) ?><?= $k === 'all' && !$peut_cibler ? ' — offre Croissance' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (!$peut_cibler): ?>
            <p class="aide">Une campagne porte sur une ville. Le ciblage sur toutes les villes
            arrive avec l’offre Croissance.</p>
          <?php endif; ?>
        </div>
        <div class="champ"><label for="redirection">Page de destination après téléchargement</label>
          <input id="redirection" name="redirection" type="url" required value="<?= e($valeurs['redirection']) ?>">
          <p class="aide">Doit pointer vers un domaine Wakabi
          (<?= e(implode(', ', WAKABI_DOMAINES)) ?>) ou l’un de leurs sous-domaines.</p></div>
        <div class="champ"><label for="expire_le">Expiration <span style="font-weight:400">(facultatif)</span></label>
          <input id="expire_le" name="expire_le" type="date" value="<?= e($valeurs['expire_le']) ?>"></div>
      </div>
    </div>

    <div class="carte" style="margin-top:16px">
      <div class="rangee" style="justify-content:space-between;align-items:baseline">
        <h3 style="margin:0">4 · L’apparence</h3>
        <button class="bouton fant petit" type="button" id="apparence-defaut">Réglages du gabarit</button>
      </div>
      <p class="aide" style="margin:6px 0 16px">Tout se déplace sauf l’essentiel : le QR, le filigrane
      et la zone photo restent, où que vous les mettiez.</p>

      <div class="atelier">
        <div class="reglages">
          <?php $liste('texte_couleur', 'Couleur du texte', APPARENCE_COULEURS, (string) $valeurs['texte_couleur']); ?>
          <?php $liste('texte_align', 'Alignement', APPARENCE_ALIGNEMENTS, (string) $valeurs['texte_align']); ?>
          <?php $curseur('bloc_x', 'Marge gauche du texte', 0, 0.8, 0.01, $valeurs['bloc_x']); ?>
          <?php $curseur('bloc_y', 'Hauteur du texte', 0.02, 0.92, 0.005, $valeurs['bloc_y']); ?>
          <?php $curseur('bloc_w', 'Largeur du bloc', 0.15, 1, 0.01, $valeurs['bloc_w']); ?>
          <?php $curseur('accroche_taille', 'Taille de l’accroche', 0.02, 0.12, 0.002, $valeurs['accroche_taille']); ?>
          <?php $curseur('champ_taille', 'Taille du prénom', 0.014, 0.06, 0.002, $valeurs['champ_taille']); ?>
          <?php $liste('qr_position', 'Coin du QR Code', APPARENCE_QR, (string) $valeurs['qr_position']); ?>
          <?php $curseur('qr_taille', 'Taille du QR', 0.12, 0.28, 0.005, $valeurs['qr_taille'],
                         'En dessous de 0,12 un téléphone peine à le lire.'); ?>
          <?php $liste('filigrane_position', 'Coin du filigrane', APPARENCE_FILIGRANE, (string) $valeurs['filigrane_position']); ?>
        </div>

        <?php
        /**
         * Le format, pour TOUS les gabarits.
         *
         * Il n'appartenait qu'à la page blanche, et un cadre 4:5 téléversé
         * sur un gabarit carré s'aplatissait d'un quart sans que rien ne le
         * dise. Le cadre impose son format : le formulaire le relève tout
         * seul au téléversement, et laisse la main pour les cas où l'on sait
         * ce qu'on fait.
         *
         * La fenêtre photo, pour TOUS les gabarits aussi.
         *
         * Par défaut elle occupe le canevas entier : la photo est cadrée sur
         * l'image complète, et seule la part qui tombe dans l'ouverture du
         * cadre se voit. Dès que le cadre a une ouverture précise — un
         * médaillon, un rectangle incliné, un côté d'affiche — il faut le
         * dire ici, sinon l'invité règle son visage à l'aveugle et le
         * résultat n'est jamais celui qu'on attend.
         *
         * Une fois la fenêtre déclarée, tout se joue à l'intérieur : « remplir »
         * remplit l'ouverture, le zoom et le glissement s'y rapportent.
         */
        ?>
        <div class="reglages fenetre-photo" style="grid-column:1/-1">
          <div class="rangee" style="grid-column:1/-1;justify-content:space-between;align-items:baseline">
            <p class="pas" style="margin:0">Le format et la fenêtre photo</p>
            <button class="bouton fant petit" type="button" id="detecter-fenetre">Relever sur le cadre</button>
          </div>
          <?php $liste('format', 'Format du décor', FORMATS, (string) $valeurs['format'],
                       'Celui du cadre, sans quoi il serait étiré.'); ?>
          <?php $liste('photo_forme', 'Forme de la fenêtre', APPARENCE_FORMES, (string) $valeurs['photo_forme']); ?>
          <?php $curseur('photo_x', 'Fenêtre : marge gauche', 0, 0.9, 0.005, $valeurs['photo_x']); ?>
          <?php $curseur('photo_y', 'Fenêtre : marge haute', 0, 0.9, 0.005, $valeurs['photo_y']); ?>
          <?php $curseur('photo_w', 'Fenêtre : largeur', 0.08, 1, 0.005, $valeurs['photo_w']); ?>
          <?php $curseur('photo_h', 'Fenêtre : hauteur', 0.08, 1, 0.005, $valeurs['photo_h'],
                         'Le pointillé sur l’aperçu montre où la photo de l’invité se placera.'); ?>
        </div>

        <div class="reglages si-vierge"<?= $valeurs['disposition'] === 'vierge' ? '' : ' hidden' ?>>
          <p class="pas" style="grid-column:1/-1;margin:0 0 4px">Page blanche : le fond</p>
          <?php $liste('fond', 'Couleur de fond', APPARENCE_COULEURS, (string) $valeurs['fond'],
                       'Il apparaît partout où la photo ne va pas.'); ?>
        </div>

        <div class="apercu-boite">
          <canvas id="apercu" width="560" height="560" aria-label="Aperçu du décor"></canvas>
          <p class="aide" id="apercu-etat">Aperçu en cours…</p>
        </div>
      </div>

      <button class="bouton" type="submit" style="width:100%;justify-content:center;margin-top:18px">
        <?= $modifie ? 'Enregistrer les modifications' : 'Créer et enregistrer' ?>
      </button>
    </div>
  </form>

  <?php
  /**
   * Confier CETTE campagne, et rien d'autre.
   *
   * Le choix était binaire jusqu'ici : garder son compte pour soi, ou
   * donner son mot de passe. C'est le mot de passe qui circulait — avec
   * les statistiques, la régie, les liens et la facturation au bout.
   *
   * Seul l'auteur invite : un équipier qui pourrait en inviter d'autres
   * ferait perdre à l'auteur la vue de qui travaille chez lui.
   */
  ?>
  <?php if ($modifie && ($modifie['auteur_id'] === $me['id'] || droit($me, 'decors_tous'))): ?>
    <?php $equipiers = equipiers_de((string) $modifie['id']); ?>
    <div class="carte" style="margin-top:18px">
      <h3 style="margin:0 0 4px">Qui travaille sur cette campagne</h3>
      <p class="aide" style="margin:0 0 14px">Invitez un graphiste, un stagiaire, un
      co-organisateur : la personne pourra modifier <strong>cette campagne uniquement</strong>
      et la soumettre à la relecture. Elle ne verra ni vos autres campagnes, ni vos liens, ni
      votre régie, ni vos factures.</p>

      <?php if ($equipiers): ?>
        <ul class="liste-comptes" style="margin-bottom:14px">
          <?php foreach ($equipiers as $eq): ?>
            <li>
              <span><b><?= e((string) $eq['nom']) ?></b>
                <span class="aide"><?= e((string) $eq['email']) ?></span></span>
              <form method="post" action="<?= e(url('?p=equipier')) ?>" style="margin:0">
                <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                <input type="hidden" name="id" value="<?= e((string) $modifie['id']) ?>">
                <input type="hidden" name="quoi" value="retirer">
                <input type="hidden" name="qui" value="<?= e((string) $eq['utilisateur_id']) ?>">
                <button class="bouton fant petit" type="submit">Retirer</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <form method="post" action="<?= e(url('?p=equipier')) ?>" class="rangee"
            style="gap:8px;flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="id" value="<?= e((string) $modifie['id']) ?>">
        <div class="champ" style="margin:0;flex:1;min-width:220px">
          <label for="eq-email">Adresse e-mail de la personne</label>
          <input id="eq-email" name="email" type="email" required
                 placeholder="elle doit déjà avoir un compte">
        </div>
        <button class="bouton fant" type="submit">Lui confier</button>
      </form>
    </div>
  <?php endif; ?>

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

<script>
window.WAKABI_APERCU = {
  base: <?= json_encode(url('')) ?>,
  csrf: <?= json_encode(jeton_csrf()) ?>
};
</script>
<script src="<?= e(actif('public/apercu.js')) ?>" defer></script>
