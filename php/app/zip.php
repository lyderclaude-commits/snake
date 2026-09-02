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

/**
 * Et le lecteur, pour restaurer.
 *
 * Écrire une archive sans savoir la relire, c'était livrer une sauvegarde
 * qu'on ne pouvait remettre en place qu'en ligne de commande — et une
 * sauvegarde qu'on ne sait pas restaurer sous pression n'est qu'à moitié
 * une sauvegarde.
 *
 * On lit le RÉPERTOIRE CENTRAL, en fin de fichier, plutôt que d'avancer
 * d'en-tête en en-tête : c'est la seule partie qu'un écrivain est tenu de
 * remplir exactement, et c'est ce que font les vrais lecteurs. Deux
 * méthodes suffisent, les mêmes qu'à l'écriture : 0 et 8.
 */
final class LecteurZip
{
    /** @var array<string, array{decalage: int, methode: int, compresse: int, brut: int}> */
    private array $entrees = [];

    public function __construct(private string $chemin)
    {
        $this->lireRepertoire();
    }

    /** Les noms contenus dans l'archive. */
    public function noms(): array
    {
        return array_keys($this->entrees);
    }

    public function contient(string $nom): bool
    {
        return isset($this->entrees[$nom]);
    }

    /** Le contenu d'une entrée, ou `null` si elle n'y est pas ou est illisible. */
    public function lire(string $nom): ?string
    {
        $e = $this->entrees[$nom] ?? null;
        if ($e === null) {
            return null;
        }
        $f = fopen($this->chemin, 'rb');
        if (!$f) {
            return null;
        }
        fseek($f, $e['decalage']);
        $tete = fread($f, 30);
        if ($tete === false || strlen($tete) < 30 || substr($tete, 0, 4) !== "PK\x03\x04") {
            fclose($f);
            return null;
        }
        // Le nom et le champ « extra » de l'en-tête LOCAL peuvent différer
        // en longueur de ceux du répertoire : on lit les siens.
        $n = unpack('v', substr($tete, 26, 2))[1];
        $x = unpack('v', substr($tete, 28, 2))[1];
        fseek($f, $e['decalage'] + 30 + $n + $x);
        $brut = $e['compresse'] > 0 ? fread($f, $e['compresse']) : '';
        fclose($f);
        if ($brut === false) {
            return null;
        }
        if ($e['methode'] === 0) {
            return $brut;
        }
        $clair = @gzinflate($brut);
        return $clair === false ? null : $clair;
    }

    /** Écrit une entrée directement dans un fichier. Rend le nombre d'octets. */
    public function extraire(string $nom, string $vers): int
    {
        $contenu = $this->lire($nom);
        if ($contenu === null) {
            return 0;
        }
        return (int) @file_put_contents($vers, $contenu);
    }

    private function lireRepertoire(): void
    {
        $taille = (int) @filesize($this->chemin);
        $f = @fopen($this->chemin, 'rb');
        if (!$f || $taille < 22) {
            throw new RuntimeException('Ce fichier n’est pas une archive lisible.');
        }
        // La fin du répertoire central est dans les 64 derniers Ko au plus :
        // c'est la taille maximale du commentaire d'archive.
        $lu = min($taille, 65557);
        fseek($f, $taille - $lu);
        $fin = (string) fread($f, $lu);
        $pos = strrpos($fin, "PK\x05\x06");
        if ($pos === false) {
            fclose($f);
            throw new RuntimeException('Archive incomplète : sa fin manque.');
        }
        $eocd = substr($fin, $pos, 22);
        $nombre = unpack('v', substr($eocd, 10, 2))[1];
        $debut = unpack('V', substr($eocd, 16, 4))[1];

        fseek($f, $debut);
        for ($i = 0; $i < $nombre; $i++) {
            $tete = fread($f, 46);
            if ($tete === false || strlen($tete) < 46 || substr($tete, 0, 4) !== "PK\x01\x02") {
                break;
            }
            $methode = unpack('v', substr($tete, 10, 2))[1];
            $compresse = unpack('V', substr($tete, 20, 4))[1];
            $brut = unpack('V', substr($tete, 24, 4))[1];
            $ln = unpack('v', substr($tete, 28, 2))[1];
            $lx = unpack('v', substr($tete, 30, 2))[1];
            $lc = unpack('v', substr($tete, 32, 2))[1];
            $decalage = unpack('V', substr($tete, 42, 4))[1];
            $nom = (string) fread($f, $ln);
            if ($lx + $lc > 0) {
                fread($f, $lx + $lc);
            }
            // Un nom qui remonte l'arborescence n'entre pas : c'est ainsi
            // qu'une archive trafiquée écrase un fichier du serveur.
            if ($nom !== '' && !str_contains($nom, '..') && !str_starts_with($nom, '/')) {
                $this->entrees[$nom] = [
                    'decalage' => $decalage, 'methode' => $methode,
                    'compresse' => $compresse, 'brut' => $brut,
                ];
            }
        }
        fclose($f);
    }
}
