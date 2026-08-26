import 'server-only';
import sharp from 'sharp';
// sharp 0.35 embarque ses types : l'espace de noms `sharp.` n'est plus
// utilisable comme qualificateur, le type s'importe nommément.
import type { Metadata } from 'sharp';
import { uploadsDir } from './config';
import { readFile, stat } from 'node:fs/promises';
import { join } from 'node:path';
import { db, nowIso } from './db';
import { DecorTemplate, PreflightReport } from '@/core/template.schema';
import { toPx } from '@/core/fitPhoto';

/**
 * Pré-vol — les sept contrôles automatiques, côté SERVEUR.
 *
 * Objectif : que le relecteur Wakabi n'ait à juger que ce qu'une machine ne
 * peut pas juger — les droits sur le visuel, le ton, la pertinence. Tout le
 * reste tourne ici, à la soumission, et un décor qui échoue ne rejoint jamais
 * la file d'attente.
 *
 * Exécuté sur le serveur et non dans le navigateur : un contrôle client
 * serait contournable, et le rapport doit faire foi devant le relecteur.
 */

type Status = 'pass' | 'warn' | 'fail';
interface Check {
  id: string;
  status: Status;
  message: string;
}

const MAX_FRAME_BYTES = 400 * 1024;
/** Au-delà, la photo de l'invité serait invisible sous le cadre. */
const OPAQUE_LIMIT = 0.85;


/** Retrouve le fichier réel derrière une URL `/api/frames/<nom>`. */
function framePath(frameUrl: string | null): string | null {
  if (!frameUrl) return null;
  const name = frameUrl.split('/').pop();
  if (!name || !/^[0-9a-f-]{36}\.(png|webp)$/.test(name)) {
    // Cadres livrés avec l'application (démonstration).
    const local = frameUrl.replace(/^\//, '');
    return local.startsWith('frames/') ? join(process.cwd(), 'public', local) : null;
  }
  // `uploadsDir()` et non `join(cwd, …)` : en production ce dossier est
  // absolu et vit hors de l'application. `join` le recollerait derrière le
  // répertoire courant, et le pré-vol déclarerait tout cadre « illisible ».
  return join(uploadsDir(), name);
}

/**
 * Part opaque d'une région du cadre.
 * On lit le canal alpha brut : c'est lui qui dit si la photo passera au travers.
 */
async function opaqueRatio(
  path: string,
  region: { left: number; top: number; width: number; height: number },
): Promise<number> {
  const { data, info } = await sharp(path)
    .ensureAlpha()
    .extract(region)
    // Réduire d'abord : on cherche une proportion, pas une valeur au pixel près.
    .resize(96, 96, { fit: 'fill' })
    .raw()
    .toBuffer({ resolveWithObject: true });

  const stride = info.channels;
  let opaque = 0;
  const total = info.width * info.height;
  for (let i = 0; i < total; i++) {
    if (data[i * stride + stride - 1] > 250) opaque++;
  }
  return opaque / total;
}

/** Luminance relative WCAG, pour le rapport de contraste. */
function luminance(hex: string): number {
  const v = hex.replace('#', '');
  const rgb = [0, 2, 4].map((i) => parseInt(v.slice(i, i + 2), 16) / 255);
  const lin = rgb.map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
  return 0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2];
}

const ratio = (a: number, b: number) => (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);

const BRAND: Record<string, string> = {
  'brand.primary': '#2563EB',
  'brand.secondary': '#0D9488',
  'brand.accent': '#F97316',
  'brand.kori': '#D97706',
  'brand.ink': '#0F172A',
  'brand.paper': '#FFFFFF',
};

