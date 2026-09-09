<?php
/**
 * L'atelier d'écriture : un texte, les canaux qui le portent, et l'aperçu
 * de ce que chacun recevra.
 *
 * Trois décisions font tout l'écran :
 *
 *  1. **La portée s'affiche avant de cocher.** Une carte par canal, avec
 *     le nombre de personnes qu'elle touche. Découvrir après l'envoi
 *     qu'une case cochée valait quatre mille messages n'est pas une
 *     information : c'est une facture.
 *
 *  2. **L'aperçu est à droite, collé, et il suit la frappe.** Le même
 *     texte ne se présente pas pareil dans une bulle Telegram, sur un
 *     écran verrouillé et dans une boîte de réception — et c'est
 *     précisément là que se joue la coupure à cent vingt caractères.
 *
 *  3. **Rien n'est masqué derrière un onglet.** Les trois étapes en tête
 *     résument leur état et mènent à leur section ; la page reste une
 *     seule page, et un seul enregistrement.
 */
$erreur = $erreur ?? null;
$portees = $portees ?? [];
$apercu_push = $apercu_push ?? 120;
$coches = $canaux_coches ?? ['email'];
$choix_canaux = $choix_canaux ?? [];

$a_whatsapp = false;
foreach ($choix_canaux as $ch) {
    if ($ch['genre'] === 'whatsapp' && in_array($ch['cle'], $coches, true)) {
        $a_whatsapp = true;
    }
}

/**
 * L'heure d'envoi, ramenée dans le fuseau de celui qui écrit.
 *
 * Le navigateur nous donne son décalage à l'enregistrement : c'est la
 * seule façon qu'un « 19 h » saisi à Lomé parte à 19 h à Lomé.
 */
$_quand = '';
if (!empty($valeurs['planifie_le'])) {
    $_t = strtotime((string) $valeurs['planifie_le']);
    $_quand = $_t !== false ? gmdate('Y-m-d\TH:i', $_t) : '';
}

