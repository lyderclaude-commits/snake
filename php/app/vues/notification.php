<?php
/**
 * La page Notification : un composer, ses canaux cochables, ses aperçus,
 * et les derniers envois en dessous.
 *
 * La mise en page reste sur la charte (.carte, .champ, .bouton, .ec-cn,
 * .ec-canaux) ; seules les bulles d'aperçu portent leurs propres classes
 * `nt-bulle` — `.bulle` est déjà pris par le chat, et un nom partagé finit
 * toujours par se battre.
 *
 * Comme dans la régie, la portée se lit AVANT de cocher : chaque carte
 * porte le nombre de personnes qu'elle touche, et l'unité en dessous. Une
 * chaîne Telegram est une destination (un envoi pour n lecteurs), le
 * tête-à-tête un envoi par personne.
 */
$erreur = $erreur ?? null;
$message = $message ?? null;
$essai = $essai ?? [];
$saisie = $saisie ?? ['segment' => '', 'titre' => '', 'corps' => '', 'lien' => '', 'libelle' => ''];
$portee = $portee ?? ['push' => 0, 'destinations' => [], 'tete_a_tete' => []];
$mes_canaux = $mes_canaux ?? [];

$seg_lib = fn(string $s): string => $segments[$s] ?? $s;
$canaux_nom = ['telegram' => 'Telegram', 'whatsapp' => 'WhatsApp'];

/* Les cartes de canal, dans l'ordre de la lecture : le push maison, puis
   les publications Telegram, puis les tête-à-tête. */
$choix = [];
if ($disponible) {
    $choix[] = [
        'cle' => 'push', 'genre' => 'push', 'sigle' => 'WEB', 'fonds' => 'cx-web',
        'libelle' => 'Push de l’application', 'sous' => 'abonnés du navigateur',
        'nb' => (int) $portee['push'], 'unite' => 'abonnés',
        'note' => 'Le canal maison : titre et texte envoyés tels quels, coupés à '
                . (int) PUSH_APERCU . ' caractères.',
    ];
}
foreach ($mes_canaux as $mc) {
    $g = (string) $mc['genre'];
    $nom = $canaux_nom[$g] ?? $g;
    foreach ($mc['destinations'] as $d) {
        $choix[] = [
            'cle' => 'd:' . $d['id'], 'genre' => $g,
            'sigle' => $g === 'telegram' ? 'TG' : 'WA',
            'fonds' => $g === 'telegram' ? 'cx-telegram' : 'cx-whatsapp',
            'libelle' => (string) $d['nom'], 'sous' => 'canal ' . (string) $mc['nom'],
            'nb' => (int) $d['abonnes'], 'unite' => 'abonnés · 1 envoi',
            'note' => 'Une publication, quel que soit le nombre de lecteurs.',
        ];
    }
    if ((int) $mc['abonnes'] > 0) {
        $choix[] = [
            'cle' => 'c:' . $mc['id'], 'genre' => $g,
            'sigle' => $g === 'telegram' ? 'TG' : 'WA',
            'fonds' => $g === 'telegram' ? 'cx-telegram' : 'cx-whatsapp',
            'libelle' => (string) $mc['nom'], 'sous' => 'tête-à-tête',
            'nb' => (int) $mc['abonnes'], 'unite' => $g === 'telegram' ? 'personnes' : 'numéros avec accord',
            'note' => $g === 'telegram'
                ? 'Un message par personne qui a écrit au bot au moins une fois.'
                : 'Demande le modèle approuvé ci-dessous, et se facture au message par Meta.',
        ];
    }
}

/** Le libellé du canal dans l'historique : le nom rangé, traduit s'il l'est. */
$hist_canal = static function (array $n) use ($seg_lib, $canaux_nom): string {
    $s = (string) $n['segment'];
    return $canaux_nom[$s] ?? $seg_lib($s);
};

/** Ce qu'il y a dans les bulles d'aperçu : la saisie, ou un texte d'exemple. */
$ap_titre = $saisie['titre'] !== '' ? $saisie['titre'] : 'Le concert de samedi est confirmé';
$ap_corps = $saisie['corps'] !== '' ? $saisie['corps']
    : 'Portes à 19h, première scène à 20h. Présentez votre badge QR à l’entrée.';
