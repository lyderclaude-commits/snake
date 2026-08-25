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

    -- ════════════════════════════════════════════════════════════════
    -- Le QR qui rapporte : un badge émis = un jeton unique, scanné une
    -- seule fois à l'entrée. C'est ce qui distingue une image partagée
    -- d'une présence mesurée.
    -- ════════════════════════════════════════════════════════════════
    CREATE TABLE IF NOT EXISTS badges (
      token      TEXT PRIMARY KEY,
      decor_id   TEXT NOT NULL REFERENCES decors(id) ON DELETE CASCADE,
      user_id    TEXT REFERENCES users(id) ON DELETE SET NULL,
      created_at TEXT NOT NULL,
      scanned_at TEXT,
      scanned_by TEXT REFERENCES users(id) ON DELETE SET NULL,
      koris      INTEGER NOT NULL DEFAULT 0
    );
    CREATE INDEX IF NOT EXISTS idx_badges_decor ON badges(decor_id);
    CREATE INDEX IF NOT EXISTS idx_badges_user ON badges(user_id);

    -- Journal des Koris : le solde se recalcule, il ne se stocke pas.
    -- Une somme fait toujours foi sur un compteur qu'on a pu oublier d'incrémenter.
    CREATE TABLE IF NOT EXISTS kori_events (
      id         INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id    TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      amount     INTEGER NOT NULL,
      reason     TEXT NOT NULL,
      badge_token TEXT,
      created_at TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_kori_user ON kori_events(user_id);

    -- Notifications dans l'application. L'e-mail demande un SMTP configuré ;
    -- en attendant, le partenaire est prévenu à sa prochaine visite.
    CREATE TABLE IF NOT EXISTS notifications (
      id         TEXT PRIMARY KEY,
      user_id    TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      kind       TEXT NOT NULL,
      title      TEXT NOT NULL,
      body       TEXT,
      href       TEXT,
      read_at    TEXT,
      created_at TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications(user_id, created_at DESC);

    -- Vérification d'adresse e-mail.
    CREATE TABLE IF NOT EXISTS email_tokens (
      token      TEXT PRIMARY KEY,
      user_id    TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      expires_at TEXT NOT NULL,
      created_at TEXT NOT NULL
    );

    -- Tentatives de connexion, pour la limitation de débit.
    CREATE TABLE IF NOT EXISTS login_attempts (
      id         INTEGER PRIMARY KEY AUTOINCREMENT,
      key        TEXT NOT NULL,
      created_at TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_attempts_key ON login_attempts(key, created_at);

    -- Rapport de pré-vol, conservé pour que le relecteur le voie.
    CREATE TABLE IF NOT EXISTS preflight (
      decor_id   TEXT PRIMARY KEY REFERENCES decors(id) ON DELETE CASCADE,
      passed     INTEGER NOT NULL,
      report     TEXT NOT NULL,
      ran_at     TEXT NOT NULL
    );
  `);

  // Colonnes ajoutées après coup : SQLite n'a pas d'ADD COLUMN IF NOT EXISTS.
  addColumn(conn, 'users', 'email_verified_at', 'TEXT');
  addColumn(conn, 'users', 'upload_bytes', 'INTEGER NOT NULL DEFAULT 0');
}

/** Ajoute une colonne si elle manque — migration idempotente. */
function addColumn(conn: Database.Database, table: string, column: string, decl: string) {
  const cols = conn.prepare(`PRAGMA table_info(${table})`).all() as { name: string }[];
  if (!cols.some((c) => c.name === column)) {
    conn.exec(`ALTER TABLE ${table} ADD COLUMN ${column} ${decl}`);
  }
}

export const nowIso = () => new Date().toISOString();
