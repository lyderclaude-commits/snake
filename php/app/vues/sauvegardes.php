<?php
/** Faire une sauvegarde, la télécharger, et régler celle du cron. */
$url_cron = base_url() . '/index.php?p=sauvegarde-auto&cle=' . $cle;
$derniere = $liste[0] ?? null;
$vieille = $derniere && (time() - $derniere['date']) > 8 * 86400;
?>
<div class="contenu">
  <section class="entete">
    <h1>Sauvegardes</h1>
    <p>Les comptes, les campagnes, les badges émis, les présences scannées — et les
    cadres, qui sont des fichiers et que personne ne pense à sauver avec la base.</p>
  </section>

  <?php if (!empty($_GET['ok'])): ?><div class="msg ok" role="status"><?= e($_GET['ok']) ?></div><?php endif; ?>
  <?php if (!empty($_GET['err'])): ?><div class="msg err" role="alert"><?= e($_GET['err']) ?></div><?php endif; ?>

  <?php if (!$derniere): ?>
    <div class="msg err" style="margin-bottom:16px">
      <strong>Aucune sauvegarde</strong>
      <p style="margin:.35em 0 0">Rien n’a jamais été sauvegardé. C’est le moment.</p>
    </div>
  <?php elseif ($vieille): ?>
    <div class="msg err" style="margin-bottom:16px">
      <strong>La dernière sauvegarde date du <?= e(gmdate('d/m/Y', $derniere['date'])) ?></strong>
      <p style="margin:.35em 0 0">Plus d’une semaine. Faites-en une, et voyez la tâche
      planifiée plus bas pour ne plus avoir à y penser.</p>
    </div>
  <?php endif; ?>

  <div class="carte">
    <h3 style="margin:0 0 4px">Sauvegarder maintenant</h3>
    <p class="aide" style="margin:0 0 16px">
      L’archive contient
      <?= $mysql ? '<code>base.sql</code>, à réimporter dans phpMyAdmin' : '<code>wakabi.sqlite</code>, la base entière' ?>,
      les cadres téléversés, et une notice qui explique comment restaurer.
      Elle ne contient <strong>pas</strong> <code>config.php</code> : il décrit votre
      serveur et n’a rien à faire dans un fichier qui circule.
    </p>
    <form method="post" action="<?= e(url('?p=sauvegarder')) ?>" style="margin:0">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <button class="bouton" type="submit">Créer une sauvegarde</button>
    </form>
  </div>

  <div class="carte" style="margin-top:16px">
    <div class="rangee" style="justify-content:space-between;align-items:baseline">
      <h3 style="margin:0">Sur le serveur</h3>
      <span class="aide"><?= count($liste) ?> archive<?= count($liste) > 1 ? 's' : '' ?>,
      les <?= SAUVEGARDES_GARDEES ?> dernières sont conservées</span>
    </div>

    <?php if (!$liste): ?>
      <p style="color:var(--text2);margin:14px 0 0">Rien pour l’instant.</p>
    <?php else: ?>
      <?php foreach ($liste as $s): ?>
        <div class="rangee" style="justify-content:space-between;border-top:1px solid var(--border);padding:10px 0;gap:12px">
          <div>
            <strong class="mono"><?= e($s['nom']) ?></strong>
            <span class="aide" style="margin-left:8px"><?= e(gmdate('d/m/Y à H:i', $s['date'])) ?> UTC
            · <?= (int) round($s['taille'] / 1024) ?> Ko</span>
          </div>
          <div class="rangee" style="gap:8px;flex-wrap:nowrap">
            <a class="bouton petit" href="<?= e(url('?p=telecharger-sauvegarde&f=' . rawurlencode($s['nom']))) ?>">Télécharger</a>
            <form method="post" action="<?= e(url('?p=supprimer-sauvegarde')) ?>" style="margin:0">
              <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
              <input type="hidden" name="f" value="<?= e($s['nom']) ?>">
              <button class="bouton fant petit" type="submit">Supprimer</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <p class="aide" style="margin:16px 0 0">
      <strong>Une sauvegarde gardée sur le serveur qu’elle sauvegarde ne sauvegarde rien.</strong>
      Téléchargez-la, et gardez-en une copie ailleurs — un disque, un espace en ligne,
      peu importe, mais pas la même machine.
    </p>
  </div>

  <div class="carte" style="margin-top:16px">
    <h3 style="margin:0 0 4px">Tous les jours, sans y penser</h3>
    <p class="aide" style="margin:0 0 14px">Dans cPanel, ouvrez <strong>Tâches Cron</strong>,
    choisissez « Une fois par jour », et collez cette ligne :</p>
    <pre style="overflow-x:auto;background:var(--fond2);padding:12px;border-radius:10px;margin:0"><code>curl -s "<?= e($url_cron) ?>"</code></pre>
    <p class="aide" style="margin:12px 0 0">
      La clé de cette adresse remplace le mot de passe : elle seule autorise la tâche.
      Ne la publiez pas. Les <?= SAUVEGARDES_GARDEES ?> dernières archives sont gardées,
      les plus anciennes s’effacent toutes seules — sinon le quota de l’hébergeur se
      remplit, et un disque plein empêche d’écrire la sauvegarde suivante.
    </p>
    <p class="aide" style="margin:10px 0 0">
      Si votre hébergement ne propose pas <code>curl</code>, remplacez-le par
      <code>wget -q -O- "…"</code>. Vous pouvez aussi ouvrir cette adresse dans un
      navigateur pour vérifier qu’elle répond.
    </p>
  </div>
</div>