export async function runPreflight(
  decorId: string,
  templateJson: string,
  frameUrl: string | null,
): Promise<{ passed: boolean; checks: Check[] }> {
  const checks: Check[] = [];
  const add = (id: string, status: Status, message: string) =>
    checks.push({ id, status, message });

  /* 1 — le schéma */
  let tpl: DecorTemplate;
  try {
    tpl = DecorTemplate.parse(JSON.parse(templateJson));
    add('schema', 'pass', 'Le gabarit respecte toutes les règles du contrat.');
  } catch (e) {
    const msg = /"message":\s*"([^"]+)"/.exec(String(e))?.[1] ?? 'gabarit invalide';
    add('schema', 'fail', msg);
    return { passed: false, checks };
  }

  const path = framePath(frameUrl);
  if (!path) {
    add('asset-format', 'fail', 'Aucun cadre exploitable n’est attaché à ce décor.');
    return { passed: false, checks };
  }

  /* 5 — format du fichier */
  let meta: Metadata;
  try {
    meta = await sharp(path).metadata();
  } catch {
    add('asset-format', 'fail', 'Le fichier du cadre est illisible ou corrompu.');
    return { passed: false, checks };
  }

  const okFormat = meta.format === 'png' || meta.format === 'webp';
  if (!okFormat) {
    add('asset-format', 'fail', `Format « ${meta.format} » refusé. Utilisez un PNG ou un WebP.`);
  } else if (!meta.hasAlpha) {
    add(
      'asset-format',
      'fail',
      'Le cadre n’a pas de canal alpha : la photo de l’invité n’apparaîtra jamais.',
    );
  } else if ((meta.width ?? 0) < tpl.canvas.width || (meta.height ?? 0) < tpl.canvas.height) {
    add(
      'asset-format',
      'warn',
      `Cadre en ${meta.width}×${meta.height}, plus petit que le canevas ${tpl.canvas.width}×${tpl.canvas.height} : il sera étiré.`,
    );
  } else {
    add('asset-format', 'pass', `${meta.format?.toUpperCase()} ${meta.width}×${meta.height}, alpha présent.`);
  }

  /* 6 — poids */
  const size = (await stat(path)).size;
  add(
    'asset-weight',
    size > MAX_FRAME_BYTES ? 'warn' : 'pass',
    `${Math.round(size / 1024)} Ko${size > MAX_FRAME_BYTES ? ` — au-delà des ${MAX_FRAME_BYTES / 1024} Ko conseillés ; la data est chère.` : '.'}`,
  );

  const W = meta.width ?? tpl.canvas.width;
  const H = meta.height ?? tpl.canvas.height;
  const clampRegion = (r: { x: number; y: number; w: number; h: number }) => ({
    left: Math.max(0, Math.min(W - 1, Math.round(r.x * W))),
    top: Math.max(0, Math.min(H - 1, Math.round(r.y * H))),
    width: Math.max(1, Math.min(W, Math.round(r.w * W))),
    height: Math.max(1, Math.min(H, Math.round(r.h * H))),
  });

  /* 2 — la zone photo reste-t-elle visible ? */
  const slot = tpl.layers.find((l) => l.type === 'photoSlot');
  if (slot && slot.type === 'photoSlot') {
    try {
      const r = await opaqueRatio(path, clampRegion(slot.rect));
      const pct = Math.round(r * 100);
      if (r > OPAQUE_LIMIT) {
        add(
          'photo-visible',
          'fail',
          `Le cadre recouvre ${pct} % de la zone photo : l’invité n’apparaîtra pas. Rendez le centre transparent.`,
        );
      } else {
        add('photo-visible', 'pass', `Zone photo dégagée à ${100 - pct} %.`);
      }
    } catch {
      add('photo-visible', 'warn', 'La zone photo n’a pas pu être analysée.');
    }
  }

  /* 4 — un texte passe-t-il sous le filigrane OU sous le QR ? */
  // Ces deux blocs sont dessinés APRÈS tous les calques : ils ne peuvent pas
  // être recouverts. Le risque est l'inverse — un texte du partenaire placé
  // à leur emplacement, qu'ils viendront masquer.
  {
    const margin = 0.04;

    const wm = tpl.watermark;
    const wmW = 0.21;
    const wmBox = {
      x: wm.position === 'bottom-left' ? margin : wm.position === 'bottom-center' ? (1 - wmW) / 2 : 1 - wmW - margin,
      y: 1 - wmW * 0.42 - margin,
      w: wmW,
      h: wmW * 0.42,
    };

    const qrW = tpl.qr.size * 1.16; // code + zone de silence
    const qrBox = tpl.qr.enabled
      ? {
          x: tpl.qr.position === 'top-right' ? 1 - qrW - margin : margin,
          y: tpl.qr.position === 'bottom-left' ? 1 - qrW - margin : margin,
          w: qrW,
          h: qrW,
        }
      : null;

    const hits = (a: { x: number; y: number; w: number; h: number }, b: typeof a) =>
      a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;

    const clash: string[] = [];
    for (const l of tpl.layers) {
      if (l.type !== 'text') continue;
      if (hits(l.rect, wmBox)) clash.push(`« ${l.id} » sous le filigrane`);
      if (qrBox && hits(l.rect, qrBox)) clash.push(`« ${l.id} » sous le QR Code`);
    }

    add(
      'watermark-clear',
      clash.length ? 'fail' : 'pass',
      clash.length
        ? `Texte masqué : ${clash.join(', ')}. Décalez-le hors de ces zones.`
        : 'Aucun texte ne heurte le filigrane ni le QR Code.',
    );
  }

  /* 3 — les textes tiennent-ils ? */
  const texts = tpl.layers.filter((l) => l.type === 'text');
  const tight = texts.filter((l) => {
    if (l.type !== 'text' || l.autoShrink) return false;
    // Estimation : ~0,55 em de large par caractère en moyenne.
    const px = toPx(l.rect, tpl.canvas.width, tpl.canvas.height);
    return l.value.length * l.size * tpl.canvas.height * 0.55 > px.w;
  });
  add(
    'text-fits',
    tight.length ? 'fail' : 'pass',
    tight.length
      ? `${tight.length} texte(s) débordent de leur zone sans réduction automatique.`
      : `${texts.length} texte(s) tiennent dans leur zone.`,
  );

  /* 7 — contraste sur photo quelconque */
  // renderScene pose systématiquement une ombre portée SOMBRE sous le texte.
  // Un texte clair est donc toujours lisible, quelle que soit la photo.
  // Le risque réel est le texte sombre : il se confond avec sa propre ombre
  // dès que la photo est sombre — et les photos de sortie le sont souvent.
  {
    const dark = texts.filter((l) => {
      if (l.type !== 'text') return false;
      const hex = BRAND[l.color] ?? l.color;
      return /^#[0-9a-f]{6}$/i.test(hex) && luminance(hex) < 0.35;
    });
    const lightest = texts.length
      ? Math.max(
          ...texts.map((l) => {
            const hex = BRAND[(l as { color: string }).color] ?? (l as { color: string }).color;
            return /^#[0-9a-f]{6}$/i.test(hex) ? luminance(hex) : 1;
          }),
        )
      : 1;
    const onShadow = ratio(lightest, luminance('#0F172A'));

    add(
      'contrast',
      dark.length ? 'warn' : 'pass',
      dark.length
        ? `${dark.length} texte(s) en couleur sombre : ils se confondront avec leur ombre portée sur une photo sombre. Préférez brand.paper.`
        : `Texte clair sur ombre portée — contraste d’au moins ${onShadow.toFixed(1)}:1 quelle que soit la photo.`,
    );
  }

  const passed = !checks.some((c) => c.status === 'fail');

  const report = PreflightReport.parse({
    templateId: tpl.id,
    ranAt: nowIso(),
    passed,
    checks,
  });
  db()
    .prepare(
      `INSERT INTO preflight (decor_id, passed, report, ran_at) VALUES (?,?,?,?)
       ON CONFLICT(decor_id) DO UPDATE SET passed=excluded.passed, report=excluded.report, ran_at=excluded.ran_at`,
    )
    .run(decorId, passed ? 1 : 0, JSON.stringify(report), report.ranAt);

  return { passed, checks };
}

export function getPreflight(decorId: string) {
  const row = db().prepare('SELECT * FROM preflight WHERE decor_id = ?').get(decorId) as
    | { passed: number; report: string; ran_at: string }
    | undefined;
  if (!row) return null;
  try {
    return { ...JSON.parse(row.report), passed: !!row.passed } as {
      passed: boolean;
      ranAt: string;
      checks: Check[];
    };
  } catch {
    return null;
  }
}
