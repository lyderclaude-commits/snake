/**
 * Le service worker — le bout de code qui vit quand le site est fermé.
 *
 * Il ne fait qu'UNE chose : recevoir une notification et l'afficher. Pas de
 * cache, pas de mode hors-ligne. Un service worker qui met en cache est un
 * service worker qui sert un jour une vieille page à quelqu'un qui vient de
 * mettre à jour, et le débogage de cette classe de panne coûte des heures.
 * Le jour où le hors-ligne sera un besoin réel, il sera ajouté ici, exprès.
 *
 * Ce fichier est servi tel quel, à la racine de l'application : la portée
 * d'un service worker est celle de son dossier, et depuis /public/ il ne
 * pourrait pas répondre pour les pages du site.
 */

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

self.addEventListener('push', (e) => {
  /**
   * Un push SANS contenu doit quand même afficher quelque chose.
   *
   * Les navigateurs exigent qu'une notification soit affichée pour chaque
   * message reçu : rester muet fait apparaître, au bout de quelques fois,
   * un « Ce site a été mis à jour en arrière-plan » que personne n'a
   * écrit. On préfère un texte à nous.
   */
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (_) { d = {}; }

  const titre = d.titre || 'Wakabi Boost';
  const options = {
    body: d.corps || '',
    icon: d.icone || './public/logo.png',
    badge: d.icone || './public/logo.png',
    image: d.image || undefined,
    tag: d.tag || 'wakabi',
    // Une promo ne doit pas remplacer silencieusement la précédente sans
    // que rien ne bouge à l'écran : si deux messages portent le même tag,
    // le second se signale quand même.
    renotify: !!d.tag,
    requireInteraction: false,
    data: { lien: d.lien || './' },
  };
  e.waitUntil(self.registration.showNotification(titre, options));
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const lien = (e.notification.data && e.notification.data.lien) || './';
  const cible = new URL(lien, self.registration.scope).href;

  /**
   * Rouvrir l'onglet DÉJÀ ouvert plutôt qu'en empiler un nouveau.
   *
   * Quelqu'un qui reçoit trois notifications dans la journée se retrouve
   * sinon avec trois onglets du même site, et finit par tout fermer.
   */
  e.waitUntil((async () => {
    const fenetres = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const f of fenetres) {
      if (f.url === cible && 'focus' in f) return f.focus();
    }
    for (const f of fenetres) {
      if ('navigate' in f) { await f.navigate(cible); return f.focus(); }
    }
    return self.clients.openWindow(cible);
  })());
});
