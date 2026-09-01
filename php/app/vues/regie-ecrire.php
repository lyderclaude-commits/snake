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

      <div class="champ" id="bloc-liste" <?= $valeurs['cible'] === 'liste' ? '' : 'hidden' ?>>
        <label for="r-liste">Les adresses, une par ligne</label>
        <textarea id="r-liste" name="liste" rows="6"
                  style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace"
                  placeholder="ama@exemple.tg&#10;Kossi Mensah &lt;kossi@exemple.tg&gt;"><?= e($valeurs['liste']) ?></textarea>
        <p class="aide">Le format <code>Nom &lt;adresse&gt;</code> est accepté. Une ligne
        illisible est ignorée, pas refusée — une virgule oubliée ne doit pas faire recommencer
        deux cents lignes.</p>
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
