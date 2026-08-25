'use server';

import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { writeFile, mkdir } from 'node:fs/promises';
import { join } from 'node:path';
import { randomUUID } from 'node:crypto';
import { requireRole, requireUser } from '@/server/auth';
import * as repo from '@/server/repo/decors';
import { buildTemplate, type LayoutId } from '@/server/buildTemplate';
import type { DecorStatus } from '@/core/template.schema';
import { runPreflight } from '@/server/preflight';
import { push } from '@/server/repo/notifications';
import { canUpload, addUsage } from '@/server/quota';
import { isVerified } from '@/server/repo/email';

export interface DecorFormState {
  error?: string;
  /**
   * Valeurs re-servies au formulaire après une erreur.
   *
   * React 19 RÉINITIALISE un formulaire une fois l'action terminée, comme le
   * ferait un envoi natif. Sans ce renvoi, un partenaire qui se trompe sur un
   * champ perdrait tout ce qu'il a saisi — et abandonnerait.
   */
  values?: Record<string, string>;
}

const KEEP = [
  'title', 'subtitle', 'city', 'rubrique', 'expiresAt', 'layout',
  'claim', 'fieldLabel', 'fieldValue', 'redirectUrl', 'redirectLabel', 'caption', 'frameUrl',
];

const echo = (form: FormData): Record<string, string> =>
  Object.fromEntries(KEEP.map((k) => [k, String(form.get(k) ?? '')]));

/* ---------------- téléversement du cadre ---------------- */

const ALLOWED = new Map([
  ['image/png', '.png'],
  ['image/webp', '.webp'],
]);
const MAX_BYTES = 800 * 1024;

/**
 * Enregistre le cadre téléversé.
 *
 * Trois règles, dans cet ordre :
 *  - liste blanche sur le TYPE RÉEL (signature du fichier), pas l'extension ;
 *  - SVG refusé : c'est un document XML qui peut porter du JavaScript ;
 *  - plafond de poids, parce que la data est chère chez nos utilisateurs.
 */
async function saveFrame(file: File): Promise<string> {
  if (!file || file.size === 0) throw new Error('Ajoutez le fichier de votre cadre.');
  if (file.size > MAX_BYTES) {
    throw new Error(`Le cadre dépasse ${Math.round(MAX_BYTES / 1024)} Ko. Compressez-le.`);
  }

  const buf = Buffer.from(await file.arrayBuffer());
  const kind = sniff(buf);
  if (!kind) {
    throw new Error('Format refusé. Utilisez un PNG ou un WebP avec fond transparent.');
  }

  const name = `${randomUUID()}${ALLOWED.get(kind)}`;
  // Hors de public/ : Next fige ce dossier au build. Servi par
  // src/app/api/frames/[name]/route.ts.
  const dir = join(process.cwd(), process.env.WAKABI_UPLOADS ?? '.data/uploads');
  await mkdir(dir, { recursive: true });
  await writeFile(join(dir, name), buf);
  return `/api/frames/${name}`;
}

/** Détecte le type sur les octets, jamais sur le nom du fichier. */
function sniff(b: Buffer): string | null {
  if (b.length > 8 && b.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) {
    return 'image/png';
  }
  if (b.length > 12 && b.subarray(0, 4).toString() === 'RIFF' && b.subarray(8, 12).toString() === 'WEBP') {
    return 'image/webp';
  }
  return null;
}

/**
 * Téléverse le cadre SÉPARÉMENT du formulaire.
 *
 * Un champ `<input type="file">` ne peut pas être re-rempli par le code :
 * si la validation du formulaire échoue, le fichier choisi est perdu et le
 * partenaire doit le re-sélectionner. En téléversant dès la sélection, on
 * conserve l'URL dans un champ caché — les erreurs de saisie ne coûtent
 * plus le fichier.
 */
export async function uploadFrame(form: FormData): Promise<{ url?: string; error?: string }> {
  const me = await requireRole('partner', 'admin');
  const file = form.get('frame') as File;

  const q = canUpload(me.id, me.role, file?.size ?? 0);
  if (!q.ok) {
    return {
      error: `Quota atteint : ${Math.round(q.used / 1024 / 1024)} Mo utilisés sur ${Math.round(q.limit / 1024 / 1024)} Mo. Supprimez d’anciens décors.`,
    };
  }

  try {
    const url = await saveFrame(file);
    addUsage(me.id, file.size);
    return { url };
  } catch (e) {
    return { error: e instanceof Error ? e.message : 'Téléversement impossible.' };
  }
}

/* ---------------- création ---------------- */

