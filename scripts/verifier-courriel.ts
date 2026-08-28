/**
 * Le client SMTP, mis à l'épreuve d'un vrai dialogue.
 *
 * Un envoi d'e-mail ne se teste pas par la recette du navigateur : rien
 * n'est visible à l'écran, et le seul verdict honnête est celui du serveur
 * d'en face. On en ouvre donc un — minimal, mais qui parle le protocole —
 * et on vérifie ce que PHP lui dit vraiment : l'ordre des commandes,
 * l'authentification, les en-têtes, l'encodage d'un sujet accentué et la
 * protection des points en début de ligne.
 *
 *   npx tsx scripts/verifier-courriel.ts
 */
import { createServer, type Socket } from 'node:net';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/**
 * PHP tourne de façon ASYNCHRONE, et ce n'est pas un détail de style.
 *
 * Avec `execFileSync`, Node bloque sa boucle d'événements pendant tout
 * l'appel : le noyau accepte bien la connexion TCP, mais le serveur
 * factice ne peut pas envoyer sa bannière avant la fin du processus PHP —
 * qui, lui, l'attend. Les deux se regardent jusqu'au délai d'attente.
 */
const lancerPhp = promisify(execFile);

let pass = 0;
let fail = 0;
const ok = (label: string, cond: boolean, detail = '') => {
  cond ? pass++ : fail++;
  console.log(`  ${cond ? '✓' : '✗'} ${label}${detail ? ' — ' + detail : ''}`);
};

interface Session {
  commandes: string[];
  message: string;
}

/**
 * Un serveur SMTP de complaisance.
 *
 * Il accepte tout, mais il NOTE tout : c'est la trace qu'on inspecte
 * ensuite. `AUTH LOGIN` est annoncé pour que le client s'authentifie —
 * sans annonce, il aurait raison de s'en passer.
 */
function serveurFactice(port: number): { sessions: Session[]; fermer: () => Promise<void> } {
  const sessions: Session[] = [];
  const serveur = createServer((socket: Socket) => {
    const session: Session = { commandes: [], message: '' };
    sessions.push(session);
    let tampon = '';
    let dansData = false;
    // AUTH LOGIN est un dialogue en DEUX temps : « Username: », la réponse,
    // « Password: », la réponse, et seulement alors le 235. Répondre 235 dès
    // le premier envoi ferait passer un client qui n'a pas donné son mot de
    // passe — un serveur de complaisance qui valide trop ne prouve rien.
    let attenduAuth: '' | 'utilisateur' | 'motdepasse' = '';

    socket.write('220 wakabi-essai ESMTP\r\n');
    socket.on('data', (bloc) => {
      tampon += bloc.toString('utf8');
      let i: number;
      while ((i = tampon.indexOf('\r\n')) >= 0) {
        const ligne = tampon.slice(0, i);
        tampon = tampon.slice(i + 2);

        if (dansData) {
          if (ligne === '.') {
            dansData = false;
            socket.write('250 2.0.0 Ok\r\n');
          } else {
            session.message += ligne + '\n';
          }
          continue;
        }

        session.commandes.push(ligne);
        const haut = ligne.toUpperCase();
        if (haut.startsWith('EHLO')) socket.write('250-wakabi-essai\r\n250-SIZE 10240000\r\n250 AUTH LOGIN PLAIN\r\n');
        else if (haut.startsWith('HELO')) socket.write('250 wakabi-essai\r\n');
        else if (haut === 'AUTH LOGIN') { attenduAuth = 'utilisateur'; socket.write('334 VXNlcm5hbWU6\r\n'); }
        else if (attenduAuth === 'utilisateur') { attenduAuth = 'motdepasse'; socket.write('334 UGFzc3dvcmQ6\r\n'); }
        else if (attenduAuth === 'motdepasse') { attenduAuth = ''; socket.write('235 2.7.0 Authentication successful\r\n'); }
        else if (haut.startsWith('MAIL FROM')) socket.write('250 2.1.0 Ok\r\n');
        else if (haut.startsWith('RCPT TO')) socket.write('250 2.1.5 Ok\r\n');
        else if (haut === 'DATA') { dansData = true; socket.write('354 End data with <CR><LF>.<CR><LF>\r\n'); }
        else if (haut === 'QUIT') { socket.write('221 2.0.0 Bye\r\n'); socket.end(); }
        else socket.write('502 5.5.2 Commande inconnue\r\n');
      }
    });
    socket.on('error', () => { /* le client raccroche : sans intérêt ici */ });
  });

  serveur.listen(port, '127.0.0.1');
  return {
    sessions,
    fermer: () => new Promise((r) => serveur.close(() => r())),
  };
}

