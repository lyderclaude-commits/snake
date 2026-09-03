<?php
/**
 * Le carnet d'adresses.
 *
 * Un seul écran, parce que c'est un seul geste : on regarde ses listes, on
 * en choisit une, on range ce qu'il y a dedans. Séparer « les listes » et
 * « les contacts » en deux pages obligerait à faire des allers-retours
 * pour la seule chose qu'on vient faire ici — sortir trois adresses d'une
 * liste avant d'écrire.
 *
 * Les formulaires d'ajout sont repliés par défaut : ouverts, ils
 * pousseraient la table sous la ligne de flottaison, et la table est ce
 * qu'on vient voir.
 */
$erreur = $erreur ?? null;
$message = $message ?? null;
$q = $filtres['q'];

$vers = function (array $sup = []) use ($filtres, $liste): string {
    $p = array_filter([
        'p' => 'regie-carnet',
        'l' => $liste['id'] ?? '',
        'etat' => $filtres['etat'] === 'actives' ? '' : $filtres['etat'],
        'q' => $filtres['q'],
    ] + $sup, fn($v) => $v !== '' && $v !== null);
    return url('?' . http_build_query($p));
};
?>
<div class="contenu">
  <p class="fil"><a href="<?= e(url('?p=regie')) ?>">← La régie</a></p>

  <section class="entete">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
      <div>
        <h1>Carnet d’adresses</h1>
        <p><?= (int) $total_carnet ?> adresse<?= $total_carnet > 1 ? 's' : '' ?> en tout,
        rangée<?= $total_carnet > 1 ? 's' : '' ?> en <?= count($listes) ?> liste<?= count($listes) > 1 ? 's' : '' ?>.
        Ce carnet est le vôtre : il survit à vos campagnes, et vous pouvez le ressortir quand vous voulez.</p>
      </div>
      <div class="rangee" style="gap:8px">
        <a class="bouton fant" href="<?= e(url('?p=regie-carnet-export' . ($liste ? '&l=' . urlencode((string) $liste['id']) : ''))) ?>">Exporter (CSV)</a>
        <a class="bouton" href="<?= e(url('?p=regie-ecrire' . ($liste ? '&l=' . urlencode((string) $liste['id']) : ''))) ?>">
          <?= $liste ? 'Écrire à cette liste' : 'Écrire une campagne' ?></a>
      </div>
    </div>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <!-- ---------- les listes ---------- -->
  <div class="rangee etiquettes" style="margin:18px 0 6px">
    <a class="etiquette<?= $liste ? '' : ' active' ?>" href="<?= e(url('?p=regie-carnet')) ?>">
      Tout le carnet <b><?= (int) $total_carnet ?></b>
    </a>
    <?php foreach ($listes as $li): ?>
      <a class="etiquette<?= $liste && $liste['id'] === $li['id'] ? ' active' : '' ?>"
         href="<?= e(url('?p=regie-carnet&l=' . urlencode((string) $li['id']))) ?>">
        <?= e((string) $li['nom']) ?> <b><?= (int) $li['actifs'] ?></b>
      </a>
    <?php endforeach; ?>
    <button type="button" class="etiquette ajout" data-ouvre="bloc-liste-neuve">+ Nouvelle liste</button>
  </div>

  <form class="carte bloc-repli" id="bloc-liste-neuve" hidden method="post" action="<?= e(url('?p=regie-carnet-action')) ?>">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
    <input type="hidden" name="quoi" value="liste-creer">
    <div class="grille g2">
      <div class="champ">
        <label for="ln-nom">Nom de la liste</label>
        <input id="ln-nom" name="nom" type="text" required maxlength="120" placeholder="Invités du Gala 2026">
      </div>
      <div class="champ">
        <label for="ln-note">À quoi elle sert <span style="font-weight:400">(facultatif)</span></label>
        <input id="ln-note" name="note" type="text" maxlength="190" placeholder="Les 180 entrées du 14 février">
      </div>
    </div>
    <button class="bouton" type="submit">Créer la liste</button>
  </form>

  <?php if ($liste): ?>
    <!-- ---------- la liste choisie ---------- -->
    <div class="carte" style="margin-bottom:16px">
      <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
        <div>
          <h3 style="margin:0"><?= e((string) $liste['nom']) ?></h3>
          <p class="aide" style="margin:.3em 0 0">
            <?= (int) $liste['actifs'] ?> adresse<?= $liste['actifs'] > 1 ? 's' : '' ?> active<?= $liste['actifs'] > 1 ? 's' : '' ?><?php
            if ((int) $liste['archives']): ?>, <?= (int) $liste['archives'] ?> archivée<?= $liste['archives'] > 1 ? 's' : '' ?><?php endif; ?>.
            <?php if ($liste['note']): ?><br><?= e((string) $liste['note']) ?><?php endif; ?>
          </p>
        </div>
        <div class="rangee" style="gap:8px;flex-wrap:wrap">
          <button type="button" class="bouton fant petit" data-ouvre="bloc-renommer">Renommer</button>
          <button type="button" class="bouton fant petit" data-ouvre="bloc-import">Importer des adresses</button>
          <button type="button" class="bouton fant petit" data-ouvre="bloc-alimenter">Alimenter depuis…</button>
          <form method="post" action="<?= e(url('?p=regie-carnet-action')) ?>"
                onsubmit="return confirm('Supprimer la liste « <?= e((string) $liste['nom']) ?> » ? Les adresses restent au carnet.')">
            <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
            <input type="hidden" name="quoi" value="liste-supprimer">
            <input type="hidden" name="liste_id" value="<?= e((string) $liste['id']) ?>">
            <button class="bouton fant petit danger" type="submit">Supprimer la liste</button>
          </form>
        </div>
      </div>

      <form class="bloc-repli" id="bloc-renommer" hidden method="post" action="<?= e(url('?p=regie-carnet-action')) ?>" style="margin-top:14px">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="quoi" value="liste-renommer">
        <input type="hidden" name="liste_id" value="<?= e((string) $liste['id']) ?>">
        <div class="grille g2">
          <div class="champ">
            <label for="lr-nom">Nom</label>
            <input id="lr-nom" name="nom" type="text" required maxlength="120" value="<?= e((string) $liste['nom']) ?>">
          </div>
          <div class="champ">
            <label for="lr-note">À quoi elle sert</label>
            <input id="lr-note" name="note" type="text" maxlength="190" value="<?= e((string) ($liste['note'] ?? '')) ?>">
          </div>
        </div>
        <button class="bouton petit" type="submit">Enregistrer</button>
      </form>
    </div>
  <?php endif; ?>

  <!-- ---------- importer ---------- -->
  <form class="carte bloc-repli" id="bloc-import" hidden method="post" action="<?= e(url('?p=regie-carnet-action')) ?>" style="margin-bottom:16px">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
    <input type="hidden" name="quoi" value="importer">
    <h3 style="margin:0 0 4px">Importer des adresses</h3>
    <p class="aide" style="margin:0 0 14px">Collez-les depuis un tableur ou un e-mail. Elles sont
    <strong>enregistrées dans le carnet</strong> — pas seulement utilisées une fois. Une adresse
    déjà connue n’est pas dupliquée : elle rejoint simplement la liste.</p>

    <div class="champ">
      <label for="im-liste">Dans quelle liste</label>
      <select id="im-liste" name="liste_id"
              onchange="document.getElementById('im-neuve').hidden = this.value !== 'nouvelle'">
        <?php foreach ($listes as $li): ?>
          <option value="<?= e((string) $li['id']) ?>" <?= $liste && $liste['id'] === $li['id'] ? 'selected' : '' ?>>
            <?= e((string) $li['nom']) ?> (<?= (int) $li['actifs'] ?>)
          </option>
        <?php endforeach; ?>
        <option value="nouvelle" <?= $listes ? '' : 'selected' ?>>— Une nouvelle liste —</option>
      </select>
    </div>
    <div class="champ" id="im-neuve" <?= $listes ? 'hidden' : '' ?>>
      <label for="im-nom">Nom de la nouvelle liste</label>
      <input id="im-nom" name="nouveau_nom" type="text" maxlength="120" placeholder="Invités du Gala 2026">
    </div>
    <div class="champ">
      <label for="im-adresses">Les adresses</label>
      <textarea id="im-adresses" name="adresses" rows="7" required
                style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace"
                placeholder="ama@exemple.tg&#10;Kossi Mensah &lt;kossi@exemple.tg&gt;&#10;afi@exemple.tg ; yao@exemple.tg"></textarea>
      <p class="aide">Une par ligne, ou séparées par des virgules ou des points-virgules.
      <code>Nom &lt;adresse&gt;</code> est reconnu. Une ligne illisible est ignorée, pas refusée —
      une virgule oubliée ne doit pas faire recommencer deux cents lignes.</p>
    </div>
    <button class="bouton" type="submit">Importer</button>
  </form>

  <!-- ---------- alimenter depuis un segment ---------- -->
  <?php if ($liste && $cibles): ?>
    <form class="carte bloc-repli" id="bloc-alimenter" hidden method="post" action="<?= e(url('?p=regie-carnet-action')) ?>" style="margin-bottom:16px">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <input type="hidden" name="quoi" value="alimenter">
      <input type="hidden" name="liste_id" value="<?= e((string) $liste['id']) ?>">
      <h3 style="margin:0 0 4px">Alimenter « <?= e((string) $liste['nom']) ?> » depuis une audience existante</h3>
      <p class="aide" style="margin:0 0 14px">Recopie une audience calculée dans cette liste, une fois.
      L’intérêt est justement de pouvoir ensuite en <strong>retirer</strong> quelqu’un, corriger un nom,
      archiver une adresse morte — ce qu’une audience calculée ne permet pas.</p>
      <div class="champ">
        <label for="al-cible">La source</label>
        <select id="al-cible" name="cible">
          <?php foreach ($cibles as $cle => $lib): ?>
            <option value="<?= e($cle) ?>"><?= e($lib) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="bouton" type="submit">Recopier dans la liste</button>
    </form>
  <?php endif; ?>

  <!-- ---------- ajouter une adresse à la main ---------- -->
  <form class="carte bloc-repli" id="bloc-ajout" hidden method="post" action="<?= e(url('?p=regie-carnet-action')) ?>" style="margin-bottom:16px">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
    <input type="hidden" name="quoi" value="contact-ajouter">
    <input type="hidden" name="revenir_l" value="<?= e((string) ($liste['id'] ?? '')) ?>">
    <h3 style="margin:0 0 14px">Ajouter une adresse</h3>
    <div class="grille g2">
      <div class="champ">
        <label for="aj-email">Adresse e-mail</label>
        <input id="aj-email" name="email" type="email" required placeholder="kossi@exemple.tg">
      </div>
      <div class="champ">
        <label for="aj-nom">Nom</label>
        <input id="aj-nom" name="nom" type="text" maxlength="120" placeholder="Kossi Mensah">
      </div>
      <div class="champ">
        <label for="aj-org">Structure <span style="font-weight:400">(facultatif)</span></label>
        <input id="aj-org" name="organisation" type="text" maxlength="120" placeholder="Maquis Akwaba">
      </div>
      <div class="champ">
        <label for="aj-tel">Téléphone <span style="font-weight:400">(facultatif)</span></label>
        <input id="aj-tel" name="telephone" type="tel" maxlength="40" placeholder="+228 90 00 00 00">
      </div>
    </div>
    <?php if ($listes): ?>
      <div class="champ">
        <span class="champ-titre">Dans quelles listes</span>
        <div class="cases">
          <?php foreach ($listes as $li): ?>
            <label class="case">
              <input type="checkbox" name="listes[]" value="<?= e((string) $li['id']) ?>"
                     <?= $liste && $liste['id'] === $li['id'] ? 'checked' : '' ?>>
              <span><?= e((string) $li['nom']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <button class="bouton" type="submit">Ajouter au carnet</button>
  </form>

  <!-- ---------- filtres ---------- -->
  <form method="get" action="<?= e(url('?p=regie-carnet')) ?>" class="rangee chercher chercher-comptes">
    <input type="hidden" name="p" value="regie-carnet">
    <?php if ($liste): ?><input type="hidden" name="l" value="<?= e((string) $liste['id']) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, adresse, structure…" aria-label="Chercher une adresse">
    <select name="etat" aria-label="Filtrer par état">
      <option value="">Actives</option>
      <option value="archives" <?= $filtres['etat'] === 'archives' ? 'selected' : '' ?>>Archivées</option>
      <option value="toutes" <?= $filtres['etat'] === 'toutes' ? 'selected' : '' ?>>Toutes</option>
    </select>
    <button class="bouton fant petit" type="submit">Filtrer</button>
    <?php if ($q || $filtres['etat'] !== 'actives'): ?>
      <a class="bouton fant petit" href="<?= e(url('?p=regie-carnet' . ($liste ? '&l=' . urlencode((string) $liste['id']) : ''))) ?>">Tout voir</a>
    <?php endif; ?>
    <button type="button" class="bouton petit" data-ouvre="bloc-ajout">+ Une adresse</button>
    <button type="button" class="bouton fant petit" data-ouvre="bloc-import">Importer</button>
  </form>

  <!-- ---------- la table ---------- -->
  <?php if (!$contacts): ?>
    <div class="carte" style="text-align:center">
      <h3 style="margin:0">Rien à cet endroit</h3>
      <p class="aide" style="margin:.4em 0 0"><?= $q || $filtres['etat'] !== 'actives'
        ? 'Aucune adresse ne correspond à ce filtre.'
        : ($liste
            ? 'Cette liste est vide. Importez un collage, ou recopiez-y une audience existante.'
            : 'Votre carnet est vide. Collez une liste : elle sera enregistrée pour de bon.') ?></p>
    </div>
  <?php else: ?>
    <form method="post" action="<?= e(url('?p=regie-carnet-action')) ?>" id="form-lot">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <input type="hidden" name="quoi" value="lot">
      <input type="hidden" name="revenir_l" value="<?= e((string) ($liste['id'] ?? '')) ?>">
      <input type="hidden" name="revenir_etat" value="<?= e($filtres['etat']) ?>">
      <input type="hidden" name="revenir_q" value="<?= e($q) ?>">

      <div class="tableau">
        <table>
          <thead>
            <tr>
              <th class="mince"><input type="checkbox" id="tout-cocher" aria-label="Tout cocher"></th>
              <th>Qui</th><th>Adresse</th><th>Listes</th><th>Ajoutée</th><th class="chiffre">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($contacts as $c): $arch = (int) $c['archive']; ?>
            <tr<?= $arch ? ' class="pale"' : '' ?>>
              <td class="mince"><input type="checkbox" name="choix[]" value="<?= e((string) $c['id']) ?>"
                                       aria-label="Choisir <?= e((string) $c['email']) ?>"></td>
              <td>
                <a href="<?= e(url('?p=regie-carnet-fiche&id=' . urlencode((string) $c['id']))) ?>">
                  <?php if ($c['nom']): ?><b><?= e((string) $c['nom']) ?></b>
                  <?php else: ?><span class="aide">sans nom</span><?php endif; ?></a>
                <?php if ($c['organisation']): ?>
                  <br><span class="aide"><?= e((string) $c['organisation']) ?></span>
                <?php endif; ?>
              </td>
              <td class="mono"><?= e((string) $c['email']) ?>
                <?php if ($arch): ?><br><span class="pastille archive">archivée</span><?php endif; ?>
              </td>
              <td>
                <?php $mes = $etiquettes[(string) $c['id']] ?? []; ?>
                <?= $mes ? e(implode(' · ', $mes)) : '<span class="aide">aucune</span>' ?>
              </td>
              <td class="aide" style="white-space:nowrap"><?= e(date_fr((string) $c['cree_le'])) ?></td>
              <td class="chiffre" style="white-space:nowrap">
                <a class="lien-bouton" href="<?= e(url('?p=regie-carnet-fiche&id=' . urlencode((string) $c['id']))) ?>">Modifier</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!--
        La barre d'actions groupées.

        Elle vit SOUS la table et non au-dessus : on coche en descendant, et
        le bouton qu'on cherche est alors sous le pouce, pas remonté hors de
        l'écran.
      -->
      <div class="rangee barre-lot" style="margin-top:12px;gap:8px;flex-wrap:wrap;align-items:center">
        <span class="aide">Sur les adresses cochées :</span>
        <button class="bouton fant petit" type="submit" name="sur" value="archiver">Archiver</button>
        <button class="bouton fant petit" type="submit" name="sur" value="reactiver">Réactiver</button>
        <?php if ($liste): ?>
          <button class="bouton fant petit" type="submit" name="sur" value="retirer">Sortir de « <?= e((string) $liste['nom']) ?> »</button>
        <?php endif; ?>
        <?php if ($listes): ?>
          <span class="aide">·</span>
          <select name="liste_cible" aria-label="Liste où ajouter" style="width:auto;min-width:170px">
            <?php foreach ($listes as $li): ?>
              <option value="<?= e((string) $li['id']) ?>"><?= e((string) $li['nom']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="bouton fant petit" type="submit" name="sur" value="ajouter">Ajouter à cette liste</button>
        <?php endif; ?>
        <button class="bouton fant petit danger" type="submit" name="sur" value="supprimer"
                onclick="return confirm('Supprimer définitivement les adresses cochées du carnet ?')">Supprimer</button>
      </div>
    </form>

    <?php if ($pages > 1): ?>
      <div class="rangee" style="justify-content:center;gap:12px;margin-top:22px;align-items:center">
        <?php if ($page_n > 1): ?>
          <a class="bouton fant petit" href="<?= e($vers(['n' => $page_n - 1])) ?>">← Précédentes</a>
        <?php endif; ?>
        <span class="aide">Page <?= (int) $page_n ?> sur <?= (int) $pages ?> · <?= (int) $combien ?> adresse(s)</span>
        <?php if ($page_n < $pages): ?>
          <a class="bouton fant petit" href="<?= e($vers(['n' => $page_n + 1])) ?>">Suivantes →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<script>
/**
 * Deux comportements, et rien de plus.
 *
 * Les blocs repliés s'ouvrent au clic sur leur bouton ; la case de tête
 * coche toute la page. Écrit ici plutôt que dans le fichier commun parce
 * que ces deux gestes n'existent que sur cet écran — les charger partout
 * ferait payer à chaque page ce dont une seule se sert.
 */
document.addEventListener('click', function (ev) {
  var b = ev.target.closest('[data-ouvre]');
  if (!b) return;
  var cible = document.getElementById(b.dataset.ouvre);
  if (!cible) return;
  cible.hidden = !cible.hidden;
  if (!cible.hidden) {
    cible.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    var premier = cible.querySelector('input:not([type=hidden]),textarea,select');
    if (premier) premier.focus();
  }
});
var tout = document.getElementById('tout-cocher');
if (tout) {
  tout.addEventListener('change', function () {
    document.querySelectorAll('#form-lot input[name="choix[]"]').forEach(function (c) {
      c.checked = tout.checked;
    });
  });
}
</script>
