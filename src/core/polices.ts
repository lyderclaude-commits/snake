/**
 * Attendre les polices AVANT de dessiner sur un canevas.
 *
 * Un canevas dessine avec la police chargée à l'instant précis où l'ordre
 * est donné. Si la fonte n'est pas encore là, il n'attend pas : il prend le
 * repli — `system-ui` — et le texte sort correct, simplement pas dans la
 * bonne police. C'est un défaut qui ne se voit pas, parce qu'il ne produit
 * rien d'anormal ; il se voit seulement quand on compare deux badges.
 *
 * Le produit demandait Bricolage Grotesque pour chaque accroche sans jamais
 * la servir : tous les badges sortaient dans la police du téléphone de
 * l'invité. La servir ne suffisait pas — encore fallait-il l'attendre.
 *
 * On échoue en silence : un badge dans une police de repli vaut mieux qu'un
 * studio qui ne dessine rien parce qu'un fichier de fonte manque.
 */
export async function attendrePolices(): Promise<void> {
  const d = (globalThis as { document?: Document }).document;
  if (!d?.fonts) return;
  await Promise.all([
    d.fonts.load("800 64px 'Bricolage Grotesque'"),
    d.fonts.load("700 64px 'Bricolage Grotesque'"),
    d.fonts.load("400 64px 'Bricolage Grotesque'"),
    d.fonts.load("600 32px 'Plus Jakarta Sans'"),
    d.fonts.load("400 32px 'Plus Jakarta Sans'"),
  ]).catch(() => undefined);
}
