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
 *
 * Depuis que la même proposition se fait à trois endroits — la carte du
 * profil ou d'un décor, l'entrée du volet du compte, l'invitation de la
 * page d'accueil — ce fichier ne pilote plus UN bouton mais tous ceux que
 * la page porte. Il n'y a qu'un abonnement par navigateur : il serait
 * absurde qu'un écran dise « abonné » pendant qu'un autre propose de
 * s'abonner.
 */

interface Contexte {
  base: string;
  csrf: string;
  cle: string;
  connecte: boolean;
  /**
   * Le décor sous lequel on s'abonne, quand il y en a un.
   *
   * C'est la seule attache entre un invité SANS COMPTE et l'organisateur
   * qui l'a fait venir : il fait son badge, s'abonne, ne crée jamais de
   * compte. Sans cet identifiant, « les invités de mes campagnes »
   * annonçait zéro abonné à un organisateur dont tous les invités
   * s'étaient abonnés chez lui.
   */
  decor?: string;
}

/** Les trois façons de proposer la même chose. */
type Genre = 'carte' | 'menu' | 'invite';

interface Bloc {
  racine: HTMLElement;
  genre: Genre;
  bouton: HTMLButtonElement;
  /** La ligne qui explique — présente partout sauf dans l'invitation. */
  aide: HTMLElement | null;
  /** Le libellé, quand il vit dans un enfant plutôt que dans le bouton. */
  titre: HTMLElement | null;
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

/* ------------------------------------------------------------------ */
/* Le silence de l'invitation                                          */
/* ------------------------------------------------------------------ */

const CLE_SILENCE = 'wakabi-push-invite';
const JOUR = 86400000;

/**
 * Jusqu'à quand l'invitation se tait.
 *
 * Le stockage local peut être refusé — navigation privée, réglages
 * stricts. Dans ce cas l'invitation reparaîtra à la visite suivante, ce
 * qui est le moindre mal : elle reste refusable d'un clic, et elle ne
 * demande toujours aucune permission d'elle-même.
 */
const silencieuse = (): boolean => {
  try {
    const t = parseInt(localStorage.getItem(CLE_SILENCE) || '0', 10);
    return Number.isFinite(t) && Date.now() < t;
  } catch { return false; }
};

const taire = (jours: number): void => {
  try { localStorage.setItem(CLE_SILENCE, String(Date.now() + jours * JOUR)); } catch { /* tant pis */ }
};

function demarrer(): void {
  const ctx = lire();
  if (!ctx) return;

  const blocs: Bloc[] = [];
  document.querySelectorAll<HTMLElement>('[data-push]').forEach((racine) => {
    const bouton = (racine.matches('[data-push-bouton]')
      ? racine
      : racine.querySelector('[data-push-bouton]')) as HTMLButtonElement | null;
    if (!bouton) return;
    blocs.push({
      racine,
      genre: (racine.getAttribute('data-push') || 'carte') as Genre,
      bouton,
      aide: racine.querySelector<HTMLElement>('[data-push-etat],[data-push-aide]'),
      titre: racine.querySelector<HTMLElement>('[data-push-titre]'),
    });
  });
  if (!blocs.length) return;

  /** Ce qui s'écrit dans un bloc, selon l'endroit où il se trouve. */
  const dire = (b: Bloc, texte: string, classe = ''): void => {
    if (!b.aide) return;
    b.aide.textContent = texte;
    if (b.genre === 'carte') b.aide.className = 'aide' + (classe ? ' ' + classe : '');
  };

  /**
   * Le service worker et le push demandent une origine sûre.
   *
   * En HTTP simple, `navigator.serviceWorker` n'existe pas — sauf sur
   * localhost. Plutôt qu'un bouton qui échoue, on explique : sur un
   * hébergement mutualisé, le certificat s'active en deux clics.
   *
   * Sauf dans la barre et sur l'accueil : là, il n'y a rien à expliquer à
   * quelqu'un qui n'a rien demandé. Le bouton s'efface, simplement.
   */
  const renoncer = (raison: string): void => {
    for (const b of blocs) {
      if (b.genre === 'carte') {
        b.bouton.disabled = true;
        dire(b, raison);
      } else {
        b.racine.remove();
      }
    }
  };

  const possible = 'serviceWorker' in navigator && 'PushManager' in window;
  if (!possible) {
    renoncer('Ce navigateur ne sait pas recevoir de notifications, ou le site n’est pas en HTTPS.');
    return;
  }
  if (Notification.permission === 'denied') {
    renoncer('Les notifications sont bloquées pour ce site. Rouvrez-les depuis les réglages '
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

  /* ---------------- l'invitation de la page d'accueil ---------------- */

  const invite = blocs.find((b) => b.genre === 'invite') ?? null;
  let inviteVue = false;

  const fermerInvite = (jours: number): void => {
    if (!invite) return;
    taire(jours);
    invite.racine.classList.remove('vue');
    document.body.classList.remove('push-invite-ouverte');
    // On attend la fin du fondu avant de la retirer : disparaître d'un
    // coup sous le doigt donne l'impression d'avoir cliqué à côté.
    window.setTimeout(() => invite.racine.remove(), 240);
  };

  const ouvrirInvite = (): void => {
    if (!invite || inviteVue || abonnement || silencieuse()) return;
    inviteVue = true;
    invite.racine.hidden = false;
    document.body.classList.add('push-invite-ouverte');
    document.documentElement.style.setProperty(
      '--push-invite-h', invite.racine.offsetHeight + 'px');
    requestAnimationFrame(() => invite.racine.classList.add('vue'));
  };

  /**
   * Quand elle s'ouvre : après un moment de lecture, ou à mi-page.
   *
   * Pas au chargement. Une carte qui saute à la figure avant qu'on ait vu
   * la page se ferme sans être lue, et celle-là ne revient qu'un mois plus
   * tard — on aura dépensé la seule occasion de convaincre.
   */
  if (invite) {
    if (silencieuse()) {
      invite.racine.remove();
    } else {
      const minuterie = window.setTimeout(ouvrirInvite, 12000);
      const auDefile = (): void => {
        const h = document.documentElement;
        const parcouru = (h.scrollTop + window.innerHeight) / Math.max(h.scrollHeight, 1);
        if (parcouru < 0.5) return;
        window.clearTimeout(minuterie);
        window.removeEventListener('scroll', auDefile);
        ouvrirInvite();
      };
      window.addEventListener('scroll', auDefile, { passive: true });
      invite.racine.querySelector('[data-push-plus-tard]')
        ?.addEventListener('click', () => fermerInvite(30));
      invite.racine.querySelector('[data-push-fermer]')
        ?.addEventListener('click', () => fermerInvite(7));
      /* Échap la referme comme la croix : c'est le geste que fait
         quiconque voit paraître une carte qu'il n'a pas demandée. */
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && inviteVue) fermerInvite(7);
      });
    }
  }

