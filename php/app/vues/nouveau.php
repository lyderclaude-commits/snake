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
        <?php
        /**
         * La galerie de modèles, et le menu déroulant qu'elle habille.
         *
         * On choisissait « Bandeau bas · Carré » dans une liste de texte,
         * sans voir à quoi cela ressemble — alors que la seule question
         * qu'on se pose devant un gabarit est justement de quoi il a l'air.
         *
         * Le `<select>` RESTE, caché derrière la galerie. Il porte toujours
         * la valeur envoyée, il fonctionne sans script, et le clavier le
         * trouve. La galerie ne fait que l'actionner : rien de ce qui
         * marchait ne dépend d'elle.
         */
        $par_nature = [
          'Formats Wakabi' => ['bandeau', 'angle', 'story'],
          'Réseaux sociaux' => ['instagram', 'facebook', 'tiktok'],
          'Sur mesure' => ['vierge'],
        ];
        $fiches = [];
        foreach (dispositions() as $d) {
            $fiches[$d['id']] = $d;
        }
        ?>
        <div class="champ sd-galerie-champ">
          <span class="champ-titre">Modèle</span>
          <div class="sd-galerie" role="radiogroup" aria-label="Modèle de décor">
            <?php foreach ($par_nature as $nature => $ids): ?>
              <?php foreach ($ids as $id): if (!isset($fiches[$id])) { continue; } $d = $fiches[$id]; ?>
                <button type="button" class="sd-modele<?= $valeurs['disposition'] === $id ? ' actif' : '' ?>"
                        role="radio" aria-checked="<?= $valeurs['disposition'] === $id ? 'true' : 'false' ?>"
                        data-modele="<?= e($id) ?>" title="<?= e($d['aide']) ?>">
                  <?php $vign = $id === 'vierge' ? '' : cadre_du_format($id); ?>
                  <span class="sd-modele-image">
                    <?php if ($vign): ?>
                      <img src="<?= e($vign) ?>" alt="" loading="lazy" decoding="async">
                    <?php else: ?><span class="sd-modele-vide">Page<br>blanche</span><?php endif; ?>
                    <span class="sd-modele-ratio"><?= e(canevas($id)['ratio']) ?></span>
                  </span>
                  <span class="sd-modele-nom"><?= e($d['nom']) ?></span>
                  <span class="sd-modele-nature"><?= e($nature) ?></span>
                </button>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="champ sd-champ-disposition">
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

        <?php
        /**
         * Les déclinaisons vivent AVEC le cadre, et non près de l’aperçu.
         *
         * C’est leur place : ce qu’un autre format oblige à changer, c’est
         * précisément le cadre — un fichier carré posé dans un 9:16
         * s’étirerait. Les textes, eux, sont en fractions du canevas et se
         * replacent seuls.
         *
         * Les mettre dans le bandeau collant coûtait aussi de la hauteur
         * d’écran sur un téléphone, au moment précis où l’on veut voir ses
         * réglages sous l’aperçu.
         *
         * Le quota ne compte QU’UNE campagne : c’est tout l’intérêt.
         */
        ?>
        <div class="champ sd-formats">
          <span class="champ-titre">Autres formats <span style="font-weight:400">(facultatif)</span></span>
          <div class="fmt-rangee" id="fmt-rangee"></div>
          <input type="hidden" name="variantes" id="champ-variantes"
                 value="<?= e($valeurs['variantes']) ?>">
          <input id="fmt-fichier" type="file" accept="image/png,image/webp" hidden>
          <p class="aide" id="fmt-aide" style="margin:6px 0 10px"></p>
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
        <div class="rangee" style="justify-content:space-between;align-items:baseline;margin-bottom:12px">
          <p class="aide" style="margin:0;max-width:32ch">Tout se déplace sauf l’essentiel : le QR,
          le filigrane et la zone photo restent, où que vous les mettiez.</p>
          <button class="bouton fant petit" type="button" id="apparence-defaut">Réglages du gabarit</button>
        </div>

        <?php
        /**
         * Le panneau de calques, et les réglages de CELUI qu'on a pris en main.
         *
         * Les dix-sept réglages restent tous là — ils vivent plus bas, dans
         * des groupes que le script montre ou cache selon la sélection. Ce
         * n'est donc pas un écran de plus : c'est le même, trié par objet
         * plutôt qu'en une grille où l'on cherchait « taille du QR » parmi
         * les curseurs du texte.
         *
         * La liste est écrite par le script, à partir du champ caché
         * `calques` et des trois objets que tout décor porte. Le serveur ne
         * la rend pas : elle change à chaque geste, et un rendu initial en
         * PHP se contredirait dès le premier ajout.
         */
        ?>
        <div class="sd-calques">
          <div class="rangee" style="justify-content:space-between;align-items:center;margin-bottom:8px">
            <p class="pas" style="margin:0">Calques</p>
            <div class="rangee" style="gap:6px">
              <button class="bouton fant petit" type="button" id="calque-texte">+ Texte</button>
              <button class="bouton fant petit" type="button" id="calque-image">+ Image</button>
            </div>
          </div>
          <ul class="sd-liste-calques" id="liste-calques"></ul>
          <input type="hidden" name="calques" id="champ-calques"
                 value="<?= e($valeurs['calques']) ?>">
          <p class="aide" id="calques-aide" style="margin:8px 0 0"></p>
          <!-- Le téléversement d'une image de calque emprunte le même
               chemin que le cadre : rien n'entre par une autre porte. -->
          <input id="calque-fichier" type="file" accept="image/png,image/webp" hidden>
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
        <?php
        /**
         * Les réglages d'un calque libre.
         *
         * Ils ne sont pas rendus par le serveur avec une valeur : c'est le
         * script qui les remplit depuis l'objet sélectionné, et qui réécrit
         * l'objet à chaque frappe. Ils ne portent donc PAS d'attribut
         * `name` — ils n'ont rien à envoyer, seul le champ caché `calques`
         * part avec le formulaire.
         */
        ?>
        <fieldset class="sd-groupe sd-libre" id="groupe-libre" hidden>
          <legend id="libre-titre">Le calque</legend>
          <div class="champ" id="libre-champ-valeur">
            <label for="l-valeur">Texte</label>
            <input id="l-valeur" type="text" maxlength="80" autocomplete="off">
          </div>
          <div class="champ" id="libre-champ-nom">
            <label for="l-nom">Nom dans la liste
              <span style="font-weight:400">(facultatif)</span></label>
            <input id="l-nom" type="text" maxlength="40" autocomplete="off">
            <p class="aide">Laissé vide, il suit le texte. « Date et lieu » se retrouve
            plus vite dans une liste de douze objets que « SAM. 12 AVRIL · 21 H ».</p>
          </div>
          <div class="reglages">
            <div class="champ reglage" id="libre-champ-police">
              <label for="l-police">Police</label>
              <select id="l-police">
                <?php foreach (APPARENCE_POLICES as $k => $v): ?>
                  <option value="<?= e($k) ?>"><?= e($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="champ reglage" id="libre-champ-couleur">
              <label for="l-couleur">Couleur</label>
              <select id="l-couleur">
                <?php foreach (APPARENCE_COULEURS as $k => $v): ?>
                  <option value="<?= e($k) ?>"><?= e($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="champ reglage" id="libre-champ-taille">
              <label for="l-taille">Taille <output id="v-l-taille"></output></label>
              <input id="l-taille" type="range" min="0.012" max="0.14" step="0.002">
            </div>
            <div class="champ reglage" id="libre-champ-align">
              <label for="l-align">Alignement</label>
              <select id="l-align">
                <?php foreach (APPARENCE_ALIGNEMENTS as $k => $v): ?>
                  <option value="<?= e($k) ?>"><?= e($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="champ reglage"><label for="l-x">Position gauche <output id="v-l-x"></output></label>
              <input id="l-x" type="range" min="0" max="0.98" step="0.005"></div>
            <div class="champ reglage"><label for="l-y">Position haute <output id="v-l-y"></output></label>
              <input id="l-y" type="range" min="0" max="0.98" step="0.005"></div>
            <div class="champ reglage"><label for="l-w">Largeur <output id="v-l-w"></output></label>
              <input id="l-w" type="range" min="0.03" max="1" step="0.005"></div>
            <div class="champ reglage"><label for="l-h">Hauteur <output id="v-l-h"></output></label>
              <input id="l-h" type="range" min="0.02" max="1" step="0.005"></div>
          </div>
          <label class="case" id="libre-champ-majuscules" style="max-width:420px;margin-top:2px">
            <input id="l-majuscules" type="checkbox"><span>Tout en capitales</span>
          </label>
          <div class="rangee" style="margin-top:12px;gap:8px">
            <button class="bouton fant petit" type="button" id="calque-supprimer">Supprimer ce calque</button>
          </div>
        </fieldset>

        <fieldset class="sd-groupe sd-natif">
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

        <fieldset class="sd-groupe sd-natif">
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
        <fieldset class="sd-groupe sd-natif fenetre-photo">
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
      <?php
      /**
       * La santé du décor, dite PENDANT qu'on règle.
       *
       * C'était une liste figée de ce que la relecture vérifierait un jour.
       * Elle est maintenant le résultat du pré-vol lui-même, recalculé à
       * chaque geste : les mêmes contrôles, la même fonction, mais rendus
       * avant l'envoi plutôt qu'après. Un décor refusé, c'est deux jours
       * perdus ; le dire tout de suite ne coûte que vingt millisecondes.
       *
       * La liste de départ reste écrite en dur : tant que le premier aperçu
       * n'est pas revenu, elle annonce au moins CE QUI SERA vérifié.
       */
      ?>
      <div class="carte sd-sante" id="sd-sante">
        <p class="pas" style="margin:0 0 10px">Santé du décor</p>
        <ul class="sd-sante-liste" id="sd-sante-liste">
          <li class="sd-ct attente">La zone photo reste visible</li>
          <li class="sd-ct attente">Les textes tiennent dans le cadre</li>
          <li class="sd-ct attente">Aucun texte sous le filigrane ni sous le QR</li>
          <li class="sd-ct attente">Format et poids soutenables en 3G</li>
          <li class="sd-ct attente">Les textes restent lisibles sur toute photo</li>
        </ul>
      </div>
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

<script>
/**
 * Le panneau de calques.
 *
 * L'état vit dans UN SEUL endroit : le champ caché `calques`, en JSON. Le
 * panneau le lit pour se dessiner et le réécrit à chaque geste ; l'aperçu le
 * poste avec le reste du formulaire et le serveur le renvoie dessiné. Il n'y
 * a donc jamais deux vérités à réconcilier — la liste à l'écran ne peut pas
 * diverger de ce qui sera enregistré, puisque c'est la même chaîne.
 */
(function () {
  var champ = document.getElementById('champ-calques');
  var liste = document.getElementById('liste-calques');
  var form = document.getElementById('form-decor');
  if (!champ || !liste || !form) { return; }

  var groupeLibre = document.getElementById('groupe-libre');
  var natifs = Array.prototype.slice.call(document.querySelectorAll('.sd-natif'));
  var aide = document.getElementById('calques-aide');
  var MAX = 12;

  /** -1 = aucun calque libre sélectionné ; on montre alors les réglages du décor. */
  var choisi = -1;

  var lire = function () {
    try { var v = JSON.parse(champ.value || '[]'); return Array.isArray(v) ? v : []; }
    catch (e) { return []; }
  };
  var ecrire = function (cs) {
    champ.value = JSON.stringify(cs);
    /* `input` et non `change` : c'est l'événement que l'aperçu écoute, et il
       redessine donc au même rythme que pour un curseur. */
    champ.dispatchEvent(new Event('input', { bubbles: true }));
  };

  /**
   * Les trois objets que TOUT décor porte.
   *
   * Ils ne sont pas dans le champ caché : ils vivent dans les réglages du
   * gabarit, et ne peuvent être ni ajoutés ni retirés. Ils figurent dans la
   * liste parce qu'un panneau de calques qui ne montrerait pas le QR ni la
   * photo mentirait sur ce que contient le décor.
   */
  var FIXES = [
    { nom: 'QR Code', eti: 'fixe' },
    { nom: 'Filigrane Wakabi', eti: 'fixe' },
    { nom: 'Fenêtre photo', eti: 'fixe' }
  ];

  function dessinerListe() {
    var cs = lire();
    liste.textContent = '';

    /* Du dessus vers le dessous, comme tout logiciel de composition : le
       dernier calque posé est le premier de la liste. */
    for (var i = cs.length - 1; i >= 0; i--) {
      liste.appendChild(ligneLibre(cs[i], i));
    }
    FIXES.forEach(function (f) { liste.appendChild(ligneFixe(f)); });

    aide.textContent = cs.length >= MAX
      ? 'Douze calques, c’est le maximum : chacun est redessiné à chaque aperçu.'
      : (cs.length === 0
        ? 'Ajoutez une date, un lieu, un hashtag, le logo d’un partenaire.'
        : cs.length + ' calque' + (cs.length > 1 ? 's' : '') + ' sur ' + MAX + '.');
  }

  function ligneLibre(c, i) {
    var li = document.createElement('li');
    li.className = 'sd-calque' + (i === choisi ? ' sel' : '');
    li.dataset.rang = String(i);

    var oeil = document.createElement('button');
    oeil.type = 'button';
    oeil.className = 'sd-cal-oeil';
    oeil.setAttribute('aria-label', c.visible === false ? 'Montrer ce calque' : 'Masquer ce calque');
    oeil.setAttribute('aria-pressed', c.visible === false ? 'true' : 'false');
    oeil.textContent = c.visible === false ? '◌' : '●';
    oeil.addEventListener('click', function (e) {
      e.stopPropagation();
      var cs = lire();
      cs[i].visible = cs[i].visible === false;
      ecrire(cs); dessinerListe();
    });

    var nom = document.createElement('span');
    nom.className = 'sd-cal-nom';
    nom.textContent = c.nom || (c.sorte === 'image' ? 'Image' : (c.valeur || 'Texte'));

    var eti = document.createElement('span');
    eti.className = 'sd-cal-eti';
    eti.textContent = c.sorte === 'image' ? 'image' : 'texte';

    li.appendChild(oeil); li.appendChild(nom); li.appendChild(eti);
    li.addEventListener('click', function () { selectionner(i); });
    return li;
  }

  function ligneFixe(f) {
    var li = document.createElement('li');
    li.className = 'sd-calque fixe';
    li.innerHTML = '<span class="sd-cal-oeil" aria-hidden="true">●</span>'
      + '<span class="sd-cal-nom"></span><span class="sd-cal-eti">🔒 ' + f.eti + '</span>';
    li.querySelector('.sd-cal-nom').textContent = f.nom;
    /* Cliquer un objet fixe ramène aux réglages du décor, où il se règle. */
    li.addEventListener('click', function () { selectionner(-1); });
    return li;
  }

  /* ---- la sélection décide de ce qu'on voit ---- */
  function selectionner(i) {
    choisi = i;
    /* L'aperçu suit : une seule sélection pour les deux, sinon on règle un
       objet en en regardant un autre. */
    document.dispatchEvent(new CustomEvent('wakabi:selection', { detail: i }));
    var cs = lire();
    var c = i >= 0 ? cs[i] : null;

    natifs.forEach(function (n) { n.hidden = c !== null; });
    groupeLibre.hidden = c === null;
    dessinerListe();
    if (!c) { return; }

    var texte = c.sorte !== 'image';
    document.getElementById('libre-titre').textContent =
      texte ? 'Le texte « ' + (c.nom || c.valeur || '') + ' »' : 'L’image « ' + (c.nom || '') + ' »';
    document.getElementById('libre-champ-valeur').hidden = !texte;
    ['libre-champ-police', 'libre-champ-couleur', 'libre-champ-taille',
     'libre-champ-align', 'libre-champ-majuscules'].forEach(function (id) {
      document.getElementById(id).hidden = !texte;
    });

    remplirCommandes(c);
  }

  /**
   * Les commandes reprennent les valeurs de l'objet.
   *
   * Rejoué quand l'aperçu écrit dans le champ caché — c'est-à-dire quand on
   * a glissé l'objet à la souris. Sans cela, les curseurs continueraient
   * d'afficher la position d'avant le geste : deux commandes pour la même
   * chose, et l'une des deux qui ment.
   *
   * Poser une valeur par script ne déclenche PAS `input` : la boucle ne se
   * referme donc pas sur elle-même.
   */
  function remplirCommandes(c) {
    poser('l-valeur', c.valeur || '');
    poser('l-nom', c.nom || '');
    poser('l-police', c.police || 'display');
    poser('l-couleur', c.couleur || 'brand.paper');
    poser('l-align', c.align || 'left');
    poser('l-taille', c.taille != null ? c.taille : 0.04);
    poser('l-x', c.x); poser('l-y', c.y); poser('l-w', c.w); poser('l-h', c.h);
    document.getElementById('l-majuscules').checked = !!c.majuscules;
    montrerNombres();
  }

  function poser(id, v) {
    var el = document.getElementById(id);
    if (el) { el.value = String(v); }
  }

  function montrerNombres() {
    [['l-taille', 'v-l-taille'], ['l-x', 'v-l-x'], ['l-y', 'v-l-y'],
     ['l-w', 'v-l-w'], ['l-h', 'v-l-h']].forEach(function (p) {
      var e = document.getElementById(p[0]), o = document.getElementById(p[1]);
      if (e && o) { o.textContent = Math.round(Number(e.value) * 100) + ' %'; }
    });
  }

  /* ---- toute modification réécrit l'objet sélectionné ---- */
  var lie = {
    'l-valeur': 'valeur', 'l-nom': 'nom', 'l-police': 'police', 'l-couleur': 'couleur',
    'l-align': 'align', 'l-taille': 'taille', 'l-x': 'x', 'l-y': 'y', 'l-w': 'w', 'l-h': 'h'
  };
  Object.keys(lie).forEach(function (id) {
    var el = document.getElementById(id);
    if (!el) { return; }
    el.addEventListener('input', function () {
      if (choisi < 0) { return; }
      var cs = lire();
      if (!cs[choisi]) { return; }
      var cle = lie[id];
      var avant = cs[choisi][cle];
      cs[choisi][cle] = el.type === 'range' ? Number(el.value) : el.value;
      /**
       * Le nom suit le texte tant qu'il n'a pas été changé à la main.
       *
       * On le sait sans drapeau, en comparant : si le nom actuel est encore
       * l'ancien texte, c'est qu'il suivait. Un drapeau aurait été plus
       * simple à écrire, et faux dès le premier enregistrement — il ne
       * survit pas à l'aller-retour par le serveur, qui ne garde que les
       * propriétés du modèle.
       */
      if (cle === 'valeur' && (cs[choisi].nom || '') === String(avant || '')) {
        cs[choisi].nom = el.value.slice(0, 40);
      }
      ecrire(cs); montrerNombres(); dessinerListe();
      var t = document.getElementById('libre-titre');
      if (t && cs[choisi].sorte !== 'image') { t.textContent = 'Le texte « ' + (cs[choisi].nom || '') + ' »'; }
    });
  });
  document.getElementById('l-majuscules').addEventListener('change', function () {
    if (choisi < 0) { return; }
    var cs = lire();
    cs[choisi].majuscules = this.checked;
    ecrire(cs);
  });

  /* ---- ajouter, supprimer ---- */
  document.getElementById('calque-texte').addEventListener('click', function () {
    var cs = lire();
    if (cs.length >= MAX) { return; }
    /* Le nom part ÉGAL au texte : c'est ce qui fait qu'il le suit ensuite
       (voir la règle de comparaison plus bas). */
    /* Chaque nouveau calque se décale un peu : posés au même endroit, deux
       textes se recouvrent exactement et l'on croit n'en avoir ajouté
       qu'un. Le décalage s'arrête avant le bord. */
    var d = Math.min(cs.length, 6) * 0.05;
    cs.push({ sorte: 'texte', nom: 'Votre texte', valeur: 'Votre texte',
              x: 0.1 + d, y: 0.1 + d, w: 0.5, h: 0.07, taille: 0.04,
              couleur: 'brand.paper', align: 'left', police: 'display',
              majuscules: false, visible: true });
    ecrire(cs); selectionner(cs.length - 1);
    document.getElementById('l-valeur').select();
  });

  /**
   * Une image de calque passe par le MÊME téléversement que le cadre.
   *
   * C'est ce qui garantit qu'elle est servie depuis ce site : le serveur
   * refuse tout calque image dont la source n'est pas une de nos deux
   * adresses connues. Un lien collé à la main ne passerait pas.
   */
  var fichier = document.getElementById('calque-fichier');
  document.getElementById('calque-image').addEventListener('click', function () {
    if (lire().length >= MAX) { return; }
    fichier.click();
  });
  fichier.addEventListener('change', function () {
    var f = fichier.files && fichier.files[0];
    if (!f) { return; }
    aide.textContent = 'Envoi de l’image…';
    var corps = new FormData();
    corps.append('csrf', (form.elements.namedItem('csrf') || {}).value || '');
    corps.append('image', f);
    fetch((window.WAKABI_APERCU || {}).base + '?p=api-calque-image',
          { method: 'POST', body: corps })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        fichier.value = '';
        if (!d.url) { aide.textContent = d.erreur || 'Image refusée.'; return; }
        var cs = lire();
        var dec = Math.min(cs.length, 6) * 0.04;
        cs.push({ sorte: 'image', nom: f.name.replace(/\.[a-z0-9]+$/i, '').slice(0, 40),
                  src: d.url, x: 0.68 - dec, y: 0.05 + dec, w: 0.26, h: 0.14,
                  opacite: 1, visible: true });
        ecrire(cs); selectionner(cs.length - 1);
      })
      .catch(function () { fichier.value = ''; aide.textContent = 'L’image n’a pas pu être envoyée.'; });
  });

  document.getElementById('calque-supprimer').addEventListener('click', function () {
    if (choisi < 0) { return; }
    var cs = lire();
    cs.splice(choisi, 1);
    ecrire(cs); selectionner(-1);
  });

  /* L'aperçu vient d'écrire dans le champ : les commandes le rattrapent. */
  champ.addEventListener('input', function () {
    if (choisi < 0) { return; }
    var c = lire()[choisi];
    if (c) { remplirCommandes(c); }
  });

  /* …et inversement : prendre un objet sur l'image l'ouvre dans le panneau. */
  document.addEventListener('wakabi:calque', function (e) {
    if (e.detail !== choisi) { selectionner(e.detail); }
  });

  dessinerListe();
})();
</script>

<script>
/**
 * Les déclinaisons : un décor, plusieurs formats.
 *
 * Le format d'origine vient du gabarit choisi et n'est pas une déclinaison
 * — c'est le décor lui-même. Les autres se déclarent ici, chacune avec son
 * cadre : c'est la seule chose qu'un autre format oblige vraiment à
 * changer, puisque tout le reste est en fractions du canevas.
 *
 * On n'affiche PAS d'aperçu par format : il faudrait trois canevas et trois
 * jeux de réglages, pour un écran qui vient déjà d'être simplifié. On
 * bascule, on regarde, on revient — le même aperçu, une déclinaison à la
 * fois.
 */
(function () {
  var champ = document.getElementById('champ-variantes');
  var rangee = document.getElementById('fmt-rangee');
  var fichier = document.getElementById('fmt-fichier');
  var aide = document.getElementById('fmt-aide');
  var dispo = document.getElementById('disposition');
  var form = document.getElementById('form-decor');
  if (!champ || !rangee || !form) { return; }

  var LIBELLES = { '1:1': 'Carré', '4:5': 'Portrait', '9:16': 'Story', '16:9': 'Paysage' };
  var enCours = '';   // le format qu'on est en train d'ajouter

  var lire = function () {
    try { var v = JSON.parse(champ.value || '{}'); return (v && typeof v === 'object') ? v : {}; }
    catch (e) { return {}; }
  };
  var ecrire = function (v) {
    champ.value = JSON.stringify(v);
    champ.dispatchEvent(new Event('input', { bubbles: true }));
  };

  /** Le format du décor lui-même : celui du champ « Format du décor ». */
  var natif = function () {
    var f = form.elements.namedItem('format');
    return f ? f.value : '1:1';
  };

  function dessiner() {
    var v = lire(), n = natif();
    rangee.textContent = '';

    Object.keys(LIBELLES).forEach(function (r) {
      var declinee = r === n || Object.prototype.hasOwnProperty.call(v, r);
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'fmt-pas' + (r === n ? ' natif' : (declinee ? ' declinee' : ''));
      b.textContent = LIBELLES[r] + ' ' + r;
      b.title = r === n
        ? 'Le format du décor'
        : (declinee ? 'Retirer cette déclinaison' : 'Ajouter ce format');
      b.addEventListener('click', function () {
        if (r === n) { return; }
        if (declinee) {
          delete v[r];
          ecrire(v); dessiner();
          aide.textContent = LIBELLES[r] + ' retiré.';
          return;
        }
        // Un autre format demande son propre cadre, sinon il s'étirerait.
        enCours = r;
        fichier.click();
      });
      rangee.appendChild(b);
    });

    var n2 = Object.keys(v).length;
    if (!aide.textContent || n2 === 0) {
      aide.textContent = n2
        ? n2 + ' déclinaison' + (n2 > 1 ? 's' : '') + ' — une seule place de quota.'
        : 'Ajoutez un format pour couvrir la story sans créer un second décor.';
    }
  }

  fichier.addEventListener('change', function () {
    var f = fichier.files && fichier.files[0];
    if (!f || !enCours) { return; }
    aide.textContent = 'Envoi du cadre ' + enCours + '…';
    var corps = new FormData();
    var jeton = form.elements.namedItem('csrf');
    corps.append('csrf', jeton ? jeton.value : '');
    corps.append('image', f);
    fetch((window.WAKABI_APERCU || {}).base + '?p=api-calque-image', { method: 'POST', body: corps })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        fichier.value = '';
        if (!d.url) { aide.textContent = d.erreur || 'Cadre refusé.'; return; }
        var v = lire();
        v[enCours] = { cadreUrl: d.url };
        ecrire(v);
        aide.textContent = LIBELLES[enCours] + ' ajouté. Le décor se prend maintenant dans '
          + (Object.keys(v).length + 1) + ' formats, pour une seule place de quota.';
        enCours = '';
        dessiner();
      })
      .catch(function () { fichier.value = ''; aide.textContent = 'Le cadre n’a pas pu être envoyé.'; });
  });

  /* Changer de gabarit change le format d'origine : la rangée le suit. */
  if (dispo) { dispo.addEventListener('change', function () { setTimeout(dessiner, 400); }); }
  var champFormat = form.elements.namedItem('format');
  if (champFormat) { champFormat.addEventListener('change', dessiner); }

  dessiner();
})();
</script>

<script>
/**
 * La galerie actionne le menu déroulant.
 *
 * Elle ne porte aucune valeur elle-même : elle pose celle du `<select>` et
 * lui envoie un `change`, exactement comme si on l'avait déplié. Tout ce
 * qui écoute ce champ — l'aperçu, la remise aux réglages d'usine — continue
 * donc de fonctionner sans rien savoir de la galerie.
 */
(function () {
  var choix = document.getElementById('disposition');
  var vignettes = Array.prototype.slice.call(document.querySelectorAll('.sd-modele'));
  if (!choix || !vignettes.length) { return; }

  var marquer = function () {
    vignettes.forEach(function (v) {
      var actif = v.dataset.modele === choix.value;
      v.classList.toggle('actif', actif);
      v.setAttribute('aria-checked', actif ? 'true' : 'false');
    });
  };

  vignettes.forEach(function (v) {
    v.addEventListener('click', function () {
      if (choix.value === v.dataset.modele) { return; }
      choix.value = v.dataset.modele;
      choix.dispatchEvent(new Event('change', { bubbles: true }));
      marquer();
    });
  });
  /* Le menu reste utilisable : s'il change, la galerie suit. */
  choix.addEventListener('change', marquer);
  marquer();
})();
</script>
