<?php
/** Les comptes : en créer, changer le rôle et l'offre, suspendre. */
$valeurs = ($valeurs ?? []) + [
    'nom' => '', 'email' => '', 'role' => 'partenaire',
    'formule' => 'decouverte', 'organisation' => '', 'ville' => 'lome',
];
$erreur = $erreur ?? null;
$ouvert = $ouvert ?? '';
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
  <?php
  /**
   * Deux formulaires, jamais un seul.
   *
   * Un compte client s'achète une offre ; un compte de la maison est un
   * collègue. Tant qu'ils partageaient une liste déroulante, « Organisateur »
   * et « Coordinateur » se touchaient à deux lignes d'écart — et le champ
   * « Offre » restait planté là, à proposer une Croissance à quelqu'un
   * qu'on embauche. Les séparer coûte un bouton de plus et supprime la
   * seule erreur de cet écran dont on ne s'aperçoit pas.
   *
   * Les champs communs sont écrits UNE fois : deux copies finiraient par
   * diverger sur le mot de passe provisoire ou la confirmation d'adresse.
   */
  $identite = function (string $prefixe, array $v) { ?>
      <div class="champ">
        <label for="<?= e($prefixe) ?>-nom">Nom</label>
        <input id="<?= e($prefixe) ?>-nom" name="nom" type="text" required value="<?= e($v['nom']) ?>">
      </div>
      <div class="champ">
        <label for="<?= e($prefixe) ?>-email">Adresse e-mail</label>
        <input id="<?= e($prefixe) ?>-email" name="email" type="email" required value="<?= e($v['email']) ?>">
        <p class="aide">Un lien de confirmation y partira tout de suite, si le transport e-mail est réglé.</p>
      </div>
  <?php };

  $motdepasse = function (string $prefixe) { ?>
      <div class="champ">
        <label for="<?= e($prefixe) ?>-mdp">Mot de passe provisoire</label>
        <input id="<?= e($prefixe) ?>-mdp" name="mot_de_passe" type="text" required minlength="8"
               autocomplete="off" value="<?= e(bin2hex(random_bytes(5))) ?>">
        <p class="aide">Proposé au hasard, modifiable. Il n’est plus lisible après la création :
        notez-le avant d’envoyer le formulaire.</p>
      </div>
      <div class="champ" style="align-self:end">
        <button class="bouton" type="submit" style="width:100%;justify-content:center">Créer le compte</button>
      </div>
  <?php };

  $roles_clients = $valeurs['role'] === '' || in_array($valeurs['role'], ROLES_PUBLICS, true)
      ? $valeurs['role'] : 'partenaire';
  ?>

  <details class="carte creer"<?= $ouvert === 'client' ? ' open' : '' ?>>
    <summary>
      <span class="bouton petit">+ Nouveau compte client</span>
      <span class="aide">Un organisateur ou un participant. Il a une offre, des quotas, et ne voit que ce qui lui appartient.</span>
    </summary>

    <form method="post" action="<?= e(url('?p=creer-compte')) ?>" class="formulaire-compte">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <?php $identite('c', $valeurs); ?>

      <div class="champ">
        <label for="c-role">Rôle</label>
        <select id="c-role" name="role"
                onchange="document.getElementById('c-role-aide').textContent = this.selectedOptions[0].dataset.aide">
          <?php foreach (ROLES_PUBLICS as $r): ?>
            <option value="<?= e($r) ?>" data-aide="<?= e(role_aide($r)) ?>"
                    <?= $roles_clients === $r ? 'selected' : '' ?>><?= e(role_libelle($r)) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="aide" id="c-role-aide"><?= e(role_aide($roles_clients)) ?></p>
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
        <p class="aide">Une offre payante ouvre une échéance de <?= (int) ABONNEMENT_JOURS ?> jours,
        suivie et relancée automatiquement.</p>
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

      <?php $motdepasse('c'); ?>
    </form>
  </details>

  <?php if (droit($me, 'comptes_internes')): ?>
    <?php $roles_maison = in_array($valeurs['role'], ROLES_INTERNES, true) ? $valeurs['role'] : 'scanner'; ?>
    <details class="carte creer" style="margin-top:12px"<?= $ouvert === 'equipe' ? ' open' : '' ?>>
      <summary>
        <span class="bouton petit fant">+ Nouveau compte de l’équipe</span>
        <span class="aide">Quelqu’un qui travaille pour la maison. <strong>Pas d’offre, pas de quota</strong> —
        on ne se vend pas des fonctions à soi-même.</span>
      </summary>

      <form method="post" action="<?= e(url('?p=creer-equipier')) ?>" class="formulaire-compte">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <?php $identite('e', $valeurs); ?>

        <div class="champ">
          <label for="e-role">Rôle</label>
          <select id="e-role" name="role"
                  onchange="document.getElementById('e-role-aide').textContent = this.selectedOptions[0].dataset.aide">
            <?php foreach (ROLES_INTERNES as $r): ?>
              <option value="<?= e($r) ?>" data-aide="<?= e(role_aide($r)) ?>"
                      <?= $roles_maison === $r ? 'selected' : '' ?>><?= e(role_libelle($r)) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="aide" id="e-role-aide"><?= e(role_aide($roles_maison)) ?></p>
        </div>

        <div class="champ">
          <label for="e-ville">Ville</label>
          <select id="e-ville" name="ville">
            <?php foreach (['lome' => 'Lomé', 'cotonou' => 'Cotonou', 'abidjan' => 'Abidjan', 'autre' => 'Autre'] as $k => $nom): ?>
              <option value="<?= e($k) ?>" <?= $valeurs['ville'] === $k ? 'selected' : '' ?>><?= e($nom) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php $motdepasse('e'); ?>
      </form>
    </details>
  <?php endif; ?>

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
              <?php
              /* Le constat SUIVI de son remède : « adresse non confirmée »
                 tout seul décrivait un problème sans donner le geste, et le
                 seul bouton à portée était celui du rôle — d'où des courriels
                 parlant d'offre là où l'on voulait une confirmation. */
              ?>
              <br><span class="aide" style="color:var(--orange)">adresse non confirmée</span>
              <?php if ($c['id'] !== $me['id']): ?>
                <form method="post" action="<?= e(url('?p=verif-renvoyer')) ?>" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                  <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                  <button class="lien-bouton" type="submit">Renvoyer le lien</button>
                </form>
              <?php endif; ?>
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
                <?php
                /**
                 * Le déroulant des offres n'apparaît QUE pour un client.
                 *
                 * Sur la ligne d'un coordinateur, il proposait de lui vendre
                 * une Croissance — et le valider lui expédiait « Votre offre
                 * est maintenant… ». Le serveur refuse désormais cette
                 * combinaison ; l'écran cesse d'abord de la suggérer.
                 */
                ?>
                <?php if (!in_array($c['role'], ROLES_INTERNES, true)): ?>
                  <select name="formule" style="width:auto" aria-label="Offre de <?= e($c['nom']) ?>">
                    <?php foreach (FORMULES as $cle => $f): ?>
                      <option value="<?= e($cle) ?>" <?= ($c['formule'] ?? 'decouverte') === $cle ? 'selected' : '' ?>><?= e($f['nom']) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <span class="aide">sans offre</span>
                <?php endif; ?>
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
        <th class="chiffre">Ce mois</th><th>Rôle</th><th>État</th></tr></thead>
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
