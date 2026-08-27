<?php
/**
 * Contenu de départ : six décors publiés, un en attente de relecture.
 *
 * Le décor en attente n'est pas décoratif : il permet de voir la file de
 * relecture et le rapport de pré-vol sans avoir à créer quoi que ce soit.
 * Les trois derniers couvrent les formats de réseau, pour qu'on puisse
 * comparer un badge Instagram, Facebook et TikTok dès l'installation.
 */

declare(strict_types=1);

function installer_demonstration(string $admin_id): void
{
    $modeles = [
        ['jy-serai', 'J’y serai à Lomé', 'Festival des Divinités Noires', 'bandeau', 'jy-serai.png',
         'J’Y SERAI', 'Ton prénom', 'Kossi', 'lome', 'evenements'],
        ['bon-plan-du-moment', 'Bon plan du moment', 'Partage ta trouvaille de la semaine', 'angle', 'bon-plan.png',
         'BON PLAN', 'Le nom du bon coin', 'Chez Léna', 'lome', 'gastronomie'],
        ['story-wakabi', 'Story Wakabi', 'Format vertical, pour le statut WhatsApp', 'story', 'story.png',
         'WAKABI', 'Ton prénom', 'Ama', 'all', 'campagne'],
        ['post-instagram', 'Post Instagram', 'Format 4:5, celui qui prend le plus de place dans le fil',
         'instagram', 'instagram.png', 'J’Y SERAI', 'Ton prénom', 'Ama', 'all', 'campagne'],
        ['post-facebook', 'Post Facebook', 'Carré, jamais recadré par le fil',
         'facebook', 'facebook.png', 'J’Y SERAI', 'Ton prénom', 'Kossi', 'all', 'campagne'],
        ['post-tiktok', 'TikTok & Reels', 'Vertical, tout remonté au-dessus des boutons de l’appli',
         'tiktok', 'tiktok.png', 'J’Y SERAI', 'Ton prénom', 'Ama', 'all', 'campagne'],
    ];

    foreach ($modeles as [$slug, $titre, $sous, $dispo, $cadre, $accroche, $libelle, $valeur, $ville, $rubrique]) {
        if (slug_existe($slug)) {
            continue;
        }
        $url = url('public/cadres/' . $cadre);
        $g = construire_gabarit([
            'slug' => $slug, 'titre' => $titre, 'sous_titre' => $sous,
            'ville' => $ville, 'rubrique' => $rubrique, 'disposition' => $dispo,
            'cadre_url' => $url, 'accroche' => $accroche,
            'champ_libelle' => $libelle, 'champ_valeur' => $valeur,
            'redirection' => 'https://wakabileguide.com/', 'redirection_libelle' => '',
            'legende' => '', 'expire_le' => '', 'cree_par' => 'equipe',
        ]);
        $id = decor_creer([
            'slug' => $slug, 'titre' => $titre, 'sous_titre' => $sous,
            'ville' => $ville, 'rubrique' => $rubrique, 'cree_par' => 'equipe',
            'auteur_id' => $admin_id, 'gabarit' => $g, 'cadre_url' => $url, 'expire_le' => '',
        ]);
        decor_transition($id, 'publie', ['id' => $admin_id, 'role' => 'equipe']);
    }

    // Un partenaire fictif, et sa soumission en attente.
    if (!utilisateur_par_email('partenaire@exemple.tg')) {
        $partenaire = creer_utilisateur([
            'email' => 'partenaire@exemple.tg',
            'mot_de_passe' => bin2hex(random_bytes(12)), // inconnu de tous : compte de vitrine
            'nom' => 'Léna Adjovi',
            'role' => 'partenaire',
            'organisation' => 'Chez Léna',
            'ville' => 'lome',
        ]);

        if (!slug_existe('soiree-maquis-akwaba')) {
            $url = url('public/cadres/bon-plan.png');
            $g = construire_gabarit([
                'slug' => 'soiree-maquis-akwaba', 'titre' => 'Soirée au Maquis Akwaba',
                'sous_titre' => 'Notre soirée live du samedi', 'ville' => 'abidjan',
                'rubrique' => 'evenements', 'disposition' => 'angle', 'cadre_url' => $url,
                'accroche' => 'J’Y SERAI', 'champ_libelle' => 'Ton prénom', 'champ_valeur' => 'Aya',
                'redirection' => 'https://wakabileguide.com/p/maquis-akwaba',
                'redirection_libelle' => '', 'legende' => '', 'expire_le' => '',
                'cree_par' => 'partenaire', 'partenaire_id' => $partenaire,
            ]);
            $id = decor_creer([
                'slug' => 'soiree-maquis-akwaba', 'titre' => 'Soirée au Maquis Akwaba',
                'sous_titre' => 'Notre soirée live du samedi', 'ville' => 'abidjan',
                'rubrique' => 'evenements', 'cree_par' => 'partenaire', 'auteur_id' => $partenaire,
                'gabarit' => $g, 'cadre_url' => $url, 'expire_le' => '',
            ]);
            // Le pré-vol tourne, comme pour une vraie soumission.
            $rapport = prevol(json_lire(decor_par_id($id)['gabarit']), $url);
            enregistrer_prevol($id, $rapport);
            if ($rapport['passe']) {
                decor_transition($id, 'en_relecture', ['id' => $partenaire, 'role' => 'partenaire']);
            }
        }
    }
}
