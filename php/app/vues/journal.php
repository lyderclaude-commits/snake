<?php
/**
 * Le journal : qui a fait quoi.
 *
 * Une longue liste par date, deux filtres, et rien d'autre. Ce qu'on
 * cherche ici est toujours l'une de deux questions : « qu'a fait cette
 * personne ? » quand on a un doute, et « qui a supprimé des décors ? »
 * quand il en manque un. Tout ce qui ne sert pas à répondre à l'une des
 * deux serait du bruit posé sur une page qu'on consulte sous tension.
 */
$vers = function (int $n) use ($acteur, $action): string {
    return url('?p=journal' . ($acteur ? '&qui=' . urlencode($acteur) : '')
               . ($action ? '&quoi=' . urlencode($action) : '')
               . ($n > 1 ? '&n=' . $n : ''));
};
?>
<div class="contenu">
  <section class="entete">
    <h1>Journal</h1>
    <p><?= (int) $combien ?> action<?= $combien > 1 ? 's' : '' ?> enregistrée<?= $combien > 1 ? 's' : '' ?>.
    On y garde les <strong>décisions</strong> — publier, refuser, suspendre, supprimer — et
    non les lectures : un journal qui note chaque page vue devient illisible en une semaine.
    Il s’efface tout seul au bout d’un an.</p>
  </section>

  <form method="get" action="<?= e(url('?p=journal')) ?>" class="rangee chercher chercher-comptes">
    <input type="hidden" name="p" value="journal">
    <select name="qui" aria-label="Filtrer par personne">
      <option value="">Tout le monde</option>
      <?php foreach ($acteurs as $a): ?>
        <option value="<?= e((string) $a['acteur_id']) ?>" <?= $acteur === $a['acteur_id'] ? 'selected' : '' ?>>
          <?= e((string) $a['acteur_nom']) ?> (<?= (int) $a['n'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <select name="quoi" aria-label="Filtrer par action">
      <option value="">Toutes les actions</option>
      <?php foreach (JOURNAL_ACTIONS as $cle => $libelle): ?>
        <option value="<?= e($cle) ?>" <?= $action === $cle ? 'selected' : '' ?>><?= e(ucfirst($libelle)) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="bouton fant petit" type="submit">Filtrer</button>
    <?php if ($acteur || $action): ?>
      <a class="bouton fant petit" href="<?= e(url('?p=journal')) ?>">Tout voir</a>
    <?php endif; ?>
  </form>

  <?php if (!$lignes): ?>
    <div class="carte" style="text-align:center">
      <h3 style="margin:0">Rien à cet endroit</h3>
      <p class="aide" style="margin:.4em 0 0"><?= $acteur || $action
          ? 'Aucune action ne correspond à ce filtre.'
          : 'Le journal se remplira à la première décision — une publication, un refus, un compte créé.' ?></p>
    </div>
  <?php else: ?>
    <div class="tableau">
      <table>
        <thead><tr><th>Quand</th><th>Qui</th><th>Quoi</th><th>Sur</th></tr></thead>
        <tbody>
        <?php foreach ($lignes as $l): ?>
          <tr>
            <td class="mono" style="white-space:nowrap">
              <?= e(gmdate('d/m/Y', strtotime((string) $l['cree_le']))) ?>
              <br><span class="aide"><?= e(gmdate('H:i', strtotime((string) $l['cree_le']))) ?> UTC</span>
            </td>
            <td>
              <b><?= e((string) ($l['acteur_nom'] ?: 'Automatique')) ?></b>
              <?php if ($l['acteur_role']): ?>
                <br><span class="aide"><?= e(role_libelle((string) $l['acteur_role'])) ?></span>
              <?php endif; ?>
            </td>
            <td><?= e(ucfirst(journal_libelle((string) $l['action']))) ?></td>
            <td>
              <?= e((string) ($l['objet_titre'] ?: '—')) ?>
              <?php if ($l['detail']): ?>
                <br><span class="aide"><?= e((string) $l['detail']) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <div class="rangee" style="justify-content:center;gap:12px;margin-top:22px;align-items:center">
        <?php if ($page_n > 1): ?>
          <a class="bouton fant petit" href="<?= e($vers($page_n - 1)) ?>">← Plus récentes</a>
        <?php endif; ?>
        <span class="aide">Page <?= (int) $page_n ?> sur <?= (int) $pages ?></span>
        <?php if ($page_n < $pages): ?>
          <a class="bouton fant petit" href="<?= e($vers($page_n + 1)) ?>">Plus anciennes →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
