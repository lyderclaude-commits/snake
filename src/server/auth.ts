import 'server-only';
import { randomBytes, scryptSync, timingSafeEqual, randomUUID } from 'node:crypto';
import { cookies } from 'next/headers';
import { db, nowIso } from './db';

/**
 * Authentification — sessions en base, mots de passe dérivés par scrypt.
 *
 * scrypt est dans la bibliothèque standard de Node : pas de dépendance
 * native supplémentaire, et c'est une fonction de dérivation à coût
 * mémoire, donc résistante au calcul massif.
 *
 * Trois rôles hiérarchisés :
 *   user    — crée des visuels, retrouve ses créations
 *   partner — crée des décors et les soumet à la relecture
 *   admin   — publie, relit, gère les comptes
 */

export type Role = 'user' | 'partner' | 'admin';

export interface SessionUser {
  id: string;
  email: string;
  name: string;
  role: Role;
  organisation: string | null;
  city: string | null;
}

const COOKIE = 'wk_session';
const DAYS = 30;

/* ---------------- mots de passe ---------------- */

export function hashPassword(plain: string): string {
  const salt = randomBytes(16);
  const key = scryptSync(plain, salt, 64);
  return `${salt.toString('hex')}:${key.toString('hex')}`;
}

export function verifyPassword(plain: string, stored: string): boolean {
  const [saltHex, keyHex] = stored.split(':');
  if (!saltHex || !keyHex) return false;
  const key = Buffer.from(keyHex, 'hex');
  const candidate = scryptSync(plain, Buffer.from(saltHex, 'hex'), key.length);
  // Comparaison à temps constant : une comparaison naïve laisserait fuiter
  // la longueur du préfixe correct.
  return key.length === candidate.length && timingSafeEqual(key, candidate);
}

/* ---------------- sessions ---------------- */

export async function createSession(userId: string): Promise<void> {
  const token = randomBytes(32).toString('base64url');
  const expires = new Date(Date.now() + DAYS * 864e5);
  db()
    .prepare('INSERT INTO sessions (token, user_id, expires_at, created_at) VALUES (?,?,?,?)')
    .run(token, userId, expires.toISOString(), nowIso());

  (await cookies()).set(COOKIE, token, {
    httpOnly: true,
    sameSite: 'lax',
    secure: process.env.NODE_ENV === 'production',
    path: '/',
    expires,
  });
}

export async function destroySession(): Promise<void> {
  const jar = await cookies();
  const token = jar.get(COOKIE)?.value;
  if (token) db().prepare('DELETE FROM sessions WHERE token = ?').run(token);
  jar.delete(COOKIE);
}

export async function currentUser(): Promise<SessionUser | null> {
  const token = (await cookies()).get(COOKIE)?.value;
  if (!token) return null;

  const row = db()
    .prepare(
      `SELECT u.id, u.email, u.name, u.role, u.organisation, u.city, u.suspended, s.expires_at
       FROM sessions s JOIN users u ON u.id = s.user_id
       WHERE s.token = ?`,
    )
    .get(token) as
    | (SessionUser & { suspended: number; expires_at: string })
    | undefined;

  if (!row) return null;
  if (new Date(row.expires_at) < new Date()) {
    db().prepare('DELETE FROM sessions WHERE token = ?').run(token);
    return null;
  }
  // Un compte suspendu perd immédiatement l'accès, sans attendre l'expiration.
  if (row.suspended) return null;

  return {
    id: row.id,
    email: row.email,
    name: row.name,
    role: row.role,
    organisation: row.organisation,
    city: row.city,
  };
}

/* ---------------- garde-fous ---------------- */

export async function requireUser(): Promise<SessionUser> {
  const u = await currentUser();
  if (!u) throw new AuthError('Connexion requise.', 401);
  return u;
}

export async function requireRole(...roles: Role[]): Promise<SessionUser> {
  const u = await requireUser();
  if (!roles.includes(u.role)) throw new AuthError('Accès refusé.', 403);
  return u;
}

export class AuthError extends Error {
  constructor(
    message: string,
    readonly status: number,
  ) {
    super(message);
  }
}

export const newId = () => randomUUID();
