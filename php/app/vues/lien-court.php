<?php
/**
 * Raccourcir un lien, sans compte.
 *
 * Un seul formulaire, court, et le prix annoncé avant le premier champ.
 * L'adresse courte montrée en bas n'est PAS le vrai code : il se tire au
 * moment de la création. C'est un exemple, et l'écran le dit, parce
 * qu'afficher « wkb.tg/Gk7mQa » comme un fait donnerait une adresse que
 * personne ne pourra jamais utiliser.
 */
$fr = static fn(int $n): string => number_format($n, 0, ',', ' ');
?>
<div class="etroit" style="max-width:560px">

  <section class="entete centre">
    <h1>Raccourcir un lien</h1>
    <p>L’adresse peut mener n’importe où : votre billetterie, votre page Facebook,
    une fiche Wakabi. Le lien, lui, reste court et se compte.</p>
  </section>

  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <div class="carte">

    <?php if ($porte === null): ?>
      <div class="msg info" style="margin:0 0 16px">
        <strong>Les liens courts ne sont proposés par aucune offre pour l’instant.</strong>
        Composez le vôtre : il sera créé dès qu’une offre en comprendra.
      </div>
    <?php elseif ((int) $porte['prix'] === 0): ?>
      <div class="msg ok" style="margin:0 0 16px">
        <strong>Les liens courts sont compris dans l’offre <?= e((string) $porte['nom']) ?>, gratuite.</strong>
        Créez votre compte et le lien part avec.
      </div>
    <?php else: ?>
      <?php
      /**
       * Le prix AVANT le premier champ, et non après le bouton.
       *
       * L'offre gratuite ne donne aucun lien court : quelqu'un qui remplit
       * ce formulaire puis crée un compte n'aurait toujours pas son lien.
       * Le dire ici est la seule façon que ça ne se vive pas comme un
       * piège ; le dire après serait exactement le piège.
       */
      ?>
      <div class="msg attention" style="margin:0 0 16px">
        <strong>Les liens courts font partie de l’offre <?= e((string) $porte['nom']) ?>.</strong>
        Composez le vôtre ici : il sera créé dès que votre compte portera une offre qui
        en comprend. <?= e((string) $porte['nom']) ?> en donne
        <?= (int) $porte['liens_courts'] < 0 ? 'sans limite'
            : e($fr((int) $porte['liens_courts'])) ?>, à
        <?= e($fr((int) ($porte['lancement'] ?: $porte['prix']))) ?> F CFA
        <?= (int) $porte['lancement'] > 0 && (int) $porte['lancement'] < (int) $porte['prix']
            ? 'le premier mois' : 'par mois' ?>.
      </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('?p=lien-court')) ?>">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

      <div class="champ">
        <label for="cible">Adresse de destination</label>
        <input id="cible" name="cible" type="url" required inputmode="url"
               placeholder="https://billetterie.mon-evenement.tg/2026"
               value="<?= e((string) $valeurs['cible']) ?>">
      </div>

      <div class="champ">
        <label for="titre">À quoi il sert <span style="font-weight:400">(pour vous)</span></label>
        <input id="titre" name="titre" type="text" maxlength="120"
               placeholder="Affiche du 12 septembre"
               value="<?= e((string) $valeurs['titre']) ?>">
        <p class="aide">Ce nom ne s’affiche nulle part : il vous sert à retrouver le lien
        parmi les autres.</p>
      </div>

      <?php
      /* Le domaine se choisit ICI, avant même d'avoir un compte : on ne
         pose pas une question d'affiche après le mur. L'exemple suit le
         choix, sans recharger la page. */
      ?>
      <div class="champ">
        <label for="domaine">Domaine du lien</label>
        <select id="domaine" name="domaine">
          <option value="">Automatique · le domaine du site</option>
          <?php foreach (DOMAINES_LIENS_COURTS as $_d): ?>
            <option value="<?= e($_d) ?>" <?= $valeurs['domaine'] === $_d ? 'selected' : '' ?>><?= e($_d) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="aide">Choisissez le domaine qui va là où vous collerez l'affiche :
        par exemple <code id="exemple-lien"><?= e(lien_court_url('AbC123', $valeurs['domaine'] ?: null)) ?></code>.</p>
      </div>

      <div class="champ">
        <label for="campagne-plus-tard">Campagne liée</label>
        <input id="campagne-plus-tard" type="text" disabled
               value="Vous la choisirez une fois connecté">
        <p class="aide">Un lien peut se rattacher à une de vos campagnes, pour que ses clics
        apparaissent dans son rapport. Vos campagnes n’existent pas encore.</p>
      </div>

      <button class="bouton" type="submit" style="width:100%;justify-content:center;margin-top:6px">
        <?= $moi ? 'Enregistrer et continuer' : 'Créer mon compte et ce lien' ?>
      </button>

      <?php
      /**
       * Se connecter est un ENVOI, pas un lien.
       *
       * Le lien quittait la page avant que le brouillon soit écrit : on
       * perdait l'adresse qu'on venait de composer en allant chercher le
       * compte qui devait la raccourcir. Le bouton poste le formulaire, et
       * `vers` dit seulement à quelle porte on frappe ensuite.
       */
      if (!$moi): ?>
        <button class="bouton fant" type="submit" name="vers" value="connexion"
                style="width:100%;justify-content:center;margin-top:8px">
          J’ai déjà un compte, me connecter
        </button>
        <p class="aide" style="text-align:center;margin:10px 0 0">
          Dans les deux cas, votre lien vous suit.
        </p>
      <?php endif; ?>
    </form>
  </div>

  <p class="aide" style="text-align:center;margin-top:14px">
    <a href="<?= e(url('?p=accueil')) ?>">Retour à l'accueil</a>
  </p>

  <script>
  (function () {
    var s = document.getElementById('domaine');
    var ex = document.getElementById('exemple-lien');
    if (!s || !ex) { return; }
    var maj = function () {
      var d = s.value;
      ex.textContent = (d ? 'https://' + d : ex.dataset.auto) + '/AbC123';
    };
    /* La forme « automatique » dépend de l'installation ; elle arrive toute
       prête dans une donnée du champ, écrite par le serveur. */
    ex.dataset.auto = <?= json_encode((string) preg_replace('~/AbC123$~', '', lien_court_url('AbC123')), JSON_UNESCAPED_UNICODE) ?>;
    s.addEventListener('change', maj);
    maj();
  })();
  </script>
</div>
