<?php
/**
 * Le transport e-mail.
 *
 * Tout le circuit existait — jetons, notifications, motifs de refus — mais
 * rien ne quittait le serveur : le partenaire devait revenir de lui-même
 * voir si son décor avait été relu. C'est le trou que ce fichier ferme.
 *
 * Un client SMTP écrit à la main, sans Composer et sans `vendor/` : la
 * version PHP se déploie en décompressant un zip sur un mutualisé, et une
 * dépendance à installer en ligne de commande annulerait cette promesse.
 * Le protocole tient en une dizaine de commandes ; le reste, ce sont des
 * en-têtes.
 *
 * Trois transports, dans cet ordre :
 *   1. SMTP authentifié, si l'équipe l'a réglé — le seul qui arrive
 *      réellement en boîte de réception depuis un mutualisé ;
 *   2. `mail()`, si l'hébergeur l'autorise — souvent classé en indésirable,
 *      mais mieux que rien ;
 *   3. rien, et on le DIT. Un envoi silencieusement perdu est pire qu'un
 *      envoi refusé : personne ne le cherche.
 */

declare(strict_types=1);

/** Les clés de réglage du transport, avec leur valeur de départ. */
const COURRIEL_DEFAUTS = [
    'smtp_hote' => '',
    'smtp_port' => '587',
    'smtp_securite' => 'tls',      // 'tls' (STARTTLS), 'ssl' (implicite), 'aucune'
    'smtp_utilisateur' => '',
    'smtp_motdepasse' => '',
    'courriel_expediteur' => '',
    'courriel_nom' => 'Wakabi Boost',
    'courriel_repondre_a' => '',
    // Le temps d'attente entre deux messages d'un même lot, en millisecondes.
    // Un relais qui reçoit vingt-cinq messages en une seconde répond 421 ; le
    // même relais accepte les mêmes vingt-cinq étalés sur dix secondes.
    'smtp_rythme_ms' => '250',
];

const COURRIEL_SECURITES = [
    'tls' => 'STARTTLS (port 587, recommandé)',
    'ssl' => 'TLS implicite (port 465)',
    'aucune' => 'Aucune (port 25, à éviter)',
];

/** Les réglages du transport, valeurs de départ comprises. */
function reglages_courriel(): array
{
    $lus = reglages_bdd(array_keys(COURRIEL_DEFAUTS));
    $r = COURRIEL_DEFAUTS;
    foreach ($r as $cle => $defaut) {
        $v = $lus[$cle] ?? '';
        $r[$cle] = $v === '' ? $defaut : $v;
    }
    if (!isset(COURRIEL_SECURITES[$r['smtp_securite']])) {
        $r['smtp_securite'] = 'tls';
    }
    return $r;
}

/**
 * Le transport est-il branché ?
 *
 * Cette réponse commande plus qu'un envoi : tant qu'elle est fausse, on
 * n'EXIGE pas d'un partenaire qu'il vérifie une adresse à laquelle on est
 * incapable d'écrire. Une garde qu'on ne peut pas lever est une porte
 * fermée à clé, pas une sécurité.
 */
function courriel_branche(): bool
{
    $r = reglages_courriel();
    return $r['smtp_hote'] !== '' && $r['courriel_expediteur'] !== '';
}

/**
 * Envoie un message. Ne lève jamais : rend un verdict.
 *
 * Un e-mail qui ne part pas ne doit pas faire échouer l'action qui l'a
 * déclenché — une décision de modération reste prise même si le courriel
 * se perd. L'appelant regarde `ok` s'il a de quoi en faire quelque chose,
 * et l'ignore sinon.
 *
 * @return array{ok: bool, message: string}
 */