const PORT = 3925;

const main = async () => {
  console.log('\n━━ Le client SMTP face à un serveur ━━\n');
  const faux = serveurFactice(PORT);
  await new Promise((r) => setTimeout(r, 250));

  // Une installation jetable : la table `reglages` vit dans une base, et on
  // ne veut surtout pas écrire dans celle de développement.
  const dossier = mkdtempSync(join(tmpdir(), 'wakabi-smtp-'));
  writeFileSync(join(dossier, 'config.php'), `<?php return [
    'sgbd' => 'sqlite',
    'dossier_donnees' => ${JSON.stringify(dossier)},
    'fichier' => ${JSON.stringify(join(dossier, 'essai.sqlite'))},
    'base_url' => 'https://boost.wakabileguide.com',
  ];`);

  // `WAKABI_CONFIG` : l'essai a sa propre base, jamais celle du développement.
  const env = { ...process.env, WAKABI_CONFIG: join(dossier, 'config.php') };

  /** Le même préambule pour chaque petit script : charger l'application. */
  const PREAMBULE = `<?php
    require ${JSON.stringify(process.cwd() + '/php')} . '/app/bootstrap.php';
    require RACINE . '/app/schema.php';
    require RACINE . '/app/gabarit.php';
    require RACINE . '/app/auth.php';
    require RACINE . '/app/depot.php';
    require RACINE . '/app/courriel.php';
  `;

  const script = PREAMBULE + `
    creer_schema(db(), false);
    reglages_bdd_poser([
      'smtp_hote' => '127.0.0.1',
      'smtp_port' => '${PORT}',
      'smtp_securite' => 'aucune',
      'smtp_utilisateur' => 'boost@wakabileguide.com',
      'smtp_motdepasse' => 'secret-2026',
      'courriel_expediteur' => 'boost@wakabileguide.com',
      'courriel_nom' => 'Wakabi Boost',
    ]);
    var_dump(courriel_branche());
    $r = courriel_mis_en_page(
      'ama@exemple.tg', 'Ama Koffi',
      'Décor approuvé — soirée à Lomé',
      'Votre décor est en ligne',
      "Bravo !\\n\\n. Une ligne qui commence par un point.",
      'https://boost.wakabileguide.com/index.php?p=partenaire',
      'Voir mes campagnes'
    );
    echo $r['ok'] ? 'ENVOI-OK' : 'ENVOI-KO:' . $r['message'], "\\n";
  `;
  const fichier = join(dossier, 'essai.php');
  writeFileSync(fichier, script);

  let sortie = '';
  try {
    const r = await lancerPhp('php', ['-d', 'error_reporting=E_ALL', fichier], { env });
    sortie = r.stdout + r.stderr;
  } catch (e: any) {
    sortie = (e.stdout ?? '') + (e.stderr ?? '');
  }

  ok('le transport se déclare branché', sortie.includes('bool(true)'), sortie.split('\n')[0]);
  ok('PHP annonce un envoi réussi', sortie.includes('ENVOI-OK'),
     sortie.match(/ENVOI-KO:.*/)?.[0] ?? '');

  await new Promise((r) => setTimeout(r, 250));
  const s = faux.sessions[0];
  ok('le serveur a bien vu une session', !!s, `${faux.sessions.length} session(s)`);

  if (s) {
    const cmds = s.commandes;
    const verbes = cmds.map((c) => c.split(' ')[0].toUpperCase());
    ok('EHLO ouvre le dialogue', verbes[0] === 'EHLO', cmds[0]);
    ok('EHLO annonce un nom de domaine', /EHLO \S+\.\S+/.test(cmds[0] ?? ''), cmds[0]);
    ok('l’authentification a lieu', cmds.includes('AUTH LOGIN'));
    ok('l’identifiant part en base64',
       cmds.some((c) => c === Buffer.from('boost@wakabileguide.com').toString('base64')));
    ok('le mot de passe part en base64',
       cmds.some((c) => c === Buffer.from('secret-2026').toString('base64')));
    ok('MAIL FROM porte l’expéditeur',
       cmds.includes('MAIL FROM:<boost@wakabileguide.com>'));
    ok('RCPT TO porte le destinataire', cmds.includes('RCPT TO:<ama@exemple.tg>'));
    ok('le dialogue se termine par QUIT', verbes[verbes.length - 1] === 'QUIT');

    const m = s.message;
    ok('le sujet accentué est encodé', /^Subject: =\?UTF-8\?B\?/m.test(m),
       m.match(/^Subject:.*/m)?.[0]?.slice(0, 46) ?? 'absent');
    ok('le sujet décodé est le bon',
       Buffer.from(m.match(/^Subject: =\?UTF-8\?B\?(.+)\?=$/m)?.[1] ?? '', 'base64').toString('utf8')
         === 'Décor approuvé — soirée à Lomé');
    ok('From porte le nom affiché', /^From: "Wakabi Boost" <boost@wakabileguide\.com>$/m.test(m),
       m.match(/^From:.*/m)?.[0] ?? 'absent');
    ok('To porte le nom du destinataire', /^To: "Ama Koffi" <ama@exemple\.tg>$/m.test(m));
    ok('le message est multipart', /^Content-Type: multipart\/alternative; boundary=/m.test(m));
    ok('la version texte est présente', m.includes('Content-Type: text/plain; charset=UTF-8'));
    ok('la version HTML est présente', m.includes('Content-Type: text/html; charset=UTF-8'));
    ok('le lien figure dans le message', m.includes('https://boost.wakabileguide.com/index.php?p=partenaire'));
    ok('Message-ID est posé', /^Message-ID: <[0-9a-f]+@boost\.wakabileguide\.com>$/m.test(m));
    ok('la réponse automatique est découragée', /^Auto-Submitted: auto-generated$/m.test(m));
    // Le point doublé est retiré par le serveur : ce qu'on lit est le texte
    // d'origine. S'il ne l'avait pas été, le message se serait arrêté là.
    ok('un point en début de ligne ne coupe pas le message',
       m.includes('. Une ligne qui commence par un point.') && m.includes('</html>'));
  }

  /* ---- transport éteint : on le dit, on ne fait pas semblant ---- */

  writeFileSync(fichier, PREAMBULE + `
    reglages_bdd_poser(['smtp_hote' => '']);
    var_dump(courriel_branche(), verification_exigee());
  `);
  const eteint = (await lancerPhp('php', [fichier], { env })).stdout;
  ok('sans serveur, le transport se déclare éteint',
     (eteint.match(/bool\(false\)/g) ?? []).length === 2, eteint.replace(/\n/g, ' '));

  /* ---- un serveur injoignable rend un message utile ---- */

  writeFileSync(fichier, PREAMBULE + `
    reglages_bdd_poser(['smtp_hote' => '127.0.0.1', 'smtp_port' => '3926']);
    $r = envoyer_courriel('ama@exemple.tg', 'Ama', 'Essai', 'Corps');
    echo ($r['ok'] ? 'OK' : 'KO'), '|', $r['message'], "\\n";
  `);
  const injoignable = (await lancerPhp('php', [fichier], { env })).stdout;
  ok('un port fermé est signalé sans jargon',
     injoignable.startsWith('KO|') && /Connexion impossible/.test(injoignable)
       && /l’hébergeur laisse sortir ce port/.test(injoignable),
     injoignable.trim().slice(0, 76));

  await faux.fermer();
  rmSync(dossier, { recursive: true, force: true });

  console.log(`\n━━ ${pass} réussis, ${fail} échoués ━━\n`);
  process.exit(fail ? 1 : 0);
};

main();
