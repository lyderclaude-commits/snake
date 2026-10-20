<?php
/**
 * Les tarifs — une page à eux, et non une ancre au milieu de l'accueil.
 *
 * Les offres vivaient à `?p=accueil#tarifs`, à quatre mille pixels du
 * haut de page. Treize boutons du produit y menaient, et chacun déposait
 * le visiteur au milieu d'une page qu'il n'avait pas demandée, après un
 * saut d'ancre qui ne dit pas qu'on a changé d'endroit. Une adresse qu'on
 * ne peut ni partager ni mettre en favori, et qu'un moteur ne distingue
 * pas de l'accueil.
 *
 * Trois choses s'y ajoutent, que l'accueil ne pouvait pas porter :
 *
 *  - LE TABLEAU COMPARATIF. Quatre cartes disent ce que chacune donne,
 *    jamais ce qu'on gagne en passant de l'une à l'autre. Pour le savoir
 *    il fallait lire quatre listes et les tenir en tête ensemble, ce qui
 *    est le moment exact où l'on referme la page.
 *  - CE QUI SE PAIE À PART. Les crédits WhatsApp sont facturés par Meta,
 *    et le dire après l'achat serait une mauvaise surprise ; le dire ici
 *    est la seule façon honnête.
 *  - LES QUESTIONS D'ARGENT. Changer d'offre, dépasser un quota, partir.
 *    Trois questions qu'on se pose devant un prix, et nulle part ailleurs.
 *
 * Les prix viennent de `offres_vitrine()`, qui les lit dans la table que
 * le produit applique. La page et le logiciel ne peuvent pas diverger.
 */
$_offres = offres_vitrine();
$_tableau = offres_tableau();
$_fr = static fn(int $n): string => number_format($n, 0, ',', ' ');
?>
<div class="page-header entete-boost">
  <div class="container"><div class="page-header-inner">
    <div class="breadcrumb">
      <a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">›</span>
      <span>Boost · Tarifs</span>
    </div>
    <h1>Commencez gratuitement,<br>payez quand ça marche</h1>
    <p>Une offre gratuite à vie, et trois qui grandissent avec vos salles.
    Sans engagement, et sans carte bancaire pour commencer.</p>
    <div class="rassure" style="margin-top:22px">
      <span class="rassure-item">−50 % les 3 premiers mois</span>
      <span class="rassure-item">Changement d’offre à tout moment</span>
      <span class="rassure-item">Vos données repartent avec vous</span>
    </div>
  </div></div>
</div>

<section class="section section-ouverture">
  <div class="container">
    <div class="pricing-grid pricing-auto fade-up">
      <?php foreach ($_offres as $o): ?>
        <div class="pricing-card<?= $o['phare'] ? ' featured' : '' ?>">
          <?php if ($o['phare']): ?><div class="featured-label">Le plus choisi</div><?php endif; ?>
          <?php if ($o['tag'] !== ''): ?><div class="pricing-tier"><?= e($o['tag']) ?></div><?php endif; ?>
          <div class="pricing-name"><?= e($o['nom']) ?></div>
          <?php if ($o['prix'] === 0): ?>
            <div class="pricing-price"><span class="price-val">Gratuit</span></div>
            <div class="price-period">à vie, sans carte bancaire</div>
          <?php else: ?>
            <div class="pricing-price">
              <span class="price-val"><?= e($_fr($o['lancement'] ?: $o['prix'])) ?></span>
              <span class="price-curr">FCFA</span>
            </div>
            <div class="price-period">
              <?php if ($o['lancement'] > 0 && $o['lancement'] < $o['prix']): ?>
                par mois les 3 premiers, puis <?= e($_fr($o['prix'])) ?>
              <?php else: ?>
                par mois, sans engagement
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <div class="pricing-divider"></div>
          <?php if ($o['prefixe']): ?>
            <div class="pricing-desc"><strong><?= e($o['prefixe']) ?></strong></div>
          <?php endif; ?>
          <div class="pricing-features">
            <?php foreach ($o['lignes'] as [$texte, $inclus]): ?>
              <div class="pf-item<?= $inclus ? '' : ' pf-dim' ?>">
                <span class="pf-check"<?= $inclus ? '' : ' aria-label="Non compris"' ?>><?= $inclus ? '✓' : '×' ?></span><?= e($texte) ?>
              </div>
            <?php endforeach; ?>
          </div>
          <a class="btn <?= $o['phare'] ? 'btn-white' : 'btn-outline' ?>"
             style="width:100%;justify-content:center"
             href="<?= e(url('?p=inscription&offre=' . $o['cle'])) ?>"><?= e($o['cta']) ?></a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php
