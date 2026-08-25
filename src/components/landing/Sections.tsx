import Link from 'next/link';
import { Card, Icon, LinkButton } from '@/components/ui';

/* ═══════════════ bandeau de chiffres ═══════════════ */

export function StatsBand({
  organisateurs,
  utilisateurs,
}: {
  organisateurs: number;
  utilisateurs: number;
}) {
  const items = [
    { v: organisateurs.toLocaleString('fr-FR'), l: 'Organisateurs' },
    { v: '98 %', l: 'messages lus' },
    { v: utilisateurs.toLocaleString('fr-FR'), l: 'Utilisateurs touchables' },
    { v: '1 FCFA', l: 'par message ciblé' },
  ];
  return (
    <section className="border-y border-wk-border bg-white">
      <div className="mx-auto grid max-w-6xl grid-cols-2 gap-6 px-5 py-8 md:grid-cols-4">
        {items.map((i) => (
          <div key={i.l}>
            <b className="block font-display text-[clamp(1.6rem,4vw,2.2rem)] font-extrabold leading-none tracking-tight text-wk-primary tabular-nums">
              {i.v}
            </b>
            <span className="mt-1.5 block text-[13px] text-wk-text2">{i.l}</span>
          </div>
        ))}
      </div>
    </section>
  );
}

/* ═══════════════ en-tête de section ═══════════════ */

export function SectionHead({
  eyebrow,
  title,
  accent,
  lede,
  center = false,
}: {
  eyebrow: string;
  title: string;
  accent?: string;
  lede?: string;
  center?: boolean;
}) {
  return (
    <div className={center ? 'mx-auto max-w-2xl text-center' : 'max-w-2xl'}>
      <p className="mb-3 inline-block rounded-full bg-wk-primary-l px-3 py-1 text-[12px] font-bold uppercase tracking-wider text-wk-primary">
        {eyebrow}
      </p>
      <h2 className="font-display text-[clamp(1.8rem,4.6vw,2.7rem)] font-extrabold leading-[1.08] tracking-[-0.03em] text-balance">
        {title}
        {accent && (
          <>
            <br />
            <span className="text-wk-primary">{accent}</span>
          </>
        )}
      </h2>
      {lede && (
        <p className="mt-4 text-[16.5px] leading-relaxed text-wk-text2 text-pretty">{lede}</p>
      )}
    </div>
  );
}

/* ═══════════════ canaux ═══════════════ */

const CHANNELS = [
  {
    emoji: '💬',
    title: 'WhatsApp & Rappels',
    body: 'Invitations, rappels automatiques J-1 et H-2, chatbots. Vos messages lus à 98 %, jamais dans les spams.',
    note: 'À partir de 1 FCFA/message',
    cta: 'Configurer',
  },
  {
    emoji: '🔔',
    title: 'Notifications Push',
    body: 'Notifiez vos abonnés directement sur leur navigateur, sans application à installer.',
    note: "Coût d'envoi : zéro",
    cta: 'Activer',
  },
  {
    emoji: '✈️',
    title: 'Telegram',
    body: 'Créez des canaux de diffusion illimités et sécurisés. Idéal pour fédérer une communauté fidèle.',
    note: 'Canaux illimités',
    cta: 'Connecter',
  },
  {
    emoji: '🔗',
    title: 'Liens courts',
    body: 'Raccourcissez vos URLs, suivez les clics en temps réel et retargetez votre audience.',
    note: 'wkb.link',
    cta: 'Générer',
  },
  {
    emoji: '🎨',
    title: 'Studio Badge « J’y serai »',
    body: 'Vos invités créent leur badge personnalisé en 1 clic et le partagent. Effet viral sur WhatsApp et les réseaux.',
    note: 'Disponible maintenant',
    cta: 'Ouvrir le Studio',
    href: '/decors',
    live: true,
  },
];

