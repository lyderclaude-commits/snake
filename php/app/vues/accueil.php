<?php
/**
 * La vitrine, reprise du prototype « Wakabi Boost ».
 *
 * Note de ton : cette page VOUVOIE, parce qu'elle s'adresse aux
 * organisateurs. Le Studio, lui, tutoie : il parle aux participants.
 * Ce n'est pas une incohérence, ce sont deux audiences.
 *
 * Note de vocabulaire : cette page dit BADGE là où le logiciel dit décor
 * ou Studio, et ÉVÉNEMENT là où elle disait salle. Le menu et le pied
 * suivent, parce qu'ils s'affichent juste au-dessus et juste au-dessous
 * d'elle. Les écrans de travail, eux, gardent les leurs, et c'est voulu :
 * dans le catalogue, dans les CGU et dans la base, « décor » désigne
 * précisément le fond qu'on dépose, pas le badge qui en sort. Ici, c'est
 * un organisateur qui lit — « décor » lui demande une explication, et
 * « salle » laisse dehors tout ce qui se passe dehors.
 *
 * Les chiffres viennent de la base réelle, augmentés du socle écosystème
 * annoncé sur le prototype. Rien n'est inventé côté produit : ce qui est
 * compté est ce qui existe.
 */
/**
 * Le socle de partenaires de l'écosystème, écrit UNE fois.
 *
 * Il sert à deux endroits de la page, et deux chiffres différents pour
 * la même population auraient fini par s'y glisser : le ruban des
 * chiffres l'annonce comme un plancher (« 2 340+ »), l'appel de fin y
 * ajoute les comptes réels et donne le total du jour.
 */
const SOCLE_PARTENAIRES = 2340;
$organisateurs = SOCLE_PARTENAIRES
    + (int) db()->query("SELECT COUNT(*) n FROM utilisateurs WHERE role='partenaire'")->fetch()['n'];

$vitrine = array_slice(decors_publies(8), 0, 4);
// Le blog sur la vitrine : trois articles, et rien si la rédaction n'a
// encore rien écrit. Une section « Le blog » vide est pire que pas de blog.
$articles = articles_publies(3);
$fr = fn(int $n) => number_format($n, 0, ',', ' ');
?>


<section class="heros">
  <div class="contenu heros-in" style="padding-bottom:0">
    <div>
      <p class="etiquette"><b>NOUVEAU</b> La suite marketing événementielle de Wakabi</p>
      <h1>Vos invitations méritent<br><em>un événement complet</em></h1>
      <p class="accroche">
        Badges viraux, WhatsApp, Push, Telegram, liens traçables.
        <strong>Wakabi Boost</strong> transforme chaque contact en présence réelle,
        et chaque présence en client fidèle grâce au <strong>QR Code qui rapporte</strong>.
      </p>
      <div class="rangee" style="margin-top:26px">
        <a class="bouton" href="<?= e(url('?p=decors')) ?>">Créer mon badge gratuit →</a>
        <a class="bouton fant" href="<?= e(url('?p=tarifs')) ?>">Voir les offres</a>
      </div>
      <ul class="gages">
        <li>Sans carte bancaire</li>
        <li>Gratuit pour démarrer</li>
        <li>Prêt en 2 minutes</li>
      </ul>
    </div>

    <!-- Ce n'est pas une maquette : c'est une capture du Studio tel qu'il
         s'affiche sur un téléphone de 390 px, une invitée en train de
         composer son badge. Ce que la vitrine montre est ce qu'on livre. -->
    <div class="hero-tel">
      <div class="tel">
        <div class="tel-ecran">
          <img src="<?= e(url('public/apercu-studio.webp')) ?>" width="640" height="1385"
               alt="Wakabi Boost sur un téléphone : une invitée compose son badge « J’y serai », photo, QR Code et prénom déjà en place.">
        </div>
        <span class="tel-encoche" aria-hidden="true"></span>
      </div>
      <div class="pastille-flot" style="top:14%;left:-6px"><b>98 %</b>taux de lecture</div>
      <div class="pastille-flot" style="bottom:6%;right:-14px"><b>+40 %</b>présence réelle</div>
    </div>
  </div>
</section>

<!-- ---------- les chiffres de l'écosystème ---------- -->
<?php
/**
 * Les chiffres de l'écosystème Wakabi, repris de la page d'accueil du
 * guide, au même dessin et aux mêmes mesures.
 *
 * Ce sont ceux de L'APPLICATION, pas ceux de Boost : dix mille personnes
 * qui sortent, et non dix mille organisateurs. Les deux premiers sont
 * des planchers, et c'est ce que le « + » dit ; le troisième est un
 * objectif annoncé, pas un état des lieux.
 *
 * Le nombre de partenaires sort du même socle que l'appel de fin, qui y
 * ajoute les comptes réels : un seul nombre dans le fichier, donc jamais
 * deux comptes différents pour les mêmes gens.
 */
