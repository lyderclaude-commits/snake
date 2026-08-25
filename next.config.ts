import type { NextConfig } from 'next';

const nextConfig: NextConfig = {
  reactStrictMode: true,
  // Le générateur est 100 % client : aucune photo ne transite par le serveur.
  // Voir docs/00-SPEC-TECHNIQUE.md, contrainte C6.
};

export default nextConfig;
