import type { Metadata, Viewport } from 'next';

/**
 * Polices AUTO-HÉBERGÉES, chargées par leur nom littéral.
 *
 * Deux raisons, et la seconde est la vraie :
 *  1. Le renderer canvas compose avec `'Bricolage Grotesque'`. next/font
 *     génère un nom de famille haché que canvas ne saurait pas résoudre.
 *  2. Dépendre de fonts.googleapis.com contredit la contrainte C2 : sur
 *     réseau instable et data payante, une requête externe bloquante peut
 *     faire échouer le rendu — et le mode hors ligne devient impossible.
 *     Fontsource sert les mêmes fichiers depuis notre propre domaine.
 */
import '@fontsource/bricolage-grotesque/latin-400.css';
import '@fontsource/bricolage-grotesque/latin-600.css';
import '@fontsource/bricolage-grotesque/latin-700.css';
import '@fontsource/bricolage-grotesque/latin-800.css';
import '@fontsource/plus-jakarta-sans/latin-400.css';
import '@fontsource/plus-jakarta-sans/latin-600.css';
import '@fontsource/plus-jakarta-sans/latin-700.css';
import './globals.css';

export const metadata: Metadata = {
  title: 'Wakabi Studio — Crée ton visuel',
  description:
    'Choisis un décor Wakabi, ajoute ta photo, partage. Gratuit, sans inscription. Le guide des bons plans — Lomé, Cotonou, Abidjan.',
};

export const viewport: Viewport = {
  themeColor: '#2563EB',
  width: 'device-width',
  initialScale: 1,
  // L'éditeur gère lui-même le pincement : laisser le navigateur zoomer
  // par-dessus rendrait le recadrage inutilisable.
  maximumScale: 1,
  userScalable: false,
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="fr">
      <body>{children}</body>
    </html>
  );
}
