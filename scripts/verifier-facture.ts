/**
 * La facture, lue comme un lecteur de PDF la lit.
 *
 * Le produit fabrique ses PDF à la main, sans bibliothèque : il n'y a donc
 * personne pour dire qu'un fichier est mal formé. Un PDF cassé ne proteste
 * pas, il refuse de s'ouvrir — chez le client, trois semaines plus tard.
 *
 * Ce vérifieur écrit le lecteur qui manque. Il ne fait confiance à rien de
 * ce que produit `EcrivainPdf` : il relit l'en-tête, retrouve la table des
 * références croisées, vérifie que CHAQUE décalage tombe sur le bon objet,
 * décompresse le flux de la page et en ressort le texte. Si ce lecteur-là
 * y arrive, Acrobat y arrivera.
 *
 * Il éprouve aussi les deux règles comptables qui n'admettent aucun écart :
 * la somme hors taxes plus la TVA fait le total au franc près, et une
 * facture annulée l'est par un avoir qui porte son propre numéro.
 *
 *   npx tsx scripts/verifier-facture.ts
 */

import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtempSync, writeFileSync, rmSync, readFileSync } from 'node:fs';
import { inflateSync } from 'node:zlib';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const lancer = promisify(execFile);
const RACINE = process.cwd() + '/php';

let pass = 0;
let fail = 0;
const ok = (label: string, cond: boolean, detail = '') => {
  cond ? pass++ : fail++;
  console.log(`  ${cond ? '✓' : '✗'} ${label}${detail ? ' — ' + detail : ''}`);
};

/* ------------------------------------------------------------------ */
/* Un lecteur de PDF, écrit contre l'écrivain                          */
/* ------------------------------------------------------------------ */

interface Lu {
  version: string;
  objets: Map<number, { debut: number; corps: string }>;
  xref: number[];
  taille: number;
  racine: number;
  pages: number;
  textes: string[];
  images: number;
}

/**
 * Relit un PDF sans rien supposer de qui l'a écrit.
 *
 * On part de la FIN, comme le fait un vrai lecteur : `startxref` donne le
 * décalage de la table, la table donne celui de chaque objet, et le
 * `trailer` désigne le catalogue. Un seul octet d'écart dans la table et
 * le fichier est refusé — c'est précisément ce qu'on veut attraper.
 */
