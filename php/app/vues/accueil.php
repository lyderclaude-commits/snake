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
      <?php foreach ([
        ['message', 'WhatsApp & Rappels', 'Invitations, rappels automatiques J-1 et H-2, chatbots. Vos messages lus à 98 %, jamais dans les spams.', 'À partir de 1 FCFA/message', false],
        ['cloche', 'Notifications Push', 'Notifiez vos abonnés directement sur leur navigateur, sans application à installer.', 'Coût d’envoi : zéro', false],
        ['avion', 'Telegram', 'Créez des canaux de diffusion illimités et sécurisés. Idéal pour fédérer une communauté fidèle.', 'Canaux illimités', false],
        ['lien', 'Liens courts', 'Raccourcissez vos URLs, suivez les clics en temps réel et retargetez votre audience.', 'wkb.link', false],
        ['studio', 'Studio Badge « J’y serai »', 'Vos invités créent leur badge personnalisé en 1 clic et le téléchargent. Effet viral sur WhatsApp et les réseaux.', 'Disponible maintenant', true],
      ] as [$ico, $canal, $corps, $note, $actif]): ?>
        <div class="canal<?= $actif ? ' actif' : '' ?>">
          <span class="ico"><?= icone($ico) ?></span>
          <b><?= e($canal) ?></b>
          <p><?= e($corps) ?></p>
          <span class="note"><?= e($note) ?></span>
          <?php if ($actif): ?>
            <a class="bouton petit" href="<?= e(url('?p=decors')) ?>" style="justify-content:center">Ouvrir le Studio</a>
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
    <div class="tete">
      <p class="sur">Le Studio</p>
      <h2>Vos invités font <em>l’affiche</em></h2>
      <p>Une photo, un décor, trente secondes. La photo ne quitte jamais leur téléphone.</p>
    </div>
    <div class="grille vitrines">
      <?php foreach ($vitrine as $d): ?>
        <a class="vignette" href="<?= e(url('?p=decor&slug=' . urlencode($d['slug']))) ?>">
          <img src="<?= e($d['cadre_url'] ?: url('public/cadres/bon-plan.png')) ?>" alt="" loading="lazy">
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

<!-- ---------- comparatif ---------- -->
<section class="bloc" style="background:var(--bg2)">
  <div class="contenu" style="padding-bottom:0">
    <div class="tete centre">
      <p class="sur">Le comparatif</p>
      <h2>Ce qu’un générateur d’images <em>ne fait pas</em></h2>
    </div>

    <?php
    /**
     * Chaque cellule porte son ÉTAT, pas un symbole écrit à la main.
     *
     * Écrire « ✗ » dans le texte laisse le lecteur deviner, et le rend
     * illisible à un lecteur d'écran. L'état gouverne l'icône, la couleur
     * et le libellé annoncé aux outils d'assistance.
     *
     * oui = fourni · non = absent · valeur = un chiffre, ni bon ni mauvais
     */
    $lignes = [
      ['Badge viral « J’y serai »',      ['oui', 'image statique'],        ['oui', 'avec QR Code intégré']],
      ['Diffusion WhatsApp',              ['oui', 'envoi aveugle'],         ['oui', 'ciblage ville + intérêt']],
      ['Rappels automatiques J-1 / H-2',  ['non', ''],                      ['oui', 'inclus']],
      ['Présence réelle mesurée',         ['non', ''],                      ['oui', 'scan QR à l’entrée']],
      ['Récompenses & fidélisation',      ['non', ''],                      ['oui', 'Koris automatiques']],
      ['Base d’utilisateurs activable',   ['non', 'vous partez de zéro'],   ['oui', '10 000 utilisateurs Wakabi']],
      ['Coût par message WhatsApp',       ['valeur', '1,5 FCFA'],           ['valeur', '1 FCFA, et ciblé']],
      ['Écosystème connecté',             ['non', 'outils isolés'],         ['oui', 'lié à l’app & aux lieux']],
    ];

    $cellule = function (array $c, bool $nous): string {
        [$etat, $texte] = $c;
        if ($etat === 'valeur') {
            return '<span class="valeur">' . e($texte) . '</span>';
        }
        $annonce = $etat === 'oui' ? 'Oui' : 'Non';
        return '<span class="verdict ' . $etat . '">'
             . '<span class="signe">' . icone($etat === 'oui' ? 'coche' : 'croix') . '</span>'
             . '<span class="sr">' . $annonce . '</span>'
             . ($texte !== '' ? '<span class="quoi">' . e($texte) . '</span>' : '')
             . '</span>';
    };
    ?>

    <div class="comparatif">
      <table>
        <caption class="sr">Comparaison entre un générateur de badges classique et Wakabi Boost</caption>
        <thead>
          <tr>
            <th scope="col">Fonctionnalité</th>
            <th scope="col">Générateurs classiques</th>
            <th scope="col" class="nous">Wakabi Boost</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lignes as [$quoi, $eux, $nous]): ?>
            <tr>
              <th scope="row"><?= e($quoi) ?></th>
              <!-- `data-quoi` : sur téléphone le tableau devient une pile de
                   cartes, l'en-tête disparaît, et chaque cellule doit alors
                   rappeler elle-même de quelle colonne elle vient. -->
              <td data-quoi="Générateurs classiques"><?= $cellule($eux, false) ?></td>
              <td class="nous" data-quoi="Wakabi Boost"><?= $cellule($nous, true) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

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
      <?php foreach ([
        ['decouverte', 'Pour tester', 'Découverte', 0, 0, 'Commencer gratuitement', false, null,
          [['1 campagne active', 1], ['50 téléchargements badge / mois', 1], ['Studio complet', 1],
           ['Stats de base', 1], ['Filigrane discret sur les badges', 0], ['QR Code Koris', 0]]],
        ['impact', 'Entrée sérieuse', 'Impact', 5000, 2500, 'Choisir Impact', true, null,
          [['3 campagnes actives', 1], ['500 téléchargements / badge', 1], ['Sans filigrane', 1],
           ['Redirection après téléchargement', 1], ['20 liens courts wkb.link', 1],
           ['QR Code Koris intégré', 1], ['Stats complètes « J’y serai »', 1], ['Achat de crédits WhatsApp', 1]]],
        ['croissance', 'Clients actifs', 'Croissance', 12000, 6000, 'Choisir Croissance', false, 'Tout Impact, plus :',
          [['5 campagnes actives', 1], ['2 000 téléchargements', 1], ['Ciblage ville + intérêt', 1],
           ['Diffusion à la base Wakabi', 1], ['100 liens courts', 1], ['Campagnes Telegram + Push', 1]]],
        ['mouvement', 'Pros & institutions', 'Mouvement', 30000, 15000, 'Nous contacter', false, 'Tout Croissance, plus :',
          [['Campagnes illimitées', 1], ['Téléchargements illimités', 1], ['Accès API REST', 1],
           ['Web Push illimité', 1], ['Article sponsorisé blog Wakabi', 1], ['Account manager dédié', 1]]],
      ] as [$cle, $tag, $nom, $prix, $lancement, $cta, $phare, $prefixe, $lignes]): ?>
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
