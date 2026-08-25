import { SectionHead } from './Sections';

const QA: [string, string][] = [
  [
    'C’est quoi la différence avec un générateur de badge classique ?',
    'Un générateur classique produit une image que vos invités partagent — c’est tout. Wakabi Boost ajoute un QR Code unique sur chaque badge : scanné à l’entrée, il mesure la présence réelle, crédite des Koris à l’invité et le transforme en client fidèle de l’écosystème. Vous ne créez plus du buzz qui retombe, vous créez de la valeur durable.',
  ],
  [
    'Est-ce que je peux vraiment démarrer gratuitement ?',
    'Oui. La formule Découverte est gratuite à vie, sans carte bancaire : 1 campagne active, 50 téléchargements de badges par mois et le Studio complet. Vous ne payez que lorsque vous voulez plus de portée ou des fonctionnalités avancées.',
  ],
  [
    'Comment fonctionne le ciblage WhatsApp ?',
    'Contrairement aux envois aveugles, Wakabi vous laisse cibler par ville, centre d’intérêt et historique de visite parmi les 10 000+ utilisateurs de l’écosystème. Vos messages atteignent les bonnes personnes — donc plus de présence pour moins de budget. Les crédits coûtent 1 FCFA par message.',
  ],
  [
    'Les crédits WhatsApp sont-ils inclus dans l’abonnement ?',
    'Non, ils s’achètent à part (1 FCFA/message), comme chez tous les acteurs sérieux — ce sont des coûts réels facturés par Meta. En revanche, nos tarifs dégressifs et le ciblage intelligent font que vous dépensez beaucoup moins pour un meilleur résultat.',
  ],
  [
    'Je n’ai aucune compétence technique. C’est compliqué ?',
    'Pas du tout. Le Studio fonctionne en glisser-déposer, les campagnes se lancent en quelques clics, et tout est en français. Si vous savez envoyer un message WhatsApp, vous savez utiliser Wakabi Boost.',
  ],
  [
    'Que devient la photo de mes invités ?',
    'Elle ne quitte jamais leur téléphone. Le badge est fabriqué entièrement dans leur navigateur : rien n’est téléversé, rien n’est conservé sur nos serveurs. C’est plus rapide sur réseau faible, moins coûteux en données, et il n’y a aucune donnée personnelle à protéger.',
  ],
];

export function Faq() {
  return (
    <section id="faq" className="mx-auto max-w-4xl scroll-mt-20 px-5 py-20">
      <SectionHead center eyebrow="Vos questions" title="Tout ce que vous" accent="voulez savoir." />
      <div className="mt-10 space-y-3">
        {QA.map(([q, a]) => (
          <details
            key={q}
            className="group overflow-hidden rounded-wk-lg border border-wk-border bg-white"
          >
            <summary className="flex cursor-pointer list-none items-center gap-4 px-5 py-4 font-display text-[15.5px] font-bold leading-snug [&::-webkit-details-marker]:hidden">
              <span className="flex-1">{q}</span>
              <span className="shrink-0 text-[20px] font-normal text-wk-text3 transition group-open:rotate-45">
                +
              </span>
            </summary>
            <p className="border-t border-wk-border px-5 py-4 text-[14.5px] leading-relaxed text-wk-text2">
              {a}
            </p>
          </details>
        ))}
      </div>
    </section>
  );
}