function lirePdf(buf: Buffer): Lu {
  const brut = buf.toString('latin1');
  const version = (/^%PDF-(\d\.\d)/.exec(brut) ?? [, ''])[1] ?? '';

  const dernier = brut.lastIndexOf('startxref');
  const depart = parseInt((/startxref\s+(\d+)/.exec(brut.slice(dernier)) ?? [, '0'])[1] ?? '0', 10);

  const table = brut.slice(depart);
  const lignes = [...table.matchAll(/(\d{10}) (\d{5}) ([nf])\s/g)];
  const xref = lignes.map((m) => parseInt(m[1]!, 10));

  const objets = new Map<number, { debut: number; corps: string }>();
  for (const m of brut.matchAll(/(\d+) 0 obj\n([\s\S]*?)\nendobj\n/g)) {
    objets.set(parseInt(m[1]!, 10), { debut: m.index!, corps: m[2]! });
  }

  const trailer = brut.slice(brut.lastIndexOf('trailer'));
  const taille = parseInt((/\/Size (\d+)/.exec(trailer) ?? [, '0'])[1] ?? '0', 10);
  const racine = parseInt((/\/Root (\d+) 0 R/.exec(trailer) ?? [, '0'])[1] ?? '0', 10);

  /* Le catalogue mène à l'arbre des pages, qui mène aux pages. */
  const cat = objets.get(racine)?.corps ?? '';
  const arbre = parseInt((/\/Pages (\d+) 0 R/.exec(cat) ?? [, '0'])[1] ?? '0', 10);
  const kids = objets.get(arbre)?.corps ?? '';
  const pages = [...kids.matchAll(/(\d+) 0 R/g)].map((m) => parseInt(m[1]!, 10));

  /* Le texte : on décompresse le flux de chaque page et on lit les Tj. */
  const textes: string[] = [];
  let images = 0;
  for (const n of pages) {
    const page = objets.get(n)?.corps ?? '';
    if (/\/XObject/.test(page)) {
      images += [...page.matchAll(/\/IM\d+ \d+ 0 R/g)].length;
    }
    const contenu = parseInt((/\/Contents (\d+) 0 R/.exec(page) ?? [, '0'])[1] ?? '0', 10);
    const o = objets.get(contenu);
    if (!o) { continue; }
    const deb = o.corps.indexOf('stream\n') + 7;
    const fin = o.corps.lastIndexOf('\nendstream');
    const flux = Buffer.from(o.corps.slice(deb, fin), 'latin1');
    const clair = /FlateDecode/.test(o.corps) ? inflateSync(flux).toString('latin1') : flux.toString('latin1');
    for (const m of clair.matchAll(/\(((?:[^()\\]|\\.)*)\)\s*Tj/g)) {
      textes.push(
        m[1]!.replace(/\\(\d{3})/g, (_, o8: string) => String.fromCharCode(parseInt(o8, 8)))
             .replace(/\\([()\\])/g, '$1')
      );
    }
  }
  // Windows-1252 vers Unicode : seuls les caractères qu'une facture emploie.
  const haut: Record<number, string> = {
    0x92: '’', 0x93: '“', 0x94: '”', 0x85: '…',
    0x96: '–', 0x97: '—', 0x9C: 'œ', 0x80: '€',
  };
  const decode = (s: string): string => [...s].map((c) => {
    const o = c.charCodeAt(0);
    return haut[o] ?? (o >= 0xA0 ? Buffer.from([o]).toString('latin1') : c);
  }).join('');

  return { version, objets, xref, taille, racine, pages: pages.length,
           textes: textes.map(decode), images };
}

/* ------------------------------------------------------------------ */
/* Le scénario PHP                                                     */
/* ------------------------------------------------------------------ */

