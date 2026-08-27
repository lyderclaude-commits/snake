<?php
$total = array_sum($compteurs);
$onglet = function (string $cle, string $nom) use ($filtre, $compteurs, $cherche): string {
    $actif = $filtre === $cle;
    $n = $cle === '' ? array_sum($compteurs) : ($compteurs[$cle] ?? 0);
    $lien = url('?p=catalogue' . ($cle ? '&statut=' . $cle : '') . ($cherche ? '&q=' . urlencode($cherche) : ''));
    return sprintf(
        '<a class="bouton %s petit" href="%s"%s>%s <span style="opacity:.65">%d</span></a>',
        $actif ? '' : 'fant',
        e($lien),
        $actif ? ' aria-current="page"' : '',
        e($nom),
        $n
    );
};
?>
<div class="contenu">
  <section class="entete">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start">
      <div>
        <h1>Tous les décors</h1>
        <p><?= $total ?> au total, tous statuts confondus : les vôtres et ceux des partenaires.</p>
      </div>
      <a class="bouton" href="<?= e(url('?p=nouveau')) ?>">+ Nouveau décor</a>
    </div>
  </section>

  <?php if (!empty($_GET['ok'])): ?><div class="msg ok" role="status"><?= e($_GET['ok']) ?></div><?php endif; ?>
  <?php if (!empty($_GET['err'])): ?><div class="msg err" role="alert"><?= e($_GET['err']) ?></div><?php endif; ?>

  <div class="rangee" style="margin-bottom:14px">
    <?= $onglet('', 'Tous') ?>
    <?= $onglet('publie', 'Publiés') ?>
    <?= $onglet('en_relecture', 'En relecture') ?>
    <?= $onglet('brouillon', 'Brouillons') ?>
    <?= $onglet('corrections', 'Corrections') ?>
    <?= $onglet('refuse', 'Refusés') ?>
    <?= $onglet('archive', 'Archivés') ?>

    <form method="get" class="rangee chercher">
      <input type="hidden" name="p" value="catalogue">
      <?php if ($filtre): ?><input type="hidden" name="statut" value="<?= e($filtre) ?>"><?php endif; ?>
      <input type="text" name="q" value="<?= e($cherche) ?>" placeholder="Chercher un titre…">
      <button class="bouton fant petit" type="submit">Chercher</button>
    </form>
  </div>

  <?php if (!$liste): ?>
    <div class="carte" style="text-align:center">
      <h3>Aucun décor ici</h3>
      <p style="color:var(--text2);margin:.4em 0 0">
        <?= $cherche ? 'Aucun résultat pour « ' . e($cherche) . ' ».' : 'Ce statut ne contient aucun décor.' ?>
      </p>
    </div>
  <?php else: foreach ($liste as $d):
      $p = presence($d['id']);
      $peut_publier = transition_permise($d['statut'], 'publie', 'equipe');
      $peut_archiver = transition_permise($d['statut'], 'archive', 'equipe');
  ?>
    <div class="carte" style="margin-bottom:12px">
      <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:16px">
        <?php if ($d['cadre_url']): ?>
          <img src="<?= e($d['cadre_url']) ?>" alt="" loading="lazy"
               style="width:64px;height:64px;object-fit:contain;background:var(--bg2);border-radius:var(--r10);flex:0 0 auto">
        <?php endif; ?>

        <div style="flex:1;min-width:200px">
          <div class="rangee" style="gap:9px">
            <b class="display" style="font-size:1.05rem"><?= e($d['titre']) ?></b>
            <span class="pastille <?= e($d['statut']) ?>"><?= e(statut_libelle($d['statut'])) ?></span>
          </div>
          <p class="aide" style="margin:3px 0 0">
            /<?= e($d['slug']) ?> · <?= e($d['auteur_nom'] ?: 'Équipe Wakabi') ?>
            (<?= $d['cree_par'] === 'equipe' ? 'équipe' : 'partenaire' ?>)
            · modifié le <?= e(substr($d['maj_le'], 0, 10)) ?>
          </p>
        </div>

        <div class="rangee" style="gap:14px;font-size:.84rem;color:var(--text2);flex:0 0 auto">
          <span title="vues">👁 <?= (int) $d['vues'] ?></span>
          <span title="badges téléchargés">↓ <?= (int) $d['telechargements'] ?></span>
          <span title="présences scannées" style="color:var(--teal);font-weight:700">✓ <?= $p['scannes'] ?></span>
        </div>
      </div>

      <div class="rangee" style="margin-top:14px;gap:8px">
        <a class="bouton fant petit" href="<?= e(url('?p=modifier&id=' . urlencode($d['id']))) ?>">Modifier</a>

        <?php if ($d['statut'] === 'publie'): ?>
          <a class="bouton fant petit" href="<?= e(url('?p=decor&slug=' . urlencode($d['slug']))) ?>">Voir en ligne</a>
        <?php endif; ?>

        <?php if ($peut_publier): ?>
          <form method="post" action="<?= e(url('?p=statut')) ?>">
            <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
            <input type="hidden" name="id" value="<?= e($d['id']) ?>">
            <input type="hidden" name="vers" value="publie">
            <button class="bouton petit" type="submit">Publier</button>
          </form>
        <?php endif; ?>

        <?php if ($peut_archiver): ?>
          <form method="post" action="<?= e(url('?p=statut')) ?>">
            <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
            <input type="hidden" name="id" value="<?= e($d['id']) ?>">
            <input type="hidden" name="vers" value="archive">
            <button class="bouton fant petit" type="submit">Archiver</button>
          </form>
        <?php endif; ?>

        <details style="margin-left:auto">
          <summary class="bouton fant petit" style="list-style:none">Supprimer…</summary>
          <form method="post" action="<?= e(url('?p=supprimer')) ?>"
                class="confirmation">
            <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
            <input type="hidden" name="id" value="<?= e($d['id']) ?>">
            <p style="margin:0 0 10px;font-size:.9rem">
              <strong style="color:var(--rouge)">La suppression est définitive.</strong>
              <?php if ($p['emis']): ?>
                Elle détruira <strong><?= $p['emis'] ?> badge(s)</strong> déjà téléchargés :
                leurs QR ne mèneront plus nulle part, et les entrées correspondantes ne
                pourront plus être validées.
              <?php else: ?>
                Aucun badge n’a encore été émis pour ce décor.
              <?php endif; ?>
              <br>Préférez <strong>Archiver</strong> si vous voulez seulement le retirer du catalogue.
            </p>
            <label for="conf-<?= e($d['id']) ?>">Retapez le titre pour confirmer</label>
            <div class="rangee" style="gap:8px;flex-wrap:nowrap">
              <input id="conf-<?= e($d['id']) ?>" name="confirmation" type="text"
                     autocomplete="off" placeholder="<?= e($d['titre']) ?>">
              <button class="bouton danger petit" type="submit">Supprimer</button>
            </div>
          </form>
        </details>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>
