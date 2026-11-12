/**
 * Le Chromium de l'image, cherché plutôt que supposé.
 *
 * Huit scripts portaient le même chemin en dur, « chromium-1194 », avec
 * le dossier « chrome-linux » qui va avec. L'image du conteneur a changé
 * de version ET de dossier (« chromium-1243/chrome-linux64 ») : tout est
 * tombé d'un coup, mille scénarios compris, pour un nombre dans une
 * chaîne. Un chemin écrit une fois ailleurs qu'ici serait retombé dans le
 * même piège au prochain changement.
 *
 * On prend la version la plus récente qui porte vraiment un binaire, et
 * `CHROMIUM` dans l'environnement garde le dernier mot : c'est par là
 * qu'on essaie un navigateur à soi sans toucher au code.
 */
import { readdirSync, existsSync } from 'node:fs';

const RACINE = '/opt/pw-browsers';

export const chromiumExe = (): string => {
  const impose = process.env.CHROMIUM;
  if (impose && existsSync(impose)) {
    return impose;
  }
  const versions = readdirSync(RACINE)
    .filter((d) => /^chromium-\d+$/.test(d))
    .sort((a, b) => Number(b.split('-')[1]) - Number(a.split('-')[1]));
  for (const v of versions) {
    for (const sous of ['chrome-linux64/chrome', 'chrome-linux/chrome']) {
      if (existsSync(`${RACINE}/${v}/${sous}`)) {
        return `${RACINE}/${v}/${sous}`;
      }
    }
  }
  throw new Error(`aucun Chromium sous ${RACINE} : ${readdirSync(RACINE).join(', ')}`);
};
