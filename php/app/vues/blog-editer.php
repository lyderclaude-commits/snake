<?php
/** Écrire ou reprendre un article. */
$erreur = $erreur ?? null;
$fige = $existant && $existant['statut'] === 'publie';
$etats = [
    'brouillon' => 'Brouillon', 'en_relecture' => 'En relecture', 'corrections' => 'À corriger',
    'refuse' => 'Refusé', 'publie' => 'En ligne',
];
?>
<div class="contenu etroit-large">
  <p class="fil"><a href="<?= e(url('?p=blog-admin')) ?>">← <?= $equipe ? 'Le blog' : 'Mes articles' ?></a></p>

  <section class="entete">
    <h1><?= $existant ? 'Modifier l’article' : 'Écrire un article' ?></h1>
    <p><?php if ($existant): ?>
      <span class="pastille <?= e($existant['statut']) ?>"><?= e($etats[$existant['statut']] ?? $existant['statut']) ?></span> ·
    <?php endif; ?>Le corps s’écrit en texte simple. Les marques ci-dessous sont les seules reconnues —
    et aucune balise HTML n’est acceptée, ce qui est exactement le but.</p>
  </section>

  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <?php if ($existant && $existant['motif'] && in_array($existant['statut'], ['corrections', 'refuse'], true)): ?>
    <div class="msg err" style="margin-bottom:16px">
      <strong><?= $existant['statut'] === 'refuse' ? 'Refusé par la rédaction' : 'La rédaction demande une correction' ?></strong>
      <p style="margin:.35em 0 0"><?= e($existant['motif']) ?></p>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('?p=blog-editer' . ($existant ? '&id=' . urlencode((string) $existant['id']) : ''))) ?>"
        enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

    <div class="carte">
      <div class="champ">
        <label for="a-titre">Titre</label>
        <input id="a-titre" name="titre" type="text" required maxlength="160"
               placeholder="Comment 400 personnes sont venues sans une seule affiche"
               value="<?= e($valeurs['titre']) ?>">
      </div>

      <div class="champ">
        <label for="a-slug">Adresse</label>
        <input id="a-slug" name="slug" type="text" value="<?= e($valeurs['slug']) ?>"
               placeholder="laissez vide : elle se déduit du titre" <?= $fige ? 'readonly' : '' ?>>
        <p class="aide">
          <?php if ($fige): ?>
            Figée depuis la publication : la changer casserait les liens déjà partagés.
          <?php else: ?>
            <?= e(base_url()) ?>/index.php?p=blog&amp;a=<strong><?= e($valeurs['slug'] ?: 'votre-titre') ?></strong>
          <?php endif; ?>
        </p>
      </div>

      <div class="champ">
        <label for="a-chapo">Chapô <span style="font-weight:400">(facultatif)</span></label>
        <textarea id="a-chapo" name="chapo" rows="2" maxlength="300"
                  placeholder="Deux phrases qui donnent envie de lire la suite."><?= e($valeurs['chapo']) ?></textarea>
        <p class="aide">C’est ce que montrent Google et WhatsApp. Vide, il est tiré du début du texte.</p>
      </div>
    </div>

    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 4px">La couverture</h3>
      <p class="aide" style="margin:0 0 14px">JPEG, PNG ou WebP, 6 Mo au plus. Elle est
      redimensionnée et recompressée à l’envoi : inutile de le faire avant.</p>

      <?php if ($valeurs['couverture']):
          $_im = image_reduite($valeurs['couverture'], 320); ?>
        <img src="<?= e($_im['src']) ?>" alt="" style="max-width:280px;border-radius:var(--r10);display:block;margin-bottom:10px"
             <?= $_im['largeur'] ? 'width="' . $_im['largeur'] . '" height="' . $_im['hauteur'] . '"' : '' ?>>
        <label style="display:block;margin-bottom:10px">
          <input type="checkbox" name="effacer_image" value="1"> Retirer cette image
        </label>
      <?php endif; ?>

      <div class="champ">
        <label for="a-image">Choisir une image</label>
        <input id="a-image" name="image" type="file" accept="image/png,image/jpeg,image/webp">
      </div>
    </div>

    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 4px">Le texte</h3>
      <details style="margin:0 0 12px">
        <summary class="aide" style="cursor:pointer">Les marques d’écriture</summary>
        <pre class="aide" style="margin:10px 0 0;white-space:pre-wrap;line-height:1.7">## Un intertitre
### Un sous-titre
Un paragraphe. Une ligne vide en sépare deux.
- un point de liste
&gt; une citation
**gras**, *italique*, `code`
[le texte du lien](https://wakabileguide.com)</pre>
      </details>

      <div class="champ">
        <label for="a-corps" class="sr-only">Corps de l’article</label>
        <textarea id="a-corps" name="corps" rows="22" required
                  style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;line-height:1.7"><?= e($valeurs['corps']) ?></textarea>
      </div>

      <div class="rangee" style="margin-top:14px;gap:10px;flex-wrap:wrap">
        <?php if ($fige): ?>
          <button class="bouton" type="submit" name="action" value="enregistrer">Enregistrer</button>
        <?php elseif ($equipe): ?>
          <button class="bouton" type="submit" name="action" value="publier">Publier</button>
          <button class="bouton fant" type="submit" name="action" value="enregistrer">Garder en brouillon</button>
        <?php else: ?>
          <button class="bouton" type="submit" name="action" value="soumettre">Proposer à la rédaction</button>
          <button class="bouton fant" type="submit" name="action" value="enregistrer">Enregistrer sans proposer</button>
        <?php endif; ?>
        <a class="bouton fant" href="<?= e(url('?p=blog-admin')) ?>">Annuler</a>
      </div>

      <?php if (!$equipe && !$fige): ?>
        <p class="aide" style="margin:12px 0 0">Une fois proposé, l’article part chez la rédaction
        et ne se modifie plus tant qu’elle n’a pas répondu — sinon quelqu’un approuverait un texte
        qui n’est déjà plus celui-là.</p>
      <?php endif; ?>
    </div>
  </form>
</div>
