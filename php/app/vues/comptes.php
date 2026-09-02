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
    <p><?= (int) $clients_total ?> client<?= $clients_total > 1 ? 's' : '' ?> et <?= count($equipe) ?> membre<?=
      count($equipe) > 1 ? 's' : '' ?> de l’équipe. C’est ici que se donnent les accès : un rôle
    décide de ce qu’une personne peut faire, une offre décide de combien elle peut en faire.</p>
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
        <?php
        /* L'aide de CHAQUE rôle est écrite d'avance et montrée à la volée :
           « Éditeur » et « Coordinateur » ne se devinent pas, et choisir de
           travers donne à quelqu'un les clés du catalogue pour un samedi. */
        /* Les deux familles sont SÉPARÉES dans la liste. Un compte client et
           un compte de la maison n'ont rien à voir : les mêler dans une liste
           à plat finit par faire créer un coordinateur là où l'on voulait un
           organisateur. */
        ?>
        <select id="c-role" name="role"
                onchange="document.getElementById('c-role-aide').textContent = this.selectedOptions[0].dataset.aide">
          <optgroup label="Comptes clients">
            <?php foreach (ROLES_PUBLICS as $r): ?>
              <option value="<?= e($r) ?>" data-aide="<?= e(role_aide($r)) ?>"
                      <?= $valeurs['role'] === $r ? 'selected' : '' ?>><?= e(role_libelle($r)) ?></option>
            <?php endforeach; ?>
          </optgroup>
          <?php if (droit($me, 'comptes_internes')): ?>
            <optgroup label="Comptes de l’équipe">
              <?php foreach (ROLES_INTERNES as $r): ?>
                <option value="<?= e($r) ?>" data-aide="<?= e(role_aide($r)) ?>"
                        <?= $valeurs['role'] === $r ? 'selected' : '' ?>><?= e(role_libelle($r)) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endif; ?>
        </select>
        <p class="aide" id="c-role-aide"><?= e(role_aide($valeurs['role'])) ?></p>
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
  <?php
  /**
   * Une seule écriture de la ligne pour les deux tables.
   *
   * Elles montrent les mêmes colonnes et les mêmes gestes ; les écrire
   * deux fois, c'est se garantir qu'un jour l'une des deux oubliera la
   * confirmation d'adresse ou le bouton « Suspendre ».
   */
  $ligne = function (array $c) use ($me) { ?>
        <tr>
          <td>
            <a href="<?= e(url('?p=organisateur&id=' . rawurlencode((string) $c['id']))) ?>"><b><?= e($c['nom']) ?></b></a>
            <br><span class="aide"><?= e($c['email']) ?></span>
            <?php if (empty($c['email_verifie_le'])): ?>
              <br><span class="aide" style="color:var(--orange)">adresse non confirmée</span>
            <?php endif; ?>
          </td>
          <td><?= e($c['organisation'] ?: 'Non renseignée') ?><br><span class="aide"><?= e($c['ville'] ?: '') ?></span></td>
          <td class="mono chiffre">
            <?= (int) $c['decors'] ?>
            <?php if ((int) $c['actives']): ?>
              <br><span class="aide"><?= (int) $c['actives'] ?> active<?= (int) $c['actives'] > 1 ? 's' : '' ?></span>
            <?php endif; ?>
          </td>
          <?php
          /**
           * La consommation du mois, dans la liste.
           *
           * C'est le chiffre qu'on cherche quand quelqu'un écrit « mes
           * invités ne peuvent plus télécharger » : il faut le voir sans
           * ouvrir une fiche, sur la ligne du compte.
           */
          $q = $c['role'] === 'partenaire' ? quota($c, 'telechargements') : -1;
          $pris = $c['role'] === 'partenaire' ? telechargements_du_mois((string) $c['id']) : 0;
          ?>
          <td class="mono chiffre">
            <?php if ($c['role'] !== 'partenaire'): ?>
              <span class="aide">—</span>
            <?php elseif ($q < 0): ?>
              <?= $pris ?><br><span class="aide">sans limite</span>
            <?php else: ?>
              <span<?= $pris >= $q ? ' style="color:var(--rouge);font-weight:800"' : '' ?>><?= $pris ?> / <?= $q ?></span>
              <?php if ($pris >= $q): ?><br><span class="aide">quota plein</span><?php endif; ?>
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
                  <optgroup label="Clients">
                    <?php foreach (ROLES_PUBLICS as $r): ?>
                      <option value="<?= e($r) ?>" <?= $c['role'] === $r ? 'selected' : '' ?>><?= e(role_libelle($r)) ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                  <?php if (droit($me, 'comptes_internes')): ?>
                    <optgroup label="Équipe">
                      <?php foreach (ROLES_INTERNES as $r): ?>
                        <option value="<?= e($r) ?>" <?= $c['role'] === $r ? 'selected' : '' ?>><?= e(role_libelle($r)) ?></option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php elseif (in_array($c['role'], ROLES_INTERNES, true)): ?>
                    <?php /* Le rôle actuel reste lisible, même s'il n'est pas
                             donnable : une liste qui n'affiche pas ce qu'elle
                             décrit se lit comme une rétrogradation en attente. */ ?>
                    <option value="<?= e($c['role']) ?>" selected><?= e(role_libelle($c['role'])) ?></option>
                  <?php endif; ?>
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
  <?php };
  ?>

  <!-- l'équipe : peu de monde, jamais tronqué, du plus ancien au plus récent -->
  <section class="bloc-comptes">
    <h2 class="titre-liste">L’équipe</h2>
    <p class="aide">Les comptes de la maison. Ils ne consomment pas de quota et ne se cherchent
    pas : ils tiennent sur cet écran.</p>
    <div class="tableau">
      <table>
        <thead><tr><th>Compte</th><th>Structure</th><th class="chiffre">Décors</th>
        <th class="chiffre">Ce mois</th><th>Rôle et offre</th><th>État</th></tr></thead>
        <tbody>
        <?php foreach ($equipe as $c) { $ligne($c); } ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- les clients : longue liste, donc cherchable -->
  <section class="bloc-comptes">
    <h2 class="titre-liste">Les clients</h2>
    <form method="get" action="<?= e(url('?p=comptes')) ?>" class="rangee chercher chercher-comptes">
      <input type="hidden" name="p" value="comptes">
      <input type="text" name="q" value="<?= e($cherche) ?>" placeholder="Nom, adresse ou structure"
             aria-label="Chercher un compte client">
      <button class="bouton fant petit" type="submit">Chercher</button>
      <?php if ($cherche !== ''): ?>
        <a class="bouton fant petit" href="<?= e(url('?p=comptes')) ?>">Effacer</a>
      <?php endif; ?>
    </form>

    <?php if (!$clients): ?>
      <p class="aide">Aucun compte client ne correspond à « <?= e($cherche) ?> ».</p>
    <?php else: ?>
      <div class="tableau">
        <table>
          <thead><tr><th>Compte</th><th>Structure</th><th class="chiffre">Décors</th>
          <th class="chiffre">Ce mois</th><th>Rôle et offre</th><th>État</th></tr></thead>
          <tbody>
          <?php foreach ($clients as $c) { $ligne($c); } ?>
          </tbody>
        </table>
      </div>
      <?php if ($clients_total > count($clients)): ?>
        <?php /* Dire ce qui n'est pas montré : une liste qui tronque en
                 silence laisse croire qu'un compte a disparu. */ ?>
        <p class="aide" style="margin-top:8px"><?= count($clients) ?> comptes affichés sur
        <?= (int) $clients_total ?>. Cherchez par nom, adresse ou structure pour trouver les autres.</p>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <p class="aide" style="margin-top:12px">Suspendre coupe immédiatement les sessions ouvertes.
  Un administrateur ne peut ni se rétrograder ni se suspendre lui-même, sans quoi
  l’installation pourrait se retrouver sans administrateur.</p>
</div>