export function Channels() {
  return (
    <section id="solutions" className="mx-auto max-w-6xl scroll-mt-20 px-5 py-20">
      <SectionHead
        eyebrow="Ne soyez plus jamais ignoré"
        title="Tous vos canaux."
        accent="Un seul tableau de bord."
        lede="Les emails finissent dans les spams. Wakabi Boost utilise les canaux que vos clients lisent vraiment — et mesure chaque résultat."
      />

      <div className="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {CHANNELS.map((c) => (
          <Card key={c.title} className="flex flex-col p-5 shadow-wk-sm transition hover:shadow-wk">
            <span className="text-[26px] leading-none">{c.emoji}</span>
            <h3 className="mt-3 font-display text-[17px] font-bold leading-tight">{c.title}</h3>
            <p className="mt-2 flex-1 text-[14px] leading-relaxed text-wk-text2">{c.body}</p>
            <p className="mt-3 text-[12.5px] font-semibold text-wk-primary">{c.note}</p>
            {c.href ? (
              <Link
                href={c.href}
                className="mt-3 inline-flex items-center gap-1.5 text-[13.5px] font-bold text-wk-primary hover:underline"
              >
                {c.cta} <Icon name="arrow" className="size-3.5" />
              </Link>
            ) : (
              <span className="mt-3 inline-flex items-center gap-1.5 text-[13.5px] font-semibold text-wk-text3">
                {c.cta} — bientôt
              </span>
            )}
          </Card>
        ))}

        {/* la carte qui porte la différence */}
        <Card className="relative flex flex-col overflow-hidden border-wk-primary bg-wk-primary p-5 text-white shadow-wk-lg">
          <span className="absolute right-4 top-4 rounded-full bg-white/20 px-2.5 py-0.5 text-[10.5px] font-bold">
            Exclusif
          </span>
          <span className="text-[26px] leading-none">🪙</span>
          <h3 className="mt-3 font-display text-[17px] font-bold leading-tight">
            QR Code qui rapporte
          </h3>
          <p className="mt-2 flex-1 text-[14px] leading-relaxed text-white/85">
            Là où les autres s&apos;arrêtent à l&apos;image, Wakabi va plus loin : chaque badge
            intègre un QR Code scanné à l&apos;entrée — qui crédite des <b>Koris</b> et trace la
            présence <b>réelle</b>.
          </p>
          <Link
            href="#comparatif"
            className="mt-3 inline-flex items-center gap-1.5 text-[13.5px] font-bold text-white hover:underline"
          >
            Voir la différence <Icon name="arrow" className="size-3.5" />
          </Link>
        </Card>
      </div>
    </section>
  );
}

/* ═══════════════ les 3 étapes ═══════════════ */

const STEPS = [
  {
    t: 'Le badge viral',
    b: 'Vos invités créent leur badge « J’y serai » dans le Studio et le partagent. Chaque partage attire de nouveaux invités.',
  },
  {
    t: 'WhatsApp & rappels auto',
    b: 'Invitations et rappels J-1, H-2 envoyés automatiquement. Vous ne courez plus après vos invités — Wakabi le fait.',
  },
  {
    t: 'QR Code à l’entrée → Koris',
    b: 'Chaque présent scanne son QR, gagne des Koris et devient un client fidèle de l’écosystème Wakabi. Vous mesurez tout.',
  },
];

export function Steps() {
  return (
    <section id="etapes" className="scroll-mt-20 bg-white">
      <div className="mx-auto max-w-6xl px-5 py-20">
        <SectionHead
          eyebrow="Unifiez vos canaux"
          title="De l’intérêt à"
          accent="la présence réelle."
          lede="Un générateur de badge isolé crée du buzz qui retombe. Wakabi Boost orchestre toute la chaîne — du premier partage jusqu’au scan à l’entrée."
        />
        <ol className="mt-10 grid gap-5 md:grid-cols-3">
          {STEPS.map((s, i) => (
            <li key={s.t} className="rounded-wk-lg border border-wk-border bg-wk-bg2 p-6">
              <span className="grid size-10 place-items-center rounded-wk-md bg-wk-primary font-display text-[17px] font-extrabold text-white">
                {i + 1}
              </span>
              <h3 className="mt-4 font-display text-[17px] font-bold leading-tight">{s.t}</h3>
              <p className="mt-2 text-[14px] leading-relaxed text-wk-text2">{s.b}</p>
            </li>
          ))}
        </ol>
      </div>
    </section>
  );
}

/* ═══════════════ comparatif ═══════════════ */

const ROWS: [string, string, string][] = [
  ['Badge viral « J’y serai »', 'image statique', 'avec QR Code intégré'],
  ['Diffusion WhatsApp', 'envoi aveugle', 'ciblage ville + intérêt'],
  ['Rappels automatiques J-1 / H-2', '✗', 'inclus'],
  ['Présence réelle mesurée', '✗', 'scan QR à l’entrée'],
  ['Récompenses & fidélisation', '✗', 'Koris automatiques'],
  ['Base d’utilisateurs activable', 'vous partez de zéro', '10 000 utilisateurs Wakabi'],
  ['Coût par message WhatsApp', '1,5 FCFA', '1 FCFA + ciblé'],
  ['Écosystème connecté', 'outils isolés', 'lié à l’app & aux lieux'],
];

