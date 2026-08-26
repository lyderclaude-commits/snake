'use server';

import QRCode from 'qrcode';
import { currentUser } from '@/server/auth';
import * as badges from '@/server/repo/badges';
import { track } from '@/server/repo/decors';
import { record } from '@/server/repo/creations';
import { badgeUrl } from '@/server/config';

/**
 * Émet un badge et renvoie son QR.
 *
 * Appelé au moment du TÉLÉCHARGEMENT, jamais à l'aperçu : chaque jeton
 * représente une entrée potentielle, et un aperçu n'en consomme pas.
 *
 * Le QR est rendu en data URI côté serveur — le navigateur n'a qu'à le
 * dessiner, et le jeton ne transite pas par une bibliothèque cliente.
 */
export async function mintBadge(decorId: string): Promise<{
  token: string;
  url: string;
  qrDataUrl: string;
}> {
  const me = await currentUser();
  const token = badges.mint(decorId, me?.id ?? null);
  const url = badgeUrl(token);

  const qrDataUrl = await QRCode.toDataURL(url, {
    margin: 0,
    width: 512,
    errorCorrectionLevel: 'M',
    color: { dark: '#0F172A', light: '#FFFFFF' },
  });

  // Un badge émis EST un téléchargement : compter les deux séparément
  // ferait diverger deux chiffres qui décrivent le même geste.
  track(decorId, 'download');
  if (me) record(me.id, decorId);

  return { token, url, qrDataUrl };
}

export interface ScanState {
  ok?: boolean;
  message?: string;
  detail?: string;
}

export async function scanBadge(_prev: ScanState, form: FormData): Promise<ScanState> {
  const me = await currentUser();
  if (!me || (me.role !== 'admin' && me.role !== 'partner')) {
    return { ok: false, message: 'Accès réservé à l’équipe et aux partenaires.' };
  }

  const token = String(form.get('token') ?? '').trim().toUpperCase();
  if (!token) return { ok: false, message: 'Saisissez ou scannez un code.' };

  const r = badges.scan(token, me.id);
  if (r.ok) {
    return {
      ok: true,
      message: `Entrée validée — ${r.decor}`,
      detail: r.who
        ? `${r.who} · ${r.koris} Koris crédités`
        : 'Badge anonyme · présence comptée, aucun Kori (pas de compte)',
    };
  }
  if (r.reason === 'already') {
    return {
      ok: false,
      message: 'Ce badge a déjà été scanné.',
      detail: `${r.decor} · le ${new Date(r.scannedAt!).toLocaleString('fr-FR')}`,
    };
  }
  return { ok: false, message: 'Code inconnu.', detail: 'Vérifiez la saisie.' };
}
