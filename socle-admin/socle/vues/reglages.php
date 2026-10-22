<?php
/** Les réglages : les onglets déclarés par le site, puis « E-mails », celui du socle. */
require_once __DIR__ . '/_champs.php';
$titre = 'Réglages';
$o = $onglets[$onglet] ?? ['titre' => '', 'description' => '', 'sections' => []];
$champ = function (string $k, string $label, string $aide = '', string $type = 'text') use ($r): string {
    $id = 'r-' . $k;
    return '<div class="champ"><label for="' . $id . '">' . e($label) . '</label>'
        . '<input id="' . $id . '" name="' . e($k) . '" type="' . e($type) . '" value="' . e($r[$k] ?? '') . '">'
        . ($aide !== '' ? '<p class="aide">' . $aide . '</p>' : '') . '</div>';
};
?>
<div class="entete"><div><h1>Réglages</h1><p><?= $o['description'] !== '' ? $o['description'] : 'Ce qui s’affiche sur toutes les pages du site.' ?></p></div>
  <div class="actions"><form method="post" action="<?= url('admin/sauvegarde') ?>"><?= csrf_champ() ?><button class="bouton fant" type="submit">Télécharger une sauvegarde</button></form></div>
</div>
<nav class="bord-onglets">
  <?php foreach ($onglets as $k => $x): ?><a href="<?= url('admin/reglages/' . $k) ?>"<?= $onglet === $k ? ' aria-current="page"' : '' ?>><?= e($x['titre']) ?></a><?php endforeach; ?>
</nav>
<form method="post" action="<?= url('admin/reglages/' . $onglet) ?>" class="pile">
  <?= csrf_champ() ?>
<?php if (!empty($o['socle'])): ?>
  <div class="carte"><h2>Les e-mails du formulaire de contact</h2>
    <p class="aide">Chaque demande est enregistrée dans « Messages »<?php if (CONTACT_EMAIL !== ''): ?> <b>et</b> envoyée par e-mail à <b><?= e(CONTACT_EMAIL) ?></b> — toujours, ce destinataire ne se retire pas<?php endif; ?>. Répondre à l’e-mail répond directement au client.</p>
    <?= $champ('contact_copies', CONTACT_EMAIL !== '' ? 'Envoyer aussi une copie à' : 'Envoyer chaque demande à', 'Plusieurs adresses séparées par des virgules.') ?>
    <?= $champ('courriel_expediteur', 'Adresse d’expédition', 'Doit appartenir au domaine du site. Vide : l’identifiant SMTP, sinon <code>' . e(courriel_expediteur()) . '</code>.', 'email') ?>
    <h3 style="margin:18px 0 4px">Serveur d’envoi (SMTP) — recommandé</h3>
    <p class="aide" style="margin:0 0 14px">Sans lui, le site passe par la fonction <code>mail()</code> de l’hébergeur : ça marche, mais ces messages tombent souvent dans les indésirables. Avec les identifiants d’une boîte du domaine (espace client de l’hébergeur, rubrique e-mails), ils arrivent signés par le vrai serveur du domaine.</p>
    <div class="champs">
      <?= $champ('smtp_hote', 'Serveur SMTP', 'Souvent <code>mail.' . e((string) preg_replace('/^www\./', '', (string) parse_url(base_url(), PHP_URL_HOST))) . '</code>. Vide : <code>mail()</code>.') ?>
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
<?php else: ?>
  <?php foreach ($o['sections'] as $s):
      $champs = []; foreach ((array) ($s['champs'] ?? []) as $k => $c) { $champs[$k] = champ_normaliser((string) $k, (array) $c); }
      $longs = (bool) array_filter($champs, fn($c) => !in_array($c['type'], CHAMP_COURTS, true) && $c['type'] !== 'case'); ?>
    <div class="carte"><?php if (($s['titre'] ?? '') !== ''): ?><h2><?= e($s['titre']) ?></h2><?php endif; ?>
      <?php if (($s['aide'] ?? '') !== ''): ?><p class="aide"><?= $s['aide'] ?></p><?php endif; ?>
      <div class="<?= $longs ? 'pile' : 'champs' ?>"<?= $longs ? ' style="gap:0"' : '' ?>>
      <?php foreach ($champs as $k => $c): ?>
        <?php if ($c['traduit'] && isset(LANGUES['en'])): ?>
          <div class="duo-langues">
            <?= champ_html($c, $k, $r[$k] ?? '', ['libelle' => $c['libelle'] . ' — français', 'id' => 'r-' . $k]) ?>
            <?= champ_html($c, $k . '_en', $r[$k . '_en'] ?? '', ['libelle' => $c['libelle'] . ' — anglais', 'en' => true, 'id' => 'r-' . $k . '_en', 'aide' => 'Vide : le français s’affiche aussi sur le site anglais.']) ?>
          </div>
        <?php else: ?>
          <?= champ_html($c, $k, $r[$k] ?? '', ['id' => 'r-' . $k]) ?>
        <?php endif; ?>
      <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
  <div><button class="bouton" type="submit">Enregistrer</button></div>
</form>
<?php if (array_filter(reglages_champs(), fn($c) => ($c['type'] ?? '') === 'image')) require __DIR__ . '/_selecteur.php'; ?>
