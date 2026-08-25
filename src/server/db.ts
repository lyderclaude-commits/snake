import 'server-only';
import Database from 'better-sqlite3';
import { mkdirSync } from 'node:fs';
import { dirname } from 'node:path';

/**
 * Base de données — SQLite.
 *
 * Pourquoi SQLite et pas Postgres tout de suite : elle tourne sans service à
 * provisionner, sans identifiants, sans réseau. On développe et on démontre
 * aujourd'hui ; le passage à Postgres (Supabase, Neon…) ne touchera que
 * `src/server/repo/*` — le reste de l'application ne connaît que ces
 * fonctions, jamais le SQL.
 *
 * Voir docs/07-BACKEND.md pour le chemin de migration.
 */

const FILE = process.env.WAKABI_DB ?? '.data/wakabi.sqlite';

let instance: Database.Database | null = null;

export function db(): Database.Database {
  if (instance) return instance;
  mkdirSync(dirname(FILE), { recursive: true });
  const conn = new Database(FILE);
  conn.pragma('journal_mode = WAL');
  conn.pragma('foreign_keys = ON');
  migrate(conn);
  instance = conn;
  return conn;
}

function migrate(conn: Database.Database) {
  conn.exec(`
    CREATE TABLE IF NOT EXISTS users (
      id            TEXT PRIMARY KEY,
      email         TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      name          TEXT NOT NULL,
      role          TEXT NOT NULL DEFAULT 'user',
      organisation  TEXT,
      city          TEXT,
      suspended     INTEGER NOT NULL DEFAULT 0,
      created_at    TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS sessions (
      token      TEXT PRIMARY KEY,
      user_id    TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      expires_at TEXT NOT NULL,
      created_at TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);

    CREATE TABLE IF NOT EXISTS decors (
      id            TEXT PRIMARY KEY,
      slug          TEXT NOT NULL UNIQUE,
      title         TEXT NOT NULL,
      subtitle      TEXT,
      city          TEXT NOT NULL DEFAULT 'all',
      rubrique      TEXT NOT NULL DEFAULT 'campagne',
      status        TEXT NOT NULL DEFAULT 'draft',
      created_by    TEXT NOT NULL DEFAULT 'wakabi-team',
      author_id     TEXT REFERENCES users(id) ON DELETE SET NULL,
      template_json TEXT NOT NULL,
      frame_url     TEXT,
      published_at  TEXT,
      expires_at    TEXT,
      submitted_at  TEXT,
      submitted_by  TEXT,
      reviewed_at   TEXT,
      reviewed_by   TEXT,
      review_note   TEXT,
      created_at    TEXT NOT NULL,
      updated_at    TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_decors_status ON decors(status);
    CREATE INDEX IF NOT EXISTS idx_decors_author ON decors(author_id);

    -- Compteurs : une ligne par événement, agrégée à la lecture.
    -- Suffisant à ce volume, et on garde la granularité pour les stats.
    CREATE TABLE IF NOT EXISTS events (
      id         INTEGER PRIMARY KEY AUTOINCREMENT,
      decor_id   TEXT NOT NULL REFERENCES decors(id) ON DELETE CASCADE,
      kind       TEXT NOT NULL,
      created_at TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_events_decor ON events(decor_id, kind);

    -- « Mes créations » : uniquement une référence au décor et la date.
    -- La photo, elle, ne quitte jamais l'appareil (contrainte C6).
    CREATE TABLE IF NOT EXISTS creations (
      id         TEXT PRIMARY KEY,
      user_id    TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      decor_id   TEXT NOT NULL REFERENCES decors(id) ON DELETE CASCADE,
      created_at TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_creations_user ON creations(user_id, created_at DESC);
  `);
}

export const nowIso = () => new Date().toISOString();
