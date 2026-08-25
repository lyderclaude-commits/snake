import 'server-only';
import { db, nowIso } from './db';

/**
 * Limitation de débit sur la connexion.
 *
 * Sans elle, rien n'empêche d'essayer les mots de passe en boucle. On compte
 * les tentatives par clé (adresse e-mail) sur une fenêtre glissante ; au-delà
 * du seuil, on refuse même si le mot de passe est bon — sinon la limite
 * n'en serait pas une.
 */

const WINDOW_MIN = 15;
const MAX_ATTEMPTS = 8;

export function tooManyAttempts(key: string): { blocked: boolean; retryInMin: number } {
  const since = new Date(Date.now() - WINDOW_MIN * 60_000).toISOString();
  db().prepare('DELETE FROM login_attempts WHERE created_at < ?').run(since);

  const r = db()
    .prepare('SELECT COUNT(*) AS n, MIN(created_at) AS first FROM login_attempts WHERE key = ?')
    .get(key.toLowerCase()) as { n: number; first: string | null };

  if (r.n < MAX_ATTEMPTS) return { blocked: false, retryInMin: 0 };

  const elapsed = r.first ? (Date.now() - new Date(r.first).getTime()) / 60_000 : 0;
  return { blocked: true, retryInMin: Math.max(1, Math.ceil(WINDOW_MIN - elapsed)) };
}

export function recordAttempt(key: string) {
  db()
    .prepare('INSERT INTO login_attempts (key, created_at) VALUES (?,?)')
    .run(key.toLowerCase(), nowIso());
}

export function clearAttempts(key: string) {
  db().prepare('DELETE FROM login_attempts WHERE key = ?').run(key.toLowerCase());
}
