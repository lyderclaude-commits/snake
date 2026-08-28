<?php
/**
 * Un écrivain de fichiers ZIP, écrit à la main.
 *
 * `ZipArchive` n'est pas toujours compilée sur un mutualisé, et une
 * sauvegarde qui échoue chez la moitié des hébergeurs ne sauvegarde rien.
 * Le format est par ailleurs court à écrire : un en-tête par fichier, un
 * répertoire central à la fin, et c'est tout.
 *
 * On écrit AU FIL DE L'EAU dans un fichier plutôt que d'assembler en
 * mémoire : une base de dix mille badges avec ses cadres dépasse la limite
 * de mémoire d'un mutualisé, et une sauvegarde qui tombe en panne de RAM le
 * jour où l'on en a le plus besoin serait une plaisanterie.
 *
 * Deux méthodes seulement : 0 (tel quel) et 8 (deflate). Aucun lecteur de
 * zip n'en demande davantage — c'est ce que produit `zip` en ligne de
 * commande depuis trente ans.
 */

declare(strict_types=1);

final class EcrivainZip
{
    /** @var list<array{nom: string, crc: int, compresse: int, brut: int, decalage: int, methode: int}> */
    private array $entrees = [];
    private $flux;

    public function __construct(private string $chemin)
    {
        $flux = @fopen($chemin, 'wb');
        if (!$flux) {
            throw new RuntimeException('Impossible d’écrire ' . basename($chemin) . ' : dossier en lecture seule ?');
        }
        $this->flux = $flux;
    }

    /** Ajoute un contenu déjà en mémoire — un dump, un fichier texte. */
    public function ajouter(string $nom, string $contenu): void
    {
        $this->ecrireEntree($nom, $contenu);
    }

    /**
     * Ajoute un fichier du disque, sans jamais le charger en entier.
     *
     * Le format ZIP demande la taille et le CRC AVANT les données. On lit
     * donc le fichier deux fois : une pour le CRC, une pour le contenu.
     * Deux lectures séquentielles coûtent moins qu'un cadre de 2 Mo tenu en
     * mémoire pendant qu'on en compresse un autre.
     */
    public function ajouterFichier(string $nom, string $source): bool
    {
        if (!is_file($source) || !is_readable($source)) {
            return false;
        }
        $brut = (int) filesize($source);
        $crc = (int) hexdec(hash_file('crc32b', $source));

        // Les images sont déjà compressées : les repasser en deflate coûte
        // du temps pour quelques octets. On les range telles quelles.
        $this->entete($nom, $crc, $brut, $brut, 0);
        $entree = @fopen($source, 'rb');
        if (!$entree) {
            return false;
        }
        while (!feof($entree)) {
            $bloc = fread($entree, 262144);
            if ($bloc === false) {
                break;
            }
            fwrite($this->flux, $bloc);
        }
        fclose($entree);
        return true;
    }

    /** Ferme l'archive en écrivant son répertoire central. */
    public function fermer(): void
    {
        $debut = ftell($this->flux);
        foreach ($this->entrees as $e) {
            // 4 octets de signature, la version d'écriture, le corps commun,
            // puis : longueur du commentaire, disque, attributs internes,
            // attributs externes, et le décalage de l'en-tête local.
            fwrite($this->flux, "\x50\x4b\x01\x02" . pack('v', 20) . $this->corpsEntete($e)
                . pack('vvvV', 0, 0, 0, 32) . pack('V', $e['decalage']) . $e['nom']);
        }
        $fin = ftell($this->flux);
        fwrite($this->flux, "\x50\x4b\x05\x06" . pack('vvvv', 0, 0, count($this->entrees), count($this->entrees))
            . pack('VV', $fin - $debut, $debut) . pack('v', 0));
        fclose($this->flux);
    }

    private function ecrireEntree(string $nom, string $contenu): void
    {
        $brut = strlen($contenu);
        $crc = (int) hexdec(hash('crc32b', $contenu));
        $compresse = gzdeflate($contenu, 6);
        // Un contenu déjà dense grossit en le compressant : on garde alors
        // l'original. C'est rare, mais un zip plus gros que sa source est
        // exactement le genre de détail qui fait douter de tout le reste.
        if ($compresse === false || strlen($compresse) >= $brut) {
            $this->entete($nom, $crc, $brut, $brut, 0);
            fwrite($this->flux, $contenu);
            return;
        }
        $this->entete($nom, $crc, strlen($compresse), $brut, 8);
        fwrite($this->flux, $compresse);
    }

    private function entete(string $nom, int $crc, int $compresse, int $brut, int $methode): void
    {
        $e = [
            'nom' => $nom,
            'crc' => $crc,
            'compresse' => $compresse,
            'brut' => $brut,
            'decalage' => (int) ftell($this->flux),
            'methode' => $methode,
        ];
        $this->entrees[] = $e;
        fwrite($this->flux, "\x50\x4b\x03\x04" . $this->corpsEntete($e) . $nom);
    }

    /**
     * L'horodatage MS-DOS, tel que le format l'exige depuis 1989.
     *
     * Le laisser à zéro donne « 1980-00-00 » dans la liste d'un zip : un
     * mois zéro et un jour zéro n'existent pas, certains outils s'en
     * plaignent, et surtout on ne sait plus quand la sauvegarde a été faite.
     * Les secondes tiennent sur cinq bits, donc par pas de deux.
     */
    private static function heureDos(): int
    {
        $t = getdate();
        return ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2);
    }

    private static function dateDos(): int
    {
        $t = getdate();
        return (max(0, $t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
    }

    /** La partie commune aux deux en-têtes, à l'octet près. */
    private function corpsEntete(array $e): string
    {
        return pack('v', 20)                     // version minimale
             . pack('v', 0x0800)                 // noms de fichiers en UTF-8
             . pack('v', $e['methode'])
             . pack('v', self::heureDos()) . pack('v', self::dateDos())
             . pack('V', $e['crc'])
             . pack('V', $e['compresse'])
             . pack('V', $e['brut'])
             . pack('v', strlen($e['nom']))
             . pack('v', 0);                     // pas de champ supplémentaire
    }
}
