'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Card, Icon } from '@/components/ui';
import { SectionHead } from './Sections';

/**
 * Tarifs — repris du prototype, avec l'offre de lancement à −50 %.
 * Le curseur de crédits WhatsApp est le seul élément interactif : il rend
 * le coût tangible, ce qu'un tableau statique ne fait pas.
 */

const PLANS = [
  {
    tag: 'Pour tester',
    name: 'Découverte',
    price: 0,
    launch: 0,
    note: 'Gratuit à vie',
    cta: 'Commencer gratuitement',
    href: '/inscription?role=partner',
    feats: [
      ['1 campagne active', true],
      ['50 téléchargements badge / mois', true],
      ['Studio complet', true],
      ['Stats de base', true],
      ['Filigrane discret sur les badges', false],
      ['QR Code Koris', false],
    ] as [string, boolean][],
  },
  {
    tag: 'Entrée sérieuse',
    name: 'Impact',
    price: 5000,
    launch: 2500,
    cta: 'Choisir Impact',
    href: '/inscription?role=partner&plan=impact',
    highlight: true,
    feats: [
      ['3 campagnes actives', true],
      ['500 téléchargements / badge', true],
      ['Sans filigrane', true],
      ['Redirection après téléchargement', true],
      ['20 liens courts wkb.link', true],
      ['QR Code Koris intégré', true],
      ['Stats complètes « J’y serai »', true],
      ['Achat de crédits WhatsApp', true],
    ] as [string, boolean][],
  },
  {
    tag: 'Clients actifs',
    name: 'Croissance',
    price: 12000,
    launch: 6000,
    cta: 'Choisir Croissance',
    href: '/inscription?role=partner&plan=croissance',
    feats: [
      ['5 campagnes actives', true],
      ['2 000 téléchargements', true],
      ['Ciblage ville + intérêt', true],
      ['Diffusion à la base Wakabi', true],
      ['100 liens courts', true],
      ['Campagnes Telegram + Push', true],
    ] as [string, boolean][],
    prefix: 'Tout Impact, plus :',
  },
  {
    tag: 'Pros & institutions',
    name: 'Mouvement',
    price: 30000,
    launch: 15000,
    cta: 'Nous contacter',
    href: '/inscription?role=partner&plan=mouvement',
    feats: [
      ['Campagnes illimitées', true],
      ['Téléchargements illimités', true],
      ['Accès API REST', true],
      ['Web Push illimité', true],
      ['Article sponsorisé blog Wakabi', true],
      ['Account manager dédié', true],
    ] as [string, boolean][],
    prefix: 'Tout Croissance, plus :',
  },
];

const fmt = (n: number) => n.toLocaleString('fr-FR');

