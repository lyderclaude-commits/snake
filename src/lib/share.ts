/**
 * Export et partage — l'arbre de décision, pas un simple bouton.
 *
 * Ouvert depuis Instagram, Facebook ou parfois WhatsApp, `<a download>` est
 * ignoré ou silencieusement bloqué, sur iOS en particulier. Or c'est
 * EXACTEMENT le chemin d'arrivée de nos utilisateurs (contrainte C3).
 *
 *   navigator.canShare({ files })
 *   ├─ oui  → navigator.share()            ← chemin principal mobile
 *   └─ non
 *      ├─ navigateur standard → <a download>
 *      └─ webview détectée    → image plein écran + « appui long »
 *
 * Voir docs/00-SPEC-TECHNIQUE.md §7.
 */

export type ExportRoute = 'share' | 'download' | 'long-press';

export function isInAppBrowser(ua: string = navigator.userAgent): boolean {
  return /(FBAN|FBAV|Instagram|Line\/|Twitter|WhatsApp|MicroMessenger|Snapchat)/i.test(ua);
}

export function chooseRoute(blob: Blob, filename: string): ExportRoute {
  const file = new File([blob], filename, { type: blob.type });
  const canShareFiles =
    typeof navigator !== 'undefined' &&
    typeof navigator.canShare === 'function' &&
    navigator.canShare({ files: [file] });

  if (canShareFiles) return 'share';
  return isInAppBrowser() ? 'long-press' : 'download';
}

export async function shareFile(
  blob: Blob,
  filename: string,
  text: string,
): Promise<boolean> {
  const file = new File([blob], filename, { type: blob.type });
  try {
    await navigator.share({ files: [file], text });
    return true;
  } catch (err) {
    // L'utilisateur a annulé : ce n'est pas une erreur.
    if (err instanceof Error && err.name === 'AbortError') return true;
    return false;
  }
}

export function triggerDownload(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  // Laisse au navigateur le temps de démarrer le téléchargement.
  setTimeout(() => URL.revokeObjectURL(url), 10_000);
}

export function slugifyFilename(slug: string, ext: string): string {
  const stamp = new Date().toISOString().slice(0, 10);
  return `wakabi-${slug}-${stamp}.${ext}`;
}
