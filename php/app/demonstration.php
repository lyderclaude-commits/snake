<?php
/**
 * Contenu de départ : six décors publiés, un en attente de relecture,
 * et trois articles de blog.
 *
 * Le décor en attente n'est pas décoratif : il permet de voir la file de
 * relecture et le rapport de pré-vol sans avoir à créer quoi que ce soit.
 * Les trois derniers couvrent les formats de réseau, pour qu'on puisse
 * comparer un badge Instagram, Facebook et TikTok dès l'installation.
 *
 * Les articles ne sont pas du remplissage : un blog vide donne l'impression
 * d'une rubrique abandonnée, et c'est la première chose que voit quelqu'un
 * qui arrive par un moteur de recherche. Ils montrent aussi les trois états
 * du circuit — publié par l'équipe, publié pour un organisateur, et un
 * troisième en attente de relecture, pour qu'on voie la file fonctionner.
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

    installer_articles($admin_id);
}

/**
 * Trois articles, dont un qui attend la relecture.
 *
 * Ils sont écrits comme de vrais articles — un cas, des chiffres, ce qui
 * n'a pas marché — parce qu'un texte de remplissage en « lorem ipsum »
 * n'apprend rien sur ce à quoi la rubrique doit ressembler, et se retrouve
 * en ligne le jour de la mise en production.
 */
