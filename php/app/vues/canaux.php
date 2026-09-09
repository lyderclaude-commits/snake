<?php
/**
 * Par où les messages sortent.
 *
 * Un écran qui répond à une seule question — « puis-je écrire, et à
 * combien de personnes ? » — et qui dit sans détour ce que chaque
 * plateforme sait faire. La ligne qui compte le plus est celle qui
 * rappelle que WhatsApp n'a ni chaîne ni groupe : c'est ce qu'on croit
 * acheter, et c'est ce qui n'existe pas.
 */
$message = $message ?? null;
$erreur = $erreur ?? null;
$canaux = $canaux ?? [];
$genres_places = array_column($canaux, 'genre');
?>
<div class="contenu">
  <section class="entete">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
      <div>
        <h1>Canaux de diffusion</h1>
        <p>Par où vos messages sortent. Un canal se branche une fois, puis se coche à l’écriture.</p>
      </div>
      <a class="bouton" href="<?= e(url('?p=regie-ecrire')) ?>">Écrire un message</a>
    </div>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <div class="cx">
    <?php foreach ($canaux as $c):
      $g = CANAUX_GENRES[$c['genre']] ?? ['nom' => $c['genre'], 'sigle' => '?', 'payant' => false];
      $branche = $c['statut'] === 'branche';
      $q = $quotas[$c['genre']] ?? null;
    ?>
      <div class="cx-carte">
        <div class="cx-tete">
          <span class="cx-rond cx-<?= e($c['genre']) ?>"><?= e($g['sigle']) ?></span>
          <span class="cx-nom"><?= e($g['nom']) ?><span><?= e($c['nom']) ?></span></span>
          <span class="etat <?= $branche ? 'on' : 'att' ?>"><?= $branche ? 'Branché' : 'À vérifier' ?></span>
        </div>

        <?php if ($c['message']): ?>
          <div class="msg err" style="margin:0;font-size:.84rem;line-height:1.5"><?= e($c['message']) ?></div>
        <?php endif; ?>

        <ul class="cx-liste">
          <?php foreach ($c['destinations'] as $d): ?>
            <li>
              <span class="cx-genre"><?= e(CANAUX_DESTINATIONS[$d['genre']] ?? $d['genre']) ?></span>
              <?= e($d['nom']) ?>
              <b><?= (int) $d['abonnes'] ?></b>
              <form method="post" action="<?= e(url('?p=canaux')) ?>" style="margin-left:6px">
                <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                <input type="hidden" name="quoi" value="retirer">
                <input type="hidden" name="destination" value="<?= e($d['id']) ?>">
                <button class="bouton fant petit" type="submit" title="Retirer cette destination">×</button>
              </form>
            </li>
          <?php endforeach; ?>
          <li>
            <span class="cx-genre">Direct</span>
            <?= $c['genre'] === 'telegram' ? 'Abonnés du bot' : 'Numéros avec accord' ?>
            <b><?= (int) $c['abonnes'] ?></b>
          </li>
        </ul>

        <?php if ($c['genre'] === 'whatsapp'): ?>
          <div class="msg err" style="margin:0;font-size:.84rem;line-height:1.5">
            <b>Ni groupe, ni chaîne WhatsApp.</b> L’API n’écrit qu’en tête-à-tête, à des numéros
            qui ont donné leur accord, avec un modèle approuvé par Meta — et chaque message est facturé.
          </div>
        <?php else: ?>
          <form method="post" action="<?= e(url('?p=canaux')) ?>" class="champ" style="margin:0">
            <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
            <input type="hidden" name="quoi" value="destination">
            <input type="hidden" name="id" value="<?= e($c['id']) ?>">
            <label for="cible-<?= e($c['id']) ?>">Ajouter une chaîne ou un groupe</label>
            <div class="rangee" style="gap:8px">
              <input id="cible-<?= e($c['id']) ?>" name="cible" type="text"
                     placeholder="@ma_chaine ou t.me/ma_chaine" style="flex:1">
              <button class="bouton fant petit" type="submit">Ajouter</button>
            </div>
            <p class="aide">Le bot doit y être <strong>administrateur</strong> — sinon Telegram
            répond « chaîne introuvable », même si elle existe.</p>
          </form>
        <?php endif; ?>

        <?php if ($q && $q['max'] >= 0): ?>
          <div class="marche" style="margin:0">
            <div class="haut"><span>Ce mois-ci</span><b><?= (int) $q['utilises'] ?> / <?= (int) $q['max'] ?></b></div>
            <div class="rail"><i style="width:<?= min(100, (int) round($q['utilises'] / max(1, $q['max']) * 100)) ?>%"></i></div>
          </div>
        <?php endif; ?>

        <div class="rangee" style="gap:8px;flex-wrap:wrap">
          <form method="post" action="<?= e(url('?p=canaux')) ?>">
            <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
            <input type="hidden" name="quoi" value="verifier">
            <input type="hidden" name="id" value="<?= e($c['id']) ?>">
            <button class="bouton fant petit" type="submit">Vérifier</button>
          </form>
          <?php if ($c['genre'] === 'telegram'): ?>
            <form method="post" action="<?= e(url('?p=canaux')) ?>">
              <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
              <input type="hidden" name="quoi" value="relever">
              <input type="hidden" name="id" value="<?= e($c['id']) ?>">
              <button class="bouton fant petit" type="submit">Relever les abonnés</button>
            </form>
          <?php endif; ?>
          <details style="margin-left:auto">
            <summary class="bouton fant petit" style="list-style:none">Débrancher…</summary>
            <form method="post" action="<?= e(url('?p=canaux')) ?>" style="margin-top:8px">
              <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
              <input type="hidden" name="quoi" value="supprimer">
              <input type="hidden" name="id" value="<?= e($c['id']) ?>">
              <button class="bouton fant petit" type="submit">Oui, débrancher</button>
            </form>
          </details>
        </div>
      </div>
    <?php endforeach; ?>

    <?php
    /**
     * Les deux canaux déjà en service figurent ici comme les autres.
     *
     * Ils ne se branchent pas sur cet écran — l'un vient des décors,
     * l'autre des réglages — mais les cacher donnerait à croire qu'écrire
     * demande d'abord de brancher Telegram, ce qui est faux.
     */
    ?>
    <div class="cx-carte">
      <div class="cx-tete">
        <span class="cx-rond cx-web">WEB</span>
        <span class="cx-nom">Notifications navigateur<span>Déjà en service</span></span>
        <span class="etat <?= $push['disponible'] ? 'on' : 'off' ?>">
          <?= $push['disponible'] ? 'Branché' : 'Indisponible' ?>
        </span>
      </div>
      <ul class="cx-liste">
        <li><span class="cx-genre">Direct</span> Appareils abonnés <b><?= (int) $push['abonnes'] ?></b></li>
      </ul>
      <p class="aide" style="margin:0">Gratuit, instantané, et déjà branché sur vos décors :
      c’est la case « Être prévenu des prochaines campagnes » du Studio.</p>
      <div class="rangee" style="gap:8px">
        <a class="bouton fant petit" href="<?= e(url('?p=diffusion')) ?>">Écrire et historique</a>
      </div>
    </div>

    <div class="cx-carte">
      <div class="cx-tete">
        <span class="cx-rond cx-ml">@</span>
        <span class="cx-nom">E-mail<span><?= $courriel ? 'Transport réglé' : 'Transport éteint' ?></span></span>
        <span class="etat <?= $courriel ? 'on' : 'off' ?>"><?= $courriel ? 'Branché' : 'Éteint' ?></span>
      </div>
      <p class="aide" style="margin:0">Le seul canal où l’adresse de l’expéditeur doit être
      authentifiée par SPF, DKIM et DMARC — sans quoi les messages sont refusés, pas classés
      en indésirables.</p>
      <div class="rangee" style="gap:8px">
        <a class="bouton fant petit" href="<?= e(url('?p=regie')) ?>">La régie</a>
        <?php if ($equipe): ?>
          <a class="bouton fant petit" href="<?= e(url('?p=reglages')) ?>">Réglages et diagnostic</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php
  /* ---------------- brancher un canal de plus ---------------- */
  $manquants = array_diff(array_keys(CANAUX_GENRES), $genres_places);
  ?>
  <div class="carte" style="margin-top:18px">
    <h3 style="margin:0 0 4px">Brancher un canal</h3>
    <p class="aide" style="margin:0 0 16px">Le jeton reste sur ce serveur. Il n’est jamais réaffiché,
    jamais dans une adresse : il vaut un mot de passe.</p>

    <div class="grille g2">
      <form method="post" action="<?= e(url('?p=canaux')) ?>" class="carte" style="box-shadow:none">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="quoi" value="brancher">
        <input type="hidden" name="genre" value="telegram">
        <h4 style="margin:0 0 4px">Telegram</h4>
        <p class="aide" style="margin:0 0 12px"><?= e(CANAUX_GENRES['telegram']['aide']) ?></p>
        <div class="champ">
          <label for="jeton-tg">Jeton du bot</label>
          <input id="jeton-tg" name="jeton" type="text" autocomplete="off"
                 placeholder="123456789:AAH...">
          <p class="aide">Écrivez à <strong>@BotFather</strong> sur Telegram, envoyez
          <code>/newbot</code>, et recopiez ici le jeton qu’il vous donne.</p>
        </div>
        <button class="bouton" type="submit">Brancher Telegram</button>
      </form>

      <form method="post" action="<?= e(url('?p=canaux')) ?>" class="carte" style="box-shadow:none">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="quoi" value="brancher">
        <input type="hidden" name="genre" value="whatsapp">
        <h4 style="margin:0 0 4px">WhatsApp Business</h4>
        <p class="aide" style="margin:0 0 12px"><?= e(CANAUX_GENRES['whatsapp']['aide']) ?></p>
        <div class="champ">
          <label for="ref-wa">Identifiant du numéro d’envoi</label>
          <input id="ref-wa" name="reference" type="text" autocomplete="off" placeholder="1234567890">
        </div>
        <div class="champ">
          <label for="jeton-wa">Jeton d’accès permanent</label>
          <input id="jeton-wa" name="jeton" type="text" autocomplete="off" placeholder="EAAG...">
          <p class="aide">Les deux se prennent dans le tableau de bord Meta, sur votre application
          WhatsApp. Un jeton temporaire ne vit que vingt-quatre heures.</p>
        </div>
        <button class="bouton" type="submit">Brancher WhatsApp</button>
      </form>
    </div>
  </div>

  <div class="carte" style="margin-top:16px">
    <h3 style="margin:0 0 8px">Ce que chaque canal sait faire</h3>
    <div style="overflow-x:auto">
      <table class="tab" style="min-width:560px">
        <tr>
          <th>Canal</th><th>Chaîne / groupe</th><th>Tête-à-tête</th><th>Coût</th><th>Délai</th>
        </tr>
        <tr>
          <td><b>Telegram</b></td>
          <td class="oui">Oui, sans limite</td>
          <td class="oui">Oui, après un /start</td>
          <td>Gratuit</td><td>Immédiat</td>
        </tr>
        <tr>
          <td><b>WhatsApp</b></td>
          <td class="non">Non — 8 membres au plus</td>
          <td>Oui, avec accord et modèle</td>
          <td>Par message</td><td>Vérification Meta</td>
        </tr>
        <tr>
          <td><b>Navigateur</b></td>
          <td>—</td><td class="oui">Oui</td><td>Gratuit</td><td>Immédiat</td>
        </tr>
        <tr>
          <td><b>E-mail</b></td>
          <td>—</td><td class="oui">Oui</td><td>Relais</td><td>Minutes</td>
        </tr>
      </table>
    </div>
  </div>
</div>
