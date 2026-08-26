/**
 * Point d'entrée pour cPanel (Phusion Passenger).
 *
 * cPanel ne lance pas `npm start` : il charge un fichier JavaScript et
 * intercepte l'appel à `listen()`. C'est ce fichier-là, désigné comme
 * « Application startup file » dans « Setup Node.js App ».
 *
 * Il fait deux choses que le serveur de Next ne fait pas tout seul :
 *   1. lire un fichier .env — l'interface cPanel des variables est
 *      pénible, et une variable oubliée ici produit des QR faux ;
 *   2. échouer bruyamment plutôt que de démarrer à moitié.
 */

'use strict';

const http = require('http');

/* ---------- 1. le fichier .env ---------- */

// Même chargeur que les outils d'administration : une seule implémentation,
// donc pas de divergence possible sur l'emplacement de la base.
const lues = require('./charger-env.js')(__dirname);

/* ---------- 2. le contrôle qui évite les badges faux ---------- */

process.env.NODE_ENV = process.env.NODE_ENV || 'production';

if (!process.env.WAKABI_BASE_URL) {
  console.error(
    '\n  WAKABI_BASE_URL est absent.\n' +
      '  Cette adresse est incrustée dans le QR de chaque badge, donc dans un\n' +
      '  fichier que l’invité garde et partage. Un badge émis avec la mauvaise\n' +
      '  adresse est faux pour toujours.\n\n' +
      '  Renseignez-la dans le fichier .env à côté de ce fichier :\n' +
      '      WAKABI_BASE_URL=https://boost.wakabileguide.com\n',
  );
  process.exit(1);
}

console.log(`[wakabi] ${lues} variable(s) lue(s) dans .env`);
console.log(`[wakabi] adresse publique : ${process.env.WAKABI_BASE_URL}`);
console.log(`[wakabi] base            : ${process.env.WAKABI_DB || '.data/wakabi.sqlite'}`);

/* ---------- 3. le serveur ---------- */

const next = require('next');
const app = next({ dev: false, dir: __dirname });
const traiter = app.getRequestHandler();

app
  .prepare()
  .then(() => {
    // Passenger remplace ce port par sa propre socket : la valeur importe peu,
    // l'appel à listen() est ce qui compte.
    const port = process.env.PORT || 3000;
    http.createServer((req, res) => traiter(req, res)).listen(port, () => {
      console.log(`[wakabi] prêt sur le port ${port}`);
    });
  })
  .catch((e) => {
    console.error('[wakabi] démarrage impossible :', e);
    process.exit(1);
  });
