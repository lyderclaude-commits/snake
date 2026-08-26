import type { NextConfig } from 'next';

const nextConfig: NextConfig = {
  reactStrictMode: true,
  // Sortie autonome : `next build` produit .next/standalone, qui embarque le
  // serveur et les seules dépendances utilisées. C'est ce qu'on téléverse —
  // pas les 400 Mo de node_modules.
  output: 'standalone',
  // better-sqlite3 est un module natif : il ne doit pas être bundlé.
  serverExternalPackages: ['better-sqlite3'],
};

export default nextConfig;
