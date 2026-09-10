<?php
/**
 * La frise des rappels d'un événement.
 *
 * Une seule question, et la réponse en un coup d'œil : qu'est-ce qui est
 * déjà parti, qu'est-ce qui attend son heure. Le reste — modifier un
 * texte, changer les canaux — se fait dans la régie, où l'on sait déjà le
 * faire.
 */
$message = $message ?? null;
$erreur = $erreur ?? null;
$rappels = $rappels ?? [];
$quand = static function (?string $iso): string {
    if (!$iso) {
        return 'sans date';
    }
    $t = strtotime($iso);
    return $t === false ? 'sans date' : gmdate('d/m/Y \à H\hi', $t);
};
$ev = $decor['evenement_le'] ? strtotime((string) $decor['evenement_le']) : null;
$jours = $ev ? (int) floor(($ev - time()) / 86400) : null;
?>
<div class="contenu">
  <p class="fil"><a class="retour" href="<?= e(url('?p=regie')) ?>"><i>←</i>La régie</a></p>

  <section class="entete">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
      <div>
        <h1>Rappels · <?= e($decor['titre']) ?></h1>
        <p>
          <?php if ($ev): ?>
            <?= e(gmdate('d/m/Y', $ev)) ?><?= $decor['ville'] ? ' · ' . e(ucfirst((string) $decor['ville'])) : '' ?>
            <?php if ($jours !== null): ?>
              · <strong><?= $jours >= 0 ? 'J − ' . $jours : 'passé depuis ' . abs($jours) . ' j' ?></strong>
            <?php endif; ?>
          <?php else: ?>
            Ce décor n’a pas encore de date d’événement.
          <?php endif; ?>
        </p>
      </div>
      <div class="rangee" style="gap:8px">
        <a class="bouton fant" href="<?= e(url('?p=modifier&id=' . urlencode((string) $decor['id']))) ?>">Le décor</a>
        <form method="post" action="<?= e(url('?p=rappels&id=' . urlencode((string) $decor['id']))) ?>">
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <input type="hidden" name="quoi" value="poser">
          <button class="bouton" type="submit">Poser les cinq rappels</button>
        </form>
      </div>
    </div>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <?php if (!$rappels): ?>
    <div class="carte">
      <h3 style="margin:0 0 6px">Aucun rappel pour l’instant</h3>
      <p class="aide" style="margin:0 0 14px">Cinq moments couvrent un événement : l’annonce, la
      semaine d’avant, la veille, deux heures avant, et le lendemain. Ils se posent d’un clic, avec
      leurs dates calculées depuis celle de l’événement, et tout reste modifiable ensuite.</p>
      <?php if (!$decor['evenement_le']): ?>
        <p class="aide" style="margin:0">Ajoutez d’abord la <strong>date de l’événement</strong> au
        décor : c’est d’elle que tout se déduit.</p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="fr">
      <?php foreach ($rappels as $r):
        $parti = in_array($r['statut'], ['envoye', 'envoi'], true);
        $prochain = !$parti && $r['planifie_le'] && strtotime((string) $r['planifie_le']) > time();
        $classe = $parti ? 'fait' : ($prochain ? 'suiv' : '');
        $portee = (int) $r['destinataires'];
      ?>
        <div class="fr-e <?= e($classe) ?>">
          <div class="fr-q">
            <?= e($quand($r['planifie_le'] ?: null)) ?>
            · <?= e(REGIE_STATUTS[$r['statut']] ?? $r['statut']) ?>
          </div>
          <div class="fr-t"><?= e($r['titre']) ?></div>
          <p class="aide" style="margin:0"><?= e(mb_strimwidth((string) $r['corps'], 0, 140, '…')) ?></p>
          <div class="puces">
            <?php foreach (regie_canaux($r) as $cc):
              $g = (string) $cc['canal'];
              $cl = $g === 'email' ? 'mail' : ($g === 'push' ? 'web' : e($g)); ?>
              <span class="puce <?= e($cl) ?>"><?= e(regie_canal_libelle($cc)) ?></span>
            <?php endforeach; ?>
            <?php if ($portee > 0): ?>
              <span class="puce" style="background:var(--bg2);color:var(--text2)"><?= $portee ?> destinations</span>
            <?php endif; ?>
          </div>
          <div class="rangee" style="gap:8px;margin-top:11px">
            <a class="bouton fant petit"
               href="<?= e(url('?p=regie-campagne&id=' . urlencode((string) $r['id']))) ?>">Ouvrir</a>
            <?php if (!$parti): ?>
              <a class="bouton fant petit"
                 href="<?= e(url('?p=regie-ecrire&id=' . urlencode((string) $r['id']))) ?>">Modifier</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="carte" style="margin-top:16px">
    <h3 style="margin:0 0 8px">Les cinq moments</h3>
    <div style="overflow-x:auto">
      <table class="tab" style="min-width:520px">
        <tr><th>Quand</th><th>Ce qu’on dit</th><th>Pourquoi</th></tr>
        <?php
        $pourquoi = [
          'annonce' => 'Le lien circule, les badges se créent : c’est ce qui remplit la salle.',
          'semaine' => 'La relance des indécis, pendant qu’il reste le temps de s’organiser.',
          'veille'  => 'Avec le badge et le code d’entrée : le seul rappel qu’on relit à la porte.',
          'portes'  => 'Court, sans lien : à deux heures, personne ne clique.',
          'merci'   => 'Aux présents scannés seulement : remercier un absent, c’est lui rappeler qu’il a manqué.',
        ];
        foreach ($modele as $m):
          $h = (int) $m['heures'];
          $libelle = $h >= 24 ? 'J − ' . intdiv($h, 24) : ($h >= 0 ? 'H − ' . $h : 'J + ' . intdiv(abs($h) + 23, 24));
        ?>
          <tr>
            <td><b><?= e($libelle) ?></b></td>
            <td><?= e($m['titre']) ?></td>
            <td style="color:var(--text2)"><?= e($pourquoi[$m['cle']] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>
