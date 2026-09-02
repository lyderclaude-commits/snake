/**
 * La double authentification, contre la norme.
 *
 * TOTP (RFC 6238) est écrit à la main dans `php/app/otp.php` : sans
 * bibliothèque à installer sur un mutualisé, mais aussi sans personne
 * pour dire si le calcul est juste. Le RFC publie des vecteurs d'essai —
 * une clé connue, des instants connus, des codes connus. C'est contre eux
 * qu'on se mesure, et non contre notre propre implémentation, qui serait
 * toujours d'accord avec elle-même.
 *
 * On y ajoute l'aller-retour base32 et la tolérance d'horloge : un
 * téléphone qui dérive de vingt secondes doit encore ouvrir la porte, et
 * un code d'il y a cinq minutes ne le doit plus.
 */

import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
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

/**
 * Les vecteurs du RFC 6238, section « Test Vectors ».
 *
 * La clé est la chaîne ASCII « 12345678901234567890 ». Les instants sont
 * en secondes ; le code attendu est celui de la tranche de trente
 * secondes qui les contient.
 */
const VECTEURS: Array<[number, string]> = [
  [59, '287082'],
  [1111111109, '081804'],
  [1111111111, '050471'],
  [1234567890, '005924'],
  [2000000000, '279037'],
];

const main = async () => {
  console.log('\n━━ La double authentification, contre le RFC 6238 ━━\n');

  const dossier = mkdtempSync(join(tmpdir(), 'wakabi-otp-'));
  const script = join(dossier, 'otp.php');
  writeFileSync(script, `<?php
    require ${JSON.stringify(RACINE)} . '/app/bootstrap.php';
    require RACINE . '/app/otp.php';
    $secret = otp_base32_encoder('12345678901234567890');
    $out = ['secret' => $secret, 'codes' => [], 'aller_retour' => [], 'tolerance' => []];
    foreach ([59, 1111111109, 1111111111, 1234567890, 2000000000] as $t) {
        $out['codes'][(string) $t] = otp_code($secret, intdiv($t, OTP_PAS));
    }
    /* L'aller-retour base32 sur des octets quelconques. */
    for ($i = 0; $i < 20; $i++) {
        $brut = random_bytes(20);
        $out['aller_retour'][] = otp_base32_decoder(otp_base32_encoder($brut)) === $brut;
    }
    /* La tolérance d'horloge, autour de maintenant. */
    $maintenant = intdiv(time(), OTP_PAS);
    foreach ([-2, -1, 0, 1, 2] as $d) {
        $out['tolerance'][(string) $d] = otp_verifier($secret, otp_code($secret, $maintenant + $d));
    }
    $out['six_chiffres'] = otp_verifier($secret, '12345');
    $out['vide'] = otp_verifier($secret, '');
    $out['uri'] = otp_uri(['email' => 'ama@wakabileguide.com'], $secret);
    echo json_encode($out), "\\n";
  `);

  const { stdout } = await lancer('php', [script], { maxBuffer: 4 * 1024 * 1024 });
  const r = JSON.parse(stdout.trim().split('\n').pop() ?? '{}') as Record<string, any>;

  ok('la clé d’essai s’écrit en base32', r.secret === 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', String(r.secret));
  for (const [t, attendu] of VECTEURS) {
    const rendu = r.codes?.[String(t)];
    ok(`le code de t=${t} est celui du RFC`, rendu === attendu,
       rendu === attendu ? String(rendu) : `${rendu} au lieu de ${attendu}`);
  }
  ok('base32 fait l’aller-retour sans perdre un octet',
     Array.isArray(r.aller_retour) && r.aller_retour.every(Boolean),
     `${(r.aller_retour ?? []).filter(Boolean).length}/20`);

  ok('une tranche d’avance est tolérée', r.tolerance?.['-1'] === true);
  ok('l’instant même est accepté', r.tolerance?.['0'] === true);
  ok('une tranche de retard est tolérée', r.tolerance?.['1'] === true);
  ok('deux tranches avant ne le sont plus', r.tolerance?.['-2'] === false);
  ok('deux tranches après non plus', r.tolerance?.['2'] === false);
  ok('un code trop court est refusé', r.six_chiffres === false);
  ok('un code vide est refusé', r.vide === false);
  ok('l’adresse otpauth nomme l’émetteur et le compte',
     typeof r.uri === 'string' && r.uri.startsWith('otpauth://totp/')
     && r.uri.includes('issuer=Wakabi%20Boost') && r.uri.includes('period=30'),
     String(r.uri).slice(0, 58) + '…');

  rmSync(dossier, { recursive: true, force: true });
  console.log(`\n━━ ${pass} réussis, ${fail} échoués ━━\n`);
  process.exit(fail === 0 ? 0 : 1);
};

void main();
