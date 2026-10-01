<?php
/**
 * Les adresses publiques du site, et la forme qu'elles prennent.
 *
 * L'écran montre trois choses dans cet ordre, et l'ordre compte : ce qui
 * est en service aujourd'hui, ce qu'on obtiendrait, et ce que le
 * changement ne casse pas. La troisième est celle qu'on vient chercher :
 * personne ne touche aux adresses d'un site qui marche sans se demander
 * d'abord ce qui arrive aux liens déjà partagés.
 */
$erreur = $erreur ?? null;
$message = $message ?? null;
$racine = base_url() . '/';
?>
<div class="contenu">
  <section class="entete">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
      <div>
        <h1>Les adresses</h1>
        <p>La forme des adresses publiques du site. <code>/decors/soiree-blanche</code>
        se lit, se recopie depuis une affiche et se partage ; <code>?p=decor&amp;slug=…</code>
        marche partout, sans rien à configurer.</p>
      </div>
      <div class="rangee" style="gap:8px">
        <a class="bouton fant" href="<?= e(url('?p=reglages')) ?>">Les réglages</a>
        <a class="bouton fant" href="<?= e(url('?p=reglages-seo')) ?>">Référencement</a>
      </div>
    </div>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <div class="msg <?= $actifs ? 'ok' : '' ?>" style="margin-bottom:16px">
    <strong><?= $actifs ? 'Adresses lisibles en service' : 'Adresses simples en service' ?></strong>
    <p style="margin:.35em 0 0">
      <?php if ($actifs): ?>
        Vos pages s’écrivent <code><?= e($racine . $base_decors) ?></code>. La réécriture
        a répondu le <?= e(substr((string) $verifie, 0, 10)) ?>.
      <?php else: ?>
        Vos pages s’écrivent <code><?= e($racine) ?>index.php?p=decors</code>. C’est la
        forme qui ne dépend de rien : ni de <code>mod_rewrite</code>, ni du fichier
        <code>.htaccess</code>.
      <?php endif; ?>
    </p>
  </div>

  <form method="post" action="<?= e(url('?p=reglages-permaliens')) ?>">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

    <div class="carte">
      <h3 style="margin:0 0 4px">La forme</h3>
      <p class="aide" style="margin:0 0 16px">Le site comprend les deux, toujours.
      Ce choix décide seulement de celle qu’il ÉCRIT.</p>

      <div style="display:grid;gap:10px">
        <label class="case" style="font-weight:400;align-items:flex-start">
          <input type="radio" name="permaliens_forme" value="simple" data-forme
                 <?= $forme === 'lisible' ? '' : 'checked' ?>>
          <span style="white-space:normal">
            <b style="font-weight:600">Simple</b> : <code>index.php?p=blog&amp;a=mon-article</code><br>
            <span class="aide" style="white-space:normal">Marche sur n’importe quel hébergement, y compris ceux qui
            n’autorisent pas la réécriture d’adresses.</span>
          </span>
        </label>
        <label class="case" style="font-weight:400;align-items:flex-start">
          <input type="radio" name="permaliens_forme" value="lisible" data-forme
                 <?= $forme === 'lisible' ? 'checked' : '' ?>>
          <span style="white-space:normal">
            <b style="font-weight:600">Lisible</b> : <code>/blog/mon-article</code><br>
            <span class="aide" style="white-space:normal">Demande <code>mod_rewrite</code> et un <code>.htaccess</code>
            lu par le serveur. Les deux sont la règle chez LWS ; le bouton
            d’enregistrement le vérifie avant d’appliquer quoi que ce soit.</span>
          </span>
        </label>
      </div>
    </div>

    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 4px">Les deux préfixes</h3>
      <p class="aide" style="margin:0 0 16px">Ce qui précède le nom d’un article ou d’un
      décor. Ils doivent différer : avec le même mot, <code>/x/soiree-blanche</code>
      pourrait désigner l’un ou l’autre.</p>

      <div class="grille g2">
        <div class="champ">
          <label for="permaliens_blog">Préfixe des articles</label>
          <input id="permaliens_blog" name="permaliens_blog" type="text" autocomplete="off"
                 data-base="blog" placeholder="blog" value="<?= e($base_blog) ?>">
          <p class="aide"><code><?= e($racine) ?><span data-apercu-blog><?= e($base_blog) ?></span>/mon-article</code></p>
        </div>
        <div class="champ">
          <label for="permaliens_decors">Préfixe des décors</label>
          <input id="permaliens_decors" name="permaliens_decors" type="text" autocomplete="off"
                 data-base="decors" placeholder="decors" value="<?= e($base_decors) ?>">
          <p class="aide"><code><?= e($racine) ?><span data-apercu-decors><?= e($base_decors) ?></span>/soiree-blanche</code></p>
        </div>
      </div>

      <label class="case" style="font-weight:400;margin-top:14px">
        <input type="checkbox" name="permaliens_barre" value="1" data-barre
               <?= $barre ? 'checked' : '' ?>>
        <span style="white-space:normal">Terminer les adresses par une barre oblique
        (<code>/blog/mon-article/</code>)
        <span class="aide" style="display:block;white-space:normal">À cocher si le site remplace un WordPress
        qui écrivait ses adresses ainsi : les deux formes répondent de toute façon, mais
        celle qu’on écrit doit être celle que Google a déjà indexée.</span></span>
      </label>
    </div>

    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 4px">Ce que ça donne</h3>
      <p class="aide" style="margin:0 0 14px">Avant d’enregistrer, pas après : une adresse
      partie dans un groupe WhatsApp ne se rattrape pas.</p>
      <div class="sd-sante-liste" data-exemples>
        <?php foreach ($exemples as [$quoi, $adresse]): ?>
          <div class="sd-ct" style="align-items:flex-start">
            <span style="min-width:150px"><b style="font-weight:600"><?= e($quoi) ?></b></span>
            <span class="mono" style="word-break:break-all"><?= e($racine . $adresse) ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="rangee" style="margin-top:16px;gap:10px">
        <button class="bouton" type="submit" name="action" value="enregistrer">Enregistrer</button>
        <button class="bouton fant" type="submit" name="action" value="essai">Refaire l’essai</button>
      </div>
    </div>
  </form>

  <?php
  /**
   * La règle qui rend ce réglage sans danger, dite en clair.
   *
   * C'est l'information qui décide : sans elle, on n'ose pas toucher aux
   * adresses d'un site qui marche. Et c'est vrai — on peut le vérifier
   * dans `app/permaliens.php` : la résolution ne consulte aucun réglage.
   */
  ?>
  <div class="carte" style="margin-top:16px">
    <h3 style="margin:0 0 8px">Ce que le changement ne casse pas</h3>
    <ul style="margin:0;padding-left:1.1em;line-height:1.7">
      <li><strong>Les adresses simples continuent de répondre</strong>, pour toujours. Elles
      redirigent vers la forme lisible, de façon permanente, ce qui transmet aussi à Google
      ce que l’ancienne adresse avait gagné.</li>
      <li><strong>Les adresses lisibles aussi</strong>, même après un retour à la forme
      simple. Le site les comprend quel que soit ce réglage : c’est pour cela que revenir en
      arrière ne casse rien de ce qui est déjà parti.</li>
      <li><strong>Les liens courts ne changent pas.</strong> Un code à six caractères reste
      un code à six caractères, et les affiches déjà imprimées marchent.</li>
      <li><strong>Les QR déjà dans des badges ne changent pas</strong> non plus : ils portent
      l’adresse qu’ils portaient le jour où ils ont été fabriqués, et elle répond.</li>
      <li>L’espace de travail garde la forme simple, volontairement. Il ne se partage pas,
      ne s’imprime pas, et chaque adresse réécrite est une occasion de casser un formulaire.</li>
    </ul>
  </div>

  <?php if ($essai !== null && !$essai['ok']): ?>
    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 4px">Le bloc à vérifier dans <code>.htaccess</code></h3>
      <p class="aide" style="margin:0 0 12px">Le fichier est livré avec ce bloc. S’il a été
      remplacé (un autre outil, une restauration, un transfert FTP partiel), recollez-le à
      la fin du <code>.htaccess</code> qui se trouve à côté de <code>index.php</code>, puis
      cliquez « Refaire l’essai ».</p>
      <pre class="mono" style="margin:0;padding:14px;border-radius:10px;overflow:auto;
        background:var(--bg2);border:1px solid var(--border);
        font-size:.82rem;line-height:1.6"><?= e($bloc) ?></pre>
      <p class="aide" style="margin:12px 0 0">L’essai a demandé
      <a href="<?= e($essai['adresse']) ?>" target="_blank" rel="noopener"><code><?= e($essai['adresse']) ?></code></a>
      et reçu <?= $essai['code'] === 0 ? 'aucune réponse' : 'un code ' . (int) $essai['code'] ?>.
      <?php if ($essai['code'] === 0): ?>
        Ouvrez cette adresse vous-même : si elle affiche
        <code><?= e(PERMALIENS_ESSAI_JETON) ?></code>, revenez cliquer « Refaire l’essai »,
        et cela suffira.
      <?php endif; ?></p>
    </div>
  <?php endif; ?>
