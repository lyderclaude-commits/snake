/**
 * L'abonnement aux notifications, côté navigateur.
 *
 * Trois états, et un seul bouton qui les traverse : « pas encore demandé »,
 * « accepté », « refusé ». Le troisième est définitif du point de vue du
 * site — un navigateur qui a refusé ne redemandera jamais, et c'est voulu.
 * On le dit clairement plutôt que de laisser cliquer un bouton mort.
 *
 * La demande de permission part d'un CLIC, jamais du chargement de la page.
 * Les navigateurs pénalisent durablement un site qui demande sans geste, et
 * un visiteur qui reçoit la fenêtre sans l'avoir cherchée refuse.
 */

interface Contexte {
  base: string;
  csrf: string;
  cle: string;
  connecte: boolean;
}

const lire = (): Contexte | null => {
  const n = document.getElementById('push-contexte');
  if (!n) return null;
  try { return JSON.parse(n.textContent || '{}') as Contexte; } catch { return null; }
};

/** La clé VAPID voyage en base64url ; l'API la veut en octets. */
function clePubliqueEnOctets(base64url: string): Uint8Array {
  const b64 = (base64url + '='.repeat((4 - (base64url.length % 4)) % 4))
    .replace(/-/g, '+').replace(/_/g, '/');
  const brut = atob(b64);
  const out = new Uint8Array(brut.length);
  for (let i = 0; i < brut.length; i++) out[i] = brut.charCodeAt(i);
  return out;
}

const b64 = (buf: ArrayBuffer | null): string => {
  if (!buf) return '';
  const o = new Uint8Array(buf);
  let s = '';
  for (let i = 0; i < o.length; i++) s += String.fromCharCode(o[i]);
  return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
};

function demarrer(): void {
  const ctx = lire();
  const bouton = document.getElementById('push-bouton') as HTMLButtonElement | null;
  const etat = document.getElementById('push-etat');
  if (!ctx || !bouton || !etat) return;

  const dire = (texte: string, classe = ''): void => {
    etat.textContent = texte;
    etat.className = 'aide' + (classe ? ' ' + classe : '');
  };

  /**
   * Le service worker et le push demandent une origine sûre.
   *
   * En HTTP simple, `navigator.serviceWorker` n'existe pas — sauf sur
   * localhost. Plutôt qu'un bouton qui échoue, on explique : sur un
   * hébergement mutualisé, le certificat s'active en deux clics.
   */
  const possible = 'serviceWorker' in navigator && 'PushManager' in window;
  if (!possible) {
    bouton.disabled = true;
    dire('Ce navigateur ne sait pas recevoir de notifications, ou le site n’est pas en HTTPS.');
    return;
  }
  if (Notification.permission === 'denied') {
    bouton.disabled = true;
    dire('Les notifications sont bloquées pour ce site. Rouvrez-les depuis les réglages '
       + 'du navigateur (le cadenas à gauche de l’adresse), puis rechargez la page.');
    return;
  }

  let abonnement: PushSubscription | null = null;

  const poster = async (page: string, corps: Record<string, string>): Promise<void> => {
    const f = new FormData();
    f.set('csrf', ctx.csrf);
    for (const [k, v] of Object.entries(corps)) f.set(k, v);
    const r = await fetch(ctx.base + 'index.php?p=' + page, { method: 'POST', body: f });
    if (!r.ok) throw new Error('refus du serveur');
  };

  const peindre = (): void => {
    if (abonnement) {
      bouton.textContent = 'Ne plus recevoir de notifications';
      bouton.className = 'bouton fant';
      dire('Ce navigateur reçoit les notifications.', 'ok');
    } else {
      bouton.textContent = 'Recevoir les notifications';
      bouton.className = 'bouton';
      dire('Vous serez prévenu des nouvelles campagnes et des offres. Un clic pour arrêter.');
    }
    bouton.disabled = false;
  };

  const enregistrement = navigator.serviceWorker.register(ctx.base + 'sw.js');

  enregistrement
    .then((r) => r.pushManager.getSubscription())
    .then((a) => { abonnement = a; peindre(); rattacher(); })
    .catch(() => { bouton.disabled = true; dire('Le service de notifications n’a pas démarré.'); });

  /**
   * Rattache au compte un abonnement pris AVANT la connexion.
   *
   * Un invité s'abonne sous son badge, sans compte ; il crée un compte le
   * lendemain. Sans ce geste, son abonnement resterait anonyme pour
   * toujours, et le segment « les invités de mes campagnes » ne le verrait
   * jamais — alors qu'il est exactement la personne visée.
   *
   * Une fois par onglet : c'est un enregistrement en base, pas une lecture.
   */
  const rattacher = (): void => {
    if (!abonnement || !ctx.connecte) return;
    try {
      if (sessionStorage.getItem('wakabi-push-lie') === abonnement.endpoint) return;
      sessionStorage.setItem('wakabi-push-lie', abonnement.endpoint);
    } catch {
      // Navigation privée, stockage refusé : on renverra une fois de trop,
      // ce qui est sans conséquence — l'enregistrement est idempotent.
    }
    const j = abonnement.toJSON() as { keys?: { p256dh?: string; auth?: string } };
    void poster('api-push-abonner', {
      endpoint: abonnement.endpoint,
      p256dh: j.keys?.p256dh || b64(abonnement.getKey('p256dh')),
      auth: j.keys?.auth || b64(abonnement.getKey('auth')),
    }).catch(() => { /* le bouton reste utilisable, c'est l'essentiel */ });
  };

  bouton.addEventListener('click', async () => {
    bouton.disabled = true;
    try {
      if (abonnement) {
        const endpoint = abonnement.endpoint;
        await abonnement.unsubscribe();
        await poster('api-push-desabonner', { endpoint });
        abonnement = null;
        peindre();
        return;
      }

      dire('En attente de votre autorisation…');
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') {
        dire(permission === 'denied'
          ? 'Refusé. Rouvrez les notifications depuis les réglages du navigateur si vous changez d’avis.'
          : 'Autorisation non accordée.');
        bouton.disabled = permission === 'denied';
        return;
      }

      const r = await enregistrement;
      abonnement = await r.pushManager.subscribe({
        // Sans contenu visible, plusieurs navigateurs refusent l'abonnement.
        userVisibleOnly: true,
        applicationServerKey: clePubliqueEnOctets(ctx.cle) as BufferSource,
      });
      const j = abonnement.toJSON() as { keys?: { p256dh?: string; auth?: string } };
      await poster('api-push-abonner', {
        endpoint: abonnement.endpoint,
        p256dh: j.keys?.p256dh || b64(abonnement.getKey('p256dh')),
        auth: j.keys?.auth || b64(abonnement.getKey('auth')),
      });
      peindre();
    } catch (e) {
      // Un abonnement laissé côté navigateur mais inconnu du serveur ne
      // recevra rien : on le défait pour que le prochain clic reparte
      // d'une situation propre.
      if (abonnement) { try { await abonnement.unsubscribe(); } catch { /* tant pis */ } }
      abonnement = null;
      bouton.disabled = false;
      bouton.textContent = 'Réessayer';
      dire('L’abonnement n’a pas abouti : ' + (e instanceof Error ? e.message : 'erreur inconnue'), 'err');
    }
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', demarrer);
} else {
  demarrer();
}