function envoyer_courriel(string $vers, string $nom_vers, string $sujet, string $texte, ?string $html = null, array $sup = []): array
{
    if (!filter_var($vers, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Adresse destinataire invalide.'];
    }
    $r = reglages_courriel();
    $de = $r['courriel_expediteur'];

    if ($r['smtp_hote'] !== '' && $de !== '') {
        /**
         * Un serveur muet ne fait perdre qu'UNE attente par requête.
         *
         * Une décision de modération prévient l'auteur, et parfois toute
         * l'équipe : autant d'envois. Si le serveur ne répond plus, chacun
         * attendrait le délai complet, et approuver un décor gèlerait la
         * page une minute. Le premier échec suffit à conclure pour les
         * suivants — la requête d'après réessaiera, elle repart à zéro.
         */
        static $muet = null;
        if ($muet !== null) {
            return ['ok' => false, 'message' => $muet];
        }
        try {
            smtp_envoyer($r, $vers, $nom_vers, $sujet, $texte, $html, $sup);
            return ['ok' => true, 'message' => 'Message remis au serveur SMTP.'];
        } catch (RuntimeException $e) {
            $muet = $e->getMessage();
            return ['ok' => false, 'message' => $muet];
        }
    }

    if (!function_exists('mail')) {
        return ['ok' => false, 'message' => 'Aucun SMTP réglé, et la fonction mail() est désactivée sur cet hébergement.'];
    }
    $de = $de ?: 'no-reply@' . (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost');
    $entetes = entetes_courriel($r, $de, $vers, $nom_vers, $sujet, $html !== null, $sup);
    // `mail()` écrit elle-même To: et Subject: — on les retire de la liste.
    unset($entetes['To'], $entetes['Subject']);
    $lignes = [];
    foreach ($entetes as $c => $v) {
        $lignes[] = $c . ': ' . $v;
    }
    $ok = @mail($vers, encoder_entete($sujet), corps_courriel($texte, $html), implode("\r\n", $lignes));
    return $ok
        ? ['ok' => true, 'message' => 'Message confié à mail(). Vérifiez le dossier « indésirables ».']
        : ['ok' => false, 'message' => 'mail() a refusé le message. Réglez un SMTP : sur un mutualisé, c'
            . ' est de toute façon le seul transport qui arrive en boîte de réception.'];
}

/* ------------------------------------------------------------------ */
/* Le protocole                                                        */
/* ------------------------------------------------------------------ */

/**
 * Le dialogue SMTP, du EHLO au QUIT.
 *
 * @throws RuntimeException avec un message que l'équipe puisse lire : le
 *         but de l'écran de test est de dire CE QUI cloche, pas « échec ».
 */
function smtp_envoyer(array $r, string $vers, string $nom_vers, string $sujet, string $texte, ?string $html, array $sup = []): void
{
    $flux = smtp_ouvrir($r);
    try {
        smtp_remettre($flux, $r, $vers, $nom_vers, $sujet, $texte, $html, $sup);
        @smtp_ecrire($flux, 'QUIT');
    } finally {
        @fclose($flux);
    }
}

/**
 * Ouvre la conversation : connexion, chiffrement, authentification.
 *
 * Séparée de l'envoi parce qu'une campagne de mille messages ouvrait mille
 * connexions — mille poignées de main TLS et mille AUTH pour mille lignes
 * de texte. Plusieurs relais limitent d'ailleurs les CONNEXIONS par heure,
 * pas les messages : c'était donc aussi une limite qu'on frôlait sans le
 * savoir.
 *
 * @return resource
 * @throws RuntimeException avec un message que l'équipe puisse lire.
 */
function smtp_ouvrir(array $r)
{
    $port = (int) $r['smtp_port'] ?: 587;
    $hote = $r['smtp_hote'];
    $adresse = ($r['smtp_securite'] === 'ssl' ? 'ssl://' : '') . $hote . ':' . $port;

    // 8 s : un serveur SMTP joignable répond en quelques dizaines de
    // millisecondes. Au-delà, c'est que le port est filtré — le dire vite
    // vaut mieux que de faire attendre une page pour la même conclusion.
    $contexte = stream_context_create(['ssl' => ['SNI_enabled' => true]]);
    $flux = @stream_socket_client($adresse, $err, $msg, 8, STREAM_CLIENT_CONNECT, $contexte);
    if (!$flux) {
        throw new RuntimeException(sprintf(
            'Connexion impossible à %s:%d (%s). Vérifiez le nom du serveur, le port, et que l’hébergeur laisse sortir ce port.',
            $hote, $port, $msg ?: 'sans détail'
        ));
    }
    stream_set_timeout($flux, 8);

    try {
        smtp_lire($flux, 220);
        $capacites = smtp_ehlo($flux, $hote);

        if ($r['smtp_securite'] === 'tls') {
            if (!preg_match('/^250[- ]STARTTLS/mi', $capacites)) {
                throw new RuntimeException(
                    'Ce serveur n’annonce pas STARTTLS. Essayez « TLS implicite » sur le port 465.'
                );
            }
            smtp_commande($flux, 'STARTTLS', 220);
            if (!@stream_socket_enable_crypto($flux, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('La négociation TLS a échoué. Le certificat du serveur est peut-être expiré.');
            }
            // Après STARTTLS, tout recommence : les capacités annoncées en
            // clair ne valent plus, et l'authentification n'apparaît souvent
            // qu'ici — c'est justement l'intérêt de la chose.
            $capacites = smtp_ehlo($flux, $hote);
        }

        if ($r['smtp_utilisateur'] !== '') {
            smtp_authentifier($flux, $capacites, $r['smtp_utilisateur'], $r['smtp_motdepasse']);
        }
    } catch (RuntimeException $e) {
        @fclose($flux);
        throw $e;
    }
    return $flux;
}

/**
 * Remet UN message dans une conversation déjà ouverte.
 *
 * @param resource $flux
 * @throws RuntimeException si le serveur refuse ce message-là.
 */
function smtp_remettre(
    $flux,
    array $r,
    string $vers,
    string $nom_vers,
    string $sujet,
    string $texte,
    ?string $html,
    array $sup = []
): void {
    smtp_commande($flux, 'MAIL FROM:<' . $r['courriel_expediteur'] . '>', 250);
    smtp_commande($flux, 'RCPT TO:<' . $vers . '>', [250, 251]);
    smtp_commande($flux, 'DATA', 354);

    $entetes = entetes_courriel($r, $r['courriel_expediteur'], $vers, $nom_vers, $sujet, $html !== null, $sup);
    $message = '';
    foreach ($entetes as $c => $v) {
        $message .= $c . ': ' . $v . "\r\n";
    }
    $message .= "\r\n" . corps_courriel($texte, $html);

    smtp_ecrire($flux, smtp_proteger_points($message) . "\r\n.");
    smtp_lire($flux, 250);
}

/** EHLO, avec repli HELO pour les serveurs d'un autre âge. */
function smtp_ehlo($flux, string $hote): string
{
    // Le nom annoncé doit ressembler à un domaine : plusieurs serveurs
    // refusent un EHLO qui ne contient pas de point.
    $moi = parse_url(base_url(), PHP_URL_HOST) ?: 'localhost';
    try {
        return smtp_commande($flux, 'EHLO ' . $moi, 250);
    } catch (RuntimeException) {
        return smtp_commande($flux, 'HELO ' . $moi, 250);
    }
}

/** AUTH, en préférant LOGIN quand les deux sont proposés. */
function smtp_authentifier($flux, string $capacites, string $utilisateur, string $motdepasse): void
{
    $propose = [];
    if (preg_match('/^250[- ]AUTH (.*)$/mi', $capacites, $m)) {
        $propose = preg_split('/\s+/', strtoupper(trim($m[1]))) ?: [];
    }

    if (!$propose || in_array('LOGIN', $propose, true)) {
        smtp_commande($flux, 'AUTH LOGIN', 334);
        smtp_commande($flux, base64_encode($utilisateur), 334);
        smtp_commande($flux, base64_encode($motdepasse), 235, 'Identifiants refusés par le serveur.');
        return;
    }
    if (in_array('PLAIN', $propose, true)) {
        smtp_commande(
            $flux,
            'AUTH PLAIN ' . base64_encode("\0" . $utilisateur . "\0" . $motdepasse),
            235,
            'Identifiants refusés par le serveur.'
        );
        return;
    }
    throw new RuntimeException(
        'Ce serveur ne propose que ' . implode(', ', $propose) . ' : ni LOGIN ni PLAIN. '
        . 'Demandez à l’hébergeur un compte SMTP classique.'
    );
}

function smtp_ecrire($flux, string $ligne): void
{
    if (@fwrite($flux, $ligne . "\r\n") === false) {
        throw new RuntimeException('La connexion au serveur SMTP s’est interrompue.');
    }
}

/**
 * Lit une réponse, éventuellement sur plusieurs lignes.
 *
 * Une réponse multi-lignes se reconnaît au tiret qui suit le code :
 * « 250-SIZE » continue, « 250 SIZE » termine. S'arrêter à la première
 * ligne laisserait le reste dans le tampon, et la commande suivante
 * lirait une réponse qui n'est pas la sienne.
 */
function smtp_lire($flux, int|array $attendu, string $sinon = ''): string
{
    $codes = (array) $attendu;
    $tout = '';
    do {
        $ligne = fgets($flux, 1024);
        if ($ligne === false) {
            $info = stream_get_meta_data($flux);
            throw new RuntimeException(!empty($info['timed_out'])
                ? 'Le serveur SMTP n’a pas répondu dans le délai imparti.'
                : 'Le serveur SMTP a coupé la connexion.');
        }
        $tout .= $ligne;
    } while (strlen($ligne) >= 4 && $ligne[3] === '-');

    $code = (int) substr($tout, 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException(($sinon ?: 'Le serveur SMTP a répondu : ') . trim($tout));
    }
    return $tout;
}

function smtp_commande($flux, string $commande, int|array $attendu, string $sinon = ''): string
{
    smtp_ecrire($flux, $commande);
    return smtp_lire($flux, $attendu, $sinon);
}

/**
 * Le point en début de ligne termine un message : il faut le doubler.
 *
 * Sans cela, un texte contenant une ligne réduite à « . » — ce qui arrive
 * dans une citation ou une liste — coupe le message en deux, et la suite
 * part au serveur comme si c'étaient des commandes.
 */
function smtp_proteger_points(string $message): string
{
    return preg_replace('/^\./m', '..', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $message))) ?? $message;
}

/* ------------------------------------------------------------------ */
/* Le message                                                          */
/* ------------------------------------------------------------------ */

/** Un en-tête non-ASCII s'encode, sinon il arrive en charabia. */
function encoder_entete(string $valeur): string
{
    return preg_match('/[\x80-\xFF]/', $valeur)
        ? '=?UTF-8?B?' . base64_encode($valeur) . '?='
        : $valeur;
}

/** Un nom affiché entre guillemets, débarrassé de quoi casser l'en-tête. */
function adresse_affichee(string $nom, string $adresse): string
{
    $nom = trim(str_replace(['"', "\r", "\n"], '', $nom));
    return $nom === '' ? $adresse : '"' . encoder_entete($nom) . '" <' . $adresse . '>';
}

function entetes_courriel(
    array $r,
    string $de,
    string $vers,
    string $nom_vers,
    string $sujet,
    bool $html,
    array $sup = []
): array {
    $limite = frontiere_courriel();
    $entetes = [
        'Date' => gmdate('D, d M Y H:i:s') . ' +0000',
        'From' => adresse_affichee($r['courriel_nom'], $de),
        'To' => adresse_affichee($nom_vers, $vers),
        'Subject' => encoder_entete($sujet),
        'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@'
                        . (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost') . '>',
        'MIME-Version' => '1.0',
    ];
    if (($r['courriel_repondre_a'] ?? '') !== '') {
        $entetes['Reply-To'] = $r['courriel_repondre_a'];
    }
    $entetes['Content-Type'] = $html
        ? 'multipart/alternative; boundary="' . $limite . '"'
        : 'text/plain; charset=UTF-8';
    if (!$html) {
        $entetes['Content-Transfer-Encoding'] = '8bit';
    }
    // Un message transactionnel ne doit pas déclencher de réponse
    // automatique : le « absent du bureau » du destinataire reviendrait sur
    // une boîte que personne ne lit.
    $entetes['Auto-Submitted'] = 'auto-generated';

    /**
     * Les en-têtes propres au message — `List-Unsubscribe` en tête.
     *
     * Ils arrivent en dernier pour pouvoir CORRIGER ce qui précède : un
     * message de la régie n'est pas « auto-generated » mais une lettre
     * d'information, et il porte son propre `List-Id`.
     */
    foreach ($sup as $cle => $valeur) {
        if ($valeur === null || $valeur === '') {
            unset($entetes[$cle]);
            continue;
        }
        $entetes[$cle] = $valeur;
    }
    return $entetes;
}

/** La frontière multipart : constante pour un message, unique entre deux. */
function frontiere_courriel(): string
{
    static $f = null;
    return $f ??= '=_wakabi_' . bin2hex(random_bytes(8));
}

function corps_courriel(string $texte, ?string $html): string
{
    if ($html === null) {
        return $texte;
    }
    $l = frontiere_courriel();
    return "--$l\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n$texte\r\n\r\n"
         . "--$l\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n$html\r\n\r\n--$l--";
}

/**
 * La mise en page d'un message, aux couleurs de la charte.
 *
 * Volontairement en tableaux et en styles écrits à la ligne : les clients
 * de messagerie ne savent toujours pas faire autrement, et Gmail retire
 * purement et simplement une balise `<style>`.
 */
function gabarit_courriel(string $titre, string $corps, string $lien = '', string $libelle = ''): string
{
    $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $bouton = '';
    if ($lien !== '') {
        $bouton = '<tr><td style="padding:8px 0 4px"><a href="' . $h($lien) . '"'
            . ' style="display:inline-block;background:#2563EB;color:#fff;text-decoration:none;'
            . 'font-weight:700;padding:12px 22px;border-radius:10px">' . $h($libelle ?: 'Ouvrir') . '</a></td></tr>';
    }
    $paragraphes = '';
    foreach (preg_split('/\n{2,}/', trim($corps)) ?: [] as $p) {
        $paragraphes .= '<p style="margin:0 0 14px;line-height:1.6">' . nl2br($h($p)) . '</p>';
    }

    return '<!doctype html><html lang="fr"><body style="margin:0;background:#F1F5F9;'
        . 'font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#0F172A">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F1F5F9;padding:24px 12px">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;'
        . 'border-radius:16px;overflow:hidden;border:1px solid #E2E8F0">'
        . '<tr><td style="background:#0F172A;padding:18px 24px;color:#fff;font-weight:800;letter-spacing:.02em">WAKABI BOOST</td></tr>'
        . '<tr><td style="padding:24px">'
        . '<h1 style="margin:0 0 14px;font-size:20px;line-height:1.3">' . $h($titre) . '</h1>'
        . '<table role="presentation" cellpadding="0" cellspacing="0"><tr><td>' . $paragraphes . '</td></tr>' . $bouton . '</table>'
        . '</td></tr>'
        . '<tr><td style="padding:14px 24px;border-top:1px solid #E2E8F0;color:#64748B;font-size:12px;line-height:1.5">'
        . 'Wakabi Boost — le guide des bons plans.<br>'
        . 'Ce message est automatique : inutile d’y répondre.'
        . '</td></tr></table></td></tr></table></body></html>';
}

/** La version texte, celle que lisent les filtres anti-spam et les montres. */
function texte_courriel(string $titre, string $corps, string $lien = ''): string
{
    $t = $titre . "\n" . str_repeat('=', mb_strlen($titre)) . "\n\n" . trim($corps);
    if ($lien !== '') {
        $t .= "\n\n" . $lien;
    }
    return $t . "\n\n--\nWakabi Boost — le guide des bons plans.\nMessage automatique, inutile d’y répondre.\n";
}

/**
 * Envoie un message mis en page. Le raccourci dont se servent les appelants.
 *
 * @return array{ok: bool, message: string}
 */
function courriel_mis_en_page(
    string $vers,
    string $nom_vers,
    string $sujet,
    string $titre,
    string $corps,
    string $lien = '',
    string $libelle = '',
    array $sup = [],
    ?SessionCourriel $session = null
): array {
    $texte = texte_courriel($titre, $corps, $lien);
    $html = gabarit_courriel($titre, $corps, $lien, $libelle);
    return $session
        ? $session->envoyer($vers, $nom_vers, $sujet, $texte, $html, $sup)
        : envoyer_courriel($vers, $nom_vers, $sujet, $texte, $html, $sup);
}

/**
 * Une conversation SMTP tenue ouverte, le temps d'un lot.
 *
 * Ouvrir, écrire vingt-cinq fois, fermer — au lieu d'ouvrir et fermer
 * vingt-cinq fois. Et surtout : un message refusé ne condamne plus les
 * suivants. Le court-circuit d'avant marquait tout le reste du lot en
 * échec définitif dès la première erreur, y compris quand elle ne
 * concernait qu'une adresse — une boîte pleine, un domaine mort.
 *
 * On distingue donc deux natures d'échec :
 *   — la conversation est morte (connexion coupée, authentification
 *     refusée) : on ferme, et tout ce qui suit sera REPRIS plus tard ;
 *   — ce destinataire-là est refusé : on note, et on continue.
 */
final class SessionCourriel
{
    /** @var resource|null */
    private $flux = null;
    private array $r;
    /** Non nul dès que la conversation elle-même est perdue. */
    private ?string $panne = null;
    private int $pause_us;
    private bool $smtp;

    public function __construct()
    {
        $this->r = reglages_courriel();
        $this->smtp = $this->r['smtp_hote'] !== '' && $this->r['courriel_expediteur'] !== '';
        $this->pause_us = max(0, min(5000, (int) $this->r['smtp_rythme_ms'])) * 1000;
    }

    /** Vrai quand le transport lui-même est tombé : inutile d'insister. */
    public function rompue(): bool
    {
        return $this->panne !== null;
    }

    public function panne(): ?string
    {
        return $this->panne;
    }

    /**
     * @return array{ok: bool, message: string, reprendre: bool}
     *         `reprendre` distingue « à retenter » de « refusé pour de bon ».
     */
    public function envoyer(
        string $vers,
        string $nom_vers,
        string $sujet,
        string $texte,
        ?string $html = null,
        array $sup = []
    ): array {
        if (!filter_var($vers, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Adresse destinataire invalide.', 'reprendre' => false];
        }
        if ($this->panne !== null) {
            return ['ok' => false, 'message' => $this->panne, 'reprendre' => true];
        }
        if (!$this->smtp) {
            // Sans SMTP réglé, on retombe sur le transport unitaire : mail()
            // n'a pas de conversation à tenir ouverte.
            $u = envoyer_courriel($vers, $nom_vers, $sujet, $texte, $html, $sup);
            return $u + ['reprendre' => !$u['ok']];
        }

        try {
            if ($this->flux === null) {
                $this->flux = smtp_ouvrir($this->r);
            }
        } catch (RuntimeException $e) {
            $this->panne = $e->getMessage();
            return ['ok' => false, 'message' => $this->panne, 'reprendre' => true];
        }

        if ($this->pause_us > 0) {
            usleep($this->pause_us);
        }

        try {
            smtp_remettre($this->flux, $this->r, $vers, $nom_vers, $sujet, $texte, $html, $sup);
            return ['ok' => true, 'message' => 'Message remis au serveur SMTP.', 'reprendre' => false];
        } catch (RuntimeException $e) {
            /**
             * Un refus 5xx vise CE destinataire ; tout le reste vise la
             * conversation. Confondre les deux, c'est soit s'acharner sur
             * une adresse morte, soit jeter des messages qui seraient
             * partis à l'essai suivant.
             */
            $m = $e->getMessage();
            if (preg_match('/\b5\d\d\b/', $m)) {
                // La conversation reste debout après un refus de
                // destinataire : on peut enchaîner sur le suivant.
                return ['ok' => false, 'message' => $m, 'reprendre' => false];
            }
            $this->fermer();
            $this->panne = $m;
            return ['ok' => false, 'message' => $m, 'reprendre' => true];
        }
    }

    public function fermer(): void
    {
        if ($this->flux !== null) {
            @smtp_ecrire($this->flux, 'QUIT');
            @fclose($this->flux);
            $this->flux = null;
        }
    }
}

/* ------------------------------------------------------------------ */
/* Le diagnostic du domaine expéditeur                                 */
/* ------------------------------------------------------------------ */

/**
 * Ce que les fournisseurs regardent avant de laisser entrer un message.
 *
 * Depuis 2024 chez Google et Yahoo, depuis 2025 chez Microsoft, un
 * expéditeur de masse doit être authentifié par SPF, DKIM et DMARC, et
 * l'adresse VISIBLE doit appartenir au domaine authentifié. Un message qui
 * échoue là-dessus n'est plus classé en indésirables : il est refusé.
 *
 * Rien de tout cela ne se règle dans l'application — ce sont trois lignes
 * dans la zone DNS. Mais l'application est le seul endroit où l'on saura
 * que ça ne va pas AVANT d'envoyer mille messages, alors elle regarde.
 *
 * `dns_get_record` peut être désactivée sur un mutualisé : on le dit au
 * lieu de conclure à tort que le domaine est mal réglé.
 */
function diagnostic_expediteur(): array
{
    $r = reglages_courriel();
    $adresse = trim((string) $r['courriel_expediteur']);
    $controles = [];
    $ajouter = function (string $id, string $etat, string $titre, string $aide = '') use (&$controles): void {
        $controles[] = ['id' => $id, 'etat' => $etat, 'titre' => $titre, 'aide' => $aide];
    };

    if ($adresse === '' || !filter_var($adresse, FILTER_VALIDATE_EMAIL)) {
        $ajouter('adresse', 'echec', 'Aucune adresse d’expédition valide',
                 'C’est elle qui figure dans « De : ». Renseignez-la ci-dessus.');
        return ['domaine' => '', 'controles' => $controles, 'passe' => false];
    }

    $domaine = strtolower(substr(strrchr($adresse, '@') ?: '', 1));
    $site = strtolower((string) (parse_url(base_url(), PHP_URL_HOST) ?: ''));

    /**
     * L'alignement : le domaine de l'adresse visible et celui du site.
     *
     * Ce n'est pas une obligation technique — on peut écrire depuis un
     * autre domaine — mais c'est celle qui se trompe le plus souvent : une
     * adresse en @gmail.com dans « De : » ne sera jamais alignée avec la
     * signature du relais, et DMARC la refuse.
     */
    $racine = static fn(string $h): string => implode('.', array_slice(explode('.', $h), -2));
    if ($site !== '' && $racine($domaine) !== $racine($site)) {
        $ajouter('alignement', 'alerte',
                 'L’adresse d’expédition n’est pas du domaine du site',
                 sprintf('« De : » est en @%s alors que le site est sur %s. DMARC exige que le domaine '
                       . 'visible corresponde à celui qui signe. Écrivez depuis @%s, ou publiez SPF, '
                       . 'DKIM et DMARC sur %s.', $domaine, $site, $racine($site), $domaine));
    } else {
        $ajouter('alignement', 'ok', 'L’adresse d’expédition est du domaine du site',
                 'C’est ce que DMARC appelle l’alignement, et c’est la condition la plus souvent manquée.');
    }

    if (!function_exists('dns_get_record')) {
        $ajouter('dns', 'alerte', 'Les enregistrements DNS n’ont pas pu être lus',
                 'L’hébergeur a désactivé `dns_get_record`. Vérifiez SPF, DKIM et DMARC à la main, '
               . 'ou depuis un autre poste.');
        return ['domaine' => $domaine, 'controles' => $controles, 'passe' => false];
    }

    $txt = static function (string $nom): array {
        $lus = @dns_get_record($nom, DNS_TXT) ?: [];
        $out = [];
        foreach ($lus as $l) {
            $v = (string) ($l['txt'] ?? '');
            if ($v !== '') {
                $out[] = $v;
            }
        }
        return $out;
    };

    /* SPF — qui a le droit d'envoyer pour ce domaine. */
    $spf = array_values(array_filter($txt($domaine), fn($v) => stripos($v, 'v=spf1') === 0));
    if (!$spf) {
        $ajouter('spf', 'echec', 'Aucun enregistrement SPF sur ' . $domaine,
                 'Publiez un TXT à la racine : v=spf1 include:<votre relais> ~all');
    } elseif (count($spf) > 1) {
        $ajouter('spf', 'echec', 'Deux enregistrements SPF sur ' . $domaine,
                 'Un seul est autorisé : les deux s’annulent. Fusionnez-les en une ligne.');
    } else {
        $ajouter('spf', 'ok', 'SPF publié', $spf[0]);
    }

    /* DMARC — ce qu'il faut faire d'un message qui échoue. */
    $dmarc = array_values(array_filter($txt('_dmarc.' . $domaine), fn($v) => stripos($v, 'v=DMARC1') === 0));
    if (!$dmarc) {
        $ajouter('dmarc', 'echec', 'Aucun enregistrement DMARC',
                 'Publiez un TXT sur _dmarc.' . $domaine . ' : v=DMARC1; p=none; rua=mailto:vous@'
               . $domaine . ' — commencez par p=none, il n’écarte rien et vous renseigne.');
    } else {
        $politique = preg_match('/\bp\s*=\s*(none|quarantine|reject)/i', $dmarc[0], $m) ? strtolower($m[1]) : '?';
        $ajouter('dmarc', 'ok', 'DMARC publié — politique « ' . $politique . ' »', $dmarc[0]);
    }

    /**
     * DKIM — la signature. On ne peut pas la deviner : le sélecteur est
     * choisi par le relais. On essaie donc les plus répandus, et l'on
     * s'abstient de conclure si aucun ne répond.
     */
    $selecteurs = ['default', 'mail', 'dkim', 'k1', 's1', 's2', 'google', 'brevo', 'mailjet', 'sendgrid', 'zoho'];
    $trouve = null;
    foreach ($selecteurs as $sel) {
        if ($txt($sel . '._domainkey.' . $domaine)) {
            $trouve = $sel;
            break;
        }
    }
    if ($trouve !== null) {
        $ajouter('dkim', 'ok', 'DKIM publié — sélecteur « ' . $trouve . ' »',
                 'La signature est posée par votre relais ; l’application ne signe rien elle-même.');
    } else {
        $ajouter('dkim', 'alerte', 'Aucune clé DKIM trouvée aux sélecteurs courants',
                 'Ce n’est pas une preuve d’absence : le sélecteur est choisi par votre relais et peut '
               . 'être quelconque. Vérifiez dans son tableau de bord que le domaine y est « authentifié ».');
    }

    $passe = !array_filter($controles, fn($c) => $c['etat'] === 'echec');
    return ['domaine' => $domaine, 'controles' => $controles, 'passe' => $passe];
}
