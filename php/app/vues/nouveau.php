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
  <section class="entete" style="padding-bottom:12px">
    <h1><?= $modifie ? 'Modifier le décor' : 'Nouveau décor' ?></h1>
    <?php if ($modifie): ?>
      <p><?= e($modifie['titre']) ?>
        <span class="pastille <?= e($modifie['statut']) ?>"><?= e(statut_libelle($modifie['statut'])) ?></span>
      </p>
      <p class="aide">L’adresse du décor (<code>/<?= e($modifie['slug']) ?></code>) ne change pas :
      elle vit dans des liens déjà partagés et dans les QR des badges déjà téléchargés.</p>
    <?php else: ?>
      <p>Trois étapes, et l’aperçu suit chaque geste.</p>
    <?php endif; ?>
  </section>

  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <?php
  /**
   * Deux colonnes, et l'aperçu ne quitte jamais l'écran.
   *
   * Il vivait sous les dix-sept réglages d'apparence, à deux mille pixels
   * du haut de la page. Tirer « marge gauche du texte » ne montrait donc
   * rien : on réglait, on descendait voir, on remontait corriger. Le
   * défaut n'était pas d'avoir un aperçu tardif, c'était de rendre chaque
   * réglage aveugle — et un réglage aveugle se fait au hasard.
   *
   * Il est maintenant COLLÉ à droite, avec le bouton d'enregistrement et
   * la liste de ce que la relecture vérifie : les trois choses dont on a
   * besoin à tout instant, jamais à chercher.
   */
  ?>
  <form method="post" enctype="multipart/form-data" id="form-decor" class="sd">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
    <input type="hidden" name="cadre_url" value="<?= e($valeurs['cadre_url']) ?>">
    <?php if ($modifie): ?><input type="hidden" name="id" value="<?= e($modifie['id']) ?>"><?php endif; ?>

    <div class="sd-gauche">
      <?php
      /**
       * Les étapes sont des ONGLETS, pas un tunnel.
       *
       * Cet écran sert deux gestes très différents : créer un décor, où
       * l'ordre aide, et en corriger un mot six semaines plus tard, où un
       * tunnel obligerait à retraverser trois pages pour changer une
       * date. Les panneaux restent donc tous accessibles en un clic, et
       * les boutons « Continuer » ne guident que la première fois.
       *
       * Rien n'est retiré du document : un panneau caché garde ses champs,
       * qui partent avec le formulaire. C'est ce qui permet d'enregistrer
       * depuis n'importe quelle étape.
       */
      $etapes = [
        'cadre'     => ['Le cadre',     'Format et image de fond'],
        'campagne'  => ['La campagne',  'Textes, ville, destination'],
        'apparence' => ['L’apparence',  'Placement et tailles'],
      ];
      ?>
      <nav class="sd-etapes" role="tablist" aria-label="Étapes de la création">
        <?php $n = 0; foreach ($etapes as $cle => [$nom, $sous]): $n++; ?>
          <button type="button" role="tab" class="sd-etape<?= $n === 1 ? ' actif' : '' ?>"
                  id="onglet-<?= e($cle) ?>" aria-controls="panneau-<?= e($cle) ?>"
                  aria-selected="<?= $n === 1 ? 'true' : 'false' ?>" data-etape="<?= e($cle) ?>">
            <span class="sd-num"><?= $n ?></span>
            <span class="sd-nom"><?= e($nom) ?><small><?= e($sous) ?></small></span>
          </button>
        <?php endforeach; ?>
      </nav>

      <!-- ═══════════ 1 · Le cadre ═══════════ -->
      <section class="carte sd-panneau" id="panneau-cadre" role="tabpanel" aria-labelledby="onglet-cadre">
        <div class="champ">
          <label for="disposition">Gabarit</label>
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
          <p class="aide">Changer de gabarit remet l’apparence à ses réglages d’origine.</p>
        </div>

        <p class="aide si-vierge"<?= $valeurs['disposition'] === 'vierge' ? '' : ' hidden' ?>>
          La page blanche n’en demande pas : son décor tient au fond, à la fenêtre photo et au texte.
          Vous pouvez tout de même en ajouter un.
        </p>

        <div class="champ">
          <p class="pas">Votre image de cadre</p>
          <!-- Même traitement que dans le Studio : le libellé natif s'affiche
               dans la langue du navigateur, celui-ci est toujours en français. -->
          <input id="cadre" name="cadre" type="file" accept="image/png,image/webp" class="fichier-natif">
          <label class="bouton fant fichier" for="cadre">
            <?= icone('studio') ?><span class="texte">Choisir un fichier</span>
          </label>
          <?php if ($modifie && $valeurs['cadre_url']): ?>
            <p class="aide">Un cadre est déjà en place. N’en choisissez un que pour le remplacer.</p>
          <?php endif; ?>
          <p class="aide">PNG ou WebP à fond transparent, 2 Mo maximum. La photo de l’invité
          apparaîtra derrière : laissez donc le centre vide. Le SVG est refusé pour raison de sécurité.</p>
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
          <div class="msg ok" style="margin:0 0 14px">Cadre en place : il survivra à une erreur de saisie.</div>
        <?php endif; ?>

        <?php
        /**
         * Le format se règle ICI, avec le cadre, parce qu'il en dépend.
         *
         * Il vivait avec l'apparence, trois écrans plus bas. Or c'est le
         * cadre qui l'impose : un fichier 4:5 posé sur un gabarit carré
         * s'aplatit d'un quart, et l'on ne comprend le défaut qu'en
         * revenant sur ses pas.
         */
        ?>
        <div class="reglages">
          <?php $liste('format', 'Format du décor', FORMATS, (string) $valeurs['format'],
                       'Celui du cadre, sans quoi il serait étiré.'); ?>
        </div>

        <div class="reglages si-vierge"<?= $valeurs['disposition'] === 'vierge' ? '' : ' hidden' ?>>
          <?php $liste('fond', 'Couleur de fond', APPARENCE_COULEURS, (string) $valeurs['fond'],
                       'Il apparaît partout où la photo ne va pas.'); ?>
        </div>

        <div class="sd-suite">
          <button class="bouton fant" type="button" data-vers="campagne">La campagne →</button>
        </div>
      </section>

      <!-- ═══════════ 2 · La campagne ═══════════ -->
      <section class="carte sd-panneau" id="panneau-campagne" role="tabpanel"
               aria-labelledby="onglet-campagne" hidden>
        <div class="champ"><label for="titre">Titre</label>
          <input id="titre" name="titre" type="text" required value="<?= e($valeurs['titre']) ?>"></div>
        <div class="champ"><label for="sous_titre">Sous-titre</label>
          <input id="sous_titre" name="sous_titre" type="text" value="<?= e($valeurs['sous_titre']) ?>"></div>

        <div class="sd-duo">
          <div class="champ">
            <label for="accroche">Accroche sur le badge <span style="font-weight:400">(facultatif)</span></label>
            <input id="accroche" name="accroche" type="text" value="<?= e($valeurs['accroche']) ?>">
          </div>
          <div class="champ">
            <label for="champ_libelle">Libellé du champ à remplir <span style="font-weight:400">(facultatif)</span></label>
            <input id="champ_libelle" name="champ_libelle" type="text" value="<?= e($valeurs['champ_libelle']) ?>">
          </div>
        </div>
        <p class="aide" style="margin-top:-6px">Laissez les deux vides pour un décor sans aucun texte :
        le cadre parle tout seul, et l’invité n’a rien à saisir.</p>

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
        <div class="sd-duo">
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
          <div class="champ"><label for="expire_le">Expiration <span style="font-weight:400">(facultatif)</span></label>
            <input id="expire_le" name="expire_le" type="date" value="<?= e($valeurs['expire_le']) ?>">
            <p class="aide">Passée cette date, le décor cesse de produire des badges.</p></div>
        </div>

        <div class="champ"><label for="redirection">Page de destination après téléchargement</label>
          <input id="redirection" name="redirection" type="url" required value="<?= e($valeurs['redirection']) ?>">
          <p class="aide">Doit pointer vers un domaine Wakabi
          (<?= e(implode(', ', WAKABI_DOMAINES)) ?>) ou l’un de leurs sous-domaines.</p></div>

        <div class="sd-suite">
          <button class="bouton fant" type="button" data-vers="cadre">← Le cadre</button>
          <button class="bouton fant" type="button" data-vers="apparence">L’apparence →</button>
        </div>
      </section>

      <!-- ═══════════ 3 · L'apparence ═══════════ -->
      <section class="carte sd-panneau" id="panneau-apparence" role="tabpanel"
               aria-labelledby="onglet-apparence" hidden>
        <div class="rangee" style="justify-content:space-between;align-items:baseline;margin-bottom:4px">
          <p class="aide" style="margin:0;max-width:34ch">Tout se déplace sauf l’essentiel : le QR,
          le filigrane et la zone photo restent, où que vous les mettiez.</p>
          <button class="bouton fant petit" type="button" id="apparence-defaut">Réglages du gabarit</button>
        </div>

        <?php
        /**
         * Dix-sept réglages en trois groupes nommés.
         *
         * En une seule grille, on cherchait « taille du QR » parmi les
         * curseurs du texte. Trois groupes de cinq ou six se parcourent
         * d'un regard, et surtout : on sait quand on a fini l'un d'eux.
         */
        ?>
        <fieldset class="sd-groupe">
          <legend>Le texte</legend>
          <div class="reglages">
            <?php $liste('texte_couleur', 'Couleur', APPARENCE_COULEURS, (string) $valeurs['texte_couleur']); ?>
            <?php $liste('texte_align', 'Alignement', APPARENCE_ALIGNEMENTS, (string) $valeurs['texte_align']); ?>
            <?php $curseur('bloc_x', 'Marge gauche', 0, 0.8, 0.01, $valeurs['bloc_x']); ?>
            <?php $curseur('bloc_y', 'Hauteur', 0.02, 0.92, 0.005, $valeurs['bloc_y']); ?>
            <?php $curseur('bloc_w', 'Largeur du bloc', 0.15, 1, 0.01, $valeurs['bloc_w']); ?>
            <?php $curseur('accroche_taille', 'Taille de l’accroche', 0.02, 0.12, 0.002, $valeurs['accroche_taille']); ?>
            <?php $curseur('champ_taille', 'Taille du prénom', 0.014, 0.06, 0.002, $valeurs['champ_taille']); ?>
          </div>
        </fieldset>

        <fieldset class="sd-groupe">
          <legend>Le QR et le filigrane</legend>
          <div class="reglages">
            <?php $liste('qr_position', 'Coin du QR Code', APPARENCE_QR, (string) $valeurs['qr_position']); ?>
            <?php $curseur('qr_taille', 'Taille du QR', 0.12, 0.28, 0.005, $valeurs['qr_taille'],
                           'En dessous de 0,12 un téléphone peine à le lire.'); ?>
            <?php $liste('filigrane_position', 'Coin du filigrane', APPARENCE_FILIGRANE, (string) $valeurs['filigrane_position']); ?>
          </div>
        </fieldset>

        <?php
        /**
         * La fenêtre photo, pour TOUS les gabarits.
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
        <fieldset class="sd-groupe fenetre-photo">
          <legend>La fenêtre photo</legend>
          <div class="rangee" style="justify-content:space-between;align-items:baseline;margin-bottom:10px">
            <p class="aide" style="margin:0;max-width:32ch">Le pointillé sur l’aperçu montre où la
            photo de l’invité se placera.</p>
            <button class="bouton fant petit" type="button" id="detecter-fenetre">Relever sur le cadre</button>
          </div>
          <div class="reglages">
            <?php $liste('photo_forme', 'Forme', APPARENCE_FORMES, (string) $valeurs['photo_forme']); ?>
            <?php $curseur('photo_x', 'Marge gauche', 0, 0.9, 0.005, $valeurs['photo_x']); ?>
            <?php $curseur('photo_y', 'Marge haute', 0, 0.9, 0.005, $valeurs['photo_y']); ?>
            <?php $curseur('photo_w', 'Largeur', 0.08, 1, 0.005, $valeurs['photo_w']); ?>
            <?php $curseur('photo_h', 'Hauteur', 0.08, 1, 0.005, $valeurs['photo_h']); ?>
          </div>
        </fieldset>

        <div class="sd-suite">
          <button class="bouton fant" type="button" data-vers="campagne">← La campagne</button>
        </div>
      </section>
    </div>

    <!-- ═══════════ l'aperçu, qui ne bouge pas ═══════════ -->
    <aside class="sd-apercu">
      <div class="carte sd-carte-apercu">
        <div class="apercu-boite">
          <canvas id="apercu" width="560" height="560" aria-label="Aperçu du décor"></canvas>
          <p class="aide" id="apercu-etat">Aperçu en cours…</p>
        </div>

        <button class="bouton sd-enregistrer" type="submit">
          <?= $modifie ? 'Enregistrer les modifications' : 'Créer et enregistrer' ?>
        </button>
        <p class="aide sd-note">Vous pouvez enregistrer depuis n’importe quelle étape.</p>
      </div>

      <?php
      /**
       * La liste de relecture, à côté de l'aperçu et non en pied de page.
       *
       * C'est pendant qu'on règle qu'elle sert : lue après l'envoi, elle
       * n'apprend plus qu'une chose, c'est qu'il faut recommencer.
       */
      ?>
      <details class="carte plate sd-relecture">
        <summary>Ce que la relecture vérifie</summary>
        <ul>
          <li>La zone photo reste visible : un cadre opaque est refusé</li>
          <li>Les textes tiennent dans le cadre</li>
          <li>Aucun texte sous le filigrane ni sous le QR</li>
          <li>Format et poids du cadre soutenables en 3G</li>
          <li>La redirection pointe vers un domaine Wakabi</li>
        </ul>
      </details>
    </aside>
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

