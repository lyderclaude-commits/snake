<?php
/**
 * L'API, expliquée à qui va l'appeler.
 *
 * Écrite ici plutôt que dans un fichier à part : une documentation
 * livrée avec le produit ne peut pas décrire une version qu'on n'a plus,
 * et elle affiche l'adresse RÉELLE de cette installation — celle qu'on
 * peut copier dans un terminal sans rien remplacer à la main.
 */
$cle_neuve = $cle_neuve ?? null;
$ouverte = capacite($me, 'api');
$base = base_url() . '/index.php?p=api&r=';
$exemple = $cle_neuve ?: 'VOTRE_CLE';
?>
<div class="contenu etroit-large">
  <section class="entete">
    <h1>L’API</h1>
    <p>Pour verser vos chiffres dans votre propre tableur, votre CRM, ou l’écran
    d’accueil de votre soirée. Tout ce que vous voyez sur votre tableau de bord se lit
    aussi par programme.</p>
  </section>

  <?php if (!empty($_GET['ok'])): ?><div class="msg ok" role="status"><?= e($_GET['ok']) ?></div><?php endif; ?>

  <?php if (!$ouverte): ?>
    <div class="carte" style="margin-bottom:18px">
      <p style="margin:0 0 4px"><strong>Votre offre <?= e(formule_libelle($me['formule'] ?? null)) ?>
      n’ouvre pas l’API.</strong></p>
      <p class="aide" style="margin:0">Elle est comprise dans l’offre
      <?= e(formule_libelle(offre_qui_debloque('api'))) ?>. La documentation ci-dessous reste
      lisible : autant savoir ce qu’on achète.</p>
    </div>
  <?php endif; ?>

  <!-- ---------- la clé ---------- -->
  <div class="carte" style="margin-bottom:18px">
    <h3 style="margin:0 0 4px">Votre clé</h3>

    <?php if ($cle_neuve): ?>
      <p class="aide" style="margin:0 0 10px"><strong>Notez-la maintenant.</strong> Elle vaut
      mot de passe et ne sera plus jamais affichée en entier — si vous la perdez, il faudra
      en fabriquer une autre.</p>
      <pre class="bloc-code" style="user-select:all"><?= e($cle_neuve) ?></pre>
    <?php elseif ($me['cle_api'] ?? null): ?>
      <p class="aide" style="margin:0 0 10px">Une clé est active :
      <code><?= e(api_cle_masquee($me['cle_api'])) ?></code>. Elle n’est plus affichable en
      entier ; en fabriquer une nouvelle remplace l’ancienne, qui cesse aussitôt de fonctionner.</p>
    <?php else: ?>
      <p class="aide" style="margin:0 0 10px">Aucune clé pour l’instant.</p>
    <?php endif; ?>

    <div class="rangee" style="gap:10px;flex-wrap:wrap">
      <form method="post" action="<?= e(url('?p=api-cle')) ?>">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <button class="bouton" type="submit">
          <?= ($me['cle_api'] ?? null) ? 'Fabriquer une nouvelle clé' : 'Fabriquer ma clé' ?>
        </button>
      </form>
      <?php if ($me['cle_api'] ?? null): ?>
        <form method="post" action="<?= e(url('?p=api-cle')) ?>">
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <input type="hidden" name="quoi" value="revoquer">
          <button class="bouton danger" type="submit">Révoquer</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- ---------- comment s'authentifier ---------- -->
  <div class="carte" style="margin-bottom:18px">
    <h3 style="margin:0 0 4px">S’authentifier</h3>
    <p class="aide" style="margin:0 0 10px">La clé voyage dans un en-tête, jamais dans
    l’adresse : une adresse se retrouve dans les journaux du serveur, dans l’historique du
    navigateur et dans le presse-papiers de qui vous demande de l’aide.</p>
    <pre class="bloc-code">curl -H "Authorization: Bearer <?= e($exemple) ?>" \
  "<?= e($base) ?>moi"</pre>
    <p class="aide" style="margin:10px 0 0">Certains hébergements mutualisés
    <strong>retirent l’en-tête <code>Authorization</code></strong> avant que PHP ne le voie.
    Si vous recevez « Clé absente » alors que vous l’envoyez bien, utilisez le repli :
    <code>X-Api-Cle: <?= e($exemple) ?></code>. Il fonctionne partout.</p>
  </div>

  <!-- ---------- les ressources ---------- -->
  <div class="carte" style="margin-bottom:18px">
    <h3 style="margin:0 0 12px">Ce qu’on peut demander</h3>
    <div class="tableau">
      <table>
        <thead><tr><th>Ressource</th><th>Ce qu’elle rend</th></tr></thead>
        <tbody>
          <?php foreach ([
              ['GET', 'moi', 'Votre compte, votre offre, son échéance, et vos quatre compteurs avec leur maximum.'],
              ['GET', 'campagnes', 'Toutes vos campagnes, avec vues, téléchargements, badges émis, présences et taux.'],
              ['GET', 'campagnes/<i>slug</i>', 'Une campagne, les mêmes chiffres.'],
              ['GET', 'badges?campagne=<i>slug</i>', 'Les badges émis, page par page. <code>page</code> et <code>par_page</code> (200 au plus).'],
              ['GET', 'presences?campagne=<i>slug</i>', 'Les seuls badges réellement scannés à l’entrée.'],
              ['GET', 'liens', 'Vos liens courts et le nombre de clics de chacun.'],
              ['POST', 'liens', 'Crée un lien court. Corps JSON : <code>cible</code>, et <code>titre</code> si vous voulez.'],
          ] as [$m, $r, $quoi]): ?>
            <tr>
              <td class="mono" style="white-space:nowrap">
                <span class="pastille <?= $m === 'POST' ? 'en_relecture' : 'publie' ?>"><?= e($m) ?></span>
                <br><?= $r /* les <i> sont les nôtres */ ?>
              </td>
              <td><?= $quoi ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="aide" style="margin:12px 0 0">L’API <strong>lit</strong>, et ne publie pas.
    Créer un décor ou le mettre en ligne passe par la relecture, comme pour tout le monde :
    c’est la garantie qu’on donne à chaque organisateur, et une API qui la contournerait la
    retirerait à tous.</p>
  </div>

  <!-- ---------- un exemple entier ---------- -->
  <div class="carte" style="margin-bottom:18px">
    <h3 style="margin:0 0 4px">Un exemple entier</h3>
    <p class="aide" style="margin:0 0 10px">Le taux de présence de chacune de vos campagnes,
    en une ligne.</p>
    <pre class="bloc-code">curl -s -H "X-Api-Cle: <?= e($exemple) ?>" "<?= e($base) ?>campagnes" \
  | jq -r '.campagnes[] | "\(.titre): \(.chiffres.presences)/\(.chiffres.badges_emis)"'</pre>

    <p class="aide" style="margin:14px 0 6px">Et créer un lien court :</p>
    <pre class="bloc-code">curl -X POST -H "X-Api-Cle: <?= e($exemple) ?>" \
  -H "Content-Type: application/json" \
  -d '{"cible":"https://wakabileguide.com/p/ma-soiree","titre":"Affiche"}' \
  "<?= e($base) ?>liens"</pre>
  </div>

  <!-- ---------- les refus ---------- -->
  <div class="carte">
    <h3 style="margin:0 0 12px">Quand ça refuse</h3>
    <p class="aide" style="margin:0 0 12px">Chaque refus rend un objet
    <code>{"ok":false,"genre":"…","message":"…"}</code>. Le <code>genre</code> est fait pour
    votre code, le <code>message</code> pour vos yeux.</p>
    <div class="tableau">
      <table>
        <thead><tr><th>Code</th><th>Genre</th><th>Ce qui s’est passé</th></tr></thead>
        <tbody>
          <?php foreach ([
              ['401', 'cle_absente', 'Aucune clé dans la requête — ou l’hébergement a retiré l’en-tête.'],
              ['401', 'cle_inconnue', 'La clé ne correspond à aucun compte : elle a été révoquée ou remplacée.'],
              ['403', 'offre_insuffisante', 'La clé est bonne, mais l’offre du compte n’ouvre plus l’API.'],
              ['403', 'compte_suspendu', 'Le compte est suspendu.'],
              ['404', 'introuvable', 'Cette campagne n’existe pas, ou n’est pas la vôtre.'],
              ['409', 'quota_atteint', 'Le quota de liens courts de votre offre est plein.'],
              ['422', 'cible_invalide', 'L’adresse envoyée n’en est pas une, ou sort des domaines Wakabi.'],
              ['429', 'debit_depasse', 'Plus de ' . API_APPELS_MAX . ' appels en un quart d’heure.'],
          ] as [$code, $genre, $quoi]): ?>
            <tr>
              <td class="mono"><?= e($code) ?></td>
              <td class="mono"><?= e($genre) ?></td>
              <td><?= $quoi ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
