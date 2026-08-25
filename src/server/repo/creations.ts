import 'server-only';
import { db, nowIso } from '../db';
import { newId } from '../auth';

/**
 * « Mes créations » — on n'enregistre QUE la référence au décor et la date.
 * La photo de l'utilisateur ne quitte jamais son appareil (contrainte C6),
 * il n'y a donc rien à stocker et rien à protéger.
 */

export interface CreationRow {
  id: string;
  decor_id: string;
  created_at: string;
  title: string;
  slug: string;
  frame_url: string | null;
}

export function record(userId: string, decorId: string): void {
  db()
    .prepare('INSERT INTO creations (id, user_id, decor_id, created_at) VALUES (?,?,?,?)')
    .run(newId(), userId, decorId, nowIso());
}

export function listForUser(userId: string, limit = 50): CreationRow[] {
  return db()
    .prepare(
      `SELECT c.id, c.decor_id, c.created_at, d.title, d.slug, d.frame_url
       FROM creations c JOIN decors d ON d.id = c.decor_id
       WHERE c.user_id = ? ORDER BY c.created_at DESC LIMIT ?`,
    )
    .all(userId, limit) as CreationRow[];
}

export function countForUser(userId: string): number {
  const r = db()
    .prepare('SELECT COUNT(*) AS n FROM creations WHERE user_id = ?')
    .get(userId) as { n: number };
  return r.n;
}
