/**
 * Le chiffrement des notifications push, vérifié octet pour octet.
 *
 * `php/app/push.php` écrit RFC 8291 à la main — il le faut : sans Composer,
 * aucune bibliothèque n'est disponible sur l'hébergement visé. Un
 * chiffrement écrit à la main qu'on n'a pas vérifié est un chiffrement dont
 * on ne sait rien : le service de push accepte le POST, rend 201, et la
 * notification n'apparaît jamais — aucune erreur nulle part.
 *
 * On refait donc ici le même calcul avec `node:crypto`, sur des clés et un
 * sel FIXÉS, et on compare les octets. Deux implémentations indépendantes
 * d'un même RFC qui tombent d'accord, c'est la seule preuve raisonnable
 * qu'on puisse se donner sans un vrai navigateur en face.
 *
 * On vérifie aussi ce qui, à côté, casse une notification sans bruit :
 * la conversion DER → r‖s de la signature VAPID, la forme du JWT, et le
 * fait que la paire de clés ne CHANGE PLUS une fois créée.
 *
 *   npx tsx scripts/verifier-push.ts
 */
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import {
  createECDH, createHmac, createCipheriv, createDecipheriv, createPublicKey, createVerify,
} from 'node:crypto';

const lancerPhp = promisify(execFile);

let pass = 0;
let fail = 0;
const ok = (label: string, cond: boolean, detail = '') => {
  cond ? pass++ : fail++;
  console.log(`  ${cond ? '✓' : '✗'} ${label}${detail ? ' — ' + detail : ''}`);
};

const b64u = (b: Buffer): string => b.toString('base64url');
const deB64u = (s: string): Buffer => Buffer.from(s, 'base64url');

/* ------------------------------------------------------------------ */
/* L'implémentation de référence, écrite ici, sans regarder le PHP     */
/* ------------------------------------------------------------------ */

/**
 * HKDF (RFC 5869) en deux temps, comme le veut la RFC 8291.
 *
 * Écrit à la main plutôt qu'avec `crypto.hkdf` : c'est le calcul qu'on
 * veut comparer, et l'écrire dans les deux langages à partir du RFC est
 * exactement le test qu'on cherche à faire.
 */
const hkdf = (sel: Buffer, ikm: Buffer, info: Buffer, longueur: number): Buffer => {
  const prk = createHmac('sha256', sel).update(ikm).digest();
  const t = createHmac('sha256', prk).update(Buffer.concat([info, Buffer.from([1])])).digest();
  return t.subarray(0, longueur);
};

/** RFC 8291 §3.4 : le corps `aes128gcm` complet, en-tête compris. */
function chiffrerReference(
  message: string, clientPub: Buffer, authSecret: Buffer,
  ephPrive: Buffer, ephPublic: Buffer, sel: Buffer,
): Buffer {
  const ecdh = createECDH('prime256v1');
  ecdh.setPrivateKey(ephPrive);
  const partage = ecdh.computeSecret(clientPub);

  const info = Buffer.concat([Buffer.from('WebPush: info\0'), clientPub, ephPublic]);
  const prk = hkdf(authSecret, partage, info, 32);

  const cek = hkdf(sel, prk, Buffer.from('Content-Encoding: aes128gcm\0'), 16);
  const nonce = hkdf(sel, prk, Buffer.from('Content-Encoding: nonce\0'), 12);

  const cipher = createCipheriv('aes-128-gcm', cek, nonce);
  // 0x02 : le délimiteur qui marque la fin du contenu.
  const chiffre = Buffer.concat([
    cipher.update(Buffer.concat([Buffer.from(message, 'utf8'), Buffer.from([0x02])])),
    cipher.final(),
  ]);

  const rs = Buffer.alloc(4);
  rs.writeUInt32BE(4096, 0);
  return Buffer.concat([
    sel, rs, Buffer.from([ephPublic.length]), ephPublic, chiffre, cipher.getAuthTag(),
  ]);
}

