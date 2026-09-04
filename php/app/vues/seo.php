<?php
/**
 * Le référencement, réglé et VÉRIFIÉ sur le même écran.
 *
 * L'aperçu de partage est en haut, avant les champs : c'est la seule chose
 * qu'on vient réellement vérifier, et la faire descendre sous quinze
 * champs revient à ne jamais la regarder.
 */
$erreur = $erreur ?? null;
$message = $message ?? null;
$v = $valeurs;
?>
<div class="contenu etroit-large">
  <section class="entete">
    <h1>Référencement</h1>
    <p>Ce que Google affiche dans ses résultats, et ce que WhatsApp montre quand
    on colle un lien. Les deux se règlent ici — et se vérifient juste en dessous.</p>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <?php if ($v['seo_indexable'] !== '1'): ?>
    <div class="msg err" style="margin-bottom:16px">
      <strong>L’indexation est coupée.</strong>
      <p style="margin:.35em 0 0">Chaque page demande aux moteurs de l’ignorer, et
      <code>robots.txt</code> interdit tout le site. C’est ce qu’il faut sur une installation
      d’essai — mais sur le vrai site, rien ne remontera jamais dans Google.</p>
    </div>
  <?php endif; ?>

  <!-- ---------- l'aperçu ---------- -->
  <div class="carte" style="margin-bottom:16px">
    <h3 style="margin:0 0 4px">Ce que voit WhatsApp</h3>
    <p class="aide" style="margin:0 0 14px">L’aperçu d’un lien vers l’accueil, tel qu’il
    apparaîtra dans une conversation. Chaque article et chaque décor porte le sien.</p>

    <div class="apercu-partage">
      <img src="<?= e($apercu['url']) ?>" alt="">
      <div class="apercu-texte">
        <b><?= e($v['seo_nom_site']) ?> — le badge qui remplit la salle</b>
        <span><?= e(mb_strimwidth($v['seo_description'], 0, 150, '…')) ?></span>
        <span class="apercu-hote"><?= e(parse_url(base_url(), PHP_URL_HOST) ?: base_url()) ?></span>
      </div>
    </div>
    <p class="aide" style="margin:12px 0 0">
      Image : <strong><?= (int) $apercu['largeur'] ?> × <?= (int) $apercu['hauteur'] ?></strong> px,
      <?= e($apercu['type']) ?>.
      <?php if ((int) $apercu['largeur'] < 1200): ?>
        <span style="color:var(--orange)">En dessous de 1200 px de large, la vignette est
        rognée sur certains téléphones.</span>
      <?php endif; ?>
    </p>
  </div>

  <!-- ---------- éprouver un lien précis ---------- -->
  <div class="carte" style="margin-bottom:16px">
    <h3 style="margin:0 0 4px">Éprouver un lien</h3>
    <p class="aide" style="margin:0 0 14px">Collez l’adresse d’un article, d’un décor ou de
    n’importe quelle page de ce site. On la lit <strong>comme le ferait WhatsApp</strong> :
    sans votre session, dans le HTML, et l’image est réellement téléchargée.</p>

    <div class="champ">
      <label for="t-url">Adresse à éprouver</label>
      <div class="rangee" style="gap:8px;align-items:stretch">
        <input id="t-url" type="url" placeholder="<?= e(base_url()) ?>/index.php?p=decor&amp;slug=…"
               style="flex:1" autocomplete="off">
        <button class="bouton" id="t-go" type="button">Éprouver</button>
      </div>
      <p class="aide">Seules les adresses de ce site sont acceptées.</p>
    </div>

    <div id="t-sortie" hidden style="margin-top:16px"></div>
  </div>

  <!-- ---------- ce que les moteurs vont chercher ---------- -->
  <div class="carte" style="margin-bottom:16px">
    <h3 style="margin:0 0 4px">Les deux fichiers que cherchent les moteurs</h3>
    <p class="aide" style="margin:0 0 14px">Ils sont <strong>engendrés</strong> par
    l’application : un fichier déposé sur le disque ne saurait pas écrire l’adresse de ce
    site, et le même paquet se décompresse sur des domaines différents.</p>

    <div class="tableau">
      <table>
        <thead><tr><th>Ce que cherche le robot</th><th>L’adresse qui marche toujours</th><th>Contenu</th></tr></thead>
        <tbody>
          <tr>
            <td><a class="mono" href="<?= e($robots_court) ?>" target="_blank" rel="noopener">/robots.txt</a></td>
            <td><a class="mono" href="<?= e($robots_php) ?>" target="_blank" rel="noopener">?p=robots</a></td>
            <td>Les règles de passage</td>
          </tr>
          <tr>
            <td><a class="mono" href="<?= e($plan_court) ?>" target="_blank" rel="noopener">/sitemap.xml</a></td>
            <td><a class="mono" href="<?= e($plan_php) ?>" target="_blank" rel="noopener">?p=sitemap</a></td>
            <td><strong><?= (int) $combien ?></strong> adresses listées</td>
          </tr>
        </tbody>
      </table>
    </div>

    <p class="aide" style="margin:12px 0 0">
      <?php if ($rewrite === false): ?>
        <strong style="color:var(--orange)">Cet hébergement n’a pas <code>mod_rewrite</code>.</strong>
        Les adresses courtes ne répondront pas : donnez la seconde colonne à Google Search
        Console, elle sert exactement le même contenu.
      <?php elseif ($rewrite === true): ?>
        <code>mod_rewrite</code> est actif : les deux colonnes répondent. Ouvrez la première
        pour vous en assurer.
      <?php else: ?>
        Les adresses courtes passent par <code>mod_rewrite</code>. Ouvrez la première colonne :
        si elle répond, tout va bien ; sinon, donnez la seconde aux moteurs.
      <?php endif; ?>
    </p>
  </div>

  <form method="post" action="<?= e(url('?p=reglages-seo')) ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

    <div class="carte">
      <h3 style="margin:0 0 14px">Le site</h3>

      <div class="grille g2">
        <div class="champ">
          <label for="s-nom">Nom du site</label>
          <input id="s-nom" name="seo_nom_site" type="text" maxlength="80"
                 value="<?= e($v['seo_nom_site']) ?>">
          <p class="aide">Il apparaît dans l’onglet du navigateur et sous chaque résultat.</p>
        </div>
        <div class="champ">
          <label for="s-org">Nom de la structure</label>
          <input id="s-org" name="seo_organisation" type="text" maxlength="80"
                 value="<?= e($v['seo_organisation']) ?>">
          <p class="aide">L’entité qui édite le site, pour les données structurées.</p>
        </div>
      </div>

      <div class="champ">
        <label for="s-desc">Description par défaut</label>
        <textarea id="s-desc" name="seo_description" rows="3" maxlength="300"><?= e($v['seo_description']) ?></textarea>
        <p class="aide">Deux phrases, 150 caractères environ. C’est le texte gris sous le titre
        dans Google, et sous le titre dans WhatsApp — <strong>pour les pages qui n’ont pas la
        leur</strong> : un article utilise son chapô, un décor son sous-titre.</p>
      </div>

      <div class="champ">
        <span class="champ-titre">Indexation</span>
        <label class="case" style="max-width:520px">
          <input type="checkbox" name="seo_indexable" value="1"
                 <?= $v['seo_indexable'] === '1' ? 'checked' : '' ?>>
          <span>Autoriser les moteurs à référencer ce site</span>
        </label>
        <p class="aide">À décocher sur une installation d’essai. Deux fois le même contenu sur
        deux adresses, et c’est parfois la copie qui l’emporte.</p>
      </div>
    </div>

    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 4px">L’image de partage</h3>
      <p class="aide" style="margin:0 0 14px">Celle des pages qui n’en ont pas : l’accueil, les
      listes. <strong>1200 × 630</strong> est le format que rendent le mieux WhatsApp, Facebook
      et LinkedIn. Vide, une carte est engendrée automatiquement.</p>

      <input type="hidden" name="seo_image" value="<?= e($v['seo_image']) ?>">
      <?php if ($v['seo_image']): ?>
        <img src="<?= e($v['seo_image']) ?>" alt=""
             style="max-width:300px;border-radius:var(--r10);display:block;margin-bottom:10px">
        <label class="case" style="max-width:320px;margin-bottom:10px">
          <input type="checkbox" name="effacer_image" value="1">
          <span>Revenir à la carte engendrée</span>
        </label>
      <?php endif; ?>
      <div class="champ">
        <label for="s-image">Choisir une image</label>
        <input id="s-image" name="image" type="file" accept="image/png,image/jpeg,image/webp">
      </div>
    </div>

    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 4px">La structure, telle que Google la comprend</h3>
      <p class="aide" style="margin:0 0 14px">Ces informations alimentent le panneau qui peut
      apparaître à droite des résultats. Un profil mort y vaut moins que pas de profil du tout.</p>

      <div class="grille g2">
        <div class="champ">
          <label for="s-tel">Téléphone</label>
          <input id="s-tel" name="seo_telephone" type="tel" maxlength="40"
                 placeholder="+228 90 00 00 00" value="<?= e($v['seo_telephone']) ?>">
        </div>
        <div class="champ">
          <label for="s-ville">Ville</label>
          <input id="s-ville" name="seo_ville" type="text" maxlength="60" value="<?= e($v['seo_ville']) ?>">
        </div>
        <div class="champ">
          <label for="s-pays">Pays <span style="font-weight:400">(code à deux lettres)</span></label>
          <input id="s-pays" name="seo_pays" type="text" maxlength="2" value="<?= e($v['seo_pays']) ?>">
        </div>
      </div>

      <div class="champ">
        <label for="s-reseaux">Profils sociaux</label>
        <textarea id="s-reseaux" name="seo_reseaux" rows="3"
                  placeholder="https://facebook.com/wakabi&#10;https://instagram.com/wakabi"><?= e($v['seo_reseaux']) ?></textarea>
        <p class="aide">Une adresse par ligne. Elles relient ce site à vos pages officielles.</p>
      </div>
    </div>

    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 4px">Vérification de propriété</h3>
      <p class="aide" style="margin:0 0 14px">Les codes que donnent Google Search Console et Bing
      Webmaster pour prouver que ce site est le vôtre. Collez <strong>le code seul</strong>,
      pas la balise entière.</p>

      <div class="grille g2">
        <div class="champ">
          <label for="s-google">Google Search Console</label>
          <input id="s-google" name="seo_verif_google" type="text" maxlength="120"
                 class="mono" value="<?= e($v['seo_verif_google']) ?>">
        </div>
        <div class="champ">
          <label for="s-bing">Bing Webmaster</label>
          <input id="s-bing" name="seo_verif_bing" type="text" maxlength="120"
                 class="mono" value="<?= e($v['seo_verif_bing']) ?>">
        </div>
      </div>

      <div class="rangee" style="margin-top:14px;gap:10px">
        <button class="bouton" type="submit">Enregistrer</button>
        <a class="bouton fant" href="<?= e(url('?p=reglages')) ?>">Les autres réglages</a>
      </div>
    </div>
  </form>