?>
<section class="socle-chiffres">
  <div class="contenu">
    <div class="socle-grille">
      <div class="socle"><b>10 000+</b><span>Utilisateurs actifs</span></div>
      <div class="socle"><b><?= $fr(SOCLE_PARTENAIRES) ?>+</b><span>Partenaires référencés</span></div>
      <div class="socle"><b>6</b><span>Pays cibles UEMOA</span></div>
    </div>
  </div>
</section>

<!-- ---------- canaux ---------- -->
<section class="bloc">
  <div class="contenu" style="padding-bottom:0">
    <div class="tete centre">
      <p class="sur">Nos solutions</p>
      <h2>Les outils permettant de <em>remplir son événement</em></h2>
      <p>Là où vos invités sont déjà, pas là où vous espérez qu’ils aillent.</p>
    </div>
    <div class="grille canaux">
      <?php
      /**
       * Trois cartes mènent quelque part, et ce sont les seules.
       *
       * Une seule portait un bouton, et ce bouton mentait : il promettait
       * le Studio et envoyait sur la LISTE DES BADGES PUBLIÉS, c'est-à-dire
       * là où l'on fait son badge, pas là où on le fabrique. Chaque
       * promesse est maintenant en face de sa porte : « Réaliser un badge »
       * ouvre le Studio, « Voir les badges » ouvre la liste.
       *
       * Les deux premières restent sans bouton : elles décrivent des
       * canaux qui se branchent depuis un compte, pas une porte d'entrée.
       */
      foreach ([
        ['cloche', 'Notifications Push', 'Notifiez vos abonnés directement sur leur navigateur, sans application à installer.', 'Coût d’envoi : zéro', null, ''],
        ['avion', 'Telegram', 'Créez des canaux de diffusion illimités et sécurisés. Idéal pour fédérer une communauté fidèle.', 'Canaux illimités', null, ''],
        ['message', 'WhatsApp & Rappels', 'Invitations, rappels automatiques J-1 et H-2, chatbots. Vos messages lus à 98 %, jamais dans les spams.', 'À partir de 1 FCFA/message', null, ''],
        ['lien', 'Liens courts', 'Une adresse courte à mettre sur une affiche, et le nombre de personnes qui l’ont réellement suivie.', 'wkb.link', '?p=lien-court', 'Raccourcir un lien'],
        ['studio', 'Badge « J’y serai »', 'Composez le badge de votre événement : déposez le vôtre, ou partez de zéro.', 'Sans compte pour commencer', '?p=creer', 'Réaliser un badge'],
        ['coche', 'Générer un badge', 'Choisissez un badge publié, ajoutez votre photo, partagez. C’est ce que font vos invités.', 'Sans compte, en 30 secondes', '?p=decors', 'Voir les badges'],
      ] as [$ico, $canal, $corps, $note, $vers, $bouton]): ?>
        <div class="canal<?= $vers ? ' actif' : '' ?>">
          <span class="ico"><?= icone($ico) ?></span>
          <b><?= e($canal) ?></b>
          <p><?= e($corps) ?></p>
          <span class="note"><?= e($note) ?></span>
          <?php if ($vers): ?>
            <a class="bouton petit" href="<?= e(url($vers)) ?>" style="justify-content:center"><?= e($bouton) ?></a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ---------- la boucle ---------- -->
<section class="bloc" style="background:var(--bg2)">
  <div class="contenu" style="padding-bottom:0">
    <div class="tete centre">
      <p class="sur">La boucle</p>
      <h2>Du partage à la <em>présence réelle</em></h2>
      <p>Trois étapes. Chacune nourrit la suivante.</p>
    </div>
    <div class="grille g3">
      <?php foreach ([
        ['Le badge viral', 'Vos invités créent leur badge « J’y serai » et le partagent. Chaque partage attire de nouveaux invités.'],
        ['WhatsApp & rappels auto', 'Invitations et rappels J-1, H-2 envoyés automatiquement. Vous ne courez plus après vos invités, Wakabi le fait pour vous.'],
        ['QR Code à l’entrée → Koris', 'Chaque présent scanne son QR, gagne des Koris et devient un client fidèle de l’écosystème Wakabi. Vous mesurez tout.'],
      ] as $i => [$t, $b]): ?>
        <div class="etape">
          <div class="n"><?= $i + 1 ?></div>
          <b><?= e($t) ?></b>
          <p><?= e($b) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ---------- les badges publiés ---------- -->
