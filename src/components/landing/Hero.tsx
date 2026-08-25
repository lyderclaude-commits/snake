import { LinkButton, Icon } from '@/components/ui';

/**
 * Héros — repris du prototype « Wakabi Boost ».
 *
 * Note de ton : la landing VOUVOIE, parce qu'elle s'adresse aux
 * organisateurs. Le Studio, lui, tutoie : il parle aux participants.
 * Ce n'est pas une incohérence, ce sont deux audiences.
 */
export function Hero() {
  return (
    <section className="relative overflow-hidden">
      {/* halo de marque, discret */}
      <div
        aria-hidden
        className="pointer-events-none absolute -top-40 left-1/2 h-[520px] w-[900px] -translate-x-1/2 rounded-full opacity-[0.07] blur-3xl"
        style={{ background: 'radial-gradient(circle, #2563EB 0%, transparent 70%)' }}
      />
      <div className="relative mx-auto grid max-w-6xl gap-12 px-5 pb-16 pt-14 lg:grid-cols-[1.1fr_0.9fr] lg:items-center lg:pb-24 lg:pt-20">
        <div>
          <p className="mb-5 inline-flex items-center gap-2 rounded-full border border-wk-border bg-white px-3 py-1.5 text-[12px] font-semibold text-wk-text2 shadow-wk-sm">
            <span className="rounded-full bg-wk-primary px-2 py-0.5 text-[10px] font-bold text-white">
              NOUVEAU
            </span>
            La suite marketing événementielle de Wakabi
          </p>

          <h1 className="font-display text-[clamp(2.4rem,6.2vw,4rem)] font-extrabold leading-[1.02] tracking-[-0.038em] text-balance">
            Vos invitations méritent
            <br />
            <span className="text-wk-primary">une salle pleine.</span>
          </h1>

          <p className="mt-5 max-w-[52ch] text-[17px] leading-relaxed text-wk-text2">
            Badges viraux, WhatsApp, Push, Telegram, liens traçables.{' '}
            <strong className="text-wk-text">Wakabi Boost</strong> transforme chaque contact en
            présence réelle — et chaque présence en client fidèle grâce au{' '}
            <strong className="text-wk-text">QR Code qui rapporte</strong>.
          </p>

          <div className="mt-8 flex flex-wrap gap-3">
            <LinkButton href="/decors" size="lg">
              Créer mon badge gratuit <Icon name="arrow" className="size-4" />
            </LinkButton>
            <LinkButton href="#tarifs" variant="ghost" size="lg">
              Voir les offres
            </LinkButton>
          </div>

          <ul className="mt-7 flex flex-wrap gap-x-6 gap-y-2 text-[13px] font-medium text-wk-text2">
            {['Sans carte bancaire', 'Gratuit pour démarrer', 'Prêt en 2 minutes'].map((t) => (
              <li key={t} className="inline-flex items-center gap-1.5">
                <Icon name="check" className="size-4 text-wk-green" />
                {t}
              </li>
            ))}
          </ul>
        </div>

        <WhatsAppMockup />
      </div>
    </section>
  );
}

/**
 * Maquette WhatsApp — la démonstration visuelle du produit.
 * Couleurs réelles de l'interface WhatsApp, pour que la scène soit
 * immédiatement reconnaissable.
 */
function WhatsAppMockup() {
  return (
    <div className="relative mx-auto mb-8 w-full max-w-[340px] lg:mb-0">
      <div className="rounded-[30px] border-[10px] border-[#0F172A] bg-[#0B141A] shadow-wk-lg">
        {/* barre de conversation */}
        <div className="flex items-center gap-2.5 rounded-t-[20px] bg-[#202C33] px-3.5 py-3">
          <span className="text-[15px] text-[#8696A0]">‹</span>
          <span className="grid size-8 place-items-center rounded-full bg-wk-primary font-display text-[13px] font-extrabold text-white">
            W
          </span>
          <span className="min-w-0">
            <b className="block truncate font-display text-[13.5px] font-bold text-[#E9EDEF]">
              Wakabi Boost
            </b>
            <span className="text-[11px] text-[#8696A0]">en ligne</span>
          </span>
        </div>

        <div className="space-y-2 px-3 pb-6 pt-4">
          <p className="mx-auto w-fit rounded-md bg-[#182229] px-2.5 py-1 text-[10.5px] text-[#8696A0]">
            Aujourd&apos;hui
          </p>

          <Bubble side="in">
            Merci d&apos;avoir confirmé ta présence à la <b>GARDEN PARTY</b> 🌴 Hâte de te voir&nbsp;!
            <Time>10:41</Time>
          </Bubble>

          <Bubble side="in">
            <span className="mb-1.5 block font-bold">🎉 GARDEN PARTY</span>
            📅 Demain à 14h
            <br />
            📍 Cotonou — HECM
            <br />
            🎟️ Ton QR&nbsp;: <span className="font-bold text-[#25D366]">prêt</span>
            <span className="mt-2 block rounded-md bg-[#182229] px-2 py-1.5 text-[11.5px] leading-snug">
              Rappel J-1 : présente ton QR Code à l&apos;entrée pour gagner{' '}
              <b className="text-wk-gold">50 Koris</b> 🪙
            </span>
            <Time>10:42</Time>
          </Bubble>

          <Bubble side="out">
            J&apos;y serai ✅<Time>10:43 ✓✓</Time>
          </Bubble>
        </div>
      </div>

      {/* deux chiffres accrochés à la maquette */}
      <Badge className="-left-5 top-20" value="98 %" label="Taux de lecture" tone="green" />
      <Badge className="-bottom-6 right-4" value="+40 %" label="Présence réelle" tone="primary" />
    </div>
  );
}

function Bubble({ side, children }: { side: 'in' | 'out'; children: React.ReactNode }) {
  const isOut = side === 'out';
  return (
    <div className={isOut ? 'flex justify-end' : 'flex'}>
      <div
        className={`max-w-[85%] rounded-lg px-2.5 py-2 text-[12.5px] leading-snug text-[#E9EDEF] ${
          isOut ? 'bg-[#005C4B]' : 'bg-[#202C33]'
        }`}
      >
        {children}
      </div>
    </div>
  );
}

const Time = ({ children }: { children: React.ReactNode }) => (
  <span className="mt-1 block text-right text-[10px] text-[#8696A0]">{children}</span>
);

function Badge({
  className,
  value,
  label,
  tone,
}: {
  className: string;
  value: string;
  label: string;
  tone: 'green' | 'primary';
}) {
  return (
    <div
      className={`absolute hidden rounded-wk-md border border-wk-border bg-white px-3 py-2 shadow-wk sm:block ${className}`}
    >
      <b
        className={`block font-display text-[19px] font-extrabold leading-none ${
          tone === 'green' ? 'text-wk-green' : 'text-wk-primary'
        }`}
      >
        {value}
      </b>
      <span className="text-[11px] text-wk-text3">{label}</span>
    </div>
  );
}
