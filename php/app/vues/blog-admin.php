<?php
/** La rédaction : ce qui est en ligne, ce qui attend. */
$erreur = $erreur ?? null;
$message = $message ?? null;
$publies = array_filter($liste, fn(array $a) => $a['statut'] === 'publie');
?>
<div class="contenu">
  <section class="entete">
    <h1>Le blog</h1>
    <p><?= count($publies) ?> article(s) en ligne sur <?= count($liste) ?>.
    C’est la seule partie du site qu’un moteur de recherche sait lire.</p>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <div class="rangee" style="margin-bottom:16px;gap:10px">
    <a class="bouton" href="<?= e(url('?p=blog-editer')) ?>">Écrire un article</a>
    <a class="bouton fant" href="<?= e(url('?p=blog')) ?>">Voir le blog public</a>
  </div>

  <?php if (!$liste): ?>
    <div class="carte"><p style="margin:0;color:var(--text2)">Aucun article. Le premier peut
    être court : une soirée, ce qui a marché, les chiffres.</p></div>
  <?php else: ?>
    <div class="tableau">
      <table>
        <thead>
          <tr><th>Titre</th><th>État</th><th>Publié</th><th>Lectures</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($liste as $a): ?>
            <tr>
              <td>
                <a href="<?= e(url('?p=blog-editer&id=' . urlencode($a['id']))) ?>"><strong><?= e($a['titre']) ?></strong></a>
                <span class="aide" style="display:block"><code><?= e($a['slug']) ?></code></span>
              </td>
              <td>
                <span class="etiquette <?= $a['statut'] === 'publie' ? 'ok' : '' ?>">
                  <?= $a['statut'] === 'publie' ? 'En ligne' : 'Brouillon' ?>
                </span>
              </td>
              <td><?= $a['publie_le'] ? e(gmdate('d/m/Y', strtotime((string) $a['publie_le']))) : '—' ?></td>
              <td><?= (int) $a['vues'] ?></td>
              <td style="white-space:nowrap;text-align:right">
                <a class="bouton fant petit" href="<?= e(url('?p=blog&a=' . urlencode($a['slug']))) ?>">Voir</a>
                <form method="post" action="<?= e(url('?p=blog-supprimer')) ?>" style="display:inline"
                      onsubmit="return confirm('Supprimer « <?= e(addslashes($a['titre'])) ?> » ? Les liens partagés vers cet article cesseront de fonctionner.')">
                  <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                  <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                  <button class="bouton danger petit" type="submit">Supprimer</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
