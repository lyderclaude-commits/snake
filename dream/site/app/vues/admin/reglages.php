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
  <div class="carte"><h2>Les e-mails du formulaire de contact</h2>
    <p class="aide">Chaque demande est enregistrée dans « Messages » <b>et</b> envoyée par e-mail à <b><?= e(CONTACT_EMAIL) ?></b> — toujours, ce destinataire ne se retire pas. Répondre à l’e-mail répond directement au client.</p>
    <?= $champ('contact_copies', 'Envoyer aussi une copie à', 'Facultatif. Plusieurs adresses séparées par des virgules.') ?>
    <?= $champ('courriel_expediteur', 'Adresse d’expédition', 'Doit appartenir au domaine du site (par exemple <code>site@liveydream.com</code>). Vide : l’identifiant SMTP, sinon <code>' . e(courriel_expediteur()) . '</code>.', 'email') ?>
    <h3 style="margin:18px 0 4px">Serveur d’envoi (SMTP) — recommandé</h3>
    <p class="aide" style="margin:0 0 14px">Sans lui, le site passe par la fonction <code>mail()</code> de l’hébergeur : ça marche, mais ces messages tombent souvent dans les indésirables. Avec les identifiants de la boîte <?= e(CONTACT_EMAIL) ?> (espace client de l’hébergeur, rubrique e-mails), ils arrivent signés par le vrai serveur du domaine.</p>
    <div class="champs">
      <?= $champ('smtp_hote', 'Serveur SMTP', 'Souvent <code>mail.liveydream.com</code>. Vide : <code>mail()</code>.') ?>
      <?= $champ('smtp_port', 'Port', '587 (STARTTLS) ou 465 (SSL).', 'number') ?>
      <div class="champ"><label for="r-smtp_chiffrement">Chiffrement</label>
        <select id="r-smtp_chiffrement" name="smtp_chiffrement">
          <?php foreach (['tls' => 'STARTTLS (port 587, recommandé)', 'ssl' => 'SSL (port 465)', 'aucun' => 'Aucun'] as $k => $l): ?>
            <option value="<?= $k ?>"<?= ($r['smtp_chiffrement'] ?: 'tls') === $k ? ' selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select></div>
      <?= $champ('smtp_utilisateur', 'Identifiant', 'Le plus souvent l’adresse e-mail elle-même.') ?>
      <div class="champ"><label for="r-smtp_mdp">Mot de passe</label>
        <input id="r-smtp_mdp" name="smtp_mdp" type="password" autocomplete="new-password" value="" placeholder="<?= $r['smtp_mdp'] !== '' ? 'enregistré — laissez vide pour le garder' : 'aucun enregistré' ?>">
        <p class="aide">Jamais réaffiché.</p></div>
    </div>
    <div class="rangee"><button class="bouton fant" type="submit" name="tester" value="1">Enregistrer et envoyer un e-mail de test</button></div>
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
