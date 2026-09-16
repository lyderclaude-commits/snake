<?php
/**
 * Le rapport, à l'écran.
 *
 * Il se lit de haut en bas comme on raconte : ce qu'on a envoyé, ce qui
 * n'est pas arrivé, ce que le public en a fait, et décor par décor. Les
 * deux boutons d'export sont en haut, avec le titre, parce que c'est là
 * qu'on les cherche — et parce que le PDF est la vraie sortie de cet
 * écran : c'est lui qu'on transmet au sponsor.
 */
$p = $r['periode'];
$portee = $r['portee'];
$envois = $r['envois'];
$t = $envois['total'];

/** Une adresse de cet écran, en gardant la période courante. */
$lien = static function (array $change) use ($p, $portee): string {
    $q = ['p' => 'rapports', 'periode' => $p['cle'], 'du' => $p['du'], 'au' => $p['au']];
    if ($portee['cle'] === 'decor' && $portee['decor']) {
        $q['decor'] = (string) $portee['decor']['slug'];
    } elseif ($portee['cle'] === 'campagne' && $portee['campagne']) {
        $q['campagne'] = (string) $portee['campagne']['id'];
    } elseif ($portee['cle'] === 'organisateur' && $portee['auteur_id']) {
        $q['organisateur'] = (string) $portee['auteur_id'];
    }
    foreach ($change as $k => $v) {
        if ($v === null) {
            unset($q[$k]);
        } else {
            $q[$k] = (string) $v;
        }
    }
    return url('?' . http_build_query($q));
};

