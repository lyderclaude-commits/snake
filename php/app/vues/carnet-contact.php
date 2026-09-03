<?php
/**
 * Une fiche du carnet.
 *
 * Tout ce qui concerne une personne sur un seul écran : ses coordonnées,
 * les listes où elle figure, et les trois gestes qui ne se ressemblent pas
 * — archiver, sortir d'une liste, supprimer. Ils sont séparés à l'écran
 * comme ils le sont dans le code, et chacun dit ce qu'il fait vraiment :
 * c'est le seul endroit du produit où une confusion coûte des adresses
 * qu'on ne retrouvera pas.
 */
$erreur = $erreur ?? null;
$message = $message ?? null;
?>
<div class="contenu etroit-large">
  <p class="fil"><a href="<?= e(url('?p=regie-carnet')) ?>">← Le carnet</a></p>

  <section class="entete">
    <h1><?= e((string) ($c['nom'] ?: $c['email'])) ?></h1>
    <p><?= e(CARNET_SOURCES[$c['source']] ?? (string) $c['source']) ?>,
    ajoutée le <?= e(date_fr((string) $c['cree_le'])) ?>.
    <?php if ((int) $c['archive']): ?>
      Elle est <strong>archivée</strong> : elle reste au carnet, mais aucune campagne ne l’atteindra.
    <?php endif; ?></p>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <?php if ($desabonne): ?>
    <!--
      Le désabonnement est la décision de la personne, pas la nôtre : cet
      écran l'affiche et n'offre AUCUN bouton pour le défaire. Pouvoir le
      lever d'ici en ferait une case à cocher, c'est-à-dire rien.
    -->
    <div class="msg err" style="margin-bottom:16px">
      <strong>Cette personne s’est désabonnée.</strong>
      <p style="margin:.35em 0 0">Elle ne recevra plus aucune campagne, de vous ni de personne, et
      ce choix ne se défait pas depuis cet écran — c’est le sien. Vous pouvez garder sa fiche
      pour vos notes.</p>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('?p=regie-carnet-action')) ?>">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
    <input type="hidden" name="quoi" value="contact-maj">
    <input type="hidden" name="id" value="<?= e((string) $c['id']) ?>">

    <div class="carte">
      <h3 style="margin:0 0 14px">Coordonnées</h3>
      <div class="grille g2">
        <div class="champ">
          <label for="c-email">Adresse e-mail</label>
          <input id="c-email" name="email" type="email" required value="<?= e((string) $c['email']) ?>">
          <p class="aide">La corriger corrige la personne partout : le carnet ne garde qu’une fiche par adresse.</p>
        </div>
        <div class="champ">
          <label for="c-nom">Nom</label>
          <input id="c-nom" name="nom" type="text" maxlength="120" value="<?= e((string) ($c['nom'] ?? '')) ?>">
        </div>
        <div class="champ">
          <label for="c-org">Structure</label>
          <input id="c-org" name="organisation" type="text" maxlength="120" value="<?= e((string) ($c['organisation'] ?? '')) ?>">
        </div>
        <div class="champ">
          <label for="c-tel">Téléphone</label>
          <input id="c-tel" name="telephone" type="tel" maxlength="40" value="<?= e((string) ($c['telephone'] ?? '')) ?>">
        </div>
      </div>
      <div class="champ">
        <label for="c-note">Note <span style="font-weight:400">(pour vous seul)</span></label>
        <textarea id="c-note" name="note" rows="3" placeholder="Rencontré au Gala. Préfère qu’on l’appelle."><?= e((string) ($c['note'] ?? '')) ?></textarea>
      </div>
    </div>

    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 4px">Ses listes</h3>
      <p class="aide" style="margin:0 0 14px">Décocher une liste <strong>en sort</strong> la personne —
      sa fiche reste au carnet, avec tout ce que vous y avez écrit.</p>
      <?php if (!$listes): ?>
        <p class="aide">Vous n’avez pas encore de liste. <a href="<?= e(url('?p=regie-carnet')) ?>">Créez-en une</a>.</p>
      <?php else: ?>
        <div class="cases">
          <?php foreach ($listes as $l): ?>
            <label class="case">
              <input type="checkbox" name="listes[]" value="<?= e((string) $l['id']) ?>"
                     <?= in_array((string) $l['id'], $siennes, true) ? 'checked' : '' ?>>
              <span><?= e((string) $l['nom']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="rangee" style="margin-top:16px;gap:10px">
        <button class="bouton" type="submit">Enregistrer</button>
        <a class="bouton fant" href="<?= e(url('?p=regie-carnet')) ?>">Annuler</a>
      </div>
    </div>
  </form>

  <div class="carte" style="margin-top:16px">
    <h3 style="margin:0 0 4px">Ce qui ne se range pas dans un formulaire</h3>
    <p class="aide" style="margin:0 0 14px">Deux gestes différents, et ils ne se remplacent pas.</p>

    <div class="rangee" style="gap:10px;flex-wrap:wrap">
      <form method="post" action="<?= e(url('?p=regie-carnet-action')) ?>">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="quoi" value="contact-archiver">
        <input type="hidden" name="id" value="<?= e((string) $c['id']) ?>">
        <input type="hidden" name="oui" value="<?= (int) $c['archive'] ? '0' : '1' ?>">
        <button class="bouton fant" type="submit">
          <?= (int) $c['archive'] ? 'Réactiver cette adresse' : 'Archiver cette adresse' ?>
        </button>
      </form>

      <form method="post" action="<?= e(url('?p=regie-carnet-action')) ?>"
            onsubmit="return confirm('Supprimer définitivement cette fiche du carnet ?')">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="quoi" value="contact-supprimer">
        <input type="hidden" name="id" value="<?= e((string) $c['id']) ?>">
        <button class="bouton fant danger" type="submit">Supprimer du carnet</button>
      </form>
    </div>

    <p class="aide" style="margin:14px 0 0">
      <strong>Archiver</strong> garde la fiche et l’historique, et n’écrit plus jamais à cette adresse —
      c’est ce qu’on fait quand un message rebondit ou qu’un client s’en va.
      <strong>Supprimer</strong> efface tout, sans retour.
    </p>
  </div>
</div>