</div>

<script>
/**
 * Les étapes : montrer un panneau, cacher les autres.
 *
 * Rien n'est retiré du document — un panneau caché garde ses champs, qui
 * partent avec le formulaire. C'est ce qui permet d'enregistrer depuis
 * n'importe quelle étape, et de revenir en arrière sans rien retaper.
 */
(function () {
  var onglets = Array.prototype.slice.call(document.querySelectorAll('.sd-etape'));
  var form = document.getElementById('form-decor');
  if (!onglets.length || !form) { return; }

  var montrer = function (cle, bouger) {
    onglets.forEach(function (o) {
      var actif = o.dataset.etape === cle;
      o.classList.toggle('actif', actif);
      o.setAttribute('aria-selected', actif ? 'true' : 'false');
      var p = document.getElementById('panneau-' + o.dataset.etape);
      if (p) { p.hidden = !actif; }
    });
    /* On ne remonte que si l'étape a été demandée : rendre visible un
       panneau pour y montrer une erreur ne doit pas, en plus, déplacer la
       page sous les yeux de qui lisait le message. */
    if (bouger && window.scrollY > 120) {
      window.scrollTo({ top: 0, behavior:
        window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    }
  };

  onglets.forEach(function (o) {
    o.addEventListener('click', function () { montrer(o.dataset.etape, true); });
  });
  Array.prototype.forEach.call(document.querySelectorAll('[data-vers]'), function (b) {
    b.addEventListener('click', function () { montrer(b.dataset.vers, true); });
  });

  /**
   * Le piège de cet écran, et la raison de ces lignes.
   *
   * « Titre » et « Page de destination » sont obligatoires et vivent dans
   * l'étape 2. Enregistrer depuis l'étape 3 laisse donc le navigateur
   * refuser l'envoi en essayant de pointer un champ qu'il ne peut pas
   * montrer : sur plusieurs navigateurs, RIEN ne se passe — pas de
   * message, pas d'envoi. L'écran paraît cassé alors qu'il se protège.
   *
   * On écoute donc `invalid`, qui se déclenche sur chaque champ fautif
   * (en capture, car il ne remonte pas), et l'on ouvre son étape avant de
   * laisser le navigateur dire ce qui ne va pas.
   */
  var ouvert = false;
  form.addEventListener('invalid', function (e) {
    var panneau = e.target.closest ? e.target.closest('.sd-panneau') : null;
    if (panneau && panneau.hidden && !ouvert) {
      ouvert = true;
      montrer(panneau.id.replace('panneau-', ''), false);
      /* Le panneau vient d'apparaître : on redonne la main au navigateur
         pour qu'il pointe le champ maintenant qu'il est visible. */
      setTimeout(function () { ouvert = false; form.reportValidity(); }, 0);
    }
  }, true);
})();
</script>
<script>
window.WAKABI_APERCU = {
  base: <?= json_encode(url('')) ?>,
  csrf: <?= json_encode(jeton_csrf()) ?>
};
</script>
<script src="<?= e(actif('public/apercu.js')) ?>" defer></script>
