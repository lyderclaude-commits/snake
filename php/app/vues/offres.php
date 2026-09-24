<?php
/**
 * Les offres, à l'écran.
 *
 * La liste à gauche, l'offre ouverte à droite. Chaque ligne du formulaire
 * porte SA phrase d'explication, celle que l'organisateur lit sur son
 * tableau de bord : on ne règle pas à l'aveugle une valeur dont on ne sait
 * plus ce qu'elle fait.
 */
$f = $neuve ? null : ($toutes[$edite] ?? null);

/** La valeur en place, ou le défaut le plus restrictif pour une offre neuve. */
$val = static function (string $cle) use ($f, $neuve) {
    if ($neuve || !$f) {
        return FORMULES['decouverte'][$cle] ?? 0;
    }
    return $f[$cle] ?? (FORMULES['decouverte'][$cle] ?? 0);
};
$fr = static fn(int $n): string => number_format($n, 0, ',', ' ');
?>
<div class="contenu">

  <section class="entete">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
      <div>
        <h1>Les offres</h1>
        <p>Ce que chaque offre donne, et ce qu’elle coûte. Les changements s’appliquent
        immédiatement, à la vitrine comme aux comptes qui portent l’offre.</p>
      </div>
      <div class="rangee" style="gap:8px">
        <a class="bouton fant" href="<?= e(url('?p=reglages')) ?>">Retour aux réglages</a>
        <a class="bouton" href="<?= e(url('?p=offres&neuve=1')) ?>">Nouvelle offre</a>
      </div>
    </div>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <div class="grille g2" style="align-items:start;grid-template-columns:minmax(240px,320px) 1fr">

    <!-- ─────────── la liste ─────────── -->
    <section class="carte">
      <h2 style="font-size:1rem">Les offres en place</h2>
      <p class="aide" style="margin:4px 0 12px">Dans l’ordre où la vitrine les montre.</p>

      <?php foreach ($toutes as $cle => $o): ?>
        <a class="seg<?= !$neuve && $cle === $edite ? ' on' : '' ?>" style="margin-bottom:6px"
           href="<?= e(url('?p=offres&offre=' . rawurlencode((string) $cle))) ?>"
           <?= !$neuve && $cle === $edite ? 'aria-current="true"' : '' ?>>
          <span class="seg-nom"><?= e((string) $o['nom']) ?></span>
          <span class="seg-aide">
            <?= (int) $portees[$cle] === 0 ? 'aucun compte'
                : (int) $portees[$cle] . ' compte' . ((int) $portees[$cle] > 1 ? 's' : '') ?><?php
            if (($o['actif'] ?? true) === false): ?> · hors vitrine<?php endif;
            if ($o['phare'] ?? false): ?> · le plus choisi<?php endif; ?>
          </span>
          <span class="seg-n">
            <b><?= (int) $o['prix'] === 0 ? '0' : e($fr((int) $o['prix'])) ?></b>
            <small><?= (int) $o['prix'] === 0 ? 'gratuit' : 'F CFA' ?></small>
          </span>
        </a>
      <?php endforeach; ?>

      <?php
      /**
       * Ce que la table ne décide pas, et pourquoi.
       *
       * Sans cette phrase, quelqu'un cherchera pendant dix minutes la case
       * qui ajoute un canal. Il n'y en a pas : une capacité existe parce
       * que du code l'exécute. L'écran distribue, il n'invente pas.
       */
      ?>
      <p class="aide" style="margin:14px 0 0;border-top:1px solid var(--border);padding-top:12px">
        Les lignes réglables ci-contre sont celles que le produit sait appliquer.
        En ajouter une demande du code : c’est lui qui envoie les messages et pose
        les filigranes.
      </p>
    </section>

    <!-- ─────────── l'offre ouverte ─────────── -->
    <section class="carte">
      <form method="post" action="<?= e(url('?p=offres')) ?>">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="quoi" value="enregistrer">
        <input type="hidden" name="neuve" value="<?= $neuve ? '1' : '0' ?>">

        <div class="rangee" style="justify-content:space-between;align-items:baseline;gap:10px">
          <h2 style="font-size:1rem"><?= $neuve ? 'Une offre de plus'
              : 'Modifier « ' . e((string) ($f['nom'] ?? '')) . ' »' ?></h2>
          <?php if (!$neuve): ?>
            <span class="aide"><?= (int) $portees[$edite] === 0 ? 'personne ne la porte'
                : (int) $portees[$edite] . ' compte' . ((int) $portees[$edite] > 1 ? 's la portent' : ' la porte') ?></span>
          <?php endif; ?>
        </div>

        <div class="grille g2" style="margin-top:14px">
          <div class="champ">
            <label for="nom">Nom affiché</label>
            <input id="nom" name="nom" type="text" required maxlength="40"
                   value="<?= e((string) ($f['nom'] ?? '')) ?>" placeholder="Impact">
          </div>
          <div class="champ">
            <label for="cle">Clé <span style="font-weight:400">(jamais affichée)</span></label>
            <?php if ($neuve): ?>
              <input id="cle" name="cle" type="text" required maxlength="39" pattern="[a-z][a-z0-9_\-]{1,38}"
                     value="<?= e((string) ($_POST['cle'] ?? '')) ?>" placeholder="essentiel">
              <p class="aide">Minuscules et tirets. Elle s’écrit sur chaque compte et ne changera plus.</p>
            <?php else: ?>
              <input type="hidden" name="cle" value="<?= e((string) $edite) ?>">
              <input id="cle" type="text" value="<?= e((string) $edite) ?>" disabled>
              <p class="aide">Elle est inscrite sur les comptes : elle ne se renomme pas.</p>
            <?php endif; ?>
          </div>
          <div class="champ">
            <label for="prix">Prix mensuel <span style="font-weight:400">(F CFA)</span></label>
            <input id="prix" name="prix" type="number" min="0" max="10000000" required
                   value="<?= (int) ($f['prix'] ?? 0) ?>">
          </div>
          <div class="champ">
            <label for="lancement">Prix de lancement</label>
            <input id="lancement" name="lancement" type="number" min="0" max="10000000"
                   value="<?= (int) ($f['lancement'] ?? 0) ?>">
            <p class="aide">La vitrine barre le prix normal et annonce celui-ci. Zéro pour ne rien annoncer.</p>
          </div>
          <div class="champ">
            <label for="tag">Accroche de la vitrine</label>
            <input id="tag" name="tag" type="text" maxlength="40"
                   value="<?= e((string) ($f['tag'] ?? '')) ?>" placeholder="Entrée sérieuse">
          </div>
          <div class="champ">
            <label for="cta">Libellé du bouton</label>
            <input id="cta" name="cta" type="text" maxlength="40"
                   value="<?= e((string) ($f['cta'] ?? '')) ?>" placeholder="Choisir Impact">
          </div>
          <div class="champ">
            <label for="rang">Ordre d’affichage</label>
            <input id="rang" name="rang" type="number" min="0" max="99"
                   value="<?= (int) ($f['rang'] ?? count($toutes)) ?>">
            <p class="aide">La vitrine annonce « Tout <em>l’offre d’avant</em>, plus : » en suivant cet ordre.</p>
          </div>
          <div class="champ">
            <label>Sur la vitrine</label>
            <div style="display:grid;gap:8px">
              <label class="case" style="font-weight:400">
                <input type="checkbox" name="actif" value="1"
                       <?= $neuve || ($f['actif'] ?? true) !== false ? 'checked' : '' ?>>
                Proposée à la vente
              </label>
              <label class="case" style="font-weight:400">
                <input type="checkbox" name="phare" value="1" <?= ($f['phare'] ?? false) ? 'checked' : '' ?>>
                Marquée « Le plus choisi »
              </label>
            </div>
            <p class="aide">Décocher la vente retire l’offre de la vitrine sans rien changer
            aux comptes qui la portent.</p>
          </div>
        </div>

        <!-- ─────────── ce qu'elle donne ─────────── -->
        <h3 style="margin:18px 0 4px">Ce qu’elle donne</h3>
        <p class="aide" style="margin:0 0 14px">
          Sur les compteurs, <strong>−1</strong> veut dire sans limite et <strong>0</strong> veut
          dire que l’offre ne l’ouvre pas du tout.
        </p>

        <div class="tableau">
          <table>
            <thead>
              <tr><th style="width:38%">La ligne</th><th style="width:22%">Valeur</th><th>Ce qu’elle fait</th></tr>
            </thead>
            <tbody>
              <?php foreach (OFFRE_LIGNES as $cle => [$libelle, $genre, $aide]): ?>
                <tr>
                  <td>
                    <label for="cap-<?= e($cle) ?>" style="font-weight:700"><?= e($libelle) ?></label>
                    <span class="rap-preuve"><?= e($genre) ?></span>
                  </td>
                  <td>
                    <?php if ($champs[$cle] === 'nombre'): ?>
                      <input id="cap-<?= e($cle) ?>" name="cap[<?= e($cle) ?>]" type="number"
                             min="-1" max="100000000" style="width:120px"
                             value="<?= (int) $val($cle) ?>">
                    <?php elseif ($champs[$cle] === 'stats'): ?>
                      <select id="cap-<?= e($cle) ?>" name="cap[<?= e($cle) ?>]">
                        <option value="base" <?= $val($cle) === 'completes' ? '' : 'selected' ?>>De base</option>
                        <option value="completes" <?= $val($cle) === 'completes' ? 'selected' : '' ?>>Complètes</option>
                      </select>
                    <?php else: ?>
                      <select id="cap-<?= e($cle) ?>" name="cap[<?= e($cle) ?>]">
                        <option value="0" <?= $val($cle) ? '' : 'selected' ?>>Non</option>
                        <option value="1" <?= $val($cle) ? 'selected' : '' ?>>Oui</option>
                      </select>
                    <?php endif; ?>
                  </td>
                  <td class="aide"><?= e($aide) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="rangee" style="margin-top:18px;gap:10px">
          <button class="bouton" type="submit"><?= $neuve ? 'Créer cette offre' : 'Enregistrer' ?></button>
          <?php if (!$neuve): ?>
            <a class="bouton fant" href="<?= e(url('?p=offres')) ?>">Annuler</a>
          <?php endif; ?>
        </div>
      </form>

      <?php
      /**
       * La suppression vit à part du formulaire, exprès.
       *
       * Un bouton « Supprimer » dans le même formulaire que « Enregistrer »
       * se clique par erreur, et ce clic-là ne se reprend pas.
       */
      if (!$neuve && $edite !== 'decouverte'): ?>
        <div style="border-top:1px solid var(--border);margin-top:20px;padding-top:16px">
          <?php if ((int) $portees[$edite] > 0): ?>
            <div class="msg info" style="margin:0">
              <strong><?= (int) $portees[$edite] ?> compte<?= (int) $portees[$edite] > 1 ? 's portent' : ' porte' ?>
              cette offre : elle ne se supprime pas.</strong>
              <p style="margin:.35em 0 0">Décochez « Proposée à la vente » pour cesser de la vendre.
              Ceux qui la portent gardent exactement ce qu’ils ont payé.</p>
            </div>
          <?php else: ?>
            <form method="post" action="<?= e(url('?p=offres')) ?>" style="margin:0">
              <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
              <input type="hidden" name="quoi" value="supprimer">
              <input type="hidden" name="cle" value="<?= e((string) $edite) ?>">
              <p class="aide" style="margin:0 0 8px">Aucun compte ne porte cette offre : elle peut disparaître.</p>
              <button class="bouton fant petit" type="submit">Supprimer « <?= e((string) ($f['nom'] ?? '')) ?> »</button>
            </form>
          <?php endif; ?>
        </div>
      <?php elseif (!$neuve): ?>
        <div style="border-top:1px solid var(--border);margin-top:20px;padding-top:16px">
          <p class="aide" style="margin:0">
            Découverte ne se supprime pas : c’est l’offre que le produit applique quand
            un compte n’en a plus, et un décor dont l’auteur a fermé son compte reste au catalogue.
          </p>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>