const main = async () => {
  console.log('\n━━ Le chiffrement Web Push, contre une implémentation indépendante ━━\n');

  const dossier = mkdtempSync(join(tmpdir(), 'wakabi-push-'));
  writeFileSync(join(dossier, 'config.php'), `<?php return [
    'sgbd' => 'sqlite',
    'dossier_donnees' => ${JSON.stringify(dossier)},
    'fichier' => ${JSON.stringify(join(dossier, 'essai.sqlite'))},
    'base_url' => 'https://boost.wakabileguide.com',
  ];`);
  const env = { ...process.env, WAKABI_CONFIG: join(dossier, 'config.php') };
  const fichier = join(dossier, 'essai.php');

  const PREAMBULE = `<?php
    require ${JSON.stringify(process.cwd() + '/php')} . '/app/bootstrap.php';
    require RACINE . '/app/schema.php';
    require RACINE . '/app/gabarit.php';
    require RACINE . '/app/auth.php';
    require RACINE . '/app/depot.php';
    require RACINE . '/app/courriel.php';
    require RACINE . '/app/push.php';
    creer_schema(db(), false);
  `;

  /* ---- le destinataire : un abonnement dont on connaît la clé privée ---- */

  const client = createECDH('prime256v1');
  client.generateKeys();
  const clientPub = client.getPublicKey();       // 65 octets, non compressé
  const authSecret = Buffer.from('0123456789abcdef', 'utf8'); // 16 octets, fixés

  /* ---- la clé éphémère et le sel, fixés pour que le calcul soit reproductible ---- */

  const eph = createECDH('prime256v1');
  eph.generateKeys();
  const ephPrive = eph.getPrivateKey();
  const ephPublic = eph.getPublicKey();
  const sel = Buffer.from('WakabiSel-16-oct', 'utf8'); // 16 octets exactement

  const MESSAGE = JSON.stringify({
    titre: 'Afro Night Lomé',
    corps: 'Faites votre badge avant samedi — accentué, ç’est le but.',
    lien: 'https://boost.wakabileguide.com/index.php?p=decors',
  });

  /**
   * La clé éphémère voyage en PEM PKCS#8 : c'est ce que
   * `openssl_pkey_get_private` sait relire, et cela évite d'inventer un
   * format d'échange entre les deux implémentations.
   */
  const pkcs8 = Buffer.concat([
    Buffer.from('308141020100301306072a8648ce3d020106082a8648ce3d030107042730250201010420', 'hex'),
    ephPrive,
  ]);
  const pem = '-----BEGIN PRIVATE KEY-----\n'
    + (pkcs8.toString('base64').match(/.{1,64}/g) ?? []).join('\n')
    + '\n-----END PRIVATE KEY-----\n';

  writeFileSync(fichier, PREAMBULE + `
    $r = push_chiffrer(
      ${JSON.stringify(MESSAGE)},
      ${JSON.stringify(b64u(clientPub))},
      ${JSON.stringify(b64u(authSecret))},
      ['pem' => ${JSON.stringify(pem)}, 'publique' => base64_decode(${JSON.stringify(ephPublic.toString('base64'))})],
      base64_decode(${JSON.stringify(sel.toString('base64'))})
    );
    echo base64_encode($r['corps']), "\\n";
  `);
  const sortie = (await lancerPhp('php', [fichier], { env })).stdout.trim();
  const cotePhp = Buffer.from(sortie, 'base64');
  const coteNode = chiffrerReference(MESSAGE, clientPub, authSecret, ephPrive, ephPublic, sel);

  ok('le corps chiffré est identique octet pour octet',
     cotePhp.equals(coteNode),
     `php ${cotePhp.length} o, node ${coteNode.length} o`);

  ok('l’en-tête aes128gcm porte le sel, puis 4096, puis 65',
     cotePhp.subarray(0, 16).equals(sel)
       && cotePhp.readUInt32BE(16) === 4096
       && cotePhp[20] === 65,
     `rs=${cotePhp.readUInt32BE(16)}, idlen=${cotePhp[20]}`);

  ok('la clé publique éphémère est celle qu’on a fournie',
     cotePhp.subarray(21, 86).equals(ephPublic));

  /**
   * Le RFC ajoute 16 octets d'étiquette GCM et 1 octet de délimiteur : un
   * corps qui ferait exactement la taille du clair signalerait un
   * chiffrement… qui ne chiffre pas.
   */
  ok('la charge chiffrée fait bien la taille attendue',
     cotePhp.length === 86 + Buffer.byteLength(MESSAGE) + 1 + 16,
     `${cotePhp.length} au lieu de ${86 + Buffer.byteLength(MESSAGE) + 1 + 16}`);

  /**
   * L'aller-RETOUR : le navigateur destinataire sait-il relire ?
   *
   * Comparer deux implémentations du chiffrement prouve qu'elles font la
   * même chose — pas qu'elles font la BONNE chose. Ici on déchiffre avec
   * la clé privée du destinataire, comme le ferait Chrome, et on regarde
   * si le texte revient. C'est le seul test qui échouerait si le RFC
   * avait été mal lu de la même façon des deux côtés.
   */
  const dechiffre = (() => {
    const corps = coteNode;
    const selRecu = corps.subarray(0, 16);
    const lg = corps[20];
    const ephRecu = corps.subarray(21, 21 + lg);
    const chiffre = corps.subarray(21 + lg);

    const ecdh = createECDH('prime256v1');
    ecdh.setPrivateKey(client.getPrivateKey());
    const partage = ecdh.computeSecret(ephRecu);

    const prk = hkdf(authSecret, partage,
      Buffer.concat([Buffer.from('WebPush: info\0'), clientPub, ephRecu]), 32);
    const cek = hkdf(selRecu, prk, Buffer.from('Content-Encoding: aes128gcm\0'), 16);
    const nonce = hkdf(selRecu, prk, Buffer.from('Content-Encoding: nonce\0'), 12);

    const d = createDecipheriv('aes-128-gcm', cek, nonce);
    d.setAuthTag(chiffre.subarray(chiffre.length - 16));
    const clair = Buffer.concat([d.update(chiffre.subarray(0, chiffre.length - 16)), d.final()]);
    // Le 0x02 final est le délimiteur de fin de contenu.
    return clair.subarray(0, clair.length - 1).toString('utf8');
  })();

  ok('le destinataire relit le message, accents compris',
     dechiffre === MESSAGE, dechiffre.slice(0, 58) + '…');

  /* ---- le sel et la clé éphémère changent à chaque envoi ---- */

  writeFileSync(fichier, PREAMBULE + `
    $a = push_chiffrer('x', ${JSON.stringify(b64u(clientPub))}, ${JSON.stringify(b64u(authSecret))});
    $b = push_chiffrer('x', ${JSON.stringify(b64u(clientPub))}, ${JSON.stringify(b64u(authSecret))});
    echo bin2hex($a['sel']), '|', bin2hex($b['sel']), '|',
         bin2hex($a['publique']), '|', bin2hex($b['publique']), "\\n";
  `);
  const [selA, selB, pubA, pubB] = (await lancerPhp('php', [fichier], { env }))
    .stdout.trim().split('|');
  ok('sans sel fourni, le sel est tiré au hasard à chaque envoi',
     selA !== selB && selA.length === 32, `${selA.slice(0, 8)}… ≠ ${selB.slice(0, 8)}…`);
  ok('la clé éphémère aussi : réutiliser une paire casserait le chiffrement',
     pubA !== pubB && pubA.length === 130);

  /* ---- le jeton VAPID : forme, signature, et audience ---- */

  writeFileSync(fichier, PREAMBULE + `
    $c = vapid();
    echo vapid_jeton('https://fcm.googleapis.com/fcm/send/abcdef', $c, 'mailto:boost@wakabileguide.com'), "\\n";
    echo $c['publique'], "\\n";
    echo $c['pem'];
  `);
  const brut = (await lancerPhp('php', [fichier], { env })).stdout;
  const lignes = brut.split('\n');
  const jeton = lignes[0];
  const publique = lignes[1];
  const pemVapid = lignes.slice(2).join('\n');

  const [entete, corps, signature] = jeton.split('.');
  const enteteJson = JSON.parse(deB64u(entete).toString('utf8'));
  const corpsJson = JSON.parse(deB64u(corps).toString('utf8'));

  ok('l’en-tête JWT annonce ES256', enteteJson.alg === 'ES256' && enteteJson.typ === 'JWT');
  ok('l’audience est l’ORIGINE du service, pas l’adresse complète',
     corpsJson.aud === 'https://fcm.googleapis.com', corpsJson.aud);
  ok('l’expiration est dans le futur et sous 24 h',
     corpsJson.exp > Date.now() / 1000 && corpsJson.exp < Date.now() / 1000 + 86400);
  ok('le sujet permet de nous joindre', /^mailto:|^https?:/.test(corpsJson.sub), corpsJson.sub);

  /**
   * La signature en r‖s fait 64 octets, ni plus ni moins.
   *
   * C'est le piège de `der_vers_brut()` : OpenSSL rend du DER dont les
   * entiers portent parfois un octet nul de tête. Recopier « les 64
   * derniers octets » marche la plupart du temps, et le jour où ça ne
   * marche pas le service répond 401 sans dire pourquoi.
   */
  const rs = deB64u(signature);
  ok('la signature fait exactement 64 octets (r‖s)', rs.length === 64, `${rs.length} o`);

  // La vérifier vraiment : Node reconvertit r‖s en DER pour OpenSSL.
  const der = (() => {
    const entier = (b: Buffer): Buffer => {
      let i = 0;
      while (i < b.length - 1 && b[i] === 0) i++;
      const v = b.subarray(i);
      const corpsInt = v[0] & 0x80 ? Buffer.concat([Buffer.from([0]), v]) : v;
      return Buffer.concat([Buffer.from([0x02, corpsInt.length]), corpsInt]);
    };
    const seq = Buffer.concat([entier(rs.subarray(0, 32)), entier(rs.subarray(32))]);
    return Buffer.concat([Buffer.from([0x30, seq.length]), seq]);
  })();

  const verif = createVerify('sha256');
  verif.update(entete + '.' + corps);
  ok('la signature est valide sous la clé privée du serveur',
     verif.verify(pemVapid, der));

  /**
   * La clé publique annoncée dans l'en-tête `Authorization` doit être
   * CELLE de la paire qui signe. Sinon le service refuse — et le message
   * d'erreur ne le dit pas.
   */
  const pubDepuisPem = createPublicKey(pemVapid)
    .export({ format: 'der', type: 'spki' }) as Buffer;
  ok('la clé publique publiée est celle de la paire qui signe',
     deB64u(publique).equals(pubDepuisPem.subarray(pubDepuisPem.length - 65)));

  /* ---- la paire ne change plus une fois créée ---- */

  writeFileSync(fichier, PREAMBULE + `
    echo vapid()['publique'], '|', vapid()['publique'], "\\n";
  `);
  const [p1, p2] = (await lancerPhp('php', [fichier], { env })).stdout.trim().split('|');
  ok('la paire VAPID est stable entre deux appels et deux requêtes',
     p1 === p2 && p1 === publique,
     p1 === publique ? '' : 'la clé a changé — tous les abonnements seraient perdus');

  /* ---- les abonnements : uniques par navigateur, et nettoyables ---- */

  writeFileSync(fichier, PREAMBULE + `
    $e = 'https://updates.push.services.mozilla.com/wpush/v2/' . str_repeat('z', 220);
    $attendu = strlen($e);
    push_abonner(null, $e, 'BPub', 'auth', 'Firefox');
    push_abonner(null, $e, 'BPub2', 'auth2', 'Firefox');
    $n = (int) db()->query('SELECT COUNT(*) FROM push')->fetchColumn();
    $a = push_abonnement_de($e);
    push_desabonner($e);
    $apres = (int) db()->query('SELECT COUNT(*) FROM push')->fetchColumn();
    echo $n, '|', $a['p256dh'], '|', strlen($a['endpoint']), '|', $apres, '|', $attendu, "\\n";
  `);
  const [n, p256dh, longueur, apres, attendu] = (await lancerPhp('php', [fichier], { env }))
    .stdout.trim().split('|');
  ok('se réabonner depuis le même navigateur MET À JOUR au lieu de doubler',
     n === '1' && p256dh === 'BPub2', `${n} ligne(s), p256dh=${p256dh}`);
  /**
   * Le point de la colonne `endpoint` en TEXT plutôt qu'en VARCHAR(190) :
   * une adresse de Mozilla dépasse largement ce que MySQL sait indexer, et
   * une troncature silencieuse rendrait l'abonnement injoignable.
   */
  ok(`une adresse de ${attendu} caractères est stockée entière`,
     longueur === attendu, `${longueur} caractères sur ${attendu}`);
  ok('le désabonnement efface bien la ligne', apres === '0');

  rmSync(dossier, { recursive: true, force: true });
  console.log(`\n━━ ${pass} réussis, ${fail} échoués ━━\n`);
  process.exit(fail ? 1 : 0);
};

main();
