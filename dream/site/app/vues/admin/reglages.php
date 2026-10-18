<?php
$titre = 'Réglages';
$onglets = ['agence' => 'L’agence', 'accueil' => 'Accueil', 'referencement' => 'Référencement', 'legal' => 'Pages légales'];
$champ = function (string $k, string $label, string $aide = '', string $type = 'text') use ($r): string {
    $id = 'r-' . $k;
    $h = '<div class="champ"><label for="' . $id . '">' . e($label) . '</label>';
    $h .= $type === 'textarea'
        ? '<textarea id="' . $id . '" name="' . e($k) . '" rows="3">' . e($r[$k] ?? '') . '</textarea>'
        : '<input id="' . $id . '" name="' . e($k) . '" type="' . e($type) . '" value="' . e($r[$k] ?? '') . '">';
    return $h . ($aide !== '' ? '<p class="aide">' . $aide . '</p>' : '') . '</div>';
};
?>
<div class="entete"><div><h1>Réglages</h1><p>Ce qui s’affiche sur toutes les pages du site : coordonnées, réseaux, textes de l’accueil, référencement.</p></div>
  <div class="actions"><form method="post" action="<?= url('admin/sauvegarde') ?>"><?= csrf_champ() ?><button class="bouton fant" type="submit">Télécharger une sauvegarde</button></form></div>
</div>
<nav class="bord-onglets">
  <?php foreach ($onglets as $k => $l): ?><a href="<?= url('admin/reglages/' . $k) ?>"<?= $onglet === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?>
</nav>
<form method="post" action="<?= url('admin/reglages/' . $onglet) ?>" class="pile">
  <?= csrf_champ() ?>
<?php if ($onglet === 'agence'): ?>
  <div class="carte"><h2>Coordonnées</h2><p class="aide">Utilisées dans le pied de page, la page Contact et les données lues par Google.</p>
    <div class="champs">
      <?= $champ('nom_site', 'Nom de l’agence') ?>
      <?= $champ('telephone', 'Téléphone principal') ?>
      <?= $champ('telephone2', 'Second téléphone') ?>
      <?= $champ('whatsapp', 'Numéro WhatsApp', 'Indicatif compris, chiffres seuls : 22870087777. Il alimente tous les boutons WhatsApp du site.') ?>
      <?= $champ('email', 'E-mail principal', '', 'email') ?>
      <?= $champ('email2', 'Second e-mail', '', 'email') ?>
      <?= $champ('adresse', 'Adresse') ?>
      <?= $champ('adresse2', 'Ville, repère') ?>
    </div>
  </div>
  <div class="carte"><h2>Horaires</h2>
    <div class="champs"><?= $champ('horaires_semaine', 'Lundi — Vendredi') ?><?= $champ('horaires_samedi', 'Samedi') ?><?= $champ('horaires_dimanche', 'Dimanche') ?></div>
  </div>
  <div class="carte"><h2>Réseaux sociaux</h2><p class="aide">Vide : l’icône disparaît du pied de page.</p>
    <div class="champs"><?= $champ('facebook', 'Facebook', '', 'url') ?><?= $champ('instagram', 'Instagram', '', 'url') ?><?= $champ('linkedin', 'LinkedIn', '', 'url') ?><?= $champ('tiktok', 'TikTok', '', 'url') ?></div>
  </div>
  <div class="carte"><h2>Formulaire de contact</h2>
    <?= $champ('contact_destinataire', 'Recevoir les demandes à', 'Chaque demande est enregistrée dans « Messages » et envoyée à cette adresse. Si les e-mails n’arrivent pas, vérifiez les indésirables : l’envoi passe par le serveur de l’hébergeur.', 'email') ?>
  </div>
<?php elseif ($onglet === 'accueil'): ?>
  <div class="carte"><h2>Le grand titre</h2>
    <p class="aide">Une ligne par retour à la ligne. <code>*mots*</code> s’affichent en couleur, <code>_mots_</code> en contour, <code>**mots**</code> en gras.</p>
    <?= $champ('hero_titre', 'Titre', '', 'textarea') ?>
    <?= $champ('hero_sous_titre', 'Sous-titre', '', 'textarea') ?>
  </div>
  <div class="carte"><h2>Les chiffres clés</h2><p class="aide">Repris sur l’accueil et la page À propos. Écrivez « 200+ » : le nombre s’anime, le signe reste.</p>
    <?php for ($i = 1; $i <= 4; $i++): ?><div class="champs"><?= $champ('stat' . $i, 'Chiffre ' . $i) ?><?= $champ('stat' . $i . '_libelle', 'Libellé') ?></div><?php endfor; ?>
  </div>
<?php elseif ($onglet === 'referencement'): ?>
  <div class="carte"><h2>Adresse du site</h2>
    <?= $champ('site_url', 'Adresse publique', 'Par exemple <code>https://liveydream.com</code>. Elle sert aux liens de partage, au plan du site et à Google. Vide : déduite de la visite.', 'url') ?>
  </div>
  <div class="carte"><h2>Description</h2>
    <?= $champ('meta_description', 'Description par défaut', 'Ce que Google affiche sous le nom du site. 150 caractères environ.', 'textarea') ?>
    <?= $champ('verif_google', 'Code de vérification Google Search Console', 'Le contenu de la balise <code>google-site-verification</code>.') ?>
    <p class="aide">Plan du site à déclarer dans Search Console : <a href="<?= url('sitemap.xml') ?>" target="_blank"><?= e(url_absolue('sitemap.xml')) ?></a></p>
  </div>
<?php else: ?>
  <div class="carte"><h2>Mentions légales</h2><p class="aide">Vide : un texte type est affiché, construit avec vos coordonnées. Indiquez au moins l’hébergeur et le responsable de publication. HTML simple accepté (&lt;h2&gt;, &lt;p&gt;, &lt;a&gt;…).</p>
    <textarea name="mentions_legales" rows="12" aria-label="Mentions légales"><?= e($r['mentions_legales']) ?></textarea>
  </div>
  <div class="carte"><h2>Politique de confidentialité</h2>
    <textarea name="confidentialite" rows="12" aria-label="Politique de confidentialité"><?= e($r['confidentialite']) ?></textarea>
  </div>
<?php endif; ?>
  <div><button class="bouton" type="submit">Enregistrer</button></div>
</form>
