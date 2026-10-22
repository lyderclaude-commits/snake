<?php
/**
 * Les réglages : des onglets déclarés par le site (`reglages` dans la
 * définition), plus celui du socle, « E-mails », où se règlent les copies
 * des demandes de contact et le serveur d'envoi.
 *
 * Un champ `traduit` se saisit deux fois, en français et en anglais
 * (`cle` et `cle_en`) ; sur le site, `reglage_l('cle')` prend la bonne.
 */

declare(strict_types=1);

/** Les onglets, celui du socle compris : `cle => [titre, description, sections]`. */
function reglages_onglets(): array
{
    $l = [];
    foreach ((array) def('reglages', []) as $k => $o) {
        $l[$k] = (array) $o + ['titre' => ucfirst((string) $k), 'description' => '', 'sections' => []];
    }
    if (module('messages')) {
        $l['courriel'] = ['titre' => 'E-mails', 'description' => '', 'sections' => [], 'socle' => true];
    }
    return $l;
}

/** Les clés que l'onglet du socle enregistre. */
const REGLAGES_COURRIEL = ['contact_copies', 'courriel_expediteur', 'smtp_hote', 'smtp_port', 'smtp_chiffrement', 'smtp_utilisateur', 'smtp_mdp'];

function admin_reglages(string $onglet, bool $post): never
{
    $onglets = reglages_onglets();
    if (!isset($onglets[$onglet])) {
        $onglet = (string) array_key_first($onglets);
    }
    $o = $onglets[$onglet] ?? null;
    if ($post && $o) {
        $retour = 'admin/reglages/' . $onglet;
        $v = [];
        if (!empty($o['socle'])) {
            foreach (REGLAGES_COURRIEL as $k) {
                if (array_key_exists($k, $_POST)) {
                    $v[$k] = trim((string) $_POST[$k]);
                }
            }
            // Le mot de passe SMTP n'est jamais réaffiché : un champ vide le garde.
            if (($v['smtp_mdp'] ?? '') === '' && !isset($_POST['smtp_mdp_effacer'])) {
                unset($v['smtp_mdp']);
            }
            if (($v['courriel_expediteur'] ?? '') !== '' && !filter_var($v['courriel_expediteur'], FILTER_VALIDATE_EMAIL)) {
                flash('err', 'L’adresse d’expédition n’est pas valide.');
                rediriger($retour);
            }
            if (isset($v['smtp_port'])) {
                $v['smtp_port'] = (string) max(1, min(65535, (int) $v['smtp_port'] ?: 587));
            }
            if (isset($v['smtp_chiffrement']) && !in_array($v['smtp_chiffrement'], ['tls', 'ssl', 'aucun'], true)) {
                $v['smtp_chiffrement'] = 'tls';
            }
        } else {
            foreach ($o['sections'] as $section) {
                foreach ((array) ($section['champs'] ?? []) as $k => $c) {
                    $c = champ_normaliser((string) $k, (array) $c);
                    foreach ($c['traduit'] && isset(LANGUES['en']) ? [$k, $k . '_en'] : [$k] as $cle) {
                        if (!array_key_exists($cle, $_POST) && $c['type'] !== 'case') {
                            continue;
                        }
                        $brut = $_POST[$cle] ?? '';
                        if ($c['type'] === 'lien' && trim((string) $brut) !== '' && !preg_match('#^https?://#i', trim((string) $brut))) {
                            flash('err', 'L’adresse « ' . trim((string) $brut) . ' » doit commencer par https://');
                            rediriger($retour);
                        }
                        if ($c['type'] === 'email' && trim((string) $brut) !== '' && !filter_var(trim((string) $brut), FILTER_VALIDATE_EMAIL)) {
                            flash('err', 'L’adresse « ' . trim((string) $brut) . ' » n’est pas une adresse e-mail valide.');
                            rediriger($retour);
                        }
                        $val = champ_saisir($c, $brut);
                        $v[$cle] = is_array($val) ? implode(', ', $val) : (string) $val;
                    }
                }
            }
            if (isset($v['site_url'])) {
                $v['site_url'] = rtrim($v['site_url'], '/');
            }
        }
        reglages_enregistrer($v);
        if (isset($_POST['tester'])) {
            $res = courriel_envoyer(courriel_destinataires(), 'Test d’envoi — ' . reglage('nom_site'),
                "Ce message confirme que le site sait envoyer des e-mails.\nLes demandes de contact arriveront de la même façon.\n",
                '<p>Ce message confirme que le site sait envoyer des e-mails.</p><p>Les demandes de contact arriveront de la même façon.</p>');
            flash($res['ok'] ? 'ok' : 'err', $res['ok']
                ? 'E-mail de test envoyé à ' . implode(', ', courriel_destinataires()) . ' (' . $res['detail'] . '). Vérifiez la boîte, et les indésirables.'
                : 'Échec de l’e-mail de test : ' . $res['detail']);
            rediriger($retour);
        }
        flash('ok', 'Réglages enregistrés.');
        rediriger($retour);
    }
    vue_admin('reglages', ['onglets' => $onglets, 'onglet' => $onglet, 'r' => reglages()]);
}
