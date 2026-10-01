<?php
/**
 * Les réglages de permaliens, et surtout leur essai.
 *
 * Un écran qui se contenterait d'enregistrer un choix serait ici
 * dangereux : les adresses sont la seule chose du site qu'on ne peut plus
 * reprendre une fois partie. Celui-ci montre donc les adresses obtenues
 * AVANT de les appliquer, puis demande au site de se répondre à lui-même
 * sur une adresse lisible — et n'active la forme lisible que si la
 * réponse est venue.
 */
$u = exiger_droit('reglages');

$message = null;
$erreur = null;
$essai = null;

$r = permaliens_reglages();
/* Ce qu'affichent les champs : ce qui vient d'être tapé si l'essai a
   échoué, sinon ce qui est enregistré. Renvoyer les valeurs d'avant
   obligerait à tout retaper après un échec qui n'a rien à voir avec
   elles. */
$forme = $r['permaliens_forme'];
$base_blog = $r['permaliens_blog'];
$base_decors = $r['permaliens_decors'];
$barre = $r['permaliens_barre'] === '1';

if ($post) {
    verifier_csrf();
    $action = (string) ($_POST['action'] ?? 'enregistrer');

    if ($action === 'essai') {
        /**
         * Refaire l'essai sans rien changer.
         *
         * Le cas qui l'exige : un hébergeur active `mod_rewrite` après
         * coup, ou un autre outil remplace le `.htaccess`. Sans ce
         * bouton, il faudrait repasser à la forme simple puis revenir à
         * la forme lisible pour que le site s'en aperçoive.
         */
        $essai = permaliens_essai();
        reglages_bdd_poser(['permaliens_verifie' => $essai['ok'] ? maintenant() : '']);
        $message = $essai['ok'] ? $essai['message'] : null;
        $erreur = $essai['ok'] ? null : $essai['message'];
    } else {
        $forme = ($_POST['permaliens_forme'] ?? '') === 'lisible' ? 'lisible' : 'simple';
        $base_blog = trim((string) ($_POST['permaliens_blog'] ?? ''));
        $base_decors = trim((string) ($_POST['permaliens_decors'] ?? ''));
        $barre = ($_POST['permaliens_barre'] ?? '') === '1';

        $b = permaliens_base($base_blog);
        $d = permaliens_base($base_decors);

        /**
         * Les deux refus, et ce qu'ils évitent.
         *
         * Un préfixe vide donnerait `/mon-article` : l'adresse d'un
         * article entrerait alors en concurrence avec celle de chaque
         * page du site et avec les codes de liens courts. Deux préfixes
         * identiques rendraient `/boost/x` indécidable — article ou
         * décor, on ne saurait pas, et le site trancherait au hasard de
         * l'ordre du code.
         */
        if ($b === '' || $d === '') {
            $erreur = 'Les deux préfixes doivent porter un nom. Sans eux, l’adresse d’un '
                    . 'article ne se distinguerait plus de celle d’une page.';
        } elseif ($b === $d) {
            /* Pas de `e()` ici : la vue échappe tout ce qu'elle affiche, et
               échapper deux fois rendrait « &amp;lt; » à l'écran. */
            $erreur = 'Les deux préfixes doivent différer : avec le même, « /' . $b
                    . '/quelque-chose » pourrait désigner un article ou un décor.';
        } elseif (in_array($b, PERMALIENS_FIXES, true) || in_array($d, PERMALIENS_FIXES, true)) {
            $erreur = 'Ce préfixe est déjà l’adresse d’une page du site. '
                    . 'Choisissez-en un autre, sinon cette page deviendrait inatteignable.';
        } elseif (preg_match('~^[A-HJ-NP-Za-hj-np-z2-9]{6}$~', $b)
                  || preg_match('~^[A-HJ-NP-Za-hj-np-z2-9]{6}$~', $d)) {
            /**
             * Six caractères de l'alphabet des liens courts : refusé.
             *
             * `/abcdef` serait attrapé par la règle des liens courts, qui
             * passe avant la règle des adresses lisibles, et la page de
             * liste répondrait « lien introuvable ». Le garde-fou de
             * `permaliens_jolie()` rattraperait le coup en retombant sur
             * la forme simple pour CETTE page, mais pas pour les fiches
             * qui sont dessous : on se retrouverait avec un préfixe à
             * moitié lisible, ce qui est pire qu'un refus franc.
             */
            $erreur = 'Un préfixe de six caractères sans i, l, o ni zéro serait confondu '
                    . 'avec un code de lien court. Ajoutez ou retirez une lettre.';
        }

        if ($erreur === null && $forme === 'lisible') {
            /**
             * L'essai AVANT l'enregistrement, et c'est tout le propos.
             *
             * Activer d'abord et vérifier ensuite distribuerait, le temps
             * d'un aller-retour, un site entier d'adresses mortes : le
             * menu, le pied de page, le plan du site et les liens de
             * chaque vignette, tous en 404. Ici, un essai qui échoue
             * laisse les adresses telles qu'elles étaient.
             */
            $essai = permaliens_essai();
            if (!$essai['ok']) {
                $erreur = 'La forme lisible n’a pas été activée. ' . $essai['message'];
                $forme = 'simple';
            }
        }

        if ($erreur === null) {
            reglages_bdd_poser([
                'permaliens_forme'   => $forme,
                'permaliens_blog'    => $b,
                'permaliens_decors'  => $d,
                'permaliens_barre'   => $barre ? '1' : '0',
            ] + ($forme === 'lisible' ? ['permaliens_verifie' => maintenant()] : []));

            /* Changer la forme des adresses publiques du site se journalise :
               c'est l'acte dont on cherchera la trace le jour où quelqu'un
               demandera depuis quand les anciennes adresses redirigent. */
            journal_ecrire($u, 'reglages.permaliens', 'reglages', 'permaliens',
                $forme === 'lisible' ? 'Adresses lisibles' : 'Adresses simples',
                $forme === 'lisible' ? '/' . $b . '/… et /' . $d . '/…' : '?p=…');

            $message = $forme === 'lisible'
                ? 'Adresses lisibles en service. Les anciennes adresses continuent de '
                  . 'répondre et redirigent vers les nouvelles.'
                : 'Adresses simples en service. Les adresses lisibles déjà partagées '
                  . 'continuent de répondre.';
            // Relire après écriture : `permaliens_reglages()` est memoïsé
            // sur le compteur d'écritures, donc à jour ici.
            $r = permaliens_reglages();
            $base_blog = $r['permaliens_blog'];
            $base_decors = $r['permaliens_decors'];
        }
    }
}

vue('reglages-permaliens', [
    'titre' => 'Réglages · Les adresses',
    'forme' => $forme,
    'base_blog' => $base_blog,
    'base_decors' => $base_decors,
    'barre' => $barre,
    'actifs' => permaliens_actifs(),
    'verifie' => permaliens_reglages()['permaliens_verifie'],
    'exemples' => permaliens_exemples($forme, $base_blog, $base_decors, $barre),
    'essai' => $essai,
    'bloc' => permaliens_bloc_htaccess(),
    'message' => $message,
    'erreur' => $erreur,
]);