function installer_articles(string $admin_id): void
{
    $admin = utilisateur_par_id($admin_id);
    $lena = utilisateur_par_email('partenaire@exemple.tg');

    $articles = [
        [
            'slug' => '400-personnes-sans-une-affiche',
            'titre' => '400 personnes, et pas une affiche imprimée',
            'chapo' => 'Au Maquis Akwaba, en mars, la salle était pleine. Le budget communication '
                     . 'tenait dans un décor et un QR Code. Voici le détail, chiffres compris.',
            'auteur' => $admin, 'statut' => 'publie',
            /**
             * Le récit mène à un décor, et le lecteur arrive sur celui-là
             * plutôt que sur un catalogue.
             *
             * On cite un décor PUBLIÉ — « J'y serai » et non celui du
             * Maquis, que la démonstration laisse volontairement en
             * relecture pour garnir la file. Un article de démonstration
             * qui pointerait un décor invisible ne montrerait rien du tout,
             * et l'on chercherait longtemps pourquoi.
             */
            'decor' => 'jy-serai',
            'corps' => <<<'TXT'
Le patron du Maquis Akwaba nous a appelés un mardi. Sa soirée live du samedi
était dans quatre jours, et il lui restait **zéro franc** de budget affichage.

## Ce qu’on a fait

Un décor, une accroche, et le lien envoyé sur son statut WhatsApp. Rien d’autre.
Chaque personne qui faisait son badge le partageait — et le badge portait le nom
du maquis, la date, et un QR unique.

- **1 214 badges** créés en 9 jours
- **62 %** présentés à l’entrée, donc scannés
- **0 franc** dépensé en impression

## Pourquoi ça marche

Une affiche parle de l’événement. Un badge parle de **la personne** qui le
partage. Ce n’est pas la même chose, et ça ne circule pas de la même façon :
personne ne republie une affiche, tout le monde republie sa propre photo.

> « J’ai su combien de monde venait avant d’ouvrir les portes. C’est la première
> fois en huit ans. »

## Ce qui n’a pas marché

Les deux premiers jours, presque rien. On avait mis l’accroche en bas du visuel,
là où Instagram pose ses boutons : elle était **cachée sur la moitié des
téléphones**. Déplacée en haut, le partage a triplé le lendemain.

C’est le genre de détail qu’on ne voit pas depuis un ordinateur. Depuis, le
gabarit TikTok remonte tout au-dessus de la zone des boutons, par défaut.
TXT,
        ],
        [
            'slug' => 'le-qr-a-lentree-ce-quil-change',
            'titre' => 'Le QR à l’entrée : ce qu’il change vraiment',
            'chapo' => 'Compter les badges téléchargés ne dit rien. Compter les gens qui sont '
                     . 'entrés dit tout. La différence entre les deux est le seul chiffre qui compte.',
            'auteur' => $admin, 'statut' => 'publie',
            'corps' => <<<'TXT'
Un générateur de badges vous donne un chiffre : le nombre de téléchargements.
C’est flatteur, et c’est à peu près inutile — il compte des intentions, pas des
présences.

## Deux chiffres, deux réalités

Sur les campagnes que nous suivons, l’écart entre les badges **créés** et les
badges **scannés à l’entrée** va de 35 % à 78 %. Une campagne à 2 000
téléchargements et 35 % de présence remplit moins qu’une campagne à 800 et 70 %.

Sans le scan, les deux se ressemblent sur le tableau de bord. Avec, on sait
laquelle refaire.

## Ce que ça coûte à l’entrée

Un téléphone, et c’est tout. L’agent ouvre la page de contrôle, approche
l’appareil du badge, entend le bip. La page **ne se recharge pas** entre deux
invités : la caméra reste ouverte, et le rythme tient une vraie file.

- Un badge déjà scanné est refusé, avec l’heure du premier passage
- Un code inconnu est refusé
- La saisie à la main reste possible si la caméra flanche

## L’effet secondaire

Chaque présence scannée crédite des Koris à l’invité. Il repart avec quelque
chose, et il a une raison de revenir à la soirée suivante. Ce n’est plus une
soirée, c’est le début d’une habitude — et c’est là que se gagne la deuxième
salle comble.
TXT,
        ],
        [
            'slug' => 'remplir-un-maquis-un-mardi-soir',
            'titre' => 'Remplir un maquis un mardi soir',
            'chapo' => 'Le week-end, tout le monde sort. Le mardi, personne. Ce que nous avons '
                     . 'essayé chez nous pour renverser ça, et ce que ça a donné.',
            'auteur' => $lena ? utilisateur_par_id($lena['id']) : null, 'statut' => 'en_relecture',
            'corps' => <<<'TXT'
Un maquis vit du vendredi et du samedi. Le reste de la semaine, la salle tourne
à un quart de sa capacité et le personnel est payé pareil.

## L’idée

Faire du mardi un rendez-vous **à part**, avec son propre nom et son propre
visuel. Pas « venez aussi le mardi » — personne ne se déplace pour un jour de
seconde zone.

## Ce qu’on a mesuré

- Semaine 1 : 40 badges, 22 présences
- Semaine 4 : 180 badges, 121 présences
- La moitié des présents de la semaine 4 étaient déjà venus une fois

## Ce qu’on referait autrement

Nous avons lancé le premier mardi avec trois jours d’avance. C’était trop court :
les gens organisent leur semaine le dimanche. À partir de la troisième semaine,
nous avons publié le décor le vendredi précédent — et le nombre de badges créés
avant le lundi a doublé.
TXT,
        ],
    ];

    foreach ($articles as $a) {
        if (article_par_slug($a['slug'])) {
            continue;
        }
        $auteur = $a['auteur'] ?: $admin;
        $decor = ($a['decor'] ?? '') !== '' ? decor_par_slug((string) $a['decor']) : null;
        $id = article_creer([
            'slug' => $a['slug'],
            'titre' => $a['titre'],
            'chapo' => $a['chapo'],
            'corps' => trim($a['corps']),
            'couverture' => '',
            'decor_id' => $decor['id'] ?? '',
            'auteur_id' => $auteur['id'] ?? $admin_id,
            'auteur_nom' => $auteur['nom'] ?? 'La rédaction Wakabi',
        ]);

        if ($a['statut'] === 'publie') {
            article_transition($id, 'publie', ['id' => $admin_id, 'role' => 'equipe']);
        } else {
            // Soumis par son auteur, pas publié : la file de relecture du
            // blog a de quoi montrer à quoi elle sert dès l'installation.
            article_transition($id, 'en_relecture', [
                'id' => $auteur['id'] ?? $admin_id,
                'role' => $auteur['role'] ?? 'equipe',
            ]);
        }
    }
}
