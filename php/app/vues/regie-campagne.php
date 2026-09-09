<?php
/** Une campagne : ce qu'elle dit, à qui, sur quels canaux, et où elle en est. */
$erreur = $erreur ?? null;
$message = $message ?? null;
$echecs = $echecs ?? [];
$canaux = $canaux ?? [['canal' => 'email']];
$modifiable = in_array($c['statut'], ['brouillon', 'corrections', 'refuse'], true);
$soumettable = in_array($c['statut'], ['brouillon', 'corrections'], true);
$bouton = function (string $quoi, string $libelle, string $classe = 'bouton',
                    bool $motif = false, array $sup = []) use ($c) {
    ob_start(); ?>
    <form method="post" action="<?= e(url('?p=regie-action')) ?>" style="display:inline">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <input type="hidden" name="id" value="<?= e($c['id']) ?>">
      <input type="hidden" name="quoi" value="<?= e($quoi) ?>">
      <?php if ($motif): ?><input type="hidden" name="motif" value=""><?php endif; ?>
      <?php foreach ($sup as $k => $v): ?>
        <input type="hidden" name="<?= e((string) $k) ?>" value="<?= e((string) $v) ?>">
      <?php endforeach; ?>
      <button class="<?= e($classe) ?>" type="submit"><?= e($libelle) ?></button>
    </form>
    <?php return ob_get_clean();
};

/**
 * Ce que l'échec veut dire, en une phrase.
 *
 * Un code seul — « 550 » — ne dit rien à qui n'a pas passé sa vie dans
 * des serveurs de messagerie. Ce qui doit se lire, c'est la décision qui
 * s'ensuit : on relance, ou on n'insiste pas.
 */
$pourquoi = static function (array $e): string {
    if ($e['statut'] === 'desabonne') {
        return 'S’est désabonné entre le moment où la liste a été figée et l’envoi. '
             . 'Écarté, comme il se doit.';
    }
    if ($e['statut'] === 'archive') {
        return 'Rangée : cette destination ne sera plus servie, ici ni dans les campagnes suivantes.';
    }
    if ($e['mortel']) {
        return 'Définitif : cette destination n’existe pas. La relancer abîmerait la '
             . 'réputation du domaine — et c’est exactement ce que les fournisseurs comptent contre vous.';
    }
    if ($e['reprenable']) {
        return 'Passager : le relais était indisponible. Un nouvel essai a des chances d’aboutir.';
    }
    return 'Refusé sur le moment. Un nouvel essai peut aboutir si la cause a disparu, '
         . 'mais rien ne le garantit.';
};

$a_relancer = count(array_filter($echecs, static fn(array $e): bool => (bool) $e['reprenable']));

/**
 * Le décompte se fait sur les LIGNES, pas sur le compteur de la campagne.
 *
 * Les deux peuvent diverger d'un cheveu — une ligne archivée reste un
 * message non remis pour le compteur, mais n'est plus une tâche à faire —
 * et afficher « 5 » au-dessus d'un tableau qui en montre 4 fait douter
 * des deux. On dit donc ce qu'on montre.
 */
