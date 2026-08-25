/**
 * Entretien : supprime les cadres orphelins et les jetons périmés.
 *
 * Un cadre téléversé puis abandonné reste sur le disque et compte dans le
 * quota de son auteur. À lancer par une tâche planifiée.
 */
import { readdir, stat, unlink } from 'node:fs/promises';
import { join } from 'node:path';
import { db, nowIso } from '../src/server/db';

const DIR = process.env.WAKABI_UPLOADS ?? '.data/uploads';
const GRACE_HOURS = 24;

const run = async () => {
  db();

  /* 1 — cadres orphelins */
  const referenced = new Set(
    (db().prepare('SELECT frame_url FROM decors WHERE frame_url IS NOT NULL').all() as {
      frame_url: string;
    }[]).map((r) => r.frame_url.split('/').pop()!),
  );

  let files: string[] = [];
  try {
    files = await readdir(join(process.cwd(), DIR));
  } catch {
    console.log('  Aucun dossier de téléversement.');
  }

  const cutoff = Date.now() - GRACE_HOURS * 3600_000;
  let freed = 0;
  let removed = 0;

  for (const f of files) {
    if (referenced.has(f)) continue;
    const path = join(process.cwd(), DIR, f);
    const info = await stat(path);
    // Délai de grâce : un cadre tout juste téléversé n'est pas encore
    // rattaché à un décor, et le supprimer casserait la création en cours.
    if (info.mtimeMs > cutoff) continue;
    await unlink(path);
    freed += info.size;
    removed++;
  }

  /* 2 — jetons périmés */
  const now = nowIso();
  const sessions = db().prepare('DELETE FROM sessions WHERE expires_at < ?').run(now).changes;
  const emails = db().prepare('DELETE FROM email_tokens WHERE expires_at < ?').run(now).changes;
  const attempts = db()
    .prepare('DELETE FROM login_attempts WHERE created_at < ?')
    .run(new Date(Date.now() - 3600_000).toISOString()).changes;

  /* 3 — décors expirés */
  const expired = db()
    .prepare("UPDATE decors SET status='expired' WHERE status='published' AND expires_at IS NOT NULL AND expires_at < ?")
    .run(now).changes;

  console.log(`  Cadres orphelins supprimés : ${removed} (${Math.round(freed / 1024)} Ko)`);
  console.log(`  Sessions périmées          : ${sessions}`);
  console.log(`  Jetons e-mail périmés      : ${emails}`);
  console.log(`  Tentatives de connexion    : ${attempts}`);
  console.log(`  Décors passés à « expiré » : ${expired}`);
};

run().catch((e) => { console.error(e); process.exit(1); });
