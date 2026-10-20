<?php
$nouveau = !$x || empty($x['id']);
$titre = $nouveau ? 'Nouveau témoignage' : 'Modifier le témoignage';
$fr = $x ? (json_decode((string) $x['fr'], true) ?: []) : [];
$en = $x ? (json_decode((string) $x['en'], true) ?: []) : [];
?>
<a class="retour" href="<?= url('admin/temoignages') ?>"><i>←</i>Les témoignages</a>
<div class="entete"><div><h1><?= e($titre) ?></h1><p>Les mots du client, tels qu’il les a dits — et leur traduction pour le site anglais.</p></div></div>
<?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>
<form method="post" action="<?= url('admin/temoignages/' . ($nouveau ? 'nouveau' : (int) $x['id'])) ?>" class="pile" style="max-width:900px">
  <?= csrf_champ() ?>
  <div class="carte">
    <div class="champs">
      <div class="champ"><label for="entreprise">Entreprise <span class="obligatoire">*</span></label><input id="entreprise" name="entreprise" required value="<?= e((string) ($x['entreprise'] ?? '')) ?>" placeholder="Woodin Togo"><p class="aide">Son initiale s’affiche dans la pastille.</p></div>
      <div class="champ"><label for="note">Étoiles</label><select id="note" name="note"><?php for ($n = 5; $n >= 1; $n--): ?><option value="<?= $n ?>"<?= (int) ($x['note'] ?? 5) === $n ? ' selected' : '' ?>><?= str_repeat('★', $n) ?></option><?php endfor; ?></select></div>
    </div>
    <div class="duo-langues">
      <div class="champ"><label for="t-fr">Témoignage <span class="drapeau">FR</span> <span class="obligatoire">*</span></label><textarea id="t-fr" name="fr[texte]" rows="6" required lang="fr"><?= e((string) ($fr['texte'] ?? '')) ?></textarea></div>
      <div class="champ"><label for="t-en">Témoignage <span class="drapeau">EN</span></label><textarea id="t-en" name="en[texte]" rows="6" lang="en"><?= e((string) ($en['texte'] ?? '')) ?></textarea></div>
      <div class="champ"><label for="a-fr">Fonction de la personne <span class="drapeau">FR</span></label><input id="a-fr" name="fr[auteur]" value="<?= e((string) ($fr['auteur'] ?? '')) ?>" placeholder="Directeur Marketing" lang="fr"></div>
      <div class="champ"><label for="a-en">Fonction de la personne <span class="drapeau">EN</span></label><input id="a-en" name="en[auteur]" value="<?= e((string) ($en['auteur'] ?? '')) ?>" placeholder="Marketing Director" lang="en"></div>
    </div>
    <label class="case" style="margin:6px 0 16px"><input type="checkbox" name="visible" value="1"<?= ($x['visible'] ?? 1) ? ' checked' : '' ?>> Visible sur l’accueil</label>
    <button class="bouton" type="submit">Enregistrer</button>
  </div>
</form>
<?php if (!$nouveau): ?>
<form method="post" action="<?= url('admin/temoignages/' . (int) $x['id'] . '/supprimer') ?>" data-confirmer="Supprimer ce témoignage ?" style="margin-top:28px">
  <?= csrf_champ() ?><button class="bouton danger fant" type="submit">Supprimer le témoignage</button>
</form>
<?php endif; ?>
