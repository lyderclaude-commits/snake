/**
 * Lecture du fichier .env — implémentation UNIQUE.
 *
 * `app.js` l'appelle au démarrage, et les outils d'administration (`seed.js`,
 * `creer-admin.js`) l'appellent aussi, par un préambule ajouté à la
 * compilation. Sans cela, un outil lancé en ligne de commande n'aurait pas
 * WAKABI_DB et écrirait dans le dossier de l'application — c'est-à-dire à
 * l'endroit précis que le prochain téléversement efface.
 *
 * Volontairement sans dépendance : ce fichier doit rester lisible et
 * modifiable directement dans l'éditeur de fichiers de cPanel.
 */

'use strict';

const fs = require('fs');
const path = require('path');

module.exports = function chargerEnv(dossier) {
  const fichier = path.join(dossier || __dirname, '.env');
  if (!fs.existsSync(fichier)) return 0;

  let n = 0;
  for (const ligne of fs.readFileSync(fichier, 'utf8').split('\n')) {
    const t = ligne.trim();
    if (!t || t.startsWith('#')) continue;

    const i = t.indexOf('=');
    if (i < 1) continue;

    const cle = t.slice(0, i).trim();
    let val = t.slice(i + 1).trim();
    // Les guillemets sont tolérés, pas exigés.
    if (
      (val.startsWith('"') && val.endsWith('"')) ||
      (val.startsWith("'") && val.endsWith("'"))
    ) {
      val = val.slice(1, -1);
    }
    // Une variable déjà posée par cPanel l'emporte sur le fichier.
    if (process.env[cle] === undefined) {
      process.env[cle] = val;
      n++;
    }
  }
  return n;
};
