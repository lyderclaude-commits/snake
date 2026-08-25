import 'server-only';
import { db, nowIso } from '../db';
import { hashPassword, newId, type Role } from '../auth';

export interface UserRow {
  id: string;
  email: string;
  name: string;
  role: Role;
  organisation: string | null;
  city: string | null;
  suspended: number;
  created_at: string;
}

export function findByEmail(email: string) {
  return db()
    .prepare('SELECT * FROM users WHERE email = ?')
    .get(email.trim().toLowerCase()) as (UserRow & { password_hash: string }) | undefined;
}

export function createUser(input: {
  email: string;
  password: string;
  name: string;
  role?: Role;
  organisation?: string | null;
  city?: string | null;
}): string {
  const id = newId();
  db()
    .prepare(
      `INSERT INTO users (id, email, password_hash, name, role, organisation, city, created_at)
       VALUES (?,?,?,?,?,?,?,?)`,
    )
    .run(
      id,
      input.email.trim().toLowerCase(),
      hashPassword(input.password),
      input.name.trim(),
      input.role ?? 'user',
      input.organisation ?? null,
      input.city ?? null,
      nowIso(),
    );
  return id;
}

export function listUsers(): UserRow[] {
  return db()
    .prepare('SELECT * FROM users ORDER BY created_at DESC')
    .all() as UserRow[];
}

export function setRole(id: string, role: Role) {
  db().prepare('UPDATE users SET role = ? WHERE id = ?').run(role, id);
}

export function setSuspended(id: string, suspended: boolean) {
  db().prepare('UPDATE users SET suspended = ? WHERE id = ?').run(suspended ? 1 : 0, id);
  if (suspended) db().prepare('DELETE FROM sessions WHERE user_id = ?').run(id);
}

export function countByRole(): Record<string, number> {
  const rows = db()
    .prepare('SELECT role, COUNT(*) AS n FROM users GROUP BY role')
    .all() as { role: string; n: number }[];
  return Object.fromEntries(rows.map((r) => [r.role, r.n]));
}
