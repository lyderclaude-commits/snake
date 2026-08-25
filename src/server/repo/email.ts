import 'server-only';
import { randomBytes } from 'node:crypto';
import { db, nowIso } from '../db';

/**
 * Vérification d'adresse e-mail.
 *
 * L'envoi réel demande un SMTP configuré, qu'on n'a pas. Le lien est donc
 * affiché à l'écran et journalisé — le flux complet est en place, il ne
 * manque que le transport.
 */

const HOURS = 48;

export function issueToken(userId: string): string {
  const token = randomBytes(24).toString('base64url');
  db().prepare('DELETE FROM email_tokens WHERE user_id = ?').run(userId);
  db()
    .prepare('INSERT INTO email_tokens (token, user_id, expires_at, created_at) VALUES (?,?,?,?)')
    .run(token, userId, new Date(Date.now() + HOURS * 3600_000).toISOString(), nowIso());
  return token;
}

export function consume(token: string): boolean {
  const row = db()
    .prepare('SELECT user_id, expires_at FROM email_tokens WHERE token = ?')
    .get(token) as { user_id: string; expires_at: string } | undefined;
  if (!row || new Date(row.expires_at) < new Date()) return false;

  db().prepare('UPDATE users SET email_verified_at = ? WHERE id = ?').run(nowIso(), row.user_id);
  db().prepare('DELETE FROM email_tokens WHERE token = ?').run(token);
  return true;
}

export function isVerified(userId: string): boolean {
  const r = db().prepare('SELECT email_verified_at FROM users WHERE id = ?').get(userId) as
    | { email_verified_at: string | null }
    | undefined;
  return !!r?.email_verified_at;
}
