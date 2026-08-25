import { readFile, stat } from 'node:fs/promises';
import { join } from 'node:path';
import { NextResponse } from 'next/server';

/**
 * Sert les cadres téléversés.
 *
 * Ils ne peuvent PAS vivre dans `public/` : Next fige ce dossier au moment du
 * build, donc un fichier écrit ensuite n'est jamais servi — et sur une image
 * de conteneur immuable, l'écriture échouerait de toute façon. Ils vivent donc
 * dans `.data/uploads/`, à côté de la base, et transitent par cette route.
 *
 * En production ce dossier sera un volume monté, ou remplacé par un stockage
 * objet : seul ce fichier changera.
 */

const DIR = process.env.WAKABI_UPLOADS ?? '.data/uploads';

const TYPES: Record<string, string> = { '.png': 'image/png', '.webp': 'image/webp' };

/** Nom généré par nous : UUID + extension. Tout le reste est refusé. */
const SAFE = /^[0-9a-f-]{36}\.(png|webp)$/;

export async function GET(
  _req: Request,
  { params }: { params: Promise<{ name: string }> },
) {
  const { name } = await params;

  // Verrou anti-traversée : on ne concatène jamais un nom non vérifié.
  if (!SAFE.test(name)) {
    return new NextResponse('Not found', { status: 404 });
  }

  const path = join(process.cwd(), DIR, name);
  try {
    const [file, info] = await Promise.all([readFile(path), stat(path)]);
    const ext = name.slice(name.lastIndexOf('.'));
    return new NextResponse(new Uint8Array(file), {
      headers: {
        'Content-Type': TYPES[ext] ?? 'application/octet-stream',
        'Content-Length': String(info.size),
        // Le nom contient un UUID : le contenu ne change jamais.
        'Cache-Control': 'public, max-age=31536000, immutable',
      },
    });
  } catch {
    return new NextResponse('Not found', { status: 404 });
  }
}