const SCENARIO = `<?php
require ${JSON.stringify(RACINE)} . '/app/bootstrap.php';
foreach (['schema','auth','gabarit','depot','prevol','courriel','og','zip','sauvegarde',
          'texte','regie','carnet','images','push','qr','icones','avatars','journal',
          'abonnement','pdf','facture','api'] as $m) {
    require RACINE . "/app/$m.php";
}
assurer_schema();
$sortie = [];

facturation_reglages_poser([
    'fact_raison' => 'Wakabi Boost', 'fact_forme' => 'SARL au capital de',
    'fact_capital' => '1 000 000 F CFA', 'fact_adresse' => 'Boulevard du 13 Janvier, Lomé',
    'fact_rccm' => 'TG-LOM-2024-B-1234', 'fact_nif' => '1000123456',
    'fact_telephone' => '+228 90 00 00 00', 'fact_courriel' => 'facturation@wakabileguide.com',
    'fact_tva' => '18',
]);

/* ---- 1. l'arithmétique, sur des montants qui piègent ---- */
$sortie['tva'] = [];
foreach ([12000, 5000, 30000, 1, 7, 999, 123456, 0] as $ttc) {
    $m = facture_montants($ttc, 1800);
    $sortie['tva'][] = ['ttc' => $ttc, 'ht' => $m['ht'], 'tva' => $m['tva'],
                        'somme' => $m['ht'] + $m['tva']];
}
$sortie['sans_tva'] = facture_montants(12000, 0);

/* ---- 2. une facture, un avoir, et la numérotation ---- */
$id = creer_utilisateur(['email' => 'lena@maquis-akwaba.ci', 'mot_de_passe' => 'un-mot-de-passe-solide',
    'nom' => 'Léna Adjovi', 'role' => 'partenaire', 'formule' => 'croissance',
    'organisation' => 'Maquis Akwaba']);
$client = utilisateur_par_id($id);
$debut = maintenant();
$fin = echeance_prolonger($client, 30, $debut);
$client = utilisateur_par_id($id);

$f1 = facture_par_id(facture_poser($client, $debut, $fin, null,
    ['statut' => 'reglee', 'mode' => 'mobile_money', 'reference' => 'MM-8841203']));
$f2 = facture_par_id(facture_poser($client, $debut, $fin, null, ['statut' => 'a_regler']));
$sortie['numeros'] = [$f1['numero'], $f2['numero']];

/* Le document de $f1 est pris ICI, tant qu'elle est réglée : l'avoir plus
   bas la fera passer en « annulée », et sa ligne de règlement changera. */
file_put_contents(getenv('PDF_FACTURE'), facture_pdf($f1));
$sortie['a_regler'] = ['statut' => $f2['statut'], 'reglee_le' => $f2['reglee_le']];
$sortie['depuis'] = (string) $client['abonne_depuis'];

/* régler la seconde */
facture_regler((string) $f2['id'], 'virement', 'VIR-77');
$f2 = facture_par_id((string) $f2['id']);
$sortie['reglee'] = ['statut' => $f2['statut'], 'mode' => $f2['mode'], 'ref' => $f2['reference']];

/* l'avoir */
$av = facture_par_id(facture_avoir((string) $f1['id'], null, 'Erreur de montant'));
$sortie['avoir'] = ['numero' => $av['numero'], 'montant' => (int) $av['montant'],
                    'ht' => (int) $av['montant_ht'], 'vise' => $av['avoir_de'] === $f1['id'],
                    'origine' => facture_par_id((string) $f1['id'])['statut']];
$sortie['avoir_deux_fois'] = facture_avoir((string) $f1['id'], null, '') === null;

/* ---- 3. l'identité est FIGÉE à l'émission ---- */
facturation_reglages_poser(['fact_raison' => 'Autre Chose SARL', 'fact_tva' => '0']);
$relue = facture_emetteur(facture_par_id((string) $f1['id']));
$sortie['figee'] = ['raison' => $relue['fact_raison'], 'taux_garde' => (int) facture_par_id((string) $f1['id'])['tva_taux']];
$apres = facture_par_id(facture_poser(utilisateur_par_id($id), $debut, $fin, null, []));
$sortie['suivante'] = ['taux' => (int) $apres['tva_taux'], 'ht' => (int) $apres['montant_ht']];
facturation_reglages_poser(['fact_raison' => 'Wakabi Boost', 'fact_tva' => '18']);

/* ---- 4. l'export du comptable ---- */
$sortie['csv'] = factures_csv(factures_du_mois());

/* ---- 5. les documents eux-mêmes ---- */
file_put_contents(getenv('PDF_AVOIR'), facture_pdf($av));
file_put_contents(getenv('PDF_SANS_TVA'), facture_pdf($apres));

echo json_encode($sortie, JSON_UNESCAPED_UNICODE), "\\n";
`;