export function Pricing() {
  return (
    <section id="tarifs" className="scroll-mt-20 bg-white">
      <div className="mx-auto max-w-6xl px-5 py-20">
        <SectionHead
          center
          eyebrow="Offre de lancement"
          title="Démarrez gratuitement."
          accent="Payez quand ça marche."
          lede="4 formules pensées pour passer du test à la salle comble. −50 % pendant le lancement."
        />

        <div className="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          {PLANS.map((p) => (
            <Card
              key={p.name}
              className={`relative flex flex-col p-5 ${
                p.highlight
                  ? 'border-2 border-wk-primary shadow-wk-lg lg:-mt-3 lg:mb-3'
                  : 'shadow-wk-sm'
              }`}
            >
              {p.highlight && (
                <span className="absolute -top-3 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-wk-primary px-3 py-1 text-[11px] font-bold text-white">
                  ★ Recommandé
                </span>
              )}
              <p className="text-[11.5px] font-bold uppercase tracking-wider text-wk-text3">
                {p.tag}
              </p>
              <h3 className="mt-1 font-display text-[21px] font-extrabold tracking-tight">
                {p.name}
              </h3>

              <p className="mt-3 flex items-baseline gap-1.5">
                {p.price > 0 && (
                  <span className="text-[15px] font-semibold text-wk-text3 line-through">
                    {fmt(p.price)}
                  </span>
                )}
                <b className="font-display text-[30px] font-extrabold leading-none tracking-tight text-wk-primary tabular-nums">
                  {p.launch === 0 ? '0' : fmt(p.launch)}
                </b>
                <span className="text-[13px] font-semibold text-wk-text2">FCFA</span>
              </p>
              <p className="mt-1 text-[11.5px] leading-snug text-wk-text3">
                {p.note ?? `/ mois · les 3 premiers mois, puis ${fmt(p.price)}`}
              </p>

              <Link
                href={p.href}
                className={`mt-4 block rounded-wk-md py-2.5 text-center font-display text-[14px] font-bold transition ${
                  p.highlight
                    ? 'bg-wk-primary text-white hover:bg-wk-primary-d'
                    : 'border border-wk-border bg-white text-wk-text2 hover:border-wk-border2'
                }`}
              >
                {p.cta}
              </Link>

              {p.prefix && (
                <p className="mt-4 text-[12px] font-bold text-wk-text2">{p.prefix}</p>
              )}
              <ul className="mt-3 space-y-2">
                {p.feats.map(([label, ok]) => (
                  <li key={label} className="flex gap-2 text-[13px] leading-snug">
                    {ok ? (
                      <Icon name="check" className="mt-0.5 size-3.5 shrink-0 text-wk-green" />
                    ) : (
                      <span className="mt-0.5 w-3.5 shrink-0 text-center text-wk-text3">—</span>
                    )}
                    <span className={ok ? 'text-wk-text2' : 'text-wk-text3'}>{label}</span>
                  </li>
                ))}
              </ul>
            </Card>
          ))}
        </div>

        <p className="mt-6 text-center text-[13px] text-wk-text3">
          💳 Sans engagement · Résiliable en 1 clic · Paiement Mobile Money (Flooz, MoMo) & carte
        </p>

        <WhatsAppCredits />

        <div className="mt-8 flex items-start gap-4 rounded-wk-lg border border-wk-border bg-wk-bg2 p-5">
          <span className="text-[24px] leading-none">🛡️</span>
          <p className="text-[14px] leading-relaxed text-wk-text2">
            <b className="text-wk-text">Essai sans risque, satisfaction garantie.</b> Testez
            gratuitement autant que vous voulez. Passez à une offre payante uniquement quand vous
            voyez les résultats. Annulez en 1 clic, sans justification, sans frais cachés.
          </p>
        </div>
      </div>
    </section>
  );
}

/** Simulateur de crédits WhatsApp — 1 FCFA le message, dégressif dès 50 000. */
function WhatsAppCredits() {
  const [n, setN] = useState(5000);
  const unit = n >= 50_000 ? 0.85 : 1;
  const total = Math.round(n * unit);

  return (
    <div className="mt-10 rounded-wk-lg border border-wk-border bg-wk-bg2 p-6">
      <h3 className="font-display text-[18px] font-extrabold tracking-tight">
        Crédits WhatsApp : payez à l’usage
      </h3>
      <p className="mt-1.5 max-w-[62ch] text-[14px] leading-relaxed text-wk-text2">
        Les crédits s’achètent à part — comme chez tous les acteurs sérieux, ce sont des coûts
        réels facturés par Meta. Mais chez Wakabi chaque message est <b>ciblé</b> et coûte{' '}
        <b>moins cher</b>.
      </p>

      <div className="mt-5 grid gap-5 md:grid-cols-[1fr_auto] md:items-end">
        <div>
          <div className="mb-2 flex items-baseline justify-between">
            <label htmlFor="msg" className="text-[13px] font-semibold text-wk-text2">
              Glissez pour simuler votre besoin
            </label>
            <b className="font-display text-[19px] font-extrabold tabular-nums text-wk-primary">
              {fmt(n)} messages
            </b>
          </div>
          <input
            id="msg"
            type="range"
            min={1000}
            max={100_000}
            step={1000}
            value={n}
            onChange={(e) => setN(Number(e.target.value))}
            className="w-full accent-wk-primary"
          />
          <div className="mt-1 flex justify-between text-[11.5px] text-wk-text3">
            <span>1 000</span>
            <span>100 000+</span>
          </div>
        </div>

        <div className="rounded-wk-md border border-wk-border bg-white p-4 text-center md:min-w-[190px]">
          <span className="block text-[12px] text-wk-text3">Investissement total</span>
          <b className="mt-1 block font-display text-[26px] font-extrabold leading-none tabular-nums text-wk-text">
            {fmt(total)}
          </b>
          <span className="text-[12px] font-semibold text-wk-text2">FCFA</span>
          <p className="mt-2 text-[11px] leading-snug text-wk-text3">
            {unit} FCFA / message
            <br />
            <span className="text-wk-green">vs 1,5 FCFA ailleurs</span>
          </p>
        </div>
      </div>
      <p className="mt-3 text-[12px] text-wk-text3">
        Tarifs dégressifs dès 50 000 messages.
      </p>
    </div>
  );
}
