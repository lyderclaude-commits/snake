import 'server-only';
import { db, nowIso } from '../db';
import { newId } from '../auth';
import { DecorTemplate } from '@/core/template.schema';
import { canTransition, type ActorRole, type DecorStatus } from '@/core/template.schema';

/**
 * Dépôt des décors — la SEULE frontière avec le SQL.
 *
 * Toute l'application passe par ces fonctions. Migrer vers Postgres revient
 * à réécrire ce fichier ; aucun composant, aucune page n'a besoin de changer.
 */

export interface DecorRow {
  id: string;
  slug: string;
  title: string;
  subtitle: string | null;
  city: string;
  rubrique: string;
  status: DecorStatus;
  created_by: 'wakabi-team' | 'partner';
  author_id: string | null;
  template_json: string;
  frame_url: string | null;
  published_at: string | null;
  expires_at: string | null;
  submitted_at: string | null;
  reviewed_at: string | null;
  reviewed_by: string | null;
  review_note: string | null;
  created_at: string;
  updated_at: string;
}

export interface DecorWithStats extends DecorRow {
  downloads: number;
  views: number;
  author_name?: string | null;
}

const STATS = `
  (SELECT COUNT(*) FROM events e WHERE e.decor_id = d.id AND e.kind='download') AS downloads,
  (SELECT COUNT(*) FROM events e WHERE e.decor_id = d.id AND e.kind='view')     AS views
`;

/** Catalogue public : publiés et non expirés, les plus récents d'abord. */
export function listPublished(limit = 60): DecorWithStats[] {
  return db()
    .prepare(
      `SELECT d.*, ${STATS} FROM decors d
       WHERE d.status = 'published'
         AND (d.expires_at IS NULL OR d.expires_at > ?)
       ORDER BY d.published_at DESC LIMIT ?`,
    )
    .all(nowIso(), limit) as DecorWithStats[];
}

export function findBySlug(slug: string): DecorWithStats | undefined {
  return db()
    .prepare(`SELECT d.*, ${STATS} FROM decors d WHERE d.slug = ?`)
    .get(slug) as DecorWithStats | undefined;
}

export function findById(id: string): DecorWithStats | undefined {
  return db()
    .prepare(`SELECT d.*, ${STATS} FROM decors d WHERE d.id = ?`)
    .get(id) as DecorWithStats | undefined;
}

export function listByAuthor(authorId: string): DecorWithStats[] {
  return db()
    .prepare(
      `SELECT d.*, ${STATS} FROM decors d WHERE d.author_id = ? ORDER BY d.updated_at DESC`,
    )
    .all(authorId) as DecorWithStats[];
}

/** File d'attente de relecture. */
export function listPending(): DecorWithStats[] {
  return db()
    .prepare(
      `SELECT d.*, u.name AS author_name, ${STATS} FROM decors d
       LEFT JOIN users u ON u.id = d.author_id
       WHERE d.status = 'pending_review' ORDER BY d.submitted_at ASC`,
    )
    .all() as DecorWithStats[];
}

export function listAll(status?: string): DecorWithStats[] {
  const base = `SELECT d.*, u.name AS author_name, ${STATS} FROM decors d
                LEFT JOIN users u ON u.id = d.author_id`;
  return status
    ? (db().prepare(`${base} WHERE d.status = ? ORDER BY d.updated_at DESC`).all(status) as DecorWithStats[])
    : (db().prepare(`${base} ORDER BY d.updated_at DESC`).all() as DecorWithStats[]);
}

/** Parse et valide le gabarit stocké. Renvoie null si le décor est corrompu. */
export function parseTemplate(row: DecorRow): DecorTemplate | null {
  try {
    return DecorTemplate.parse(JSON.parse(row.template_json));
  } catch {
    return null;
  }
}

/* ---------------- écriture ---------------- */

export interface CreateInput {
  slug: string;
  title: string;
  subtitle?: string;
  city: string;
  rubrique: string;
  template: unknown;
  frameUrl: string | null;
  authorId: string;
  createdBy: 'wakabi-team' | 'partner';
  expiresAt?: string | null;
}

