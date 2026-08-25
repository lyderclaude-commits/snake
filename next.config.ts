import type { NextConfig } from 'next';

const nextConfig: NextConfig = {
  reactStrictMode: true,
  // better-sqlite3 est un module natif : il ne doit pas être bundlé.
  serverExternalPackages: ['better-sqlite3'],
};

export default nextConfig;
