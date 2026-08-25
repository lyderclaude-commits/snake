import 'server-only';
import { randomBytes } from 'node:crypto';
import { db, nowIso } from '../db';

/**
 * Badges émis et présence réelle.
 *
 * Chaque téléchargement émet un jeton unique, incrusté dans le visuel sous
 * forme de QR. Scanné à l'entrée, il transforme une image partagée en
 * présence mesurée — c'est la différence avec un générateur classique.
 */

export const KORIS_PER_SCAN = 50;

export interface BadgeRow {
  token: string;
  decor_id: string;
  user_id: string | null;
  created_at: string;
  scanned_at: string | null;
  scanned_by: string | null;
  koris: number;
}

/** Jeton court, lisible, sans caractères ambigus — il finit dans un QR. */
function newToken(): string {
  const alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
  const bytes = randomBytes(10);
  return Array.from(bytes, (b) => alphabet[b % alphabet.length]).join('');
}

export function mint(decorId: string, userId: string | null): string {
  let token = newToken();
  // Collision quasi impossible (32^10), mais la vérifier coûte une requête.
  while (db().prepare('SELECT 1 FROM badges WHERE token = ?').get(token)) token = newToken();

  db()
    .prepare('INSERT INTO badges (token, decor_id, user_id, created_at) VALUES (?,?,?,?)')
    .run(token, decorId, userId, nowIso());
  return token;
}

export function find(token: string) {
  return db()
    .prepare(
      `SELECT b.*, d.title AS decor_title, d.slug AS decor_slug, u.name AS user_name
       FROM badges b
       JOIN decors d ON d.id = b.decor_id
       LEFT JOIN users u ON u.id = b.user_id
       WHERE b.token = ?`,
    )
    .get(token.trim().toUpperCase()) as
    | (BadgeRow & { decor_title: string; decor_slug: string; user_name: string | null })
    | undefined;
}

export type ScanOutcome =
  | { ok: true; koris: number; decor: string; who: string | null }
  | { ok: false; reason: 'unknown' | 'already'; scannedAt?: string; decor?: string };

/**
 * Valide une entrée. Idempotent par construction : un jeton déjà scanné ne
 * crédite jamais deux fois — sinon il suffirait de rescanner le même badge.
 */
export function scan(token: string, scannerId: string): ScanOutcome {
  const b = find(token);
  if (!b) return { ok: false, reason: 'unknown' };
  if (b.scanned_at) {
    return { ok: false, reason: 'already', scannedAt: b.scanned_at, decor: b.decor_title };
  }

  const now = nowIso();
  const tx = db().transaction(() => {
    db()
      .prepare('UPDATE badges SET scanned_at = ?, scanned_by = ?, koris = ? WHERE token = ?')
      .run(now, scannerId, b.user_id ? KORIS_PER_SCAN : 0, b.token);

    // Les Koris ne sont crédités qu'à un compte identifié : un badge créé
    // sans compte reste comptabilisé en présence, mais ne rapporte rien.
    if (b.user_id) {
      db()
        .prepare(
          `INSERT INTO kori_events (user_id, amount, reason, badge_token, created_at)
           VALUES (?,?,?,?,?)`,
        )
        .run(b.user_id, KORIS_PER_SCAN, `Présence — ${b.decor_title}`, b.token, now);
    }
  });
  tx();

  return {
    ok: true,
    koris: b.user_id ? KORIS_PER_SCAN : 0,
    decor: b.decor_title,
    who: b.user_name,
  };
}

/** Solde Koris — recalculé, jamais stocké. */
export function koriBalance(userId: string): number {
  const r = db()
    .prepare('SELECT COALESCE(SUM(amount),0) AS n FROM kori_events WHERE user_id = ?')
    .get(userId) as { n: number };
  return r.n;
}

export function koriHistory(userId: string, limit = 20) {
  return db()
    .prepare('SELECT * FROM kori_events WHERE user_id = ? ORDER BY created_at DESC LIMIT ?')
    .all(userId, limit) as {
    id: number;
    amount: number;
    reason: string;
    created_at: string;
  }[];
}

/** Présence par décor : émis, scannés, taux. */
export function attendance(decorId: string) {
  const r = db()
    .prepare(
      `SELECT COUNT(*) AS issued,
              SUM(CASE WHEN scanned_at IS NOT NULL THEN 1 ELSE 0 END) AS scanned
       FROM badges WHERE decor_id = ?`,
    )
    .get(decorId) as { issued: number; scanned: number | null };
  const scanned = r.scanned ?? 0;
  return { issued: r.issued, scanned, rate: r.issued ? scanned / r.issued : 0 };
}

export function recentScans(limit = 15) {
  return db()
    .prepare(
      `SELECT b.token, b.scanned_at, d.title AS decor, u.name AS who
       FROM badges b JOIN decors d ON d.id = b.decor_id
       LEFT JOIN users u ON u.id = b.user_id
       WHERE b.scanned_at IS NOT NULL
       ORDER BY b.scanned_at DESC LIMIT ?`,
    )
    .all(limit) as { token: string; scanned_at: string; decor: string; who: string | null }[];
}
