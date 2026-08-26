/**
 * Crée un compte d'équipe en production.
 *
 * Le seed installe des comptes de démonstration dont le mot de passe est
 * écrit dans le dépôt — publiquement lisible. Sur un serveur en ligne, ce
 * n'est pas une option : ce script-ci prend un mot de passe que vous seul
 * connaissez, et l'adresse est vérifiée avant vérification.
 *
 *   node creer-admin.js "vous@wakabileguide.com" "Votre Nom" "un-mot-de-passe-long"
 *
 * Relancé sur une adresse existante, il PROMEUT le compte en administrateur
 * et remplace son mot de passe — c'est aussi la sortie de secours quand on
 * a perdu l'accès.
 */

import { db, nowIso } from '../src/server/db';
import { createUser, findByEmail } from '../src/server/repo/users';
import { hashPassword } from '../src/server/auth';

const [email, nom, motDePasse] = process.argv.slice(2);

function stop(message: string): never {
  console.error(`\n  ✗ ${message}\n`);
  process.exit(1);
}

if (!email || !nom || !motDePasse) {
  stop(
    'Usage : node creer-admin.js "adresse@exemple.com" "Nom Complet" "mot-de-passe"\n' +
      '    Pensez aux guillemets : un espace couperait l’argument en deux.',
  );
}
if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) stop(`« ${email} » n’est pas une adresse valide.`);
if (motDePasse.length < 12) {
  stop(
    `Ce mot de passe fait ${motDePasse.length} caractères.\n` +
      '    Douze au minimum : ce compte publie, modère et gère les autres comptes.',
  );
}

const existant = findByEmail(email);

if (existant) {
  db()
    .prepare('UPDATE users SET password_hash = ?, role = ?, name = ?, suspended = 0 WHERE id = ?')
    .run(hashPassword(motDePasse), 'admin', nom.trim(), existant.id);
  console.log(`\n  ✓ ${email} — mot de passe remplacé, rôle administrateur confirmé.\n`);
} else {
  const id = createUser({ email, password: motDePasse, name: nom, role: 'admin' });
  // Pas de courriel à envoyer à soi-même : l'adresse est vérifiée d'office.
  db().prepare('UPDATE users SET email_verified_at = ? WHERE id = ?').run(nowIso(), id);
  console.log(`\n  ✓ ${email} — compte administrateur créé.\n`);
}

const total = db().prepare("SELECT COUNT(*) AS n FROM users WHERE role = 'admin'").get() as {
  n: number;
};
console.log(`    ${total.n} administrateur(s) au total.`);
console.log(`    Connectez-vous sur ${process.env.WAKABI_BASE_URL ?? 'votre site'}/connexion\n`);