<?php if ($vitrine): ?>
<section class="bloc">
  <div class="contenu" style="padding-bottom:0">
    <div class="tete centre">
      <p class="sur">Les Badges</p>
      <h2>Vos invités font <em>l’affiche</em></h2>
      <p>Une photo, un badge, trente secondes. La photo ne quitte jamais leur téléphone.</p>
    </div>
    <div class="grille vitrines">
      <?php foreach ($vitrine as $d): ?>
        <a class="vignette" href="<?= e(url('?p=decor&slug=' . urlencode($d['slug']))) ?>">
          <?php $_im = image_reduite($d['cadre_url'] ?: url('public/cadres/bon-plan.png'), 320); ?>
          <img src="<?= e($_im['src']) ?>"
               <?= $_im['srcset'] ? 'srcset="' . e($_im['srcset']) . '" sizes="(max-width:700px) 92vw, 320px"' : '' ?>
               <?= $_im['largeur'] ? 'width="' . $_im['largeur'] . '" height="' . $_im['hauteur'] . '"' : '' ?>
               alt="" loading="lazy" decoding="async">
          <div class="bas">
            <b><?= e($d['titre']) ?></b>
            <span><?= (int) $d['telechargements'] ?> badges créés</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="rangee" style="margin-top:20px">
      <a class="bouton fant" href="<?= e(url('?p=decors')) ?>">Voir tous les badges</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ---------- l'application Wakabi ---------- -->
<?php
/**
 * L'application, prise par le bout qui intéresse un organisateur.
 *
 * Elle n'est PAS en ouverture, et c'est délibéré : un visiteur vient
 * ici pour remplir son événement, pas pour télécharger une
 * application. L'argument n'est donc pas « voici notre app », c'est
 * « derrière Boost il y a une audience, et votre soirée y est déjà ».
 *
 * Le texte, les atouts et les visuels sont CEUX de la page
 * « L'Application », aux mêmes adresses d'images : une seconde version
 * du même argumentaire aurait fini par dire autre chose.
 *
 * Une seule chose change, et c'est la personne. La page de
 * l'application tutoie — elle parle à qui sort ; celle-ci vouvoie, elle
 * parle à qui organise. « Tu sais où aller » devient donc « vos invités
 * savent où aller » : même information, même page, une seule voix.
 */
?>
<section class="bloc bloc-app">
  <div class="contenu">
    <div class="tete centre">
      <p class="sur">L’application Wakabi</p>
      <h2>Simple. Rapide. <em>Addictif.</em></h2>
      <p>Boost ne parle pas dans le vide : derrière lui, il y a l’application où vos invités
      cherchent déjà quoi faire ce soir dans leur ville.</p>
    </div>
    <div class="app-duo">
      <?php
      /**
       * Les deux captures sont SERVIES PAR LE SITE, et non par un tiers.
       *
       * La page de l'application va encore chercher les siennes chez
       * `admin.wakabileguide.com` : une requête vers un domaine qu'on ne
       * tient pas, sur une connexion où le mégaoctet se compte, et une
       * image qui disparaît le jour où ce domaine bouge. Celles-ci
       * vivent dans `public/`, partent dans le zip, et pèsent 26 et
       * 36 Ko en WebP contre 180 et 373 en PNG.
       */
      ?>
      <div class="app-tels">
        <img class="app-tel-cote" src="<?= e(url('public/apercu-app-lieu.webp')) ?>"
             alt="La galerie d’un lieu dans l’application Wakabi : l’hôtel Sarakawa en photos"
             width="325" height="605" loading="lazy" decoding="async">
        <img class="app-tel-un" src="<?= e(url('public/apercu-app-accueil.webp')) ?>"
             alt="L’accueil de l’application Wakabi : la recherche, les évènements à venir et les lieux"
             width="325" height="605" loading="lazy" decoding="async">
      </div>
      <div>
        <p>Wakabi a été conçue pour la réalité africaine : mobile first, légère, optimisée
        pour les connexions 3G et les smartphones d’entrée de gamme. En quelques secondes,
        vos invités savent où aller, ce qui se passe et combien ils vont économiser.</p>
        <ul class="app-atouts">
          <li>Géolocalisation</li>
          <li>Filtres intelligents</li>
          <li>Agenda events</li>
          <li>QR Code</li>
          <li>Notifications live</li>
          <li>Avis communauté</li>
          <li>Mode hors ligne</li>
        </ul>
        <div class="app-magasins">
          <a class="app-mag" href="<?= e(APPLICATION_URL) ?>" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 3.2v17.6c0 .5.5.8.9.6l12.4-8.8c.4-.3.4-.9 0-1.2L4.9 2.6c-.4-.2-.9.1-.9.6Z"/></svg>
            <span><small>Disponible sur</small><strong>Google Play</strong></span>
          </a>
          <span class="app-mag gris">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.2 12.6c0-2 1.6-3 1.7-3-.9-1.4-2.4-1.6-2.9-1.6-1.2-.1-2.4.7-3 .7-.6 0-1.6-.7-2.6-.7-1.3 0-2.6.8-3.3 2-1.4 2.4-.4 6 1 8 .7 1 1.5 2 2.5 2 1 0 1.4-.6 2.6-.6s1.5.6 2.6.6 1.7-.9 2.4-1.9c.7-1.1 1-2.2 1-2.2s-1.9-.8-2-3.3ZM14.4 6.3c.5-.7.9-1.6.8-2.6-.8 0-1.8.5-2.4 1.2-.5.6-1 1.6-.8 2.5.9.1 1.8-.4 2.4-1.1Z"/></svg>
            <span><small>Bientôt sur</small><strong>App Store</strong></span>
          </span>
          <a class="lien-app" href="<?= e(url('?p=application')) ?>">Tout sur l’application →</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ---------- témoignages ---------- -->