$par_nature = ['echec' => 0, 'desabonne' => 0, 'archive' => 0];
foreach ($echecs as $e) {
    $par_nature[$e['statut']] = ($par_nature[$e['statut']] ?? 0) + 1;
}
$detail = [];
$mots = [
    'echec'     => ['en échec', 'en échec'],
    'desabonne' => ['désabonné', 'désabonnés'],
    // « Archivé » seul se lisait mal sous une barre d'envoi qui dit « 1 en
    // échec » : ce sont les mêmes lignes, vues à deux moments.
    'archive'   => ['échec archivé', 'échecs archivés'],
];
foreach ($mots as $k => [$un, $plusieurs]) {
    if ($par_nature[$k] > 0) {
        $detail[] = $par_nature[$k] . ' ' . ($par_nature[$k] > 1 ? $plusieurs : $un);
    }
}
?>
<div class="contenu etroit-large">
  <p class="fil"><a href="<?= e(url('?p=regie')) ?>">← La régie</a></p>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <section class="entete">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
      <div>
        <h1 style="margin-bottom:6px"><?= e($c['sujet']) ?></h1>
        <p>
          <span class="pastille <?= e($c['statut']) ?>"><?= e(REGIE_STATUTS[$c['statut']] ?? $c['statut']) ?></span>
          · <?php if ($liste): ?>
            <a href="<?= e(url('?p=regie-carnet&l=' . urlencode((string) $liste['id']))) ?>">« <?= e((string) $liste['nom']) ?> »</a>
          <?php else: ?>
            <?= e(REGIE_CIBLES[$c['cible']][0] ?? $c['cible']) ?>
          <?php endif; ?>
          · <strong><?= (int) $vise ?></strong> destinataire(s)
          <?php if ($equipe && $auteur): ?> · <?= e($auteur['nom']) ?><?php endif; ?>
          <?php if ($c['rappel']): ?> · rappel <?= e(rappel_libelle((string) $c['rappel'])) ?><?php endif; ?>
        </p>
        <div class="puces">
          <?php foreach ($canaux as $cc):
            $g = (string) $cc['canal'];
            $cl = $g === 'email' ? 'mail' : ($g === 'push' ? 'web' : $g); ?>
            <span class="puce <?= e($cl) ?>"><?= e(regie_canal_libelle($cc)) ?></span>
          <?php endforeach; ?>
          <?php if (!empty($c['planifie_le']) && ($_t = strtotime((string) $c['planifie_le']))): ?>
            <span class="puce plus">Programmée · <?= e(gmdate('d/m/Y \à H\hi', $_t)) ?> UTC</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="rangee" style="gap:8px">
        <?php if ($modifiable): ?>
          <a class="bouton fant" href="<?= e(url('?p=regie-ecrire&id=' . urlencode($c['id']))) ?>">Modifier</a>
        <?php endif; ?>
        <?php if ($c['statut'] !== 'envoi'): ?>
          <details class="sup">
            <summary class="bouton fant">Supprimer…</summary>
            <div class="sup-p mot">
              <p>La campagne et son suivi d’envoi disparaissent. Les messages déjà partis,
              eux, sont chez les gens : rien ne les rappelle.</p>
              <?= $bouton('supprimer', 'Supprimer pour de bon', 'bouton danger petit') ?>
            </div>
          </details>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?php if ($c['motif']): ?>
    <div class="msg <?= $c['statut'] === 'refuse' ? 'err' : '' ?>" style="margin-bottom:16px">
      <strong><?= $c['statut'] === 'refuse' ? 'Refusée' : ($c['statut'] === 'corrections' ? 'À corriger' : 'Note de la régie') ?></strong>
      <p style="margin:.35em 0 0"><?= e($c['motif']) ?></p>
    </div>
  <?php endif; ?>

  <!-- ---------- l'aperçu ---------- -->
  <div class="carte">
    <h3 style="margin:0 0 4px">Ce que la personne recevra</h3>
    <p class="aide" style="margin:0 0 16px">Le pied de désabonnement est ajouté à l’envoi ;
    il n’est pas dans cet aperçu mais il partira.</p>

    <div class="apercu-courriel">
      <div class="entete-courriel">WAKABI BOOST</div>
      <div class="corps-courriel">
        <h4><?= e($c['titre']) ?></h4>
        <?php foreach (preg_split('/\n{2,}/', trim((string) $c['corps'])) ?: [] as $p): ?>
          <p><?= nl2br(e($p)) ?></p>
        <?php endforeach; ?>
        <?php if ($c['lien']): ?>
          <p><span class="faux-bouton"><?= e($c['lien_libelle'] ?: 'Ouvrir') ?></span></p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ---------- l'avancement ---------- -->
  <?php if (in_array($c['statut'], ['prete', 'envoi', 'envoye'], true)): ?>
    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 14px">L’envoi</h3>
      <?php
      $total = max(1, (int) $c['destinataires']);
      $faits = (int) $c['envoyes'] + (int) $c['echecs'];
      ?>
      <div class="marche">
        <div class="haut">
          <span>Messages partis</span>
          <b><?= (int) $c['envoyes'] ?> / <?= (int) $c['destinataires'] ?></b>
        </div>
        <div class="rail"><i style="width:<?= min(100, (int) round($faits / $total * 100)) ?>%"></i></div>
        <span class="taux">
          <?= (int) $c['envoyes'] ?> parti<?= $c['envoyes'] > 1 ? 's' : '' ?><?php
            if ((int) $c['echecs'] > 0): ?>,
            <strong style="color:#B91C1C"><?= (int) $c['echecs'] ?> en échec</strong><?php
            endif; ?>,
          <?= max(0, (int) $c['destinataires'] - (int) $c['envoyes'] - (int) $c['echecs']) ?> restant(s).
          <?php if ($c['statut'] === 'envoye'): ?>
            Terminé<?= $c['envoye_le'] ? ' le ' . e(gmdate('d/m/Y à H:i', strtotime((string) $c['envoye_le']))) . ' UTC' : '' ?>.
          <?php else: ?>
            Par lots de <?= REGIE_LOT ?>.
          <?php endif; ?>
          « Parti » veut dire accepté par le relais — pas encore arrivé.
        </span>
      </div>
    </div>
  <?php endif; ?>

  <!-- ---------- ce qui n'est pas passé ---------- -->
  <?php if ($echecs): ?>
    <div class="carte" style="margin-top:16px">
      <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
        <div>
          <h3 style="margin:0 0 4px">Ce qui n’a pas abouti</h3>
          <p class="aide" style="margin:0"><strong><?= e(implode(', ', $detail)) ?></strong>.
          Le nombre seul ne dit pas s’il faut agir : voici lesquels, pourquoi, et lesquels
          se relancent.</p>
        </div>
        <div class="rangee" style="gap:8px">
          <?php if ($a_relancer > 0): ?>
            <?= $bouton('relancer', 'Relancer ' . ($a_relancer > 1 ? 'les ' . $a_relancer . ' reprenables' : 'la reprenable')) ?>
          <?php endif; ?>
          <a class="bouton fant" href="<?= e(url('?p=regie-echecs-export&id=' . urlencode((string) $c['id']))) ?>">Exporter la liste</a>
        </div>
      </div>

      <div style="overflow-x:auto;margin-top:14px">
        <table class="ech" style="min-width:560px">
          <tr><th style="min-width:160px">Destinataire</th><th>État</th><th class="chiffre">Essais</th>
          <th>Ce que le serveur a répondu</th><th></th></tr>
          <?php foreach ($echecs as $e): ?>
            <tr>
              <td>
                <b style="overflow-wrap:break-word"><?= e($e['qui'] ?: '—') ?></b>
                <span class="aide" style="display:block"><?= e($e['nom'] ?: '—') ?><?php
                  if ($e['genre_canal'] !== 'email'): ?> · <?= e($e['genre_canal']) ?><?php endif; ?></span>
              </td>
              <td><span class="pastille <?= e((string) $e['statut']) ?>"><?php
                echo e($e['statut'] === 'echec' && !$e['reprenable']
                    ? 'Refusé'
                    : (REGIE_ENVOIS_STATUTS[$e['statut']] ?? (string) $e['statut'])); ?></span></td>
              <td class="chiffre"><?= (int) $e['tentatives'] ?></td>
              <td class="<?= $e['statut'] === 'echec' ? 'motif' : 'aide' ?>">
                <?php
                /* Le code s'affiche à part : le laisser aussi dans la phrase
                   donnerait « 421 421 4.7.0 … », et l'on doute de ce qu'on lit. */
                $dit = preg_replace('/^Le serveur SMTP a répondu :\s*/u', '', (string) ($e['message'] ?? ''));
                if ($e['code']) {
                    $dit = preg_replace('/^' . preg_quote((string) $e['code'], '/') . '[ .-]*/', '', (string) $dit);
                }
                ?>
                <?php if ($e['code']): ?><span class="mono"><?= e((string) $e['code']) ?></span> <?php endif; ?>
                <?= e(trim((string) $dit) ?: '—') ?>
                <span class="aide" style="display:block;margin-top:2px"><?= e($pourquoi($e)) ?></span>
              </td>
              <td style="text-align:right;white-space:nowrap">
                <?php if ($e['statut'] === 'echec' && $e['mortel']): ?>
                  <?= $bouton('archiver', 'Archiver', 'bouton fant petit', false, ['envoi' => (string) $e['id']]) ?>
                <?php elseif ($e['statut'] === 'echec'): ?>
                  <?= $bouton('relancer-un', 'Relancer', 'bouton fant petit', false, ['envoi' => (string) $e['id']]) ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>

      <p class="aide" style="margin:14px 0 0;border-top:1px solid var(--border);padding-top:12px">
        <strong>Pourquoi deux boutons différents.</strong> Un <span class="mono">4xx</span> est un
        incident : le relais était occupé, il ne le sera plus. Un <span class="mono">5xx</span> est un
        verdict : l’adresse n’existe pas, ou la boîte est fermée. Relancer un verdict, c’est envoyer
        trois fois un message à une adresse morte — et c’est exactement ce que les fournisseurs
        comptent contre vous.
      </p>
    </div>
  <?php endif; ?>

  <!-- ---------- ce qu'on peut faire ---------- -->
  <?php
  /* Une carte « Ce que vous pouvez faire » vide, sous une campagne
     terminée, promet une action qui n'existe pas. */
  $a_faire = $soumettable
      || ($equipe && in_array($c['statut'], ['en_relecture', 'prete', 'envoi'], true))
      || (!$equipe && in_array($c['statut'], ['en_relecture', 'prete', 'envoi'], true));
  ?>
  <?php if ($a_faire): ?>
  <div class="carte" style="margin-top:16px">
    <h3 style="margin:0 0 14px">Ce que vous pouvez faire</h3>

    <div class="rangee" style="gap:10px;flex-wrap:wrap">
      <?php if ($soumettable): ?>
        <?= $bouton('soumettre', $equipe ? 'Préparer l’envoi' : 'Soumettre à la régie') ?>
      <?php endif; ?>

      <?php if ($equipe && $c['statut'] === 'en_relecture'): ?>
        <?= $bouton('approuver', 'Approuver') ?>
      <?php endif; ?>

      <?php if ($equipe && in_array($c['statut'], ['prete', 'envoi'], true)): ?>
        <?= $bouton('envoyer', $c['statut'] === 'prete' ? 'Envoyer le premier lot' : 'Envoyer le lot suivant') ?>
      <?php endif; ?>

    </div>

    <?php if ($equipe && in_array($c['statut'], ['en_relecture', 'prete'], true)): ?>
      <form method="post" action="<?= e(url('?p=regie-action')) ?>" style="margin-top:18px;border-top:1px solid var(--border);padding-top:16px">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="id" value="<?= e($c['id']) ?>">
        <div class="champ">
          <label for="r-motif">Motif — obligatoire pour renvoyer ou refuser</label>
          <textarea id="r-motif" name="motif" rows="2"
                    placeholder="Le ton est trop insistant ; retirez la mention du prix."></textarea>
        </div>
        <div class="rangee" style="gap:10px">
          <button class="bouton fant" type="submit" name="quoi" value="corrections">Renvoyer à l’auteur</button>
          <button class="bouton danger" type="submit" name="quoi" value="refuser">Refuser</button>
        </div>
      </form>
    <?php endif; ?>

    <?php if (!$equipe && $c['statut'] === 'en_relecture'): ?>
      <p class="aide" style="margin:14px 0 0">En attente de la régie. Vous serez prévenu par
      notification et par e-mail dès qu’une décision est prise.</p>
    <?php endif; ?>

    <?php if (!$equipe && in_array($c['statut'], ['prete', 'envoi'], true)): ?>
      <p class="aide" style="margin:14px 0 0">Approuvée. L’envoi est déclenché par l’équipe,
      par lots, pour ne pas saturer le serveur d’envoi.</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!$equipe): ?>
    <p class="aide" style="margin-top:14px">
      Il vous reste <strong><?= $quota['max'] < 0 ? 'un nombre illimité d’' : (int) $quota['reste'] . ' ' ?>envois</strong>
      ce mois-ci<?= $quota['max'] < 0 ? '' : ' sur ' . (int) $quota['max'] ?>.
    </p>
  <?php endif; ?>
</div>
