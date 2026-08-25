import Link from 'next/link';
import { WakabiMark } from '@/components/WakabiMark';

export function Footer() {
  return (
    <footer className="mt-24 border-t border-wk-border bg-white">
      <div className="mx-auto grid max-w-6xl gap-8 px-5 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div className="sm:col-span-2 lg:col-span-1">
          <WakabiMark />
          <p className="mt-4 max-w-[34ch] text-[13.5px] leading-relaxed text-wk-text2">
            Le guide qui transforme chaque sortie en expérience inoubliable. Lomé, Cotonou,
            Abidjan.
          </p>
        </div>
        {[
          ['Le studio', [['/decors', 'Tous les décors'], ['/#etapes', 'Comment ça marche'], ['/#confiance', 'Tes données']]],
          ['Partenaires', [['/#partenaires', 'Devenir partenaire'], ['/inscription?role=partner', 'Créer un compte'], ['/connexion', 'Se connecter']]],
          ['Wakabi', [['https://wakabileguide.com/', 'Le guide'], ['https://wakabileguide.com/kori', 'Carte & Kori'], ['https://wakabileguide.com/contact', 'Contact']]],
        ].map(([title, links]) => (
          <div key={title as string}>
            <p className="mb-3 font-display text-[13px] font-bold text-wk-text">{title as string}</p>
            <ul className="space-y-2">
              {(links as string[][]).map(([href, label]) => (
                <li key={href}>
                  <Link
                    href={href}
                    className="text-[13.5px] text-wk-text2 transition hover:text-wk-primary"
                  >
                    {label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>
      <div className="border-t border-wk-border">
        <p className="mx-auto max-w-6xl px-5 py-5 text-[12.5px] text-wk-text3">
          © 2026 Wakabi — Le guide des bons plans. Fait avec amour depuis Lomé.
        </p>
      </div>
    </footer>
  );
}
