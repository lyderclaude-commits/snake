<?php
/** Les comptes : en créer, changer le rôle et l'offre, suspendre. */
$valeurs = ($valeurs ?? []) + [
    'nom' => '', 'email' => '', 'role' => 'partenaire',
    'formule' => 'decouverte', 'organisation' => '', 'ville' => 'lome',
];
$erreur = $erreur ?? null;
$ouvert = $ouvert ?? false;
?>
<div class="contenu">
  <section class="entete">
    <h1>Comptes</h1>
    <p><?= count($liste) ?> inscrits. C’est ici que se donnent les accès : un rôle décide de ce
    qu’une personne peut faire, une offre décide de combien elle peut en faire.</p>
  </section>

  <?php if (!empty($_GET['ok'])): ?><div class="msg ok" role="status"><?= e($_GET['ok']) ?></div><?php endif; ?>
  <?php if (!empty($_GET['err'])): ?><div class="msg err" role="alert"><?= e($_GET['err']) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <!-- ---------- créer un compte ---------- -->
  <details class="carte creer"<?= $ouvert ? ' open' : '' ?>>
    <summary>
      <span class="bouton petit">+ Créer un compte</span>
      <span class="aide">Pour un organisateur qui a payé son offre, ou pour un membre de l’équipe.</span>
    </summary>

    <form method="post" action="<?= e(url('?p=creer-compte')) ?>" class="formulaire-compte">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

      <div class="champ">
        <label for="c-nom">Nom</label>
        <input id="c-nom" name="nom" type="text" required value="<?= e($valeurs['nom']) ?>">
      </div>
      <div class="champ">
        <label for="c-email">Adresse e-mail</label>
        <input id="c-email" name="email" type="email" required value="<?= e($valeurs['email']) ?>">
      </div>

      <div class="champ">
        <label for="c-role">Rôle</label>
        <select id="c-role" name="role">
          <?php foreach (ROLES as $r): ?>
            <option value="<?= e($r) ?>" <?= $valeurs['role'] === $r ? 'selected' : '' ?>><?= e(role_libelle($r)) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="aide">L’équipe voit tout et modère. L’organisateur crée des campagnes soumises à relecture.</p>
      </div>

      <div class="champ">
        <label for="c-formule">Offre</label>
        <select id="c-formule" name="formule">
          <?php foreach (FORMULES as $cle => $f): ?>
            <?php
            $combien = $f['campagnes'] < 0
                ? 'illimité'
                : $f['campagnes'] . ' campagne' . ($f['campagnes'] > 1 ? 's' : '');
            $tarif = $f['prix'] ? number_format($f['prix'], 0, ',', ' ') . ' F/mois' : 'gratuit';
            ?>
            <option value="<?= e($cle) ?>" <?= $valeurs['formule'] === $cle ? 'selected' : '' ?>><?=
              e($f['nom'] . ' · ' . $combien . ' · ' . $tarif) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="aide">Sans effet sur un compte de l’équipe, qui n’a jamais de quota.</p>
      </div>

      <div class="champ">
        <label for="c-org">Structure</label>
        <input id="c-org" name="organisation" type="text" value="<?= e($valeurs['organisation']) ?>">
      </div>
      <div class="champ">
        <label for="c-ville">Ville</label>
        <select id="c-ville" name="ville">
          <?php foreach (['lome' => 'Lomé', 'cotonou' => 'Cotonou', 'abidjan' => 'Abidjan', 'autre' => 'Autre'] as $k => $nom): ?>
            <option value="<?= e($k) ?>" <?= $valeurs['ville'] === $k ? 'selected' : '' ?>><?= e($nom) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="champ">
        <label for="c-mdp">Mot de passe provisoire</label>
        <input id="c-mdp" name="mot_de_passe" type="text" required minlength="8"
               autocomplete="off" value="<?= e(bin2hex(random_bytes(5))) ?>">
        <p class="aide">Proposé au hasard, modifiable. Il n’est plus lisible après la création :
        notez-le avant d’envoyer le formulaire.</p>
      </div>

      <div class="champ" style="align-self:end">
        <button class="bouton" type="submit" style="width:100%;justify-content:center">Créer le compte</button>
      </div>
    </form>
  </details>

  <!-- ---------- la liste ---------- -->
  <div class="tableau">
    <table>
      <thead><tr><th>Compte</th><th>Structure</th><th class="chiffre">Décors</th><th>Rôle et offre</th><th>État</th></tr></thead>
      <tbody>
      <?php foreach ($liste as $c): ?>
        <tr>
          <td><b><?= e($c['nom']) ?></b><br><span class="aide"><?= e($c['email']) ?></span></td>
          <td><?= e($c['organisation'] ?: 'Non renseignée') ?><br><span class="aide"><?= e($c['ville'] ?: '') ?></span></td>
          <td class="mono chiffre">
            <?= (int) $c['decors'] ?>
            <?php if ((int) $c['actives']): ?>
              <br><span class="aide"><?= (int) $c['actives'] ?> active<?= (int) $c['actives'] > 1 ? 's' : '' ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($c['id'] === $me['id']): ?>
              <span class="pastille publie"><?= e(role_libelle($c['role'])) ?></span>
              <span class="aide">Votre compte</span>
            <?php else: ?>
              <form method="post" action="<?= e(url('?p=role')) ?>" class="rangee" style="gap:6px">
                <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                <select name="role" style="width:auto" aria-label="Rôle de <?= e($c['nom']) ?>">
                  <?php foreach (ROLES as $r): ?>
                    <option value="<?= e($r) ?>" <?= $c['role'] === $r ? 'selected' : '' ?>><?= e(role_libelle($r)) ?></option>
                  <?php endforeach; ?>
                </select>
                <select name="formule" style="width:auto" aria-label="Offre de <?= e($c['nom']) ?>">
                  <?php foreach (FORMULES as $cle => $f): ?>
                    <option value="<?= e($cle) ?>" <?= ($c['formule'] ?? 'decouverte') === $cle ? 'selected' : '' ?>><?= e($f['nom']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="bouton fant petit" type="submit">OK</button>
              </form>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($c['id'] !== $me['id']): ?>
              <form method="post" action="<?= e(url('?p=suspendre')) ?>">
                <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                <button class="bouton <?= $c['suspendu'] ? 'fant' : 'danger' ?> petit" type="submit">
                  <?= $c['suspendu'] ? 'Réactiver' : 'Suspendre' ?>
                </button>
              </form>
            <?php else: ?><span class="pastille publie">Actif</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="aide" style="margin-top:12px">Suspendre coupe immédiatement les sessions ouvertes.
  Un administrateur ne peut ni se rétrograder ni se suspendre lui-même, sans quoi
  l’installation pourrait se retrouver sans administrateur.</p>
</div>