  /* ---------------- l'état, partout en même temps ---------------- */

  const peindre = (): void => {
    for (const b of blocs) {
      const cible = b.titre ?? b.bouton;
      if (abonnement) {
        if (b.genre === 'invite') { continue; }
        cible.textContent = 'Ne plus recevoir de notifications';
        if (b.genre === 'carte') {
          b.bouton.className = 'bouton fant';
          dire(b, 'Ce navigateur reçoit les notifications.', 'ok');
        } else {
          b.bouton.classList.add('wk-push-ok');
          dire(b, 'Cet appareil est prévenu.');
        }
      } else {
        cible.textContent = 'Recevoir les notifications';
        if (b.genre === 'carte') {
          b.bouton.className = 'bouton';
          dire(b, 'Vous serez prévenu des nouvelles campagnes et des offres. Un clic pour arrêter.');
        } else if (b.genre === 'menu') {
          b.bouton.classList.remove('wk-push-ok');
          dire(b, 'Les sorties et les campagnes, dès qu’elles ouvrent.');
        }
      }
      b.bouton.disabled = false;
      // La barre et l'accueil partent cachés : le script est le seul à
      // savoir si ce navigateur peut recevoir quoi que ce soit.
      if (b.genre === 'menu') b.bouton.hidden = false;
    }
    // Un abonnement pris ailleurs (ou déjà en place) rend l'invitation
    // sans objet : elle ne s'ouvrira pas, et se referme si elle est là.
    if (abonnement && invite && inviteVue) fermerInvite(3650);
  };

  const enregistrer = (): Promise<ServiceWorkerRegistration> =>
    navigator.serviceWorker.register(ctx.base + 'sw.js');

  /**
   * À l'ouverture on REGARDE, on n'installe pas.
   *
   * L'entrée de la barre paraît maintenant sur toutes les pages d'un
   * visiteur sans compte. Y installer un service worker d'office
   * reviendrait à poser du code de fond dans le navigateur de quelqu'un
   * qui n'a rien demandé — pour la seule satisfaction de savoir qu'il
   * n'est pas abonné, ce que l'absence d'enregistrement dit déjà.
   *
   * S'il y en a déjà un, en revanche, on le rafraîchit : c'est lui qui
   * rattrape les abonnements que le navigateur renouvelle tout seul, et
   * une version figée de ce fichier laisserait ces gens-là sans rien
   * recevoir, sans que rien ne le signale.
   */
  navigator.serviceWorker.getRegistration()
    .then((r) => {
      if (!r) return null;
      void enregistrer();
      return r.pushManager.getSubscription();
    })
    .then((a) => { abonnement = a; peindre(); rattacher(); })
    .catch(() => renoncer('Le service de notifications n’a pas démarré.'));

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
      decor: ctx.decor ?? '',
    }).catch(() => { /* le bouton reste utilisable, c'est l'essentiel */ });
  };

  const cliquer = async (b: Bloc): Promise<void> => {
    for (const x of blocs) x.bouton.disabled = true;
    try {
      if (abonnement) {
        const endpoint = abonnement.endpoint;
        await abonnement.unsubscribe();
        await poster('api-push-desabonner', { endpoint });
        abonnement = null;
        peindre();
        return;
      }

      dire(b, 'En attente de votre autorisation…');
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') {
        if (permission === 'denied') {
          renoncer('Refusé. Rouvrez les notifications depuis les réglages du navigateur '
                 + 'si vous changez d’avis.');
          return;
        }
        dire(b, 'Autorisation non accordée.');
        for (const x of blocs) x.bouton.disabled = false;
        return;
      }

      const r = await enregistrer();
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
        decor: ctx.decor ?? '',
      });
      peindre();
    } catch (e) {
      // Un abonnement laissé côté navigateur mais inconnu du serveur ne
      // recevra rien : on le défait pour que le prochain clic reparte
      // d'une situation propre.
      if (abonnement) { try { await abonnement.unsubscribe(); } catch { /* tant pis */ } }
      abonnement = null;
      for (const x of blocs) x.bouton.disabled = false;
      if (b.genre === 'carte') b.bouton.textContent = 'Réessayer';
      dire(b, 'L’abonnement n’a pas abouti : ' + (e instanceof Error ? e.message : 'erreur inconnue'), 'err');
    }
  };

  for (const b of blocs) {
    b.bouton.addEventListener('click', () => { void cliquer(b); });
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', demarrer);
} else {
  demarrer();
}
