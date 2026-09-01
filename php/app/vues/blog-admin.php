<?php
/** Le blog côté rédaction : tout pour l'équipe, ses propres articles pour un organisateur. */
$erreur = $erreur ?? null;
$message = $message ?? null;
$publies = array_filter($liste, fn(array $a) => $a['statut'] === 'publie');
$etats = [
    'brouillon' => 'Brouillon', 'en_relecture' => 'En relecture', 'corrections' => 'À corriger',
    'refuse' => 'Refusé', 'publie' => 'En ligne',
];
?>
<div class="contenu">
  <section class="entete">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
      <div>
        <h1><?= $equipe ? 'Le blog' : 'Mes articles' ?></h1>
        <p><?php if ($equipe): ?>
          <?= count($publies) ?> article(s) en ligne sur <?= count($liste) ?>.
          C’est la seule partie du site qu’un moteur de recherche sait lire.
        <?php else: ?>
          Racontez une campagne qui a marché. La rédaction relit, puis publie sur le blog du guide —
          lu par tous les visiteurs.
        <?php endif; ?></p>
      </div>
      <div class="rangee" style="gap:10px">
        <?php if ($equipe && $a_relire): ?>
          <a class="bouton" href="<?= e(url('?p=blog-relecture')) ?>"><?= (int) $a_relire ?> à relire</a>
        <?php endif; ?>
        <a class="bouton<?= $equipe && $a_relire ? ' fant' : '' ?>" href="<?= e(url('?p=blog-editer')) ?>">Écrire un article</a>
      </div>
    </div>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <?php if (!$liste): ?>
    <div class="carte">
      <h3 style="margin:0 0 6px">Aucun article pour l’instant</h3>
      <p class="aide" style="margin:0 0 14px"><?= $equipe
        ? 'Le premier peut être court : une soirée, ce qui a marché, les chiffres.'
        : 'Un bon article de blog raconte un cas réel : combien de personnes, par quel moyen, et ce qui n’a pas marché. C’est ce qui se lit.' ?></p>
      <a class="bouton" href="<?= e(url('?p=blog-editer')) ?>">Écrire le premier</a>
    </div>
  <?php else: ?>
    <div class="tableau">
      <table>
        <thead>
          <tr><th>Titre</th><?= $equipe ? '<th>Auteur</th>' : '' ?><th>État</th><th>Publié</th>
          <th class="chiffre">Lectures</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($liste as $a): ?>
            <tr>
              <td>
                <a href="<?= e(url('?p=blog-editer&id=' . urlencode($a['id']))) ?>"><strong><?= e($a['titre']) ?></strong></a>
                <span class="aide" style="display:block"><code><?= e($a['slug']) ?></code></span>
                <?php if ($a['motif'] && in_array($a['statut'], ['corrections', 'refuse'], true)): ?>
                  <span class="aide" style="display:block;color:var(--rouge)"><?= e($a['motif']) ?></span>
                <?php endif; ?>
              </td>
              <?php if ($equipe): ?><td class="aide"><?= e($a['auteur_nom'] ?: '—') ?></td><?php endif; ?>
              <td><span class="pastille <?= e($a['statut']) ?>"><?= e($etats[$a['statut']] ?? $a['statut']) ?></span></td>
              <td class="aide"><?= $a['publie_le'] ? e(gmdate('d/m/Y', strtotime((string) $a['publie_le']))) : '—' ?></td>
              <td class="chiffre"><?= (int) $a['vues'] ?></td>
              <td style="text-align:right;white-space:nowrap">
                <?php if ($a['statut'] === 'publie'): ?>
                  <a class="bouton fant petit" href="<?= e(url('?p=blog&a=' . urlencode($a['slug']))) ?>">Voir</a>
                <?php elseif (in_array($a['statut'], ['brouillon', 'corrections'], true)): ?>
                  <form method="post" action="<?= e(url('?p=blog-action')) ?>" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                    <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                    <input type="hidden" name="quoi" value="<?= $equipe ? 'publier' : 'soumettre' ?>">
                    <button class="bouton petit" type="submit"><?= $equipe ? 'Publier' : 'Proposer' ?></button>
                  </form>
                <?php elseif ($a['statut'] === 'refuse'): ?>
                  <form method="post" action="<?= e(url('?p=blog-action')) ?>" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                    <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                    <input type="hidden" name="quoi" value="reprendre">
                    <button class="bouton fant petit" type="submit">Reprendre</button>
                  </form>
                <?php endif; ?>

                <?php if ($equipe || $a['statut'] !== 'publie'): ?>
                  <form method="post" action="<?= e(url('?p=blog-action')) ?>" style="display:inline"
                        onsubmit="return confirm('Supprimer « <?= e(addslashes($a['titre'])) ?> » ?<?= $a['statut'] === 'publie' ? ' Les liens partagés vers cet article cesseront de fonctionner.' : '' ?>')">
                    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                    <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                    <input type="hidden" name="quoi" value="supprimer">
                    <button class="bouton danger petit" type="submit">Supprimer</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if (!$equipe): ?>
    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 8px">Comment ça se passe</h3>
      <ol style="margin:0;padding-left:1.2em;line-height:1.8">
        <li>Vous écrivez, vous enregistrez autant de fois que vous voulez.</li>
        <li>Vous <strong>proposez</strong> : l’article part chez la rédaction et ne bouge plus.</li>
        <li>Elle publie, ou vous le renvoie avec un motif. Vous êtes prévenu dans les deux cas.</li>
        <li>Publié, il apparaît sur le blog et sur la page d’accueil, signé de votre nom.</li>
      </ol>
      <p class="aide" style="margin:12px 0 0">La rédaction relit parce que c’est le nom du guide
      qui porte l’article. Ce n’est pas de la défiance : un blog où n’importe quoi paraît ne se lit
      plus, et votre article y perdrait autant que le nôtre.</p>
    </div>
  <?php endif; ?>
</div>
