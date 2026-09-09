<?php
/** Rédiger une campagne e-mail. */
$erreur = $erreur ?? null;
?>
<div class="contenu etroit-large">
  <p class="fil"><a href="<?= e(url('?p=regie')) ?>">← La régie</a></p>

  <section class="entete">
    <h1><?= $existante ? 'Modifier la campagne' : 'Nouvelle campagne' ?></h1>
    <p>Le corps s’écrit en texte simple. Une ligne vide sépare deux paragraphes ; le reste
    est mis en forme automatiquement.</p>
  </section>

  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <form method="post" action="<?= e(url('?p=regie-ecrire' . ($existante ? '&id=' . urlencode((string) $existante['id']) : ''))) ?>">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

    <?php
    /**
     * Par où le message sort.
     *
     * En tête du formulaire, avant « à qui » : le canal décide de ce qu'on
     * peut écrire — un modèle WhatsApp n'est pas un courriel — et le
     * choisir après avoir rédigé fait recommencer.
     */
    $coches = $canaux_coches ?? ['email'];
    $a_whatsapp = false;
    foreach ($choix_canaux ?? [] as $ch) {
        if ($ch['genre'] === 'whatsapp' && in_array($ch['cle'], $coches, true)) {
            $a_whatsapp = true;
        }
    }
    ?>
    <div class="carte" style="margin-bottom:16px">
      <h3 style="margin:0 0 4px">Où l’envoyer</h3>
      <p class="aide" style="margin:0 0 14px">Le même texte part sur chaque canal coché, mis en forme
      selon ce que le canal sait afficher. <a href="<?= e(url('?p=canaux')) ?>">Brancher un canal</a>.</p>

      <ul class="cx-liste">
        <?php foreach ($choix_canaux ?? [] as $ch):
          $ici = in_array($ch['cle'], $coches, true); ?>
          <li<?= $ici ? ' style="background:var(--paper);border:1px solid var(--primary)"' : '' ?>>
            <input type="checkbox" name="canaux[]" value="<?= e($ch['cle']) ?>"
                   id="cx-<?= e($ch['cle']) ?>" <?= $ici ? 'checked' : '' ?>
                   style="width:17px;height:17px;accent-color:var(--primary);flex:0 0 auto">
            <label for="cx-<?= e($ch['cle']) ?>" style="flex:1;font-weight:400;margin:0;cursor:pointer">
              <b style="font-weight:600"><?= e($ch['libelle']) ?></b><br>
              <span class="aide"><?= e($ch['aide']) ?></span>
            </label>
            <?php if (isset($ch['n'])): ?><b><?= (int) $ch['n'] ?></b><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>

      <?php if ($a_whatsapp): ?>
        <div class="champ" style="margin-top:14px">
          <label for="modele-wa">Modèle WhatsApp approuvé</label>
          <input id="modele-wa" name="modele_whatsapp" type="text"
                 value="<?= e($modele_whatsapp ?? '') ?>" placeholder="rappel_evenement_fr">
          <p class="aide">Le nom exact du modèle, tel qu’il figure dans votre compte Meta. Hors des
          vingt-quatre heures qui suivent un message du destinataire — c’est-à-dire toujours, pour un
          rappel — Meta refuse le texte libre : votre titre, votre message et votre lien remplissent
          les variables du modèle, dans cet ordre.</p>
        </div>
      <?php endif; ?>
    </div>

    <?php
    /**
     * Quand. Vide = tout de suite, dès que l'équipe l'aura relue.
     *
     * L'heure est saisie dans le fuseau de celui qui écrit, et le
     * navigateur nous donne son décalage : c'est la seule façon qu'un
     * « 19 h » saisi à Lomé parte à 19 h à Lomé.
     */
    $_quand = '';
    if (!empty($valeurs['planifie_le'])) {
        $_t = strtotime((string) $valeurs['planifie_le']);
        $_quand = $_t !== false ? gmdate('Y-m-d\TH:i', $_t) : '';
    }
    ?>
    <div class="carte" style="margin-bottom:16px">
      <h3 style="margin:0 0 4px">Quand</h3>
      <p class="aide" style="margin:0 0 14px">Laissé vide, le message part dès qu’il est prêt.
      Avec une date, il attend son heure — le cron l’ouvre tout seul.</p>
      <div class="champ" style="margin:0">
        <label for="r-quand">Date et heure d’envoi <span style="font-weight:400">(facultatif)</span></label>
        <input id="r-quand" name="planifie_le" type="datetime-local" value="<?= e($_quand) ?>">
        <input type="hidden" name="decalage" id="r-decalage" value="0">
      </div>
    </div>
    <script>
      /* Le décalage du navigateur, pour que l'heure saisie soit l'heure vécue.
         `getTimezoneOffset` rend l'inverse de ce qu'on attend : d'où le signe. */
      (function () {
        var d = document.getElementById('r-decalage');
        if (d) { d.value = String(new Date().getTimezoneOffset()); }
      })();
    </script>

    <div class="carte">
      <h3 style="margin:0 0 4px">À qui</h3>
      <p class="aide" style="margin:0 0 16px">Le nombre exact de personnes touchées s’affiche
      une fois la campagne enregistrée — il dépend de qui est désabonné et de qui a un compte.</p>

      <div class="champ">
        <label for="r-cible">Cible</label>
        <select id="r-cible" name="cible" onchange="document.getElementById('bloc-liste').hidden = this.value !== 'liste'">
          <?php foreach ($cibles as $cle => $lib): ?>
            <option value="<?= e($cle) ?>" <?= $valeurs['cible'] === $cle ? 'selected' : '' ?>><?= e($lib) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!$equipe): ?>
          <p class="aide">« Mes invités » : les gens qui ont créé un badge sur vos campagnes
          <em>et</em> qui ont un compte. La base du guide, elle, ne se loue pas.</p>
        <?php endif; ?>
      </div>

      <div id="bloc-liste" <?= $valeurs['cible'] === 'liste' ? '' : 'hidden' ?>>
        <?php
        /**
         * Une liste du carnet, pas un collage jetable.
         *
         * Le collage reste possible — c'est le geste le plus rapide, et on
         * ne prend pas le risque de le remplacer par un formulaire. Mais
         * il ATTERRIT dans une liste : la campagne suivante repartira de la
         * même, corrigée, au lieu d'un nouveau copier-coller depuis le
         * même tableur avec les mêmes fautes.
         */
        $choisie = $valeurs['liste_id'];
        ?>
        <div class="champ">
          <label for="r-liste-id">Quelle liste</label>
          <select id="r-liste-id" name="liste_id"
                  onchange="document.getElementById('bloc-liste-nom').hidden = this.value !== 'nouvelle'">
            <?php foreach ($listes as $li): ?>
              <option value="<?= e((string) $li['id']) ?>" <?= $choisie === $li['id'] ? 'selected' : '' ?>>
                <?= e((string) $li['nom']) ?> (<?= (int) $li['actifs'] ?> adresse<?= $li['actifs'] > 1 ? 's' : '' ?>)
              </option>
            <?php endforeach; ?>
            <option value="nouvelle" <?= $choisie === '' ? 'selected' : '' ?>>— Une nouvelle liste —</option>
          </select>
          <?php if ($listes): ?>
            <p class="aide">Vos listes se gèrent dans le
            <a href="<?= e(url('?p=regie-carnet')) ?>">carnet d’adresses</a> : y corriger un nom, en sortir
            quelqu’un ou archiver une adresse morte vaut pour toutes vos campagnes.</p>
          <?php endif; ?>
        </div>

        <div class="champ" id="bloc-liste-nom" <?= $choisie === '' ? '' : 'hidden' ?>>
          <label for="r-liste-nom">Nom de la nouvelle liste</label>
          <input id="r-liste-nom" name="nouveau_nom" type="text" maxlength="120"
                 placeholder="Invités du Gala 2026">
          <p class="aide">Laissé vide, elle prendra l’objet de la campagne.</p>
        </div>

        <div class="champ">
          <label for="r-liste">Ajouter des adresses <span style="font-weight:400">(facultatif si la liste en contient déjà)</span></label>
          <textarea id="r-liste" name="liste" rows="6"
                    style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace"
                    placeholder="ama@exemple.tg&#10;Kossi Mensah &lt;kossi@exemple.tg&gt;"><?= e($valeurs['liste']) ?></textarea>
          <p class="aide"><strong>Elles sont enregistrées dans votre carnet</strong>, pas seulement
          utilisées une fois. Le format <code>Nom &lt;adresse&gt;</code> est accepté ; une adresse
          déjà connue n’est pas dupliquée. Une ligne illisible est ignorée, pas refusée — une
          virgule oubliée ne doit pas faire recommencer deux cents lignes.</p>
        </div>
      </div>
    </div>

    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 4px">Le message</h3>
      <p class="aide" style="margin:0 0 16px">L’objet décide de l’ouverture ; le titre est la
      première ligne à l’intérieur. Ce sont deux textes différents, et c’est voulu.</p>

      <div class="grille g2">
        <div class="champ">
          <label for="r-sujet">Objet <span style="font-weight:400">(120 caractères)</span></label>
          <input id="r-sujet" name="sujet" type="text" required maxlength="120"
                 placeholder="Votre badge vous ouvre la soirée de samedi"
                 value="<?= e($valeurs['sujet']) ?>">
        </div>
        <div class="champ">
          <label for="r-titre">Titre dans le message</label>
          <input id="r-titre" name="titre" type="text" required maxlength="120"
                 placeholder="On remet ça samedi"
                 value="<?= e($valeurs['titre']) ?>">
        </div>
      </div>

      <div class="champ">
        <label for="r-corps">Le texte</label>
        <textarea id="r-corps" name="corps" rows="12" required
                  placeholder="Bonjour,&#10;&#10;Vous étiez là au Maquis Akwaba en mars…"><?= e($valeurs['corps']) ?></textarea>
        <p class="aide">Le lien de désabonnement est ajouté tout seul à la fin. Ne l’écrivez pas.</p>
      </div>

      <div class="grille g2">
        <div class="champ">
          <label for="r-lien">Le bouton mène à <span style="font-weight:400">(facultatif)</span></label>
          <input id="r-lien" name="lien" type="url" placeholder="<?= e(base_url() . '/index.php?p=decors') ?>"
                 value="<?= e($valeurs['lien']) ?>">
          <?php if (!$equipe): ?>
            <p class="aide">Une adresse <?= e(implode(' ou ', WAKABI_DOMAINES)) ?>.</p>
          <?php endif; ?>
        </div>
        <div class="champ">
          <label for="r-libelle">Texte du bouton</label>
          <input id="r-libelle" name="lien_libelle" type="text" maxlength="40"
                 placeholder="Faire mon badge" value="<?= e($valeurs['lien_libelle']) ?>">
        </div>
      </div>

      <div class="rangee" style="margin-top:14px;gap:10px">
        <button class="bouton" type="submit">Enregistrer</button>
        <a class="bouton fant" href="<?= e(url($existante ? '?p=regie-campagne&id=' . urlencode((string) $existante['id']) : '?p=regie')) ?>">Annuler</a>
      </div>
    </div>
  </form>
</div>