export function Comparison() {
  return (
    <section id="comparatif" className="mx-auto max-w-6xl scroll-mt-20 px-5 py-20">
      <SectionHead
        eyebrow="La différence Wakabi"
        title="Pourquoi un simple générateur"
        accent="ne suffit plus."
        lede="Les autres outils s’arrêtent à l’image. Wakabi transforme chaque campagne en présence mesurée et en clients fidèles."
      />

      <div className="mt-10 overflow-x-auto rounded-wk-lg border border-wk-border bg-white">
        <table className="w-full min-w-[600px] border-collapse text-[14px]">
          <thead>
            <tr className="border-b border-wk-border bg-wk-surface2">
              <th className="px-5 py-3 text-left font-display text-[13px] font-bold">
                Fonctionnalité
              </th>
              <th className="px-5 py-3 text-left text-[12.5px] font-semibold text-wk-text3">
                Générateurs classiques
              </th>
              <th className="px-5 py-3 text-left font-display text-[13px] font-bold text-wk-primary">
                Wakabi Boost
              </th>
            </tr>
          </thead>
          <tbody>
            {ROWS.map(([f, a, b]) => (
              <tr key={f} className="border-b border-wk-border last:border-0">
                <td className="px-5 py-3.5 font-semibold">{f}</td>
                <td className="px-5 py-3.5 text-wk-text3">
                  {a === '✗' ? <span className="text-wk-red">✗</span> : a}
                </td>
                <td className="px-5 py-3.5">
                  <span className="inline-flex items-center gap-1.5 font-semibold text-wk-text">
                    <Icon name="check" className="size-4 shrink-0 text-wk-green" />
                    {b}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="mt-4 text-center font-display text-[15px] font-bold text-wk-text2">
        Même budget. Bien plus de résultats.
      </p>
    </section>
  );
}

/* ═══════════════ témoignages ═══════════════ */

const QUOTES = [
  {
    q: 'Avant Wakabi Boost, je devais relancer mes invités un par un. Maintenant les rappels partent seuls et ma dernière soirée a fait salle comble.',
    n: 'Kofi Mensah',
    r: 'Organisateur, Lomé',
    i: 'KM',
    m: '1 200 badges',
  },
  {
    q: 'Le badge « J’y serai » a été partagé plus de 1 200 fois en 3 jours. Une visibilité que je n’aurais jamais pu m’offrir en pub classique.',
    n: 'Aïcha Traoré',
    r: 'Promotrice événementielle, Cotonou',
    i: 'AT',
    m: '98 % lus',
  },
  {
    q: 'Le QR Code à l’entrée a tout changé : je sais exactement qui est venu, et mes invités reviennent pour les Koris. Du jamais vu.',
    n: 'Emmanuel Agbo',
    r: 'Gérant de lieu, Cotonou',
    i: 'EA',
    m: 'Présence tracée',
  },
];

export function Testimonials() {
  return (
    <section className="bg-white">
      <div className="mx-auto max-w-6xl px-5 py-20">
        <SectionHead
          eyebrow="Ils ont rempli leurs salles"
          title="Des résultats,"
          accent="pas des promesses."
        />
        <div className="mt-10 grid gap-5 md:grid-cols-3">
          {QUOTES.map((t) => (
            <Card key={t.n} className="flex flex-col p-6 shadow-wk-sm">
              <p className="flex-1 text-[15px] leading-relaxed text-wk-text2">« {t.q} »</p>
              <div className="mt-5 flex items-center gap-3 border-t border-wk-border pt-4">
                <span className="grid size-10 shrink-0 place-items-center rounded-full bg-wk-primary-l font-display text-[13px] font-extrabold text-wk-primary">
                  {t.i}
                </span>
                <span className="min-w-0 flex-1">
                  <b className="block truncate font-display text-[14px] font-bold">{t.n}</b>
                  <span className="block truncate text-[12.5px] text-wk-text3">{t.r}</span>
                </span>
                <span className="shrink-0 rounded-wk-sm bg-green-50 px-2 py-1 text-[11px] font-bold text-wk-green">
                  {t.m}
                </span>
              </div>
            </Card>
          ))}
        </div>
      </div>
    </section>
  );
}

/* ═══════════════ appel final ═══════════════ */

export function FinalCta({ organisateurs }: { organisateurs: number }) {
  return (
    <section className="mx-auto max-w-6xl px-5 py-20">
      <div className="relative overflow-hidden rounded-wk-xl bg-wk-primary px-6 py-14 text-center text-white shadow-wk-lg">
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 opacity-20"
          style={{ background: 'radial-gradient(circle at 30% 20%, #60A5FA 0%, transparent 55%)' }}
        />
        <div className="relative">
          <h2 className="mx-auto max-w-[20ch] font-display text-[clamp(1.8rem,4.6vw,2.8rem)] font-extrabold leading-[1.08] tracking-[-0.03em] text-balance">
            Votre prochaine salle comble commence maintenant.
          </h2>
          <p className="mx-auto mt-4 max-w-[46ch] text-[16px] text-white/85">
            Rejoignez les {organisateurs.toLocaleString('fr-FR')} organisateurs qui ne laissent
            plus jamais une chaise vide.
          </p>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <LinkButton href="/decors" variant="ghost" size="lg" className="!border-white !bg-white !text-wk-primary">
              Créer mon badge gratuit
            </LinkButton>
            <LinkButton
              href="#tarifs"
              variant="ghost"
              size="lg"
              className="!border-white/45 !bg-transparent !text-white hover:!bg-white/10"
            >
              Voir les offres −50 %
            </LinkButton>
          </div>
        </div>
      </div>
    </section>
  );
}
