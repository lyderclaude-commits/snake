/**
 * Marque textuelle de l'interface.
 *
 * Ce n'est PAS le logo Wakabi : aucun fichier vectoriel n'existe, et
 * reconstruire la marque de mémoire finirait par être publié par
 * inadvertance. On compose donc le nom et la signature dans les polices de
 * la charte, en attendant l'asset. Voir docs/02-CHARTE-WAKABI.md §2 bis.
 */
export function WakabiMark({ tagline = true }: { tagline?: boolean }) {
  return (
    <span className="inline-flex flex-col leading-none">
      <span className="font-display text-[19px] font-extrabold tracking-[-0.03em] text-wk-text">
        WAKABI<span className="text-wk-primary">.</span>
      </span>
      {tagline && (
        <span className="mt-[3px] text-[7.5px] font-semibold tracking-[0.22em] text-wk-text3">
          LE GUIDE DES BONS PLANS
        </span>
      )}
    </span>
  );
}