</div>

<?php
/**
 * L'aperçu qui suit la frappe.
 *
 * Tout l'intérêt de l'écran est de VOIR les adresses avant de les
 * appliquer ; les voir après avoir enregistré serait les découvrir. Le
 * serveur a déjà rendu la liste juste, donc sans JavaScript l'écran reste
 * complet — ce script ne fait que la suivre pendant qu'on tape.
 */
?>
<script>
(function () {
  var racine = <?= json_encode($racine, JSON_UNESCAPED_SLASHES) ?>;
  var zone = document.querySelector('[data-exemples]');
  var blog = document.querySelector('[data-base="blog"]');
  var decors = document.querySelector('[data-base="decors"]');
  var barre = document.querySelector('[data-barre]');
  var formes = document.querySelectorAll('[data-forme]');
  if (!zone || !blog || !decors) { return; }

  /* La même épuration que `permaliens_base()` côté serveur. Deux
     implémentations, oui — mais celle-ci ne décide de rien : elle montre.
     Si elles divergeaient, c'est l'aperçu qui aurait tort, et
     l'enregistrement qui ferait foi. */
  var propre = function (v, defaut) {
    var s = (v || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    return s || defaut;
  };

  var dessiner = function () {
    var lisible = false;
    Array.prototype.forEach.call(formes, function (f) {
      if (f.checked && f.value === 'lisible') { lisible = true; }
    });
    var b = propre(blog.value, 'blog');
    var d = propre(decors.value, 'decors');
    var fin = (barre && barre.checked) ? '/' : '';

    var lignes = lisible
      ? [['Le catalogue', d + fin], ['Un décor', d + '/soiree-blanche-lome' + fin],
         ['Le blog', b + fin], ['Un article', b + '/remplir-une-salle-a-lome' + fin],
         ['Devenir partenaire', 'partenaires' + fin]]
      : [['Le catalogue', 'index.php?p=decors'],
         ['Un décor', 'index.php?p=decor&slug=soiree-blanche-lome'],
         ['Le blog', 'index.php?p=blog'],
         ['Un article', 'index.php?p=blog&a=remplir-une-salle-a-lome'],
         ['Devenir partenaire', 'index.php?p=partenaires']];

    zone.textContent = '';
    lignes.forEach(function (l) {
      var ligne = document.createElement('div');
      ligne.className = 'sd-ct';
      ligne.style.alignItems = 'flex-start';
      var quoi = document.createElement('span');
      quoi.style.minWidth = '150px';
      var gras = document.createElement('b');
      gras.style.fontWeight = '600';
      gras.textContent = l[0];
      quoi.appendChild(gras);
      var adresse = document.createElement('span');
      adresse.className = 'mono';
      adresse.style.wordBreak = 'break-all';
      adresse.textContent = racine + l[1];
      ligne.appendChild(quoi);
      ligne.appendChild(adresse);
      zone.appendChild(ligne);
    });

    var ab = document.querySelector('[data-apercu-blog]');
    var ad = document.querySelector('[data-apercu-decors]');
    if (ab) { ab.textContent = b; }
    if (ad) { ad.textContent = d; }
  };

  [blog, decors].forEach(function (c) { c.addEventListener('input', dessiner); });
  if (barre) { barre.addEventListener('change', dessiner); }
  Array.prototype.forEach.call(formes, function (f) { f.addEventListener('change', dessiner); });
})();
</script>