const main = async () => {
  console.log('\n━━ La facture, et le PDF qu’on remet ━━\n');

  const dossier = mkdtempSync(join(tmpdir(), 'wakabi-facture-'));
  try {
    writeFileSync(join(dossier, 'config.php'), `<?php return ['sgbd' => 'sqlite',
      'dossier_donnees' => ${JSON.stringify(join(dossier, 'donnees'))},
      'fichier' => ${JSON.stringify(join(dossier, 'donnees', 'wakabi.sqlite'))}];`);
    const script = join(dossier, 'scenario.php');
    writeFileSync(script, SCENARIO);

    const chemins = {
      facture: join(dossier, 'facture.pdf'),
      avoir: join(dossier, 'avoir.pdf'),
      sansTva: join(dossier, 'sans-tva.pdf'),
    };
    const { stdout } = await lancer('php', [script], {
      env: { ...process.env, WAKABI_CONFIG: join(dossier, 'config.php'),
             PDF_FACTURE: chemins.facture, PDF_AVOIR: chemins.avoir,
             PDF_SANS_TVA: chemins.sansTva },
      maxBuffer: 20 * 1024 * 1024,
    });
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const r = JSON.parse(stdout.trim().split('\n').pop() ?? '{}') as Record<string, any>;

    /* ---------------- l'arithmétique ---------------- */
    console.log('  ── ce que le client paie, et ce que le fisc lit ──');
    const lignes: { ttc: number; ht: number; tva: number; somme: number }[] = r.tva ?? [];
    ok('la somme tombe juste au franc près, sur huit montants',
       lignes.length === 8 && lignes.every((l) => l.somme === l.ttc),
       lignes.filter((l) => l.somme !== l.ttc).map((l) => l.ttc).join(', ') || '8 / 8');
    const douze = lignes.find((l) => l.ttc === 12000);
    ok('12 000 F TTC donnent 10 169 HT et 1 831 de TVA',
       douze?.ht === 10169 && douze?.tva === 1831, `${douze?.ht} + ${douze?.tva}`);
    ok('un montant nul ne fabrique pas de taxe',
       lignes.find((l) => l.ttc === 0)?.tva === 0);
    ok('à taux zéro, le hors taxes EST le total',
       r.sans_tva?.ht === 12000 && r.sans_tva?.tva === 0);

    /* ---------------- la vie d'une facture ---------------- */
    console.log('\n  ── émettre, régler, annuler ──');
    ok('les numéros se suivent, sans trou',
       r.numeros?.[0] === 'WB-' + new Date().getUTCFullYear() + '-0001'
       && r.numeros?.[1] === 'WB-' + new Date().getUTCFullYear() + '-0002',
       (r.numeros ?? []).join(' '));
    ok('une facture à régler n’est pas datée comme réglée',
       r.a_regler?.statut === 'a_regler' && r.a_regler?.reglee_le === null);
    ok('la régler note le mode ET la référence',
       r.reglee?.statut === 'reglee' && r.reglee?.mode === 'virement' && r.reglee?.ref === 'VIR-77');
    ok('le premier paiement date le début de l’abonnement',
       typeof r.depuis === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(r.depuis), r.depuis);

    ok('l’avoir porte son propre numéro, et le montant en négatif',
       r.avoir?.numero === 'WB-' + new Date().getUTCFullYear() + '-0003'
       && r.avoir?.montant === -12000 && r.avoir?.ht === -10169,
       `${r.avoir?.numero} · ${r.avoir?.montant}`);
    ok('il désigne la facture qu’il annule', r.avoir?.vise === true);
    ok('et celle-ci passe en « annulée », sans disparaître',
       r.avoir?.origine === 'annulee');
    ok('on n’annule pas deux fois la même facture', r.avoir_deux_fois === true);

    /* ---------------- ce qui est figé ---------------- */
    console.log('\n  ── un document déjà remis ne se réécrit pas ──');
    ok('changer la raison sociale ne touche pas les factures émises',
       r.figee?.raison === 'Wakabi Boost', r.figee?.raison);
    ok('changer le taux de TVA non plus', r.figee?.taux_garde === 1800, `${r.figee?.taux_garde}`);
    ok('mais la facture SUIVANTE prend le nouveau taux',
       r.suivante?.taux === 0 && r.suivante?.ht === 12000,
       `taux ${r.suivante?.taux}, HT ${r.suivante?.ht}`);

    /* ---------------- l'export ---------------- */
    const csv = String(r.csv ?? '');
    ok('l’export du comptable porte sa marque d’octets et ses colonnes',
       csv.startsWith('﻿') && csv.includes('Numero;Date;Statut'), csv.slice(1, 34));
    ok('il chiffre le hors taxes, la TVA et le total séparément',
       (csv.match(/"10169";"1831";"12000"/) ?? []).length > 0);

    /* ---------------- le PDF, relu comme un lecteur le lit ---------------- */
    console.log('\n  ── le PDF, relu octet par octet ──');
    const buf = readFileSync(chemins.facture);
    const pdf = lirePdf(buf);

    ok('le fichier s’annonce comme un PDF', pdf.version === '1.4', pdf.version);
    ok('il se termine par la marque de fin',
       buf.toString('latin1').trimEnd().endsWith('%%EOF'));
    ok('la table des références décrit tous les objets',
       pdf.taille === pdf.objets.size + 1 && pdf.xref.length === pdf.taille,
       `${pdf.objets.size} objets, Size ${pdf.taille}`);

    /**
     * Le contrôle qui compte vraiment.
     *
     * Un lecteur ne parcourt pas le fichier : il saute au décalage que la
     * table lui donne. Si l'un d'eux est faux d'un octet, le document est
     * refusé en entier — et rien dans le fichier ne le laisse deviner.
     */
    const faux = [...pdf.objets.entries()].filter(([n, o]) => pdf.xref[n] !== o.debut);
    ok('CHAQUE décalage tombe exactement sur son objet', faux.length === 0,
       faux.length ? `objet ${faux[0]![0]} annoncé en ${pdf.xref[faux[0]![0]]}, trouvé en ${faux[0]![1].debut}` : `${pdf.objets.size} vérifiés`);

    ok('une page, et une seule', pdf.pages === 1, `${pdf.pages}`);
    ok('le catalogue se laisse suivre jusqu’à elle', pdf.racine > 0 && pdf.pages > 0);
    ok('le logo y est incorporé, avec son masque de transparence',
       pdf.images >= 1 && /\/SMask \d+ 0 R/.test([...pdf.objets.values()].map((o) => o.corps).join('')),
       `${pdf.images} image(s)`);

    const texte = pdf.textes.join(' | ');
    ok('le numéro de facture s’y lit', texte.includes('WB-' + new Date().getUTCFullYear() + '-0001'));
    ok('le client aussi', texte.includes('Maquis Akwaba') && texte.includes('Léna Adjovi'),
       pdf.textes.filter((t) => t.includes('Akwaba') || t.includes('Adjovi')).join(' '));
    ok('les trois montants y sont, et le total porte la devise',
       texte.includes('10 169') && texte.includes('1 831') && texte.includes('12 000 F CFA'));
    ok('les mentions légales y sont', texte.includes('TG-LOM-2024-B-1234') && texte.includes('1000123456'));
    ok('le mode de règlement et sa référence aussi',
       texte.includes('Mobile Money') && texte.includes('MM-8841203'));

    /**
     * Les accents traversent le transcodage.
     *
     * Les polices standard écrivent en Windows-1252 : un « é » mal
     * transcodé ne casse pas le fichier, il imprime un autre caractère.
     * On relit donc le texte et on y cherche les accents français.
     */
    ok('les accents survivent au passage en Windows-1252',
       texte.includes('Léna') && texte.includes('Émise') && texte.includes('é'),
       pdf.textes.find((t) => t.includes('Émise')) ?? '');
    ok('aucun caractère n’a été remplacé par un point d’interrogation',
       !texte.includes('?'), (texte.match(/.{0,12}\?.{0,12}/) ?? [''])[0]);

    /* ---- l'avoir ---- */
    const av = lirePdf(readFileSync(chemins.avoir));
    const tav = av.textes.join(' | ');
    ok('l’avoir se présente comme un avoir, pas comme une facture',
       tav.includes('AVOIR') && !tav.includes('FACTURE'));
    ok('son montant est négatif, et le signe moins s’imprime',
       tav.includes('-12 000 F CFA'), av.textes.find((t) => t.includes('12 000')) ?? '');
    ok('il nomme la facture qu’il annule',
       tav.includes('WB-' + new Date().getUTCFullYear() + '-0001'));

    /* ---- sans TVA ---- */
    const st = lirePdf(readFileSync(chemins.sansTva));
    const tst = st.textes.join(' | ');
    ok('sans TVA, la facture ne montre ni hors taxes ni taxe',
       !tst.includes('Total hors taxes') && !/TVA \d/.test(tst));
    ok('elle porte la mention qui explique pourquoi',
       tst.includes('TVA non applicable'));
    ok('et sa colonne s’intitule « Montant », pas « Montant HT »',
       tst.includes('MONTANT') && !tst.includes('MONTANT HT'));
  } finally {
    rmSync(dossier, { recursive: true, force: true });
  }

  console.log(`\n━━ ${pass} réussis, ${fail} échoués ━━\n`);
  process.exit(fail === 0 ? 0 : 1);
};

void main();