$sigles = ['email' => '@', 'push' => 'WEB', 'telegram' => 'TG', 'whatsapp' => 'WA'];
$fonds  = ['email' => 'cx-ml', 'push' => 'cx-web', 'telegram' => 'cx-telegram', 'whatsapp' => 'cx-whatsapp'];
$points = ['email' => '#0F172A', 'push' => '#2563EB', 'telegram' => '#229ED9', 'whatsapp' => '#25D366'];
?>
<div class="contenu">
  <p class="fil"><a href="<?= e(url('?p=regie')) ?>">← La régie</a></p>

  <section class="entete" style="padding-bottom:12px">
    <h1><?= $existante ? 'Modifier le message' : 'Nouveau message' ?></h1>
    <p>Un texte, les canaux qui le portent, et l’aperçu de ce que chacun recevra.</p>
  </section>

  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <div class="ec-etapes">
    <a class="ec-etape actif" href="#et-message"><i>1</i><b>Le message<span id="rs-message">Titre, texte, lien</span></b></a>
    <a class="ec-etape" href="#et-canaux"><i>2</i><b>Les canaux<span id="rs-canaux">—</span></b></a>
    <a class="ec-etape" href="#et-qui"><i>3</i><b>Qui et quand<span id="rs-qui">—</span></b></a>
  </div>

  <form method="post" action="<?= e(url('?p=regie-ecrire' . ($existante ? '&id=' . urlencode((string) $existante['id']) : ''))) ?>">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

    <div class="ec">
      <!-- ================= à gauche : ce qu'on règle ================= -->
      <div style="display:flex;flex-direction:column;gap:16px">

        <div class="carte" id="et-message">
          <h3 style="margin:0 0 4px">Le message</h3>
          <p class="aide" style="margin:0 0 16px">Le même texte part sur chaque canal coché, mis en
          forme selon ce que le canal sait afficher. Une ligne vide sépare deux paragraphes.</p>

          <div class="champ">
            <label for="r-titre">Titre</label>
            <input id="r-titre" name="titre" type="text" required maxlength="120"
                   placeholder="On remet ça samedi" value="<?= e($valeurs['titre']) ?>">
            <p class="aide" id="c-titre">—</p>
          </div>

          <div class="champ">
            <label for="r-corps">Message</label>
            <textarea id="r-corps" name="corps" rows="8" required style="line-height:1.55"
                      placeholder="Rendez-vous ce samedi au Palais des Congrès. Votre badge suffit à l’entrée."><?= e($valeurs['corps']) ?></textarea>
            <p class="aide" id="c-corps">—</p>
          </div>

          <div class="grille g2">
            <div class="champ">
              <label for="r-lien">Lien <span style="font-weight:400">(facultatif)</span></label>
              <input id="r-lien" name="lien" type="url" placeholder="<?= e(base_url() . '/index.php?p=decors') ?>"
                     value="<?= e($valeurs['lien']) ?>">
              <?php if (!$equipe): ?>
                <p class="aide">Une adresse <?= e(implode(' ou ', WAKABI_DOMAINES)) ?>.</p>
              <?php endif; ?>
            </div>
            <div class="champ">
              <label for="r-libelle">Libellé du bouton</label>
              <input id="r-libelle" name="lien_libelle" type="text" maxlength="40"
                     placeholder="Voir mon badge" value="<?= e($valeurs['lien_libelle']) ?>">
            </div>
          </div>

          <div class="champ" style="margin-bottom:0">
            <label for="r-sujet">Objet de l’e-mail <span style="font-weight:400">(120 caractères)</span></label>
            <input id="r-sujet" name="sujet" type="text" required maxlength="120"
                   placeholder="Votre badge vous ouvre la soirée de samedi"
                   value="<?= e($valeurs['sujet']) ?>">
            <p class="aide">Ce que la personne lit avant d’ouvrir. Les autres canaux n’en ont pas :
            ils affichent le titre.</p>
          </div>
        </div>

        <div class="carte" id="et-canaux">
          <h3 style="margin:0 0 4px">Les canaux</h3>
          <p class="aide" style="margin:0 0 14px">La portée s’affiche avant de cocher, pas après avoir
          envoyé. <a href="<?= e(url('?p=canaux')) ?>">Brancher un canal</a>.</p>

          <div class="ec-canaux">
            <?php foreach ($choix_canaux as $ch):
              $ici = in_array($ch['cle'], $coches, true);
              $g = (string) $ch['genre']; ?>
              <label class="ec-cn" data-cle="<?= e($ch['cle']) ?>" data-genre="<?= e($g) ?>"
                     data-n="<?= (int) ($ch['n'] ?? 0) ?>" data-envois="<?= (int) ($ch['envois'] ?? 0) ?>"
                     data-payant="<?= !empty($ch['payant']) ? '1' : '0' ?>"
                     data-suit="<?= e((string) ($ch['suit'] ?? '')) ?>">
                <input type="checkbox" name="canaux[]" value="<?= e($ch['cle']) ?>" <?= $ici ? 'checked' : '' ?>>
                <div class="ec-cn-h">
                  <span class="cx-rond ec-rond <?= e($fonds[$g] ?? '') ?>"><?= e($sigles[$g] ?? '·') ?></span>
                  <b><?= e($ch['libelle']) ?><span><?= e((string) ($ch['sous'] ?? '')) ?></span></b>
                  <span class="ec-coche">✓</span>
                </div>
                <div class="ec-cn-n"><b class="nb"><?= number_format((int) ($ch['n'] ?? 0), 0, ',', ' ') ?></b>
                  <small><?= e((string) ($ch['unite'] ?? 'personnes')) ?></small></div>
                <div class="ec-cn-note<?= !empty($ch['ton']) ? ' ' . e((string) $ch['ton']) : '' ?>">
                  <span class="txt"><?= e((string) ($ch['note'] ?? $ch['aide'])) ?></span>
                  <span class="ecartes"<?= empty($ch['ecartes']) ? ' hidden' : '' ?>>
                    <?= (int) ($ch['ecartes'] ?? 0) ?> adresse(s) non confirmée(s) sont écartées.
                  </span>
                </div>
              </label>
            <?php endforeach; ?>
          </div>

          <div class="champ" id="bloc-wa" style="margin:14px 0 0" <?= $a_whatsapp ? '' : 'hidden' ?>>
            <label for="modele-wa">Modèle WhatsApp approuvé</label>
            <input id="modele-wa" name="modele_whatsapp" type="text"
                   value="<?= e($modele_whatsapp ?? '') ?>" placeholder="rappel_evenement_fr">
            <p class="aide">Le nom exact du modèle, tel qu’il figure dans votre compte Meta. Hors des
            vingt-quatre heures qui suivent un message du destinataire — c’est-à-dire toujours, pour un
            rappel — Meta refuse le texte libre : votre titre, votre message et votre lien remplissent
            les variables du modèle, dans cet ordre.</p>
          </div>
        </div>

        <div class="carte" id="et-qui">
          <h3 style="margin:0 0 4px">Qui, et quand</h3>
          <p class="aide" style="margin:0 0 16px">La cible commande l’e-mail et les notifications ;
          les chaînes et les groupes, eux, ont leurs propres abonnés.</p>

          <div class="champ">
            <label for="r-cible">Cible</label>
            <select id="r-cible" name="cible">
              <?php foreach ($cibles as $cle => $lib): ?>
                <option value="<?= e($cle) ?>" <?= $valeurs['cible'] === $cle ? 'selected' : '' ?>><?= e($lib) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (!$equipe): ?>
              <p class="aide">« Mes invités » : les gens qui ont créé un badge sur vos campagnes
              <em>et</em> qui ont un compte. La base du guide, elle, ne se loue pas.</p>
            <?php endif; ?>
          </div>

          <div id="bloc-liste" <?= $valeurs['cible'] === 'liste' ? '' : 'hidden' ?>>
            <?php $choisie = $valeurs['liste_id']; ?>
            <div class="champ">
              <label for="r-liste-id">Quelle liste</label>
              <select id="r-liste-id" name="liste_id">
                <?php foreach ($listes as $li): ?>
                  <option value="<?= e((string) $li['id']) ?>" <?= $choisie === $li['id'] ? 'selected' : '' ?>>
                    <?= e((string) $li['nom']) ?> (<?= (int) $li['actifs'] ?> adresse<?= $li['actifs'] > 1 ? 's' : '' ?>)
                  </option>
                <?php endforeach; ?>
                <option value="nouvelle" <?= $choisie === '' ? 'selected' : '' ?>>— Une nouvelle liste —</option>
              </select>
              <?php if ($listes): ?>
                <p class="aide">Vos listes se gèrent dans le
                <a href="<?= e(url('?p=regie-carnet')) ?>">carnet d’adresses</a> : y corriger un nom, en sortir
                quelqu’un ou archiver une adresse morte vaut pour toutes vos campagnes.</p>
              <?php endif; ?>
            </div>

            <div class="champ" id="bloc-liste-nom" <?= $choisie === '' ? '' : 'hidden' ?>>
              <label for="r-liste-nom">Nom de la nouvelle liste</label>
              <input id="r-liste-nom" name="nouveau_nom" type="text" maxlength="120"
                     placeholder="Invités du Gala 2026">
              <p class="aide">Laissé vide, elle prendra l’objet de la campagne.</p>
            </div>

            <div class="champ">
              <label for="r-liste">Ajouter des adresses <span style="font-weight:400">(facultatif si la liste en contient déjà)</span></label>
              <textarea id="r-liste" name="liste" rows="5"
                        style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace"
                        placeholder="ama@exemple.tg&#10;Kossi Mensah &lt;kossi@exemple.tg&gt;"><?= e($valeurs['liste']) ?></textarea>
              <p class="aide"><strong>Elles sont enregistrées dans votre carnet</strong>, pas seulement
              utilisées une fois. Le format <code>Nom &lt;adresse&gt;</code> est accepté ; une adresse
              déjà connue n’est pas dupliquée. <strong>Une liste du carnet n’est pas soumise à la
              confirmation d’adresse</strong> : ces adresses sont les vôtres, vous les avez apportées.</p>
            </div>
          </div>

          <div class="champ" style="margin-bottom:0">
            <label for="r-quand">Date et heure d’envoi <span style="font-weight:400">(facultatif)</span></label>
            <input id="r-quand" name="planifie_le" type="datetime-local" value="<?= e($_quand) ?>">
            <input type="hidden" name="decalage" id="r-decalage" value="0">
            <p class="aide">Laissé vide, le message part dès qu’il est prêt. Avec une date, il attend
            son heure — la tâche automatique l’ouvre toute seule, à l’heure de votre fuseau.</p>
          </div>
        </div>
      </div>

      <!-- ================= à droite : ce que ça donne ================= -->
      <div class="ec-apercu" style="position:sticky;top:78px;display:flex;flex-direction:column;gap:14px">

        <div class="carte" style="padding:14px">
          <div class="ap-onglets" id="ap-onglets">
            <button type="button" class="ap-o actif" data-ap="tg">Telegram</button>
            <button type="button" class="ap-o" data-ap="ml">E-mail</button>
            <button type="button" class="ap-o" data-ap="wa">WhatsApp</button>
          </div>

          <div data-vue="tg">
            <div class="tel">
              <div class="bulle">
                <b id="ap-tg-titre">—</b><br>
                <span id="ap-tg-corps"></span>
                <span class="bt" id="ap-tg-bt" hidden>Ouvrir</span>
                <div class="h"><?= e(gmdate('H:i')) ?></div>
              </div>
            </div>
            <p class="aide" style="margin:9px 0 0">Le lien devient un bouton, plus grand sous le pouce.
            Telegram accepte 4 096 caractères : ce n’est pas lui qui vous limite.</p>
          </div>

          <div data-vue="ml" hidden>
            <div class="ml-p">
              <div class="ml-c">
                <div class="ml-t">WAKABI BOOST</div>
                <div class="ml-b">
                  <h4 id="ap-ml-titre">—</h4>
                  <div id="ap-ml-corps"></div>
                  <span class="ml-bt" id="ap-ml-bt" hidden>Ouvrir</span>
                </div>
                <div class="ml-f">Vous recevez ce message parce que vous avez créé un badge.
                Se désabonner — ajouté automatiquement, il ne se retire pas.</div>
              </div>
            </div>
            <p class="aide" style="margin:9px 0 0">L’objet, lui, se lit avant l’ouverture :
            <b id="ap-ml-sujet">—</b></p>
          </div>

          <div data-vue="wa" hidden>
            <div class="wa-p">
              <div class="wa-c">
                <b id="ap-wa-titre">—</b><br>
                <span id="ap-wa-corps"></span>
                <div id="ap-wa-lien" style="margin-top:6px;color:#027EB5" hidden></div>
                <div class="h"><?= e(gmdate('H:i')) ?> ✓✓</div>
              </div>
            </div>
            <p class="aide" style="margin:9px 0 0">Meta n’envoie pas ce texte tel quel : votre titre,
            votre message et votre lien remplissent les variables du modèle approuvé, dans cet ordre.
            Les retours à la ligne y sont interdits.</p>
          </div>
        </div>

        <div class="carte" style="padding:14px">
          <p class="aide" style="margin:0 0 8px"><b style="color:var(--text)">Et sur un téléphone
          verrouillé :</b></p>
          <div class="notif">
            <div class="notif-c">
              <div class="src"><i></i> <?= e(parse_url(base_url(), PHP_URL_HOST) ?: 'wakabi') ?> · maintenant</div>
              <b id="ap-web-titre">—</b>
              <span id="ap-web-corps"></span><span class="coupe" id="ap-web-coupe" hidden>…</span>
            </div>
          </div>
          <p class="aide" style="margin:9px 0 0">C’est là que les <?= (int) $apercu_push ?> caractères se
          voient : l’essentiel doit tenir avant la coupure.</p>
        </div>

        <div class="carte" style="padding:16px">
          <h3 style="margin:0 0 10px;font-size:.95rem">Ce que ce message touche</h3>
          <div class="pt" id="pt-lignes"></div>
          <button class="bouton" type="submit" style="width:100%;justify-content:center;margin-top:14px">
            <?= $existante ? 'Enregistrer les changements' : 'Enregistrer' ?>
          </button>
          <p class="aide" style="margin:8px 0 0;text-align:center">
            <?= $equipe ? 'Vous relirez et déclencherez l’envoi depuis la fiche.'
                        : 'Rien ne part avant la relecture de l’équipe.' ?>
          </p>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