<section class="bloc">
  <div class="contenu" style="padding-bottom:0">
    <div class="tete centre">
      <p class="sur">Ils l’utilisent</p>
      <h2>Des événements complets, <em>pas des promesses</em></h2>
    </div>
    <div class="grille g3">
      <?php foreach ([
        ['Avant Wakabi Boost, je devais relancer mes invités un par un. Maintenant les rappels partent seuls et ma dernière soirée a fait salle comble.', 'Kofi Mensah', 'Organisateur, Lomé', 'kofi', '1 200 badges'],
        ['Le badge « J’y serai » a été partagé plus de 1 200 fois en 3 jours. Une visibilité que je n’aurais jamais pu m’offrir en pub classique.', 'Aïcha Traoré', 'Promotrice événementielle, Cotonou', 'aicha', '98 % lus'],
        ['Le QR Code à l’entrée a tout changé : je sais exactement qui est venu, et mes invités reviennent pour les Koris. Du jamais vu.', 'Emmanuel Agbo', 'Gérant de lieu, Cotonou', 'emmanuel', 'Présence tracée'],
      ] as [$q, $nom, $role, $portrait, $mesure]): ?>
        <div class="avis">
          <q><?= e($q) ?></q>
          <div class="qui">
            <?= avatar($portrait, 42) ?>
            <span><b><?= e($nom) ?></b><span><?= e($role) ?></span></span>
            <span class="mesure"><?= e($mesure) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ---------- le blog ---------- -->
<?php if ($articles): ?>
<section class="bloc" style="background:var(--bg2)">
  <div class="contenu" style="padding-bottom:0">
    <div class="tete centre">
      <p class="sur">Le blog</p>
      <h2>Ce qu’on apprend <em>en remplissant des événements</em></h2>
    </div>
    <div class="grille g3">
      <?php foreach ($articles as $a): ?>
        <a class="vignette article" href="<?= e(url('?p=blog&a=' . urlencode($a['slug']))) ?>">
          <?php if ($a['couverture']):
              $_im = image_reduite($a['couverture'], 320); ?>
            <img src="<?= e($_im['src']) ?>"
                 <?= $_im['srcset'] ? 'srcset="' . e($_im['srcset']) . '" sizes="(max-width:700px) 92vw, 320px"' : '' ?>
                 <?= $_im['largeur'] ? 'width="' . $_im['largeur'] . '" height="' . $_im['hauteur'] . '"' : '' ?>
                 alt="" loading="lazy" decoding="async">
          <?php endif; ?>
          <div class="bas">
            <b><?= e($a['titre']) ?></b>
            <span><?= e(gmdate('d/m/Y', strtotime((string) $a['publie_le']))) ?>
              · <?= texte_minutes((string) $a['corps']) ?> min de lecture</span>
            <span class="chapo"><?= e($a['chapo'] ?: texte_extrait((string) $a['corps'], 110)) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="rangee" style="margin-top:20px">
      <a class="bouton fant" href="<?= e(url('?p=blog')) ?>">Tous les articles</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ---------- appel final ---------- -->
<section class="bloc" style="padding-top:0">
  <div class="contenu" style="padding-bottom:0">
    <div class="final">
      <h2>Votre prochain événement complet commence maintenant</h2>
      <p>Rejoignez les <?= $fr($organisateurs) ?> organisateurs qui ne laissent plus jamais une chaise vide.</p>
      <div class="rangee" style="justify-content:center;margin-top:26px">
        <a class="bouton" href="<?= e(url('?p=decors')) ?>">Créer mon badge gratuit</a>
        <a class="bouton fant" href="<?= e(url('?p=tarifs')) ?>">Voir les offres −50 %</a>
      </div>
    </div>
  </div>
</section>