$ap_lib = $saisie['libelle'] !== '' ? $saisie['libelle'] : 'Acheter un billet';
?>
<style>
.nt-bulle{border-radius:12px;padding:12px 14px;font-size:.86rem;line-height:1.55;color:var(--ink)}
.nt-bulle .t{display:block;margin-bottom:3px;font-weight:700}
.nt-bulle .lien{display:inline-flex;margin-top:8px;color:#fff;border-radius:8px;padding:6px 12px;font-size:.8rem;text-decoration:none}
.nt-pa{background:var(--bg2);border:1px solid var(--border)}
.nt-pa .lien{background:var(--primary)}
.nt-tg{background:#fff;border:1px solid var(--border)}
.nt-tg .lien{background:var(--primary)}
.nt-wa{background:#E7F7EE;border:1px solid #C9EBD9;color:#0F172A}
.nt-wa .lien{background:#25D366}
</style>
<div class="contenu">

  <section class="entete">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
      <div>
        <h1>Notification</h1>
        <p>Un message poussé en une fois sur le push de l'application, Telegram et WhatsApp :
        titre, texte, lien. Pour l'envoyer plus tard dans la journée, programmez-le depuis
        « Campagne Email » ; ici c'est maintenant, d'un seul geste.</p>
      </div>
      <a class="bouton fant" href="<?= e(url('?p=canaux')) ?>">Les Canaux</a>
    </div>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>
  <?php if (!$disponible): ?>
    <div class="msg err" style="margin-bottom:16px">
      <strong>L'envoi du push est débranché sur cet hébergement.</strong>
      <p style="margin:.35em 0 0"><?= e(implode(' · ', array_map('strval', $prerequis))) ?>.
      Telegram et WhatsApp, eux, restent envoyables.</p>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('?p=notification')) ?>">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

    <div class="grille" style="grid-template-columns:minmax(0,1fr) 360px;align-items:start">

      <!-- ======= à gauche : le composer, les canaux, l'envoi ======= -->
      <div class="pile">

        <div class="carte">
          <h3 style="margin:0 0 4px">Le message</h3>
          <p class="aide" style="margin:0 0 14px">Le titre s'affiche en tête de la notification ;
          le texte suit sur les trois canaux.</p>

          <?php if (count($segments) > 1): ?>
            <div class="champ">
              <label for="nt-segment">À qui</label>
              <select id="nt-segment" name="segment">
                <?php foreach ($segments as $cle => $lib): ?>
                  <option value="<?= e($cle) ?>" <?= $saisie['segment'] === $cle ? 'selected' : '' ?>>
                    <?= e($lib) ?><?= $disponible ? ' (' . (int) $portee['push'] . ')' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="aide">La portée du push suit ce choix ; les canaux branchés gardent
              la leur.</p>
            </div>
          <?php endif; ?>

          <div class="champ">
            <label for="nt-titre">Titre <span style="font-weight:400">(60 caractères)</span></label>
            <input id="nt-titre" name="titre" type="text" required maxlength="60"
                   placeholder="Le concert de samedi est confirmé" value="<?= e($saisie['titre']) ?>">
          </div>

          <div class="champ">
            <label for="nt-corps">Message</label>
            <textarea id="nt-corps" name="corps" rows="5" required
                      placeholder="Portes à 19h, première scène à 20h."><?= e($saisie['corps']) ?></textarea>
            <p class="aide">320 caractères au plus : le push du navigateur, le canal le plus
            court, est coupé à <?= (int) PUSH_APERCU ?>.</p>
          </div>

          <div class="grille g2">
            <div class="champ">
              <label for="nt-lien">Lien <span style="font-weight:400">(optionnel)</span></label>
              <input id="nt-lien" name="lien" type="url" value="<?= e($saisie['lien']) ?>"
                     placeholder="<?= e(base_url() . '/index.php?p=decors') ?>">
              <p class="aide">Le bouton ouvre ce lien<?= $equipe ? '' : '. Une adresse '
                . e(implode(' ou ', WAKABI_DOMAINES)) ?>.</p>
            </div>
            <div class="champ">
              <label for="nt-libelle">Libellé du bouton</label>
              <input id="nt-libelle" name="libelle" type="text" maxlength="40"
                     placeholder="Acheter un billet" value="<?= e($saisie['libelle']) ?>">
            </div>
          </div>
        </div>

        <div class="carte">
          <h3 style="margin:0 0 4px">Les canaux</h3>
          <p class="aide" style="margin:0 0 14px">La portée s'affiche avant de cocher, pas
          après avoir envoyé.
          <?php if ($mes_canaux === []): ?>
            <a href="<?= e(url('?p=canaux')) ?>">Brancher Telegram ou WhatsApp</a>.
          <?php endif; ?></p>

          <div class="ec-canaux">
            <?php foreach ($choix as $ch): ?>
              <label class="ec-cn">
                <input type="checkbox" name="canaux[]" value="<?= e($ch['cle']) ?>" <?= $ch['cle'] === 'push' ? 'checked' : '' ?>>
                <div class="ec-cn-h">
                  <span class="cx-rond ec-rond <?= e($ch['fonds']) ?>"><?= e($ch['sigle']) ?></span>
                  <b><?= e($ch['libelle']) ?><span><?= e($ch['sous']) ?><?= $ch['cle'] === 'push' ? '' : '' ?></span></b>
                  <span class="ec-coche">✓</span>
                </div>
                <div class="ec-cn-n"><b class="nb"><?= number_format($ch['nb'], 0, ',', ' ') ?></b>
                  <small><?= e($ch['unite']) ?></small></div>
                <div class="ec-cn-note"><span class="txt"><?= e($ch['note']) ?></span></div>
              </label>
            <?php endforeach; ?>
            <?php if (!$disponible): ?>
              <div class="ec-cn-note"><span class="txt">Le push du navigateur est indisponible ici :
              les actions de chiffrement manquent. La carte n'apparaît pas ci-dessus ; les autres
              canaux partent quand même.</span></div>
            <?php endif; ?>
            <?php if (!$choix && !$disponible): ?>
              <p style="color:var(--text2);margin:0">Aucun canal à cocher : abonnez-vous depuis
              Mon profil, et branchez Telegram ou WhatsApp dans « Les Canaux ».</p>
            <?php endif; ?>
          </div>

          <?php if (array_filter($choix, fn(array $c): bool => $c['genre'] === 'whatsapp')): ?>
            <div class="champ" style="margin:14px 0 0">
              <label for="nt-wa">Modèle WhatsApp approuvé</label>
              <input id="nt-wa" name="modele_whatsapp" type="text"
                     placeholder="rappel_evenement_fr"
                     value="<?= e((string) ($_POST['modele_whatsapp'] ?? '')) ?>">
              <p class="aide">Le nom exact du modèle, tel qu'il figure dans votre compte Meta.
              Hors des vingt-quatre heures qui suivent un message du destinataire, Meta refuse le
              texte libre : votre titre, votre message et votre lien remplissent les variables du
              modèle, dans cet ordre.</p>
            </div>
          <?php endif; ?>
        </div>

        <div class="rangee" style="gap:10px">
          <button class="bouton" type="submit">Envoyer la notification</button>
          <button class="bouton fant" type="submit" name="action" value="essai">S'envoyer un essai</button>
          <span class="aide" style="margin:0">Les échecs sont comptés et racontés ci-dessus.</span>
        </div>

      </div>

      <!-- ======= à droite : les aperçus ======= -->
      <div class="pile">
        <div class="carte">
          <h3 style="margin:0 0 14px">Aperçu</h3>

          <p class="pas">Push de l'application</p>
          <div class="nt-bulle nt-pa" style="margin-bottom:16px">
            <b class="t"><?= e($ap_titre) ?></b>
            <?= e($ap_corps) ?>
            <br><span class="lien"><?= e($ap_lib) ?></span>
          </div>

          <p class="pas">Telegram</p>
          <div class="nt-bulle nt-tg" style="margin-bottom:16px">
            <b class="t"><?= e($ap_titre) ?></b>
            <?= e($ap_corps) ?>
            <br><span class="lien"><?= e($ap_lib) ?></span>
          </div>

          <p class="pas">WhatsApp</p>
          <div class="nt-bulle nt-wa">
            <b class="t"><?= e($ap_titre) ?></b>
            <?= e($ap_corps) ?>
            <br><span class="lien"><?= e($ap_lib) ?></span>
          </div>

          <p class="aide" style="margin-top:16px">Le push et Telegram utilisent le message tel
          quel. Le modèle WhatsApp remplit ses variables dans cet ordre : titre, texte, lien.
          WhatsApp coupe aussi les retours à la ligne dans une variable.</p>
        </div>

        <div class="msg info" style="margin:0">
          <strong>Pousse partout, d'un seul geste.</strong>
          <p style="margin:.35em 0 0">Le message part aux canaux cochés, avec la même
          formulation et le même lien. Un essai peut partir sur le seul push, sur cet écran,
          avant de viser tout le monde.</p>
        </div>
      </div>
    </div>
  </form>

  <!-- ======= les derniers envois ======= -->
  <div style="height:18px"></div>
  <div class="carte plate">
    <h3 style="margin:0 0 4px">Derniers envois</h3>
    <?php if (!$historique): ?>
      <p style="color:var(--text2);margin:0">Rien pour l'instant : le premier envoi s'inscrira
      ici, canal par canal.</p>
    <?php else: ?>
      <table class="bord-table">
        <tr><th>Message</th><th>Canal</th><th class="bord-t-n">Touchés</th></tr>
        <?php foreach ($historique as $n): ?>
          <tr>
            <td><b><?= e((string) $n['titre']) ?></b></td>
            <td class="bord-age"><?= e($hist_canal($n)) ?></td>
            <td class="bord-age" style="text-align:right">
              <?= e(gmdate('d/m', strtotime((string) $n['cree_le']))) ?> ·
              <?= (int) $n['remises'] ?>/<?= (int) $n['abonnements'] ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
      <?php if ($pages > 1): ?>
        <div class="rangee" style="justify-content:center;margin-top:12px">
          <?php for ($i = max(1, $page_n - 2); $i <= min($pages, $page_n + 2); $i++): ?>
            <a class="bouton <?= $i === $page_n ? '' : 'fant' ?> petit"
               href="<?= e(url('?p=notification&n=' . $i)) ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
