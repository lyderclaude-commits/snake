/**
 * La lecture du QR à la caméra, pour le contrôle d'entrée.
 *
 * Saisir dix caractères à la main marche — mais pas devant une file. Le
 * geste devient : approcher le téléphone du badge, entendre le bip, passer
 * au suivant. La page ne se recharge pas : la caméra reste ouverte et seule
 * la réponse change, sinon chaque invité coûterait une mise au point.
 *
 * Deux décodeurs, dans cet ordre :
 *
 *  1. `BarcodeDetector`, présent depuis Chrome 83 — donc sur la quasi-
 *     totalité des téléphones Android. Rien à télécharger, décodage confié
 *     au système, et c'est le cas courant à une porte de Lomé ou d'Abidjan.
 *  2. jsQR, chargé SEULEMENT si le premier manque — Safari, essentiellement.
 *     127 Ko qu'on ne fait pas payer à ceux qui n'en ont pas besoin.
 *
 * Le formulaire de saisie reste là, et continue de fonctionner sans une
 * ligne de JavaScript : la caméra est un raccourci, pas une dépendance.
 */

interface Contexte {
  base: string;
  csrf: string;
}

interface Verdict {
  ok: boolean;
  message: string;
  detail?: string;
  jeton?: string;
  passages?: { heure: string; porteur: string; decor: string }[];
}

/** Le décodeur du navigateur, quand il existe. */
interface DetecteurNatif {
  detect(source: CanvasImageSource): Promise<{ rawValue: string }[]>;
}

declare const jsQR: undefined | ((
  donnees: Uint8ClampedArray, largeur: number, hauteur: number,
  options?: { inversionAttempts?: string },
) => { data: string } | null);

/**
 * Le jeton d'un badge, extrait de ce que porte le QR.
 *
 * Le QR d'un badge encode une ADRESSE (`…?p=qr&jeton=XXXX`), pas le code
 * seul : scanné avec l'appareil photo du téléphone, il doit ouvrir la page
 * du badge. L'agent, lui, ne veut que les dix caractères. On accepte donc
 * les deux formes — et rien d'autre, pour qu'un QR étranger collé sur un
 * badge ne parte pas au serveur.
 */
export function jetonDuQr(brut: string): string | null {
  const texte = brut.trim();
  const direct = /^[A-Z0-9]{10}$/i.exec(texte);
  if (direct) return texte.toUpperCase();

  const dansUrl = /[?&]jeton=([A-Z0-9]{10})(?:$|&)/i.exec(texte);
  return dansUrl ? dansUrl[1].toUpperCase() : null;
}

