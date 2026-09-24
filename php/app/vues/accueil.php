<?php
/**
 * La vitrine, reprise du prototype « Wakabi Boost ».
 *
 * Note de ton : cette page VOUVOIE, parce qu'elle s'adresse aux
 * organisateurs. Le Studio, lui, tutoie : il parle aux participants.
 * Ce n'est pas une incohérence, ce sont deux audiences.
 *
 * Les chiffres viennent de la base réelle, augmentés du socle écosystème
 * annoncé sur le prototype. Rien n'est inventé côté produit : ce qui est
 * compté est ce qui existe.
 */
$organisateurs = 2340 + (int) db()->query("SELECT COUNT(*) n FROM utilisateurs WHERE role='partenaire'")->fetch()['n'];
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
      <h1>Vos invitations méritent<br><em>une salle pleine</em></h1>
      <p class="accroche">
        Badges viraux, WhatsApp, Push, Telegram, liens traçables.
        <strong>Wakabi Boost</strong> transforme chaque contact en présence réelle,
        et chaque présence en client fidèle grâce au <strong>QR Code qui rapporte</strong>.
      </p>
      <div class="rangee" style="margin-top:26px">
        <a class="bouton" href="<?= e(url('?p=decors')) ?>">Créer mon badge gratuit →</a>
        <a class="bouton fant" href="#tarifs">Voir les offres</a>
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
               alt="Le Studio Wakabi Boost sur un téléphone : une invitée compose son badge « J’y serai », photo, QR Code et prénom déjà en place.">
        </div>
        <span class="tel-encoche" aria-hidden="true"></span>
      </div>
      <div class="pastille-flot" style="top:14%;left:-6px"><b>98 %</b>taux de lecture</div>
      <div class="pastille-flot" style="bottom:6%;right:-14px"><b>+40 %</b>présence réelle</div>
    </div>
  </div>
</section>

