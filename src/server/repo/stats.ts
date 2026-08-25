import 'server-only';
import { db } from '../db';

export interface Overview {
  decorsPublished: number;
  decorsPending: number;
  decorsTotal: number;
  downloads: number;
  views: number;
  users: number;
  partners: number;
}

export function overview(): Overview {
  const one = (sql: string, ...p: unknown[]) =>
    (db().prepare(sql).get(...p) as { n: number }).n;
  return {
    decorsPublished: one("SELECT COUNT(*) AS n FROM decors WHERE status='published'"),
    decorsPending: one("SELECT COUNT(*) AS n FROM decors WHERE status='pending_review'"),
    decorsTotal: one('SELECT COUNT(*) AS n FROM decors'),
    downloads: one("SELECT COUNT(*) AS n FROM events WHERE kind='download'"),
    views: one("SELECT COUNT(*) AS n FROM events WHERE kind='view'"),
    users: one('SELECT COUNT(*) AS n FROM users'),
    partners: one("SELECT COUNT(*) AS n FROM users WHERE role='partner'"),
  };
}

/**
 * Téléchargements par jour, sur une fenêtre GLISSANTE complète.
 *
 * On complète les jours sans événement avec zéro : une série temporelle qui
 * n'affiche que les jours actifs déforme la lecture — un seul jour de données
 * produirait une barre pleine largeur, qu'on interpréterait comme une
 * constante.
 */
export function dailyDownloads(days = 14): { day: string; n: number }[] {
  const rows = db()
    .prepare(
      `SELECT substr(created_at,1,10) AS day, COUNT(*) AS n
       FROM events WHERE kind='download' AND created_at >= ?
       GROUP BY day`,
    )
    .all(new Date(Date.now() - days * 864e5).toISOString()) as { day: string; n: number }[];

  const byDay = new Map(rows.map((r) => [r.day, r.n]));
  const out: { day: string; n: number }[] = [];
  for (let i = days - 1; i >= 0; i--) {
    const day = new Date(Date.now() - i * 864e5).toISOString().slice(0, 10);
    out.push({ day, n: byDay.get(day) ?? 0 });
  }
  return out;
}
