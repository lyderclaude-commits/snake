import 'server-only';
import { db, nowIso } from '../db';
import { newId } from '../auth';

/**
 * Notifications dans l'application.
 *
 * L'envoi d'e-mail demande un SMTP configuré, qu'on n'a pas encore. En
 * attendant, le partenaire est prévenu à sa prochaine visite — ce qui vaut
 * mieux que rien, et ce qui restera utile même une fois l'e-mail branché.
 */

export interface NotifRow {
  id: string;
  kind: string;
  title: string;
  body: string | null;
  href: string | null;
  read_at: string | null;
  created_at: string;
}

export function push(
  userId: string,
  kind: string,
  title: string,
  body?: string,
  href?: string,
) {
  db()
    .prepare(
      `INSERT INTO notifications (id, user_id, kind, title, body, href, created_at)
       VALUES (?,?,?,?,?,?,?)`,
    )
    .run(newId(), userId, kind, title, body ?? null, href ?? null, nowIso());
}

export function listFor(userId: string, limit = 20): NotifRow[] {
  return db()
    .prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?')
    .all(userId, limit) as NotifRow[];
}

export function unreadCount(userId: string): number {
  const r = db()
    .prepare('SELECT COUNT(*) AS n FROM notifications WHERE user_id = ? AND read_at IS NULL')
    .get(userId) as { n: number };
  return r.n;
}

export function markAllRead(userId: string) {
  db()
    .prepare('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL')
    .run(nowIso(), userId);
}