/**
 * Le tableau, dans l'ordre de la table que le produit applique.
 *
 * Il se fait défiler du doigt sur un téléphone plutôt que de se replier :
 * la colonne de gauche n'a de sens qu'en face des autres, et une ligne
 * repliée sous son offre ne compare plus rien.
 */
?>
<section class="section section-grise">
  <div class="container">
    <div style="text-align:center;margin-bottom:36px" class="fade-up">
      <div class="tag">Ligne à ligne</div>
      <h2 class="headline">Ce que vous gagnez <span>en montant d’une offre</span></h2>
      <p style="color:var(--text2);max-width:62ch;margin:14px auto 0;line-height:1.8">
        Les quatre cartes disent ce que chacune donne. Ce tableau dit ce qui change.
      </p>
    </div>

    <div class="admin-table-wrap fade-up">
      <table class="admin-table tableau-offres">
        <thead>
          <tr>
            <th>Ce qui est compris</th>
            <?php foreach ($_offres as $o): ?>
              <th class="col-offre<?= $o['phare'] ? ' phare' : '' ?>"><?= e($o['nom']) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($_tableau as $ligne): ?>
            <tr>
              <td>
                <strong><?= e($ligne['libelle']) ?></strong>
                <span class="ligne-aide"><?= e($ligne['aide']) ?></span>
              </td>
              <?php foreach ($_offres as $o): $v = $ligne['valeurs'][$o['cle']] ?? ''; ?>
                <td class="col-offre<?= $o['phare'] ? ' phare' : '' ?>">
                  <?php if ($v === 'oui'): ?>
                    <span class="oui" aria-label="Compris">✓</span>
                  <?php elseif ($v === ''): ?>
                    <span class="non" aria-label="Non compris">×</span>
                  <?php else: ?>
                    <b><?= e($v) ?></b>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="app-showcase fade-up">
      <div>
        <div class="tag tag-orange">Ce qui se paie à part</div>
        <h2 class="headline" style="margin:10px 0 16px">Les crédits WhatsApp,<br>
        <span>et rien d’autre</span></h2>
        <p style="color:var(--text2);line-height:1.85;margin-bottom:16px">
          Meta facture chaque message WhatsApp envoyé depuis l’API officielle, à nous comme à
          tout le monde. Nous les revendons <strong>au prix coûtant, 1 FCFA le message</strong>,
          et votre tableau de bord affiche ce qu’il vous reste.
        </p>
        <p style="color:var(--text2);line-height:1.85;margin-bottom:22px">
          Telegram et les notifications du navigateur, eux, ne coûtent rien à l’envoi : ils sont
          compris dans votre offre, au compteur près.
        </p>
        <div class="feature-pills">
          <span class="pill"><span class="pill-dot"></span>Pas de frais d’ouverture</span>
          <span class="pill"><span class="pill-dot"></span>Pas d’engagement de durée</span>
          <span class="pill"><span class="pill-dot"></span>Pas de commission sur vos ventes</span>
        </div>
      </div>
      <div>
        <div class="encart">
          <div class="encart-titre">Compris dans toutes les offres, la gratuite incluse</div>
          <div class="pf-list">
            <div class="pf-item"><span class="pf-check">✓</span>Le Studio entier, tous les gabarits</div>
            <div class="pf-item"><span class="pf-check">✓</span>La photo de l’invité ne quitte pas son appareil</div>
            <div class="pf-item"><span class="pf-check">✓</span>Le QR de contrôle à l’entrée</div>
            <div class="pf-item"><span class="pf-check">✓</span>Le désabonnement en un clic sur vos envois</div>
            <div class="pf-item"><span class="pf-check">✓</span>L’export de toutes vos données, à tout moment</div>
            <div class="pf-item"><span class="pf-check">✓</span>Les mises à jour, sans supplément</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section section-grise">
  <div class="container">
    <div class="colonne-lecture" style="max-width:780px">
      <div style="text-align:center;margin-bottom:30px" class="fade-up">
        <div class="tag">Questions d’argent</div>
        <h2 class="headline">Ce qu’on nous demande <span>devant un prix</span></h2>
      </div>
      <div class="faq-list fade-up">
        <details class="faq-item"><summary class="faq-q">Puis-je changer d’offre en cours de route ?<span>+</span></summary>
          <div class="faq-a">Oui, dans les deux sens et à tout moment. Monter prend effet tout de
          suite ; descendre prend effet à la fin de la période déjà payée, pour que vous ne
          perdiez pas ce que vous avez réglé.</div></details>
        <details class="faq-item"><summary class="faq-q">Que se passe-t-il si je dépasse un quota ?<span>+</span></summary>
          <div class="faq-a">Rien ne s’efface et aucune campagne en cours ne s’arrête : c’est
          l’ouverture d’une campagne de PLUS qui est refusée, et vos invités cessent de pouvoir
          télécharger de nouveaux badges. Votre tableau de bord vous prévient avant, pas
          après.</div></details>
        <details class="faq-item"><summary class="faq-q">Faut-il une carte bancaire pour commencer ?<span>+</span></summary>
          <div class="faq-a">Non. L’offre Découverte est gratuite à vie et ne demande rien d’autre
          qu’une adresse e-mail. Vous ne payez qu’au moment où vous choisissez une offre
          supérieure.</div></details>
        <details class="faq-item"><summary class="faq-q">Comment se règle un abonnement ?<span>+</span></summary>
          <div class="faq-a">L’équipe encaisse comme vous le préférez, puis enregistre le paiement :
          cela pose l’échéance et émet une facture numérotée que vous retrouvez seul depuis votre
          profil. Écrivez-nous pour convenir du moyen.</div></details>
        <details class="faq-item"><summary class="faq-q">Que devient mon compte si j’arrête de payer ?<span>+</span></summary>
          <div class="faq-a">Il retombe sur l’offre gratuite. Rien n’est supprimé : vos décors, vos
          contacts et vos chiffres restent, et vous retrouvez vos droits au renouvellement. Les
          badges déjà entre les mains de vos invités continuent de fonctionner.</div></details>
        <details class="faq-item"><summary class="faq-q">Et si je veux partir pour de bon ?<span>+</span></summary>
          <div class="faq-a">Votre profil porte un bouton qui exporte toutes vos données dans un
          fichier lisible, et un bouton qui supprime le compte. Sans nous écrire et sans attendre
          de réponse : la <a href="<?= e(url('?p=confidentialite')) ?>">politique de
          confidentialité</a> dit exactement ce que chacun efface.</div></details>
      </div>
    </div>
  </div>
</section>

<section class="section-sm appel-final">
  <div class="container">
    <div class="colonne-etroite fade-up" style="text-align:center">
      <h2 class="headline">Essayez avec <span>une vraie campagne</span></h2>
      <p style="margin:14px 0 24px;line-height:1.8">
        L’offre gratuite suffit pour publier un décor et voir ce que vos invités en font.
        On parle d’argent après.
      </p>
      <div class="hero-ctas" style="justify-content:center">
        <a class="btn btn-white btn-lg" href="<?= e(url('?p=inscription')) ?>">Créer mon compte</a>
        <a class="btn btn-outline btn-lg btn-sur-bleu" href="<?= e(url('?p=contact')) ?>">Parler à quelqu’un</a>
      </div>
    </div>
  </div>
</section>
