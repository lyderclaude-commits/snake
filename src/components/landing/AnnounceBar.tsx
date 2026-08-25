import Link from 'next/link';

export function AnnounceBar() {
  return (
    <Link
      href="#tarifs"
      className="block bg-wk-primary text-center text-white transition hover:bg-wk-primary-d"
    >
      <p className="mx-auto max-w-6xl px-5 py-2 text-[12.5px] font-semibold">
        🎉 Offre de lancement Wakabi Boost — <b>−50 % les 3 premiers mois</b>
        <span className="hidden sm:inline"> · se termine bientôt</span>
      </p>
    </Link>
  );
}