export async function createDecor(
  _prev: DecorFormState,
  form: FormData,
): Promise<DecorFormState> {
  const me = await requireRole('partner', 'admin');

  const title = String(form.get('title') ?? '').trim();
  const claim = String(form.get('claim') ?? '').trim();
  const redirectUrl = String(form.get('redirectUrl') ?? '').trim();
  const layout = String(form.get('layout') ?? 'bandeau') as LayoutId;

  if (!title) return { error: 'Donnez un titre à votre décor.', values: echo(form) };
  if (!claim) return { error: 'Indiquez l’accroche affichée sur le badge.', values: echo(form) };
  if (!redirectUrl) return { error: 'Indiquez la page vers laquelle renvoyer après téléchargement.', values: echo(form) };

  let slug = repo.slugify(title);
  let n = 2;
  while (repo.slugExists(slug)) slug = `${repo.slugify(title)}-${n++}`;

  const frameUrl = String(form.get('frameUrl') ?? '').trim();
  if (!frameUrl.startsWith('/api/frames/')) {
    return { error: 'Téléversez d’abord le fichier de votre cadre.', values: echo(form) };
  }

  let template;
  try {
    template = buildTemplate({
      slug, title,
      subtitle: String(form.get('subtitle') ?? '').trim(),
      city: String(form.get('city') ?? 'all'),
      rubrique: String(form.get('rubrique') ?? 'campagne'),
      layout, frameUrl, claim,
      fieldLabel: String(form.get('fieldLabel') ?? '').trim() || 'Ton prénom',
      fieldValue: String(form.get('fieldValue') ?? '').trim() || 'Kossi',
      redirectUrl,
      redirectLabel: String(form.get('redirectLabel') ?? '').trim(),
      caption: String(form.get('caption') ?? '').trim(),
      expiresAt: String(form.get('expiresAt') ?? '').trim() || null,
      createdBy: me.role === 'admin' ? 'wakabi-team' : 'partner',
      partnerId: me.role === 'partner' ? me.id : undefined,
    });
  } catch (e) {
    // Le schéma refuse par exemple une redirection hors domaine Wakabi
    // pour un décor de partenaire : le message vient de lui.
    const msg = e instanceof Error ? e.message : '';
    const first = /"message":\s*"([^"]+)"/.exec(msg)?.[1];
    return { error: first ?? 'Ce décor ne respecte pas les règles de publication.', values: echo(form) };
  }

  const id = repo.create({
    slug, title,
    subtitle: String(form.get('subtitle') ?? '').trim() || undefined,
    city: String(form.get('city') ?? 'all'),
    rubrique: String(form.get('rubrique') ?? 'campagne'),
    template, frameUrl,
    authorId: me.id,
    createdBy: me.role === 'admin' ? 'wakabi-team' : 'partner',
    expiresAt: String(form.get('expiresAt') ?? '').trim() || null,
  });

  revalidatePath('/partenaire');
  redirect(me.role === 'admin' ? '/admin' : `/partenaire?cree=${id}`);
}

/* ---------------- transitions ---------------- */

export interface SubmitResult {
  ok: boolean;
  message?: string;
  failures?: string[];
}

/**
 * Soumet un décor — après le pré-vol.
 *
 * Un décor qui échoue ne rejoint JAMAIS la file d'attente : il revient au
 * partenaire avec la liste de ce qui cloche. C'est ce qui permet de tenir
 * l'engagement des 24 h — le relecteur ne voit que des décors valides.
 */
export async function submitDecor(id: string): Promise<SubmitResult> {
  const me = await requireRole('partner', 'admin');
  const d = repo.findById(id);
  if (!d) return { ok: false, message: 'Décor introuvable.' };
  if (me.role === 'partner' && d.author_id !== me.id) {
    return { ok: false, message: 'Ce décor ne vous appartient pas.' };
  }
  if (me.role === 'partner' && !isVerified(me.id)) {
    return { ok: false, message: 'Vérifiez d’abord votre adresse e-mail.' };
  }

  const flight = await runPreflight(id, d.template_json, d.frame_url);
  if (!flight.passed) {
    return {
      ok: false,
      message: 'Le contrôle automatique a relevé des problèmes bloquants.',
      failures: flight.checks.filter((c) => c.status === 'fail').map((c) => c.message),
    };
  }

  repo.transition(id, 'pending_review', { id: me.id, role: 'partner' });

  // Prévenir l'équipe : la file d'attente ne se surveille pas toute seule.
  for (const admin of listAdmins()) {
    push(admin.id, 'review', 'Un décor attend votre relecture', d.title, '/admin/moderation');
  }

  revalidatePath('/partenaire');
  revalidatePath('/admin/moderation');
  return { ok: true, message: 'Soumis — réponse sous 24 h ouvrées.' };
}

function listAdmins() {
  const { listUsers } = require('@/server/repo/users') as typeof import('@/server/repo/users');
  return listUsers().filter((u) => u.role === 'admin' && !u.suspended);
}

export async function reviewDecor(form: FormData) {
  const me = await requireRole('admin');
  const id = String(form.get('id') ?? '');
  const to = String(form.get('to') ?? '') as DecorStatus;
  const note = String(form.get('note') ?? '');

  const d = repo.findById(id);
  try {
    repo.transition(id, to, { id: me.id, role: 'wakabi-team' }, note);

    // Le partenaire doit apprendre la décision, et surtout son motif.
    if (d?.author_id) {
      const titles: Record<string, string> = {
        published: 'Votre décor est publié 🎉',
        changes_requested: 'Corrections demandées sur votre décor',
        rejected: 'Votre décor a été refusé',
      };
      if (titles[to]) {
        push(
          d.author_id,
          to,
          titles[to],
          to === 'published' ? d.title : `${d.title} — ${note}`,
          '/partenaire',
        );
      }
    }
  } catch {
    // La machine à états a refusé : on laisse la page se recharger telle quelle.
  }
  revalidatePath('/admin/moderation');
  revalidatePath('/admin');
  revalidatePath('/decors');
}

export async function trackEvent(decorId: string, kind: 'view' | 'download') {
  repo.track(decorId, kind);
}

export async function recordCreation(decorId: string) {
  const me = await requireUser().catch(() => null);
  if (!me) return;
  const { record } = await import('@/server/repo/creations');
  record(me.id, decorId);
}