export function create(input: CreateInput): string {
  const id = newId();
  const now = nowIso();
  db()
    .prepare(
      `INSERT INTO decors
       (id, slug, title, subtitle, city, rubrique, status, created_by, author_id,
        template_json, frame_url, expires_at, created_at, updated_at)
       VALUES (?,?,?,?,?,?,'draft',?,?,?,?,?,?,?)`,
    )
    .run(
      id, input.slug, input.title, input.subtitle ?? null, input.city, input.rubrique,
      input.createdBy, input.authorId, JSON.stringify(input.template),
      input.frameUrl, input.expiresAt ?? null, now, now,
    );
  return id;
}

export function updateTemplate(id: string, template: unknown, patch: Partial<CreateInput> = {}) {
  db()
    .prepare(
      `UPDATE decors SET template_json = ?, title = COALESCE(?, title),
       subtitle = COALESCE(?, subtitle), city = COALESCE(?, city),
       rubrique = COALESCE(?, rubrique), frame_url = COALESCE(?, frame_url),
       updated_at = ? WHERE id = ?`,
    )
    .run(
      JSON.stringify(template), patch.title ?? null, patch.subtitle ?? null,
      patch.city ?? null, patch.rubrique ?? null, patch.frameUrl ?? null, nowIso(), id,
    );
}

export class TransitionError extends Error {}

/**
 * Change le statut d'un décor en faisant respecter la machine à états.
 *
 * C'est ici que le contrat devient exécutable : `canTransition` refuse
 * qu'un partenaire publie ou s'auto-approuve, et le motif est exigé pour
 * un refus ou une demande de correction.
 */
export function transition(
  id: string,
  to: DecorStatus,
  actor: { id: string; role: ActorRole },
  note?: string,
): void {
  const row = findById(id);
  if (!row) throw new TransitionError('Décor introuvable.');

  if (!canTransition(row.status, to, actor.role)) {
    throw new TransitionError(
      `Transition « ${row.status} → ${to} » non autorisée pour ce rôle.`,
    );
  }
  if ((to === 'rejected' || to === 'changes_requested') && !note?.trim()) {
    throw new TransitionError('Un motif est obligatoire pour refuser ou demander des corrections.');
  }

  const now = nowIso();
  const sets: string[] = ['status = ?', 'updated_at = ?'];
  const vals: unknown[] = [to, now];

  if (to === 'pending_review') {
    sets.push('submitted_at = ?', 'submitted_by = ?');
    vals.push(now, actor.id);
  }
  if (to === 'published') {
    sets.push('published_at = COALESCE(published_at, ?)');
    vals.push(now);
    if (row.created_by === 'partner') {
      sets.push('reviewed_at = ?', 'reviewed_by = ?');
      vals.push(now, actor.id);
    }
  }
  if (to === 'rejected' || to === 'changes_requested') {
    sets.push('reviewed_at = ?', 'reviewed_by = ?', 'review_note = ?');
    vals.push(now, actor.id, note!.trim());
  }

  vals.push(id);
  db().prepare(`UPDATE decors SET ${sets.join(', ')} WHERE id = ?`).run(...vals);
}

export function remove(id: string) {
  db().prepare('DELETE FROM decors WHERE id = ?').run(id);
}

/* ---------------- compteurs ---------------- */

export function track(decorId: string, kind: 'view' | 'download') {
  db()
    .prepare('INSERT INTO events (decor_id, kind, created_at) VALUES (?,?,?)')
    .run(decorId, kind, nowIso());
}

export function slugExists(slug: string, exceptId?: string): boolean {
  const row = exceptId
    ? db().prepare('SELECT 1 FROM decors WHERE slug = ? AND id <> ?').get(slug, exceptId)
    : db().prepare('SELECT 1 FROM decors WHERE slug = ?').get(slug);
  return !!row;
}

export function slugify(input: string): string {
  return input
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 60) || 'decor';
}