<!-- ---------- canaux ---------- -->
<section class="bloc">
  <div class="contenu" style="padding-bottom:0">
    <div class="tete centre">
      <p class="sur">Tous vos canaux</p>
      <h2>Un seul outil pour <em>remplir la salle</em></h2>
      <p>Là où vos invités sont déjà, pas là où vous espérez qu’ils aillent.</p>
    </div>
    <div class="grille canaux">
      <?php
      /**
       * Trois cartes mènent quelque part, et ce sont les seules.
       *
       * Une seule portait un bouton, et ce bouton mentait : « Ouvrir le
       * Studio » envoyait sur la LISTE DES DÉCORS, c'est-à-dire là où l'on
       * fait son badge, pas là où on le fabrique. Chaque promesse est
       * maintenant en face de sa porte, et « Générer un badge » récupère
       * la destination qu'on lui avait prise.
       *
       * Les deux premières restent sans bouton : elles décrivent des
       * canaux qui se branchent depuis un compte, pas une porte d'entrée.
       */
      foreach ([
        ['cloche', 'Notifications Push', 'Notifiez vos abonnés directement sur leur navigateur, sans application à installer.', 'Coût d’envoi : zéro', null, ''],
        ['avion', 'Telegram', 'Créez des canaux de diffusion illimités et sécurisés. Idéal pour fédérer une communauté fidèle.', 'Canaux illimités', null, ''],
        ['message', 'WhatsApp & Rappels', 'Invitations, rappels automatiques J-1 et H-2, chatbots. Vos messages lus à 98 %, jamais dans les spams.', 'À partir de 1 FCFA/message', null, ''],
        ['lien', 'Liens courts', 'Une adresse courte à mettre sur une affiche, et le nombre de personnes qui l’ont réellement suivie.', 'wkb.link', '?p=lien-court', 'Raccourcir un lien'],
        ['studio', 'Studio Badge « J’y serai »', 'Composez le décor de votre événement : déposez le vôtre, ou partez de zéro dans le Studio.', 'Sans compte pour commencer', '?p=creer', 'Ouvrir le Studio'],
        ['coche', 'Générer un badge', 'Choisissez un décor publié, ajoutez votre photo, partagez. C’est ce que font vos invités.', 'Sans compte, en 30 secondes', '?p=decors', 'Voir les décors'],
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
        ['Le badge viral', 'Vos invités créent leur badge « J’y serai » dans le Studio et le partagent. Chaque partage attire de nouveaux invités.'],
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

<!-- ---------- le Studio ---------- -->
<?php if ($vitrine): ?>
<section class="bloc">
  <div class="contenu" style="padding-bottom:0">
    <div class="tete centre">
      <p class="sur">Le Studio</p>
      <h2>Vos invités font <em>l’affiche</em></h2>
      <p>Une photo, un décor, trente secondes. La photo ne quitte jamais leur téléphone.</p>
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
      <a class="bouton fant" href="<?= e(url('?p=decors')) ?>">Voir tous les décors</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ---------- témoignages ---------- -->
<section class="bloc">
  <div class="contenu" style="padding-bottom:0">
    <div class="tete centre">
      <p class="sur">Ils l’utilisent</p>
      <h2>Des salles pleines, <em>pas des promesses</em></h2>
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

<!-- ---------- tarifs ---------- -->
<section class="bloc" id="tarifs" style="background:var(--bg2);scroll-margin-top:70px">
  <div class="contenu" style="padding-bottom:0">
    <div class="tete centre">
      <p class="sur">Les offres</p>
      <h2>Commencez gratuitement, <em>payez quand ça marche</em></h2>
      <p><strong>−50 % les 3 premiers mois</strong>, offre de lancement.</p>
    </div>
    <div class="grille offres">
      <?php
      /**
       * Les prix et les lignes viennent de `formules()`, pas d'une liste écrite ici.
       *
       * Cette grille était recopiée à la main : le jour où une offre a
       * changé dans le produit, la vitrine a continué d'en promettre une
       * autre. Elle est maintenant DÉDUITE de la table que le code
       * applique, ligne à ligne. Une promesse et son application ne peuvent
       * plus diverger : elles n'ont qu'une source.
       */
      $lignes_offre = function (string $cle): array {
          $f = formules()[$cle] ?? formules()['decouverte'] ?? FORMULES['decouverte'];
          $out = [];
          // Les compteurs, formulés comme on les vend.
          $out[] = [$f['campagnes'] < 0 ? 'Campagnes illimitées'
                    : $f['campagnes'] . ' campagne' . ($f['campagnes'] > 1 ? 's' : '') . ' active'
                      . ($f['campagnes'] > 1 ? 's' : ''), 1];
          $out[] = [$f['telechargements'] < 0 ? 'Téléchargements illimités'
                    : number_format($f['telechargements'], 0, ',', ' ') . ' téléchargements / mois', 1];
          if ($f['liens_courts'] !== 0) {
              $out[] = [$f['liens_courts'] < 0 ? 'Liens courts illimités'
                        : $f['liens_courts'] . ' liens courts wkb.link', 1];
          }
          // Un compteur qui vaut zéro ne se montre pas : « 0 e-mail par
          // mois » se lit comme une privation, alors que l'offre n'a
          // simplement jamais promis cette ligne.
          if ($f['emails_par_mois'] !== 0) {
              $out[] = [$f['emails_par_mois'] < 0 ? 'E-mails marketing illimités'
                        : number_format($f['emails_par_mois'], 0, ',', ' ') . ' e-mails marketing / mois', 1];
          }
          // Le Studio est dans toutes les offres, y compris la gratuite :
          // c'est le produit lui-même, et le dire ici évite qu'on croie
          // l'offre d'essai amputée.
          $out[] = ['Studio complet', 1];
          if ($f['stats'] !== 'completes') {
              $out[] = ['Statistiques de base', 1];
          }

          // Puis les capacités et les services, dans l'ordre de la table.
          $barrees = 0;
          foreach (OFFRE_LIGNES as $k => [$libelle, $nature, ]) {
              if ($nature === 'compteur') {
                  continue;
              }
              $ouvert = $k === 'stats' ? $f['stats'] === 'completes' : (bool) $f[$k];
              if ($ouvert) {
                  $out[] = [$libelle, 1];
                  continue;
              }
              // Deux lignes barrées suffisent à situer une offre d'entrée.
              // Les dix y tiendraient, mais la carte deviendrait un
              // catalogue de ce qu'on n'a pas — la liste complète est sur le
              // tableau de bord, là où elle sert à décider.
              if ($cle === 'decouverte' && $barrees < 2) {
                  $out[] = [$libelle, 0];
                  $barrees++;
              }
          }
          return $out;
      };
      /** Ce que l'offre précédente donnait déjà : inutile de le relister. */
      $nouveautes = function (string $cle, ?string $avant) use ($lignes_offre): array {
          if (!$avant) {
              return $lignes_offre($cle);
          }
          $deja = [];
          foreach ($lignes_offre($avant) as [$t, $ok]) {
              if ($ok) {
                  $deja[$t] = true;
              }
          }
          return array_values(array_filter($lignes_offre($cle),
              fn($l) => !isset($deja[$l[0]])));
      };
      /**
       * La liste des offres n'est plus écrite ici non plus.
       *
       * Les quatre clés étaient codées en dur avec leur accroche, leur
       * bouton et leur « Tout Impact, plus : ». Ajouter une offre depuis
       * l'administration aurait donc donné une offre invisible, et en
       * supprimer une aurait cassé la page. Tout se déduit maintenant de
       * l'ordre d'affichage : l'offre précédente est celle d'avant dans la
       * liste, quelle qu'elle soit.
       */
      $vendues = formules_actives();
      $cles = array_keys($vendues);
      foreach ($cles as $i => $cle):
        $f = $vendues[$cle];
        $tag = (string) ($f['tag'] ?? '');
        $cta = (string) ($f['cta'] ?? '') ?: ('Choisir ' . $f['nom']);
        $phare = (bool) ($f['phare'] ?? false);
        /**
         * La PREMIÈRE offre payante liste tout ce qu'elle donne.
         *
         * « Tout Découverte, plus : » ferait paraître maigre l'offre
         * d'entrée, puisque Découverte ne donne presque rien : la moitié de
         * ses lignes disparaîtraient au nom d'un doublon. Le raccourci ne
         * vaut qu'entre deux offres qui se paient toutes les deux.
         */
        $prec = $i > 0 ? $cles[$i - 1] : null;
        $avant = $prec !== null && (int) $f['prix'] > 0
              && (int) $vendues[$prec]['prix'] > 0 ? $prec : null;
        $prefixe = $avant ? 'Tout ' . $vendues[$avant]['nom'] . ', plus :' : null;
        $nom = $f['nom'];
        $prix = (int) $f['prix'];
        $lancement = (int) $f['lancement'];
        $lignes = $nouveautes($cle, $avant);
      ?>
        <div class="offre<?= $phare ? ' phare' : '' ?>">
          <?php if ($phare): ?><span class="ruban">Le plus choisi</span><?php endif; ?>
          <span class="tag"><?= e($tag) ?></span>
          <h3><?= e($nom) ?></h3>
          <?php if ($prix === 0): ?>
            <div class="prix">Gratuit <small>à vie</small></div>
          <?php else: ?>
            <div class="prix"><s><?= $fr($prix) ?></s><?= $fr($lancement) ?> <small>FCFA/mois</small></div>
            <p class="avant">−50 % les 3 premiers mois</p>
          <?php endif; ?>
          <?php if ($prefixe): ?><p class="prefixe"><?= e($prefixe) ?></p><?php endif; ?>
          <ul>
            <?php foreach ($lignes as [$texte, $inclus]): ?>
              <li class="<?= $inclus ? '' : 'non' ?>"><span><?= e($texte) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <a class="bouton<?= $phare ? '' : ' fant' ?>" style="justify-content:center"
             href="<?= e(url('?p=inscription&offre=' . $cle)) ?>"><?= e($cta) ?></a>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="aide" style="text-align:center;margin-top:18px">
      Les crédits WhatsApp s’achètent à part (1 FCFA/message) : ce sont des coûts réels
      facturés par Meta, chez nous comme ailleurs.
    </p>
  </div>
</section>

<!-- ---------- le blog ---------- -->
<?php if ($articles): ?>
<section class="bloc" style="background:var(--bg2)">
  <div class="contenu" style="padding-bottom:0">
    <div class="tete centre">
      <p class="sur">Le blog</p>
      <h2>Ce qu’on apprend <em>en remplissant des salles</em></h2>
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

<!-- ---------- questions ---------- -->
<section class="bloc">
  <div class="contenu" style="max-width:760px;padding-bottom:0">
    <div class="tete centre">
      <p class="sur">Vos questions</p>
      <h2>Tout ce que vous <em>voulez savoir</em></h2>
    </div>
    <?php foreach ([
      ['C’est quoi la différence avec un générateur de badge classique ?',
       'Un générateur classique produit une image que vos invités partagent, c’est tout. Wakabi Boost ajoute un QR Code unique sur chaque badge : scanné à l’entrée, il mesure la présence réelle, crédite des Koris à l’invité et le transforme en client fidèle de l’écosystème. Vous ne créez plus du buzz qui retombe, vous créez de la valeur durable.'],
      ['Est-ce que je peux vraiment démarrer gratuitement ?',
       'Oui. La formule Découverte est gratuite à vie, sans carte bancaire : 1 campagne active, 50 téléchargements de badges par mois et le Studio complet. Vous ne payez que lorsque vous voulez plus de portée ou des fonctionnalités avancées.'],
      ['Comment fonctionne le ciblage WhatsApp ?',
       'Contrairement aux envois aveugles, Wakabi vous laisse cibler par ville, centre d’intérêt et historique de visite parmi les 10 000+ utilisateurs de l’écosystème. Vos messages atteignent les bonnes personnes, donc plus de présence pour moins de budget. Les crédits coûtent 1 FCFA par message.'],
      ['Les crédits WhatsApp sont-ils inclus dans l’abonnement ?',
       'Non, ils s’achètent à part (1 FCFA/message), comme chez tous les acteurs sérieux : ce sont des coûts réels facturés par Meta. En revanche, nos tarifs dégressifs et le ciblage intelligent font que vous dépensez beaucoup moins pour un meilleur résultat.'],
      ['Je n’ai aucune compétence technique. C’est compliqué ?',
       'Pas du tout. Le Studio fonctionne en glisser-déposer, les campagnes se lancent en quelques clics, et tout est en français. Si vous savez envoyer un message WhatsApp, vous savez utiliser Wakabi Boost.'],
      ['Que devient la photo de mes invités ?',
       'Elle ne quitte jamais leur téléphone. Le badge est fabriqué entièrement dans leur navigateur : rien n’est téléversé, rien n’est conservé sur nos serveurs. C’est plus rapide sur réseau faible, moins coûteux en données, et il n’y a aucune donnée personnelle à protéger.'],
    ] as [$q, $r]): ?>
      <details class="qr">
        <summary><span><?= e($q) ?></span></summary>
        <p><?= e($r) ?></p>
      </details>
    <?php endforeach; ?>
  </div>
</section>

<!-- ---------- appel final ---------- -->
<section class="bloc" style="padding-top:0">
  <div class="contenu" style="padding-bottom:0">
    <div class="final">
      <h2>Votre prochaine salle comble commence maintenant</h2>
      <p>Rejoignez les <?= $fr($organisateurs) ?> organisateurs qui ne laissent plus jamais une chaise vide.</p>
      <div class="rangee" style="justify-content:center;margin-top:26px">
        <a class="bouton" href="<?= e(url('?p=decors')) ?>">Créer mon badge gratuit</a>
        <a class="bouton fant" href="#tarifs">Voir les offres −50 %</a>
      </div>
    </div>
  </div>
</section>
