import 'server-only';
import { resolve } from 'node:path';

/**
 * Adresse publique de l'application.
 *
 * Elle est incrustée dans le QR de chaque badge, donc dans un fichier que
 * l'invité garde et partage. Un badge émis avec la mauvaise adresse reste
 * faux pour toujours : c'est le seul réglage à ne pas se tromper au
 * déploiement.
 *
 * En production, WAKABI_BASE_URL est obligatoire.
 */
export function baseUrl(): string {
  const raw = process.env.WAKABI_BASE_URL?.trim();

  if (!raw) {
    if (process.env.NODE_ENV === 'production') {
      throw new Error(
        'WAKABI_BASE_URL est absent. Renseignez-le (ex. https://boost.wakabileguide.com) : ' +
          'sans lui, les QR des badges pointeraient vers une adresse fausse, définitivement.',
      );
    }
    return 'http://localhost:3000';
  }
  return raw.replace(/\/+$/, '');
}

/** Adresse encodée dans le QR d'un badge. */
export function badgeUrl(token: string): string {
  return `${baseUrl()}/qr/${token}`;
}

/**
 * Dossier des cadres téléversés.
 *
 * `resolve` et non `join` : en production ce chemin est absolu et pointe hors
 * de l'application (un volume qui survit au redéploiement). `join` le
 * recollerait derrière le répertoire courant et le fichier deviendrait
 * introuvable.
 */
export function uploadsDir(): string {
  return resolve(process.cwd(), process.env.WAKABI_UPLOADS ?? '.data/uploads');
}