function demarrer(ctx: Contexte) {
  const bouton = document.getElementById('camera') as HTMLButtonElement | null;
  const boite = document.getElementById('camera-boite');
  const video = document.getElementById('camera-video') as HTMLVideoElement | null;
  const etat = document.getElementById('camera-etat');
  const verdict = document.getElementById('camera-verdict');
  const journal = document.getElementById('passages');
  if (!bouton || !boite || !video || !etat || !verdict) return;
  // Rebaptisés après le garde : le typage ne suit pas la vérification à
  // l'intérieur des fonctions déclarées plus bas.
  const commande = bouton, cadre = boite, ecran = video, reponse = verdict;

  const toile = document.createElement('canvas');
  const dessin = toile.getContext('2d', { willReadFrequently: true });

  let flux: MediaStream | null = null;
  let detecteur: DetecteurNatif | null = null;
  let boucle = 0;
  let dernier = '';
  let dernierA = 0;
  let occupe = false;

  const dire = (texte: string) => { etat.textContent = texte; };

  /* ---------------- le son ---------------- */

  /**
   * Un bip, synthétisé plutôt que téléchargé.
   *
   * L'agent ne regarde pas l'écran : il regarde le badge et la file. Le son
   * est ce qui lui dit de passer au suivant. Deux hauteurs — grave pour un
   * refus — parce qu'à cette distance on entend mieux qu'on ne lit.
   */
  let audio: AudioContext | null = null;
  const bip = (ok: boolean) => {
    try {
      audio ??= new (window.AudioContext || (window as any).webkitAudioContext)();
      const o = audio.createOscillator();
      const g = audio.createGain();
      o.frequency.value = ok ? 880 : 220;
      g.gain.value = 0.12;
      o.connect(g).connect(audio.destination);
      o.start();
      o.stop(audio.currentTime + (ok ? 0.09 : 0.22));
    } catch { /* pas de son : l'écran suffira */ }
  };

  /* ---------------- la caméra ---------------- */

  async function ouvrir() {
    // getUserMedia n'existe qu'en contexte sûr. Sur un site en http://, le
    // bouton ne répondrait rien du tout : mieux vaut dire pourquoi.
    if (!navigator.mediaDevices?.getUserMedia) {
      dire(window.isSecureContext
        ? 'Ce navigateur ne donne pas accès à la caméra. Saisissez le code à la main.'
        : 'La caméra n’est accessible qu’en HTTPS. Passez le site en https://, ou saisissez le code.');
      cadre.hidden = false;
      return;
    }

    dire('Autorisez la caméra…');
    cadre.hidden = false;
    commande.disabled = true;
    try {
      flux = await navigator.mediaDevices.getUserMedia({
        // La caméra arrière, et une définition suffisante pour lire un QR
        // imprimé sur un téléphone à trente centimètres.
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false,
      });
    } catch (e: any) {
      commande.disabled = false;
      dire(e?.name === 'NotAllowedError'
        ? 'Accès refusé. Autorisez la caméra dans les réglages du navigateur, puis réessayez.'
        : 'Aucune caméra utilisable. Saisissez le code à la main.');
      return;
    }

    ecran.srcObject = flux;
    ecran.setAttribute('playsinline', '');   // sans quoi iOS ouvre le plein écran
    await ecran.play().catch(() => {});
    commande.textContent = 'Arrêter la caméra';
    commande.disabled = false;
    commande.dataset.actif = '1';
    dire('Approchez le QR du badge.');

    await preparerDetecteur();
    boucler();
  }

  function fermer() {
    cancelAnimationFrame(boucle);
    flux?.getTracks().forEach((t) => t.stop());
    flux = null;
    ecran.srcObject = null;
    cadre.hidden = true;
    commande.textContent = 'Scanner avec la caméra';
    delete commande.dataset.actif;
  }

  /** Le décodeur natif, ou jsQR chargé à la demande. */
  async function preparerDetecteur() {
    const BD = (window as any).BarcodeDetector;
    if (BD) {
      try {
        const formats: string[] = await BD.getSupportedFormats?.() ?? ['qr_code'];
        if (formats.includes('qr_code')) {
          detecteur = new BD({ formats: ['qr_code'] });
          return;
        }
      } catch { /* on passera par jsQR */ }
    }
    if (typeof jsQR === 'function') return;

    dire('Préparation du lecteur…');
    await new Promise<void>((resoudre) => {
      const s = document.createElement('script');
      s.src = ctx.base + 'public/jsqr.js';
      s.onload = () => resoudre();
      s.onerror = () => resoudre();
      document.head.appendChild(s);
    });
    dire(typeof jsQR === 'function'
      ? 'Approchez le QR du badge.'
      : 'Lecteur indisponible : saisissez le code à la main.');
  }

  /* ---------------- la boucle de lecture ---------------- */

  function boucler() {
    boucle = requestAnimationFrame(boucler);
    if (occupe || ecran.readyState < 2 || !dessin) return;

    const l = ecran.videoWidth;
    const h = ecran.videoHeight;
    if (!l || !h) return;

    if (detecteur) {
      // Le décodeur natif lit la vidéo directement : pas de copie, pas de
      // relecture de pixels, et c'est ce qui tient la cadence sur un
      // téléphone d'entrée de gamme.
      occupe = true;
      detecteur.detect(ecran)
        .then((codes) => { if (codes[0]) traiter(codes[0].rawValue); })
        .catch(() => {})
        .finally(() => { occupe = false; });
      return;
    }

    if (typeof jsQR !== 'function') return;
    // jsQR travaille sur des pixels : on réduit d'abord. Un QR de dix
    // caractères reste lisible bien en dessous de la définition de la
    // caméra, et lire un million de pixels par image ferait chauffer le
    // téléphone pour rien.
    const echelle = Math.min(1, 640 / Math.max(l, h));
    toile.width = Math.round(l * echelle);
    toile.height = Math.round(h * echelle);
    dessin.drawImage(ecran, 0, 0, toile.width, toile.height);
    const image = dessin.getImageData(0, 0, toile.width, toile.height);
    const trouve = jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' });
    if (trouve?.data) traiter(trouve.data);
  }

  /* ---------------- la décision ---------------- */

  function traiter(brut: string) {
    const jeton = jetonDuQr(brut);
    if (!jeton) {
      dire('Ce QR n’est pas un badge Wakabi.');
      return;
    }
    /**
     * Un badge n'est envoyé qu'UNE fois tant qu'on ne voit pas autre chose.
     *
     * La caméra lit douze fois par seconde ; un badge resté devant
     * l'objectif partirait donc douze fois, et la deuxième réponse serait
     * « déjà scanné » — un refus affiché sur un badge qu'on vient de
     * valider, c'est-à-dire la pire chose possible devant une file.
     *
     * Une simple temporisation ne suffit pas : elle suppose que l'agent
     * range le badge dans les quatre secondes. On garde donc le dernier
     * code jusqu'à en voir un AUTRE — et une minute plus tard, au cas où
     * quelqu'un reviendrait présenter le même.
     */
    const t = Date.now();
    if (jeton === dernier && t - dernierA < 60_000) return;
    dernier = jeton;
    dernierA = t;

    occupe = true;
    dire('Vérification…');
    fetch(ctx.base + '?p=api-scan', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ csrf: ctx.csrf, jeton }).toString(),
    })
      .then((r) => r.json())
      .then((v: Verdict) => {
        montrer(v);
        bip(v.ok);
        dire('Badge suivant.');
      })
      .catch(() => {
        dire('Réseau indisponible. Le code est ' + jeton + ' : notez-le, ou réessayez.');
        // Un échec réseau ne doit pas bloquer une deuxième tentative sur le
        // même badge : c'est le seul cas où répéter est la bonne chose.
        dernier = '';
      })
      .finally(() => { occupe = false; });
  }

  function montrer(v: Verdict) {
    reponse.hidden = false;
    reponse.className = 'msg ' + (v.ok ? 'ok' : 'err');
    reponse.innerHTML = '';
    const fort = document.createElement('strong');
    fort.style.fontSize = '1.05rem';
    fort.textContent = v.message;
    reponse.appendChild(fort);
    if (v.detail) {
      const p = document.createElement('p');
      p.style.margin = '.35em 0 0';
      p.textContent = v.detail;
      reponse.appendChild(p);
    }

    if (!journal || !v.passages) return;
    journal.innerHTML = '';
    for (const p of v.passages) {
      const ligne = document.createElement('div');
      ligne.className = 'rangee';
      ligne.style.cssText = 'justify-content:space-between;border-top:1px solid var(--border);padding:8px 0;font-size:.88rem';
      for (const [texte, style] of [
        [p.heure, 'mono aide'], [p.porteur, ''], [p.decor, 'aide'],
      ] as [string, string][]) {
        const s = document.createElement('span');
        if (style) s.className = style;
        if (style === '') s.style.flex = '1';
        s.textContent = texte;
        ligne.appendChild(s);
      }
      journal.appendChild(ligne);
    }
  }

  commande.addEventListener('click', () => (commande.dataset.actif ? fermer() : ouvrir()));
  // Quitter la page sans éteindre la caméra laisserait la diode allumée et
  // la batterie s'user : le navigateur ne le fait pas toujours tout seul.
  window.addEventListener('pagehide', fermer);
}

// Le garde sur `window` permet d'importer `jetonDuQr` hors navigateur : la
// recette vérifie ce filtre sans avoir à ouvrir de page.
const ctx = typeof window === 'undefined'
  ? undefined
  : (window as unknown as { WAKABI_SCAN?: Contexte }).WAKABI_SCAN;
if (ctx) demarrer(ctx);