$nb = static fn(int $n): string => number_format($n, 0, ',', ' ');
$pc = static fn(float $x): string => number_format($x * 100, ($x * 100) < 10 ? 1 : 0, ',', ' ') . ' %';
?>
<div class="contenu">

  <section class="entete">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
      <div>
        <h1>Rapports</h1>
        <p>
          <strong><?= e((string) $portee['titre']) ?></strong> ·
          <?= e((string) $p['libelle']) ?> ·
          <?= (int) $p['jours'] ?> jour<?= (int) $p['jours'] > 1 ? 's' : '' ?>
        </p>
      </div>
      <div class="rangee" style="gap:8px">
        <?php if ($portee['cle'] === 'decor' && $portee['decor']): ?>
          <?php
          /**
           * Le point d’entrée des segments est ICI, sur le rapport.
           *
           * « 114 badges jamais téléchargés » devient un lien : c’est la
           * même phrase des deux côtés, et personne n’a à retrouver le
           * segment dans un menu.
           */
          ?>
          <a class="bouton"
             href="<?= e(url('?p=segments&decor=' . rawurlencode((string) $portee['decor']['slug']))) ?>">À qui j’écris</a>
          <a class="bouton fant"
             href="<?= e(url('?p=sponsor&decor=' . rawurlencode((string) $portee['decor']['slug']))) ?>">Sponsor</a>
        <?php endif; ?>
        <a class="bouton<?= $portee['cle'] === 'decor' ? ' fant' : '' ?>"
           href="<?= e($lien(['export' => 'pdf'])) ?>">Exporter en PDF</a>
        <a class="bouton fant" href="<?= e($lien(['export' => 'csv'])) ?>">CSV</a>
      </div>
    </div>
  </section>

  <!-- ------------------- la période et la portée ------------------- -->
  <form class="filtres-rapport carte plate" method="get" action="<?= e(url('?p=rapports')) ?>">
    <input type="hidden" name="p" value="rapports">

    <div class="rangee" style="gap:6px">
      <?php foreach (RAPPORT_PERIODES as $cle => $libelle): ?>
        <?php if ($cle === 'libre') { continue; } ?>
        <a class="puce-p<?= $p['cle'] === $cle ? ' on' : '' ?>"
           href="<?= e($lien(['periode' => $cle, 'du' => null, 'au' => null])) ?>"><?= e($libelle) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="rangee" style="gap:8px">
      <input type="hidden" name="periode" value="libre">
      <label class="rap-champ" for="f-du">Du
        <input id="f-du" type="date" name="du" value="<?= e($p['du']) ?>"></label>
      <label class="rap-champ" for="f-au">au
        <input id="f-au" type="date" name="au" value="<?= e($p['au']) ?>"></label>

      <?php if ($equipe && $organisateurs): ?>
        <label class="rap-champ" for="f-org">Compte
          <select id="f-org" name="organisateur">
            <option value="">Toute la plateforme</option>
            <?php foreach ($organisateurs as $o): ?>
              <option value="<?= e($o['id']) ?>"
                <?= $portee['cle'] === 'organisateur' && $portee['auteur_id'] === $o['id'] ? ' selected' : '' ?>>
                <?= e($o['nom']) ?></option>
            <?php endforeach; ?>
          </select></label>
      <?php endif; ?>

      <?php if ($decors_choisis): ?>
        <label class="rap-champ" for="f-decor">Décor
          <select id="f-decor" name="decor">
            <option value="">Tous les décors</option>
            <?php foreach ($decors_choisis as $d): ?>
              <option value="<?= e((string) $d['slug']) ?>"
                <?= $portee['cle'] === 'decor' && (string) $portee['decor']['id'] === (string) $d['id']
                    ? ' selected' : '' ?>><?= e((string) $d['titre']) ?></option>
            <?php endforeach; ?>
          </select></label>
      <?php endif; ?>

      <button class="bouton petit" type="submit">Afficher</button>
      <?php if ($portee['cle'] !== 'plateforme' && $equipe): ?>
        <a class="bouton fant petit" href="<?= e(url('?p=rapports&periode=' . $p['cle'])) ?>">Tout voir</a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($portee['cle'] === 'campagne'): ?>
    <p class="aide" style="margin:0 0 14px">
      <?= e((string) $portee['detail']) ?>.
      <a href="<?= e(url('?p=regie-campagne&id=' . (string) $portee['campagne']['id'])) ?>">Ouvrir la campagne</a>
    </p>
  <?php endif; ?>

  <!-- ------------------- les tuiles ------------------- -->
  <div class="grille g4" style="margin-bottom:18px">
    <div class="stat p">
      <b><?= e($nb((int) $t['programmes'])) ?></b>
      <span>Messages programmés sur la période</span>
    </div>
    <div class="stat">
      <b><?= e($nb((int) $t['envoyes'])) ?></b>
      <span>Partis · <?= e($pc((float) $t['taux_envoi'])) ?> de la file</span>
    </div>
    <div class="stat<?= (int) $t['echecs'] > 0 ? ' o' : '' ?>">
      <b><?= e($nb((int) $t['echecs'])) ?></b>
      <span>Échecs · <?= e($pc((float) $t['taux_echec'])) ?></span>
    </div>
    <?php if ($portee['cle'] === 'campagne'): ?>
      <div class="stat"><b><?= e($nb((int) $t['ecartes'])) ?></b>
        <span>Écartés : désabonnés ou archivés</span></div>
      <div class="stat v"><b><?= e($nb((int) $t['ouverts'])) ?></b>
        <span>Ouvertures constatées, au moins</span></div>
      <div class="stat"><b><?= e($nb((int) $t['attente'])) ?></b>
        <span>Encore en attente d’envoi</span></div>
    <?php else: ?>
      <div class="stat v"><b><?= e($nb((int) $r['activite']['badges']['valeur'])) ?></b>
        <span>Badges créés<?= $r['activite']['badges']['variation'] === null ? ''
            : ' · ' . ($r['activite']['badges']['variation'] >= 0 ? '+' : '')
              . $r['activite']['badges']['variation'] . ' % vs période d’avant' ?></span></div>
      <div class="stat v"><b><?= e($nb((int) $r['activite']['presences']['valeur'])) ?></b>
        <span>Présences scannées<?= $r['activite']['presences']['variation'] === null ? ''
            : ' · ' . ($r['activite']['presences']['variation'] >= 0 ? '+' : '')
              . $r['activite']['presences']['variation'] . ' %' ?></span></div>
      <div class="stat"><b><?= e($nb((int) $r['activite']['vues']['valeur'])) ?></b>
        <span>Vues de décors</span></div>
    <?php endif; ?>
  </div>

  <!-- ------------------- par canal ------------------- -->
  <section class="carte" style="margin-bottom:18px">
    <h2>Par canal</h2>
    <p class="aide" style="margin:4px 0 0">
      Une ligne par canal, et une colonne qui reste vide quand personne ne peut la remplir.
      Les chiffres sont ceux de la file d’envoi, pas des estimations.
    </p>

    <?php if ((int) $t['programmes'] === 0): ?>
      <p class="aide" style="margin-top:14px">Aucun message n’a été programmé sur cette période.</p>
    <?php else: ?>

      <div class="rap-barre" role="img"
           aria-label="Répartition des envois par canal sur la période">
        <?php foreach ($envois['lignes'] as $l): ?>
          <?php if ((int) $l['programmes'] === 0) { continue; } ?>
          <i class="c-<?= e((string) $l['puce']) ?>" style="width:<?= round((float) $l['part'] * 100, 2) ?>%"></i>
        <?php endforeach; ?>
      </div>
      <div class="rap-legende">
        <?php foreach ($envois['lignes'] as $l): ?>
          <?php if ((int) $l['programmes'] === 0) { continue; } ?>
          <span><i class="pois c-<?= e((string) $l['puce']) ?>"></i>
            <?= e((string) $l['nom']) ?> · <?= e($nb((int) $l['programmes'])) ?>
            (<?= e($pc((float) $l['part'])) ?>)</span>
        <?php endforeach; ?>
      </div>

      <div class="tableau" style="margin-top:14px">
        <table>
          <thead>
            <tr>
              <th>Canal</th>
              <th class="num">Programmés</th>
              <th class="num">Partis</th>
              <th class="num">Échecs</th>
              <th class="num">Écartés</th>
              <th class="num">En attente</th>
              <th class="num">Ouvertures</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($envois['lignes'] as $l): ?>
              <?php if ((int) $l['programmes'] === 0) { continue; } ?>
              <tr>
                <td>
                  <span class="puce <?= e((string) $l['puce']) ?>"><?= e((string) $l['nom']) ?></span>
                  <span class="rap-preuve">« parti » = <?= e((string) $l['preuve']) ?></span>
                </td>
                <td class="num"><?= e($nb((int) $l['programmes'])) ?></td>
                <td class="num"><?= e($nb((int) $l['envoyes'])) ?></td>
                <td class="num<?= (int) $l['echecs'] > 0 ? ' rouge' : '' ?>"><?= e($nb((int) $l['echecs'])) ?></td>
                <td class="num"><?= e($nb((int) $l['ecartes'])) ?></td>
                <td class="num"><?= e($nb((int) $l['attente'])) ?></td>
                <td class="num">
                  <?php if ($l['lecture'] === null): ?>
                    <span class="rap-vide" title="Ce canal ne rend aucune preuve de lecture">non mesuré</span>
                  <?php else: ?>
                    <?= e($nb((int) $l['ouverts'])) ?>
                    <?php if ((int) $l['ouverts'] > 0): ?>
                      <small class="rap-vide"><?= e($pc((float) $l['taux_ouverture'])) ?></small>
                    <?php endif; ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <tr class="rap-total">
              <td>Total</td>
              <td class="num"><?= e($nb((int) $t['programmes'])) ?></td>
              <td class="num"><?= e($nb((int) $t['envoyes'])) ?></td>
              <td class="num"><?= e($nb((int) $t['echecs'])) ?></td>
              <td class="num"><?= e($nb((int) $t['ecartes'])) ?></td>
              <td class="num"><?= e($nb((int) $t['attente'])) ?></td>
              <td class="num"><?= e($nb((int) $t['ouverts'])) ?></td>
            </tr>
          </tbody>
        </table>
      </div>

      <p class="aide" style="margin-top:12px">
        <strong>Aucun de ces nombres ne dit « reçu ».</strong> Un serveur qui accepte un message ne
        garantit pas qu’il le remettra, et seul Telegram confirme la publication. Les ouvertures
        sont un minimum : les images sont souvent bloquées, et une lecture sans image ne se compte pas.
      </p>
    <?php endif; ?>
  </section>

  <!-- ------------------- ce qui n'est pas arrivé ------------------- -->
  <?php if ($r['echecs']): ?>
    <section class="carte" style="margin-bottom:18px">
      <div class="rangee" style="justify-content:space-between;align-items:flex-start">
        <div>
          <h2>Ce qui n’est pas arrivé</h2>
          <p class="aide" style="margin:4px 0 0">Regroupé par motif. Ce qui se relance et ce qui ne se
          relancera jamais ne se traitent pas de la même façon.</p>
        </div>
        <span class="pastille echec"><?= e($nb((int) $t['echecs'])) ?> échec<?= (int) $t['echecs'] > 1 ? 's' : '' ?></span>
      </div>
      <div class="tableau" style="margin-top:12px">
        <table>
          <thead>
            <tr><th>Canal</th><th>Code</th><th>Ce que ça veut dire</th>
                <th class="num">Nombre</th><th>Suite</th></tr>
          </thead>
          <tbody>
            <?php foreach ($r['echecs'] as $ec): ?>
              <tr>
                <td><span class="puce <?= e((string) $ec['puce']) ?>"><?= e((string) $ec['canal_nom']) ?></span></td>
                <td class="mono"><?= e((string) ($ec['code'] ?: 'sans code')) ?></td>
                <td>
                  <?= e((string) $ec['explication']) ?>
                  <?php if (($ec['message'] ?? '') !== ''): ?>
                    <span class="rap-brut"><?= e(mb_substr((string) $ec['message'], 0, 90)) ?></span>
                  <?php endif; ?>
                </td>
                <td class="num"><?= e($nb((int) $ec['n'])) ?></td>
                <td>
                  <?php if ($ec['mortel']): ?>
                    <span class="rap-mortel">À écarter du carnet</span>
                  <?php elseif ($ec['reprenable']): ?>
                    <span class="rap-repris">Se relance</span>
                  <?php else: ?>
                    <span class="rap-vide">Sans suite</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="aide" style="margin-top:12px">
        Les relances se lancent depuis la campagne concernée, où l’on voit les destinataires un par un.
      </p>
    </section>
  <?php endif; ?>

  <div class="grille g2" style="margin-bottom:18px;align-items:start">

    <!-- ------------------- l'entonnoir ------------------- -->
    <?php if ($r['entonnoir']): ?>
      <section class="carte">
        <h2>De la vue à la porte</h2>
        <p class="aide" style="margin:4px 0 14px">Ce que devient une visite, sur la période.
        Le pourcentage est le passage depuis l’étape d’avant : c’est là que ça fuit.</p>
        <?php foreach ($r['entonnoir'] as $pas): ?>
          <div class="marche">
            <div class="haut">
              <span><?= e((string) $pas['nom']) ?></span>
              <b><?= e($nb((int) $pas['n'])) ?></b>
            </div>
            <div class="rail"><i style="width:<?= round((float) $pas['part'] * 100, 2) ?>%"></i></div>
            <?php if ($pas['passage'] !== null): ?>
              <span class="taux"><?= e($pc((float) $pas['passage'])) ?> de l’étape précédente</span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <!-- ------------------- l'activité ------------------- -->
    <?php if ($portee['cle'] !== 'campagne'): ?>
      <section class="carte">
        <h2>Comparé à la période d’avant</h2>
        <p class="aide" style="margin:4px 0 14px">Même durée, juste avant :
        du <?= e(date_fr($p['avant_debut'])) ?> au <?= e(date_fr($p['debut'])) ?>.</p>
        <div class="tableau">
          <table>
            <thead><tr><th>Mesure</th><th class="num">Période</th>
                       <th class="num">Avant</th><th class="num">Variation</th></tr></thead>
            <tbody>
              <?php foreach ($r['activite'] as $a): ?>
                <tr>
                  <td><?= e((string) $a['titre']) ?></td>
                  <td class="num"><?= e($nb((int) $a['valeur'])) ?></td>
                  <td class="num"><?= e($nb((int) $a['avant'])) ?></td>
                  <td class="num">
                    <?php if ($a['variation'] === null): ?>
                      <span class="rap-vide">sans comparaison</span>
                    <?php else: ?>
                      <span class="<?= (int) $a['variation'] >= 0 ? 'rap-haut' : 'rap-bas' ?>">
                        <?= (int) $a['variation'] >= 0 ? '+' : '' ?><?= (int) $a['variation'] ?> %</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>
  </div>

  <!-- ------------------- les campagnes ------------------- -->
  <?php if ($r['campagnes']): ?>
    <section class="carte" style="margin-bottom:18px">
      <h2><?= $portee['cle'] === 'decor' ? 'Les rappels de ce décor' : 'Les campagnes de la période' ?></h2>
      <p class="aide" style="margin:4px 0 12px">Dans l’ordre où elles sont parties. Les nombres sont
      ceux de la période : une campagne à cheval sur deux mois se lit sur les deux rapports, et la
      somme des lignes tombe juste sur le total plus haut.</p>
      <div class="tableau">
        <table>
          <thead>
            <tr><th>Campagne</th><th>Canaux</th><th class="num">Programmés</th>
                <th class="num">Partis</th><th class="num">Échecs</th><th class="num">Ouvertures</th></tr>
          </thead>
          <tbody>
            <?php foreach ($r['campagnes'] as $c): ?>
              <tr>
                <td>
                  <?php if (($c['moment'] ?? '') !== ''): ?>
                    <span class="rap-moment"><?= e((string) $c['moment']) ?></span>
                  <?php endif; ?>
                  <a href="<?= e($lien(['campagne' => (string) $c['id'], 'decor' => null,
                                        'organisateur' => null])) ?>"><?= e((string) $c['titre']) ?></a>
                  <span class="rap-quand">
                    <?php if (!empty($c['a_venir'])): ?><strong>À venir</strong> · <?php endif; ?>
                    <?= e(date_fr((string) $c['quand'])) ?>
                    <?php if (($c['decor_titre'] ?? '') !== '' && $portee['cle'] !== 'decor'): ?>
                      · <?= e((string) $c['decor_titre']) ?>
                    <?php endif; ?>
                  </span>
                </td>
                <td>
                  <?php foreach ($c['canaux_lus'] as $cc): ?>
                    <span class="puce <?= e((string) $cc['puce']) ?>"><?= e((string) $cc['nom']) ?></span>
                  <?php endforeach; ?>
                </td>
                <td class="num"><?= e($nb((int) $c['programmes'])) ?></td>
                <td class="num"><?= e($nb((int) $c['envoyes'])) ?></td>
                <td class="num<?= (int) $c['echecs'] > 0 ? ' rouge' : '' ?>"><?= e($nb((int) $c['echecs'])) ?></td>
                <td class="num"><?= e($nb((int) $c['ouverts'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>

  <!-- ------------------- le sondage du lendemain ------------------- -->
  <?php if ((int) $r['sondage']['reponses'] > 0): ?>
    <?php $so = $r['sondage']; ?>
    <section class="carte" style="margin-bottom:18px">
      <h2>Ce qu’ils en ont pensé</h2>
      <p class="aide" style="margin:4px 0 14px">
        <?= e($nb((int) $so['reponses'])) ?> réponse<?= (int) $so['reponses'] > 1 ? 's' : '' ?>
        <?php if ((int) $so['partis'] > 0): ?>
          sur <?= e($nb((int) $so['partis'])) ?> message<?= (int) $so['partis'] > 1 ? 's' : '' ?> partis
        <?php endif; ?>
        · un jeton, une réponse · les notes sont anonymes, et le restent.
      </p>

      <div class="grille g3" style="margin-bottom:16px">
        <div class="stat p"><b><?= $so['moyenne'] === null ? '<span class="rap-vide">non mesurée</span>'
            : e(number_format((float) $so['moyenne'], 1, ',', ' ')) ?></b>
          <span>Note moyenne sur 5</span></div>
        <div class="stat v"><b><?= $so['revient'] === null ? '<span class="rap-vide">sans réponse</span>'
            : e($pc((float) $so['revient'])) ?></b>
          <span>Reviendront l’an prochain</span></div>
        <div class="stat"><b><?= $so['taux'] === null ? '<span class="rap-vide">sans comparaison</span>'
            : e($pc((float) $so['taux'])) ?></b>
          <span>Ont répondu</span></div>
      </div>

      <?php foreach ($so['distribution'] as $note => $combien): ?>
        <div class="marche">
          <div class="haut">
            <span><?= (int) $note ?> · <?= e(SONDAGE_NOTES[$note]) ?></span>
            <b><?= e($nb((int) $combien)) ?></b>
          </div>
          <div class="rail"><i style="width:<?= (int) $so['reponses'] > 0
              ? round($combien / (int) $so['reponses'] * 100, 2) : 0 ?>%"></i></div>
        </div>
      <?php endforeach; ?>

      <?php if ($so['mots']): ?>
        <h3 style="margin:20px 0 8px">Les mots, en entier</h3>
        <p class="aide" style="margin:0 0 12px">
          C’est ce qui se lit en premier. <?= e($nb(count($so['mots']))) ?>
          personne<?= count($so['mots']) > 1 ? 's ont' : ' a' ?> écrit quelque chose<?php
          if (count($so['mots']) > SONDAGE_MOTS): ?>, dont voici les
          <?= (int) SONDAGE_MOTS ?> dernières — le PDF les porte toutes<?php endif; ?>.
        </p>
        <div class="sond-mots">
          <?php foreach (array_slice($so['mots'], 0, SONDAGE_MOTS) as $m): ?>
            <blockquote class="sond-mot">
              <p><?= e((string) $m['mot']) ?></p>
              <footer><?= (int) $m['note'] ?>/5 ·
                <?= (int) $m['venu'] === 1 ? 'est venu' : 'n’est pas venu' ?> ·
                <?= e(date_fr((string) $m['cree_le'])) ?></footer>
            </blockquote>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <!-- ------------------- les décors ------------------- -->
  <?php if ($r['decors']): ?>
    <section class="carte" style="margin-bottom:18px">
      <h2>Les décors, classés sur la présence</h2>
      <p class="aide" style="margin:4px 0 12px">Pas sur les vues : un décor très vu qui ne remplit pas
      la salle est exactement celui qu’on croit bon et qui ne l’est pas.</p>
      <div class="tableau">
        <table>
          <thead>
            <tr><th>Décor</th><th class="num">Vues</th><th class="num">Badges</th>
                <th class="num">Téléchargés</th><th class="num">Présents</th><th class="num">Taux</th>
                <th>À qui écrire</th></tr>
          </thead>
          <tbody>
            <?php foreach ($r['decors'] as $d): ?>
              <tr>
                <td>
                  <a href="<?= e($lien(['decor' => (string) $d['slug'], 'campagne' => null,
                                        'organisateur' => null])) ?>"><?= e((string) $d['titre']) ?></a>
                  <?php if (($d['evenement_le'] ?? null)): ?>
                    <span class="rap-quand"><?= e(date_fr((string) $d['evenement_le'])) ?></span>
                  <?php endif; ?>
                </td>
                <td class="num"><?= e($nb((int) $d['vues'])) ?></td>
                <td class="num"><?= e($nb((int) $d['badges'])) ?></td>
                <td class="num"><?= e($nb((int) $d['telechargements'])) ?></td>
                <td class="num"><?= e($nb((int) $d['presences'])) ?></td>
                <td class="num">
                  <?= $d['taux_presence'] === null ? '<span class="rap-vide">sans objet</span>'
                      : e($pc((float) $d['taux_presence'])) ?>
                </td>
                <td>
                  <a href="<?= e(url('?p=segments&decor=' . rawurlencode((string) $d['slug']))) ?>">Les segments</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>

  <!-- ------------------- l'entrée, heure par heure ------------------- -->
  <?php if ($r['entree']): ?>
    <section class="carte" style="margin-bottom:18px">
      <h2>L’entrée, heure par heure</h2>
      <p class="aide" style="margin:4px 0 14px">Quand les badges ont été scannés à la porte.
      C’est le chiffre qui dit combien de scanners il fallait, et à quelle heure.</p>
      <?php foreach ($r['entree'] as $h): ?>
        <div class="marche">
          <div class="haut">
            <span><?= e((string) $h['heure']) ?></span>
            <b><?= e($nb((int) $h['n'])) ?></b>
          </div>
          <div class="rail"><i style="width:<?= round((float) $h['part'] * 100, 2) ?>%"></i></div>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <!-- ------------------- les liens courts ------------------- -->
  <?php if ($r['liens']): ?>
    <section class="carte" style="margin-bottom:18px">
      <h2>Les liens courts</h2>
      <p class="aide" style="margin:4px 0 12px">Attention au sens : ce compteur est
      <strong>cumulé depuis la création du lien</strong>, il ne se limite pas à la période.</p>
      <div class="tableau">
        <table>
          <thead><tr><th>Lien</th><th>Titre</th><th class="num">Clics</th><th>Dernier clic</th></tr></thead>
          <tbody>
            <?php foreach ($r['liens'] as $l): ?>
              <tr>
                <td class="mono"><?= e(lien_court_url((string) $l['code'])) ?></td>
                <td><?= e((string) ($l['titre'] ?: 'Sans titre')) ?></td>
                <td class="num"><?= e($nb((int) $l['clics'])) ?></td>
                <td><?= e(($l['dernier_clic'] ?? null) ? date_fr((string) $l['dernier_clic']) : 'jamais') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>

  <p class="aide" style="margin:0 0 30px">
    Rapport établi le <?= e(date_fr($r['edite_le'])) ?>.
    Le PDF porte les mêmes chiffres, avec la note qui explique ce que chacun prouve.
  </p>
</div>