/**
 * L'écran d'écriture, côté navigateur.
 *
 * Il ne calcule rien que le serveur ne sache déjà : les portées lui sont
 * données pour TOUTES les cibles au chargement, et il se contente de
 * choisir la bonne. C'est ce qui permet de voir la portée changer en
 * même temps que la cible, sans aller-retour et sans écran d'attente.
 */
(function () {
  var PORTEES = <?= json_encode($portees, JSON_UNESCAPED_UNICODE) ?>;
  var COUPE = <?= (int) $apercu_push ?>;
  var POINTS = <?= json_encode($points, JSON_UNESCAPED_UNICODE) ?>;

  var $ = function (id) { return document.getElementById(id); };
  var titre = $('r-titre'), corps = $('r-corps'), lien = $('r-lien'),
      libelle = $('r-libelle'), sujet = $('r-sujet'),
      cible = $('r-cible'), listeId = $('r-liste-id');
  var cartes = [].slice.call(document.querySelectorAll('.ec-cn'));

  var nb = function (n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' '); };
  var txt = function (s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

  /* Le décalage du navigateur, pour que l'heure saisie soit l'heure vécue.
     `getTimezoneOffset` rend l'inverse de ce qu'on attend : d'où le signe. */
  if ($('r-decalage')) { $('r-decalage').value = String(new Date().getTimezoneOffset()); }

  /* ---------- la cible du moment, et ce qu'elle pèse ---------- */
  function portee() {
    var c = cible ? cible.value : '';
    var cle = c === 'liste' && listeId ? 'liste:' + listeId.value : c;
    return PORTEES[cle] || { n: 0, ecartes: 0, push: 0 };
  }

  function majCartes() {
    var p = portee();
    cartes.forEach(function (k) {
      var suit = k.getAttribute('data-suit');
      if (suit === 'n') {
        k.setAttribute('data-n', p.n);
        k.setAttribute('data-envois', p.n);
      } else if (suit === 'push') {
        k.setAttribute('data-n', p.push);
      }
      if (suit) {
        k.querySelector('.nb').textContent = nb(k.getAttribute('data-n'));
        var ec = k.querySelector('.ecartes');
        if (ec && suit === 'n') {
          ec.hidden = !p.ecartes;
          ec.textContent = p.ecartes + ' adresse(s) non confirmée(s) sont écartées.';
        }
      }
    });
  }

  /* ---------- la portée, canal par canal ---------- */
  /* Le nom du canal, sans le sous-titre qui suit dans le même <b>. */
  function nomDe(k) {
    var b = k.querySelector('.ec-cn-h b');
    var t = b && b.firstChild && b.firstChild.nodeType === 3 ? b.firstChild.nodeValue : '';
    return (t || '').trim() || 'Ce canal';
  }

  function majPortee() {
    var envois = 0, gens = 0, payants = 0, coches = 0, html = '';
    cartes.forEach(function (k) {
      // `.on` double `:has(input:checked)` pour les navigateurs qui ne
      // connaissent pas encore le second.
      k.classList.toggle('on', k.querySelector('input').checked);
      if (!k.querySelector('input').checked) { return; }
      coches++;
      var n = parseInt(k.getAttribute('data-n'), 10) || 0;
      var e = parseInt(k.getAttribute('data-envois'), 10) || 0;
      envois += e; gens += n;
      if (k.getAttribute('data-payant') === '1') { payants += e; }
      html += '<div class="pt-l"><i style="background:' +
              (POINTS[k.getAttribute('data-genre')] || '#94A3B8') + '"></i> ' +
              txt(nomDe(k)) +
              '<b>' + nb(e) + '</b></div>';
    });
    if (!coches) {
      html = '<div class="pt-l"><span class="aide" style="margin:0">Aucun canal coché : ' +
             'le message partira par e-mail.</span></div>';
    }
    html += '<div class="pt-l pt-tot"><span>Envois</span><b>' + nb(envois) + '</b></div>' +
            '<div class="pt-l"><span class="aide" style="margin:0">Personnes touchées</span><b>' + nb(gens) + '</b></div>' +
            '<div class="pt-l"><span class="aide" style="margin:0">Coût</span><b style="color:' +
            (payants ? '#B91C1C' : '#166534') + '">' +
            (payants ? nb(payants) + ' message(s) facturés par Meta' : 'Gratuit') + '</b></div>';
    if (gens > envois) {
      html += '<p class="aide" style="margin:8px 0 0">« Personnes » compte au plus : quelqu’un ' +
              'présent sur une chaîne <em>et</em> abonné au bot y figure deux fois.</p>';
    }
    $('pt-lignes').innerHTML = html;
    $('rs-canaux').textContent = coches + ' coché' + (coches > 1 ? 's' : '') + ' · ' + nb(gens) + ' personnes';

    var wa = cartes.some(function (k) {
      return k.getAttribute('data-genre') === 'whatsapp' && k.querySelector('input').checked;
    });
    $('bloc-wa').hidden = !wa;
  }

  /* ---------- l'aperçu, et les compteurs ---------- */
  /**
   * Un aperçu vide n'apprend rien.
   *
   * Tant que le champ n'est pas rempli, on montre son exemple, en plus
   * pâle : la personne voit à quoi ressemblera sa bulle avant d'avoir
   * tapé un mot, au lieu d'un rectangle noir qui a l'air cassé.
   */
  function vu(ch) { return ch.value.trim() || ch.placeholder || ''; }

  function majApercu() {
    var vide = !titre.value.trim() && !corps.value.trim();
    var t = vu(titre), c = vu(corps),
        l = lien.value.trim(), lb = (libelle.value.trim() || 'Voir mon badge');
    document.querySelector('.ec-apercu').classList.toggle('exemple', vide);

    $('ap-tg-titre').textContent = t || '—';
    $('ap-tg-corps').innerHTML = txt(c).replace(/\n/g, '<br>');
    $('ap-tg-bt').hidden = !l && !vide;
    $('ap-tg-bt').textContent = lb;

    $('ap-web-titre').textContent = t || '—';
    $('ap-web-corps').textContent = c.slice(0, COUPE);
    $('ap-web-coupe').hidden = c.length <= COUPE;

    $('ap-ml-titre').textContent = t || '—';
    $('ap-ml-corps').innerHTML = c.split(/\n{2,}/).map(function (p) {
      return '<p>' + txt(p).replace(/\n/g, '<br>') + '</p>';
    }).join('');
    $('ap-ml-bt').hidden = !l && !vide;
    $('ap-ml-bt').textContent = lb;
    $('ap-ml-sujet').textContent = vu(sujet) || '—';

    $('ap-wa-titre').textContent = t || '—';
    $('ap-wa-corps').textContent = c.replace(/\s+/g, ' ');
    $('ap-wa-lien').hidden = !l;
    $('ap-wa-lien').textContent = l;


    var nt = titre.value.trim().length, nc = corps.value.trim().length;
    $('c-titre').textContent = nt + ' caractères. Sur une notification, on ne voit '
      + 'que les premiers — mettez l’essentiel devant.';
    $('c-corps').innerHTML = nc + ' caractères · '
      + (nc > COUPE ? '<span style="color:#C2410C">la notification coupera à ' + COUPE + '</span>'
                    : 'tient entier sur une notification')
      + ' · Telegram en accepte 4 096, un modèle WhatsApp 1 024.';
    $('rs-message').textContent = nt ? (nt + ' + ' + nc + ' caractères') : 'Titre, texte, lien';
  }

  function majQui() {
    var quand = $('r-quand').value;
    var ou = cible.options[cible.selectedIndex] ? cible.options[cible.selectedIndex].text : '';
    $('rs-qui').textContent = ou + ' · ' + (quand ? quand.replace('T', ' à ') : 'tout de suite');
    $('bloc-liste').hidden = cible.value !== 'liste';
    if (listeId) { $('bloc-liste-nom').hidden = listeId.value !== 'nouvelle'; }
  }

  /* ---------- les onglets de l'aperçu ---------- */
  document.getElementById('ap-onglets').addEventListener('click', function (ev) {
    var b = ev.target.closest('.ap-o');
    if (!b) { return; }
    [].forEach.call(this.querySelectorAll('.ap-o'), function (o) { o.classList.remove('actif'); });
    b.classList.add('actif');
    [].forEach.call(document.querySelectorAll('[data-vue]'), function (v) {
      v.hidden = v.getAttribute('data-vue') !== b.getAttribute('data-ap');
    });
  });

  [titre, corps, lien, libelle, sujet].forEach(function (ch) {
    if (ch) { ch.addEventListener('input', majApercu); }
  });
  cartes.forEach(function (k) {
    k.querySelector('input').addEventListener('change', majPortee);
  });
  [cible, listeId, $('r-quand')].forEach(function (ch) {
    if (ch) {
      ch.addEventListener('change', function () { majQui(); majCartes(); majPortee(); });
    }
  });

  /**
   * L'étape « en cours » suit le champ où l'on travaille.
   *
   * Pas une navigation : les trois sections restent toutes visibles. Le
   * repère sert seulement à savoir où l'on en est dans une page longue —
   * et à ce que le résumé qu'on lit en tête soit celui qu'on modifie.
   */
  var sections = ['et-message', 'et-canaux', 'et-qui'];
  var etapes = [].slice.call(document.querySelectorAll('.ec-etape'));
  function ici(i) {
    etapes.forEach(function (e, k) { e.classList.toggle('actif', k === i); });
  }
  sections.forEach(function (id, i) {
    var s = $(id);
    if (s) {
      s.addEventListener('focusin', function () { ici(i); });
      s.addEventListener('click', function () { ici(i); });
    }
  });

  majCartes(); majPortee(); majApercu(); majQui();
})();
</script>