</div>

<script>
/**
 * L'épreuve d'un lien, faite DANS LE NAVIGATEUR et non sur le serveur.
 *
 * Le serveur ne peut pas s'interroger lui-même : sur un hébergement
 * mutualisé qui ne sert qu'une requête à la fois, il s'attendrait
 * lui-même et la page se figerait jusqu'au délai d'attente. Le
 * navigateur, lui, a déjà une connexion ouverte et n'a rien à attendre.
 *
 * C'est aussi la fidélité qui compte : on lit le HTML tel qu'il arrive,
 * et l'image est vraiment téléchargée puis mesurée — pas crue sur parole.
 */
(function () {
  var champ = document.getElementById('t-url');
  var bouton = document.getElementById('t-go');
  var sortie = document.getElementById('t-sortie');
  if (!champ || !bouton || !sortie) { return; }

  var BASE = <?= json_encode(base_url(), JSON_UNESCAPED_SLASHES) ?>;
  var MIN = <?= SEO_IMAGE_MIN ?>;

  function ligne(etat, texte) {
    var p = document.createElement('p');
    p.className = 'aide';
    p.style.margin = '0 0 6px';
    p.style.color = etat === 'mal' ? 'var(--rouge, #B91C1C)'
                  : etat === 'moyen' ? 'var(--orange, #C2410C)' : 'var(--text2)';
    p.textContent = (etat === 'bien' ? '✓ ' : etat === 'moyen' ? '! ' : '✗ ') + texte;
    return p;
  }

  function meta(doc, cle) {
    var b = doc.querySelector('meta[property="' + cle + '"], meta[name="' + cle + '"]');
    return b ? (b.getAttribute('content') || '') : '';
  }

  /** Les dimensions réelles, mesurées en chargeant l'image. */
  function mesurer(url) {
    return new Promise(function (res) {
      var i = new Image();
      i.onload = function () { res({ l: i.naturalWidth, h: i.naturalHeight }); };
      i.onerror = function () { res(null); };
      i.src = url;
    });
  }

  bouton.addEventListener('click', function () {
    var url = (champ.value || '').trim();
    sortie.hidden = false;
    sortie.textContent = '';

    if (url.indexOf(BASE) !== 0) {
      sortie.appendChild(ligne('mal', 'Cette adresse n’appartient pas à ce site. Elle doit '
        + 'commencer par ' + BASE));
      return;
    }

    sortie.appendChild(ligne('neutre', 'Lecture en cours…'));

    fetch(url, { credentials: 'omit', redirect: 'follow' }).then(function (r) {
      return r.text().then(function (t) { return { statut: r.status, html: t, fin: r.url }; });
    }).then(function (rep) {
      sortie.textContent = '';
      var doc = new DOMParser().parseFromString(rep.html, 'text/html');
      var titre = meta(doc, 'og:title') || (doc.querySelector('title') || {}).textContent || '';
      var desc = meta(doc, 'og:description');
      var img = meta(doc, 'og:image');
      var canon = (doc.querySelector('link[rel=canonical]') || { getAttribute: function () { return ''; } })
        .getAttribute('href') || '';

      /**
       * Le verdict d'abord, le détail ensuite. Une page qui répond 404 ne
       * montre AUCUN aperçu — ni image, ni titre, ni accroche : c'est le
       * cas qu'il faut nommer en premier, parce que tout le reste devient
       * sans objet.
       */
      if (rep.statut >= 400) {
        sortie.appendChild(ligne('mal', 'La page répond ' + rep.statut + '. Aucun aperçu ne '
          + 's’affichera — ni image, ni titre, ni accroche. C’est le cas d’un décor jamais '
          + 'publié, ou d’une adresse qui a changé.'));
        return;
      }

      if (canon && canon.replace(/&amp;/g, '&') !== rep.fin.replace(/&amp;/g, '&')) {
        sortie.appendChild(ligne('mal', 'Cette page se déclare canonique vers une AUTRE '
          + 'adresse : ' + canon + '. C’est l’aperçu de celle-là qui s’affichera, pas le sien.'));
      } else if (canon) {
        sortie.appendChild(ligne('bien', 'Elle se déclare canonique vers elle-même.'));
      } else {
        sortie.appendChild(ligne('moyen', 'Elle n’annonce pas d’adresse canonique.'));
      }

      sortie.appendChild(ligne(titre ? 'bien' : 'mal',
        titre ? 'Titre : ' + titre : 'Aucun titre de partage.'));
      sortie.appendChild(ligne(desc.length >= 50 ? 'bien' : desc ? 'moyen' : 'mal',
        desc ? 'Accroche (' + desc.length + ' car.) : ' + desc
             : 'Aucune accroche de partage.'));

      if (!img) {
        sortie.appendChild(ligne('mal', 'Aucune image annoncée : la vignette restera vide.'));
        return;
      }

      mesurer(img).then(function (d) {
        if (!d) {
          sortie.appendChild(ligne('mal', 'L’image annoncée ne se télécharge pas : ' + img
            + '. La vignette restera vide, et la messagerie gardera ce vide en cache.'));
          return;
        }
        sortie.appendChild(ligne(d.l >= MIN ? 'bien' : 'moyen',
          'Image téléchargée, ' + d.l + ' × ' + d.h + ' px'
          + (d.l >= MIN ? '.' : ' — en dessous de ' + MIN + ' px, la vignette est réduite '
            + 'à un timbre-poste.')));

        var carte = document.createElement('div');
        carte.className = 'apercu-partage';
        carte.style.marginTop = '12px';
        var vi = document.createElement('img');
        vi.src = img; vi.alt = '';
        var tx = document.createElement('div');
        tx.className = 'apercu-texte';
        var b = document.createElement('b'); b.textContent = titre;
        var s1 = document.createElement('span'); s1.textContent = desc;
        var s2 = document.createElement('span'); s2.className = 'apercu-hote';
        s2.textContent = location.host;
        tx.appendChild(b); tx.appendChild(s1); tx.appendChild(s2);
        carte.appendChild(vi); carte.appendChild(tx);
        sortie.appendChild(carte);
      });
    }).catch(function (e) {
      sortie.textContent = '';
      sortie.appendChild(ligne('mal', 'La page n’a pas pu être lue : ' + e.message));
    });
  });

  champ.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); bouton.click(); }
  });
})();
</script>
