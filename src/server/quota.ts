import 'server-only';
import { db } from './db';

/**
 * Quotas de téléversement par compte.
 *
 * Un compte compromis ne doit pas pouvoir remplir le disque. On compte les
 * octets réellement écrits plutôt que le nombre de fichiers : c'est l'espace
 * qui manque, pas les entrées de répertoire.
 */

export const QUOTA_BYTES: Record<string, number> = {
  partner: 20 * 1024 * 1024, // 20 Mo — environ 50 cadres
  admin: 500 * 1024 * 1024,
  user: 0,
};

export function usage(userId: string): number {
  const r = db().prepare('SELECT upload_bytes FROM users WHERE id = ?').get(userId) as
    | { upload_bytes: number }
    | undefined;
  return r?.upload_bytes ?? 0;
}

export function canUpload(userId: string, role: string, bytes: number) {
  const limit = QUOTA_BYTES[role] ?? 0;
  const used = usage(userId);
  return { ok: used + bytes <= limit, used, limit };
}

export function addUsage(userId: string, bytes: number) {
  db().prepare('UPDATE users SET upload_bytes = upload_bytes + ? WHERE id = ?').run(bytes, userId);
}
