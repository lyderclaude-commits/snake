/* Live Y Dream — le service worker des notifications de l'administration.
   Il ne fait qu'une chose : afficher la notification d'une nouvelle demande,
   et ouvrir la demande au clic. Pas de cache, pas de mode hors-ligne : un
   service worker qui met en cache finit un jour par servir une vieille page. */

self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });

self.addEventListener('push', function (e) {
  var d = {};
  try { d = e.data ? e.data.json() : {}; } catch (err) { d = {}; }
  // Un push doit TOUJOURS afficher quelque chose : sinon le navigateur écrit
  // lui-même « Ce site a été mis à jour en arrière-plan ».
  e.waitUntil(self.registration.showNotification(d.titre || 'Live Y Dream', {
    body: d.corps || 'Nouvelle demande de contact',
    icon: d.icone || 'assets/images/icone-notification.png',
    badge: d.icone || 'assets/images/icone-notification.png',
    tag: d.tag || 'lyd',
    renotify: true,
    data: { lien: d.lien || 'admin/messages' },
  }));
});

self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var lien = new URL(e.notification.data && e.notification.data.lien || 'admin/messages', self.registration.scope).href;
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (fenetres) {
    for (var i = 0; i < fenetres.length; i++) {
      if (fenetres[i].url.indexOf('/admin') !== -1 && 'navigate' in fenetres[i]) {
        return fenetres[i].navigate(lien).then(function (f) { return f && f.focus(); });
      }
    }
    return self.clients.openWindow(lien);
  }));
});
