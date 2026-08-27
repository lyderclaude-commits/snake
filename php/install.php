<?php
/**
 * Installateur — la seule page que vous ouvrirez à la main.
 *
 * Il vérifie ce que propose l'hébergeur, crée les tables, votre compte
 * d'équipe et des décors de démonstration, écrit config.php, puis se
 * neutralise : relancé après coup, il refuse de repartir.
 */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/schema.php';
require __DIR__ . '/app/gabarit.php';

$deja = is_file(RACINE . '/config.php');
$erreur = null;
$fait = false;

/* ---------------- diagnostic de l'hébergement ---------------- */

$php_ok = PHP_VERSION_ID >= 80100;
$extensions = [
    'PDO' => extension_loaded('pdo'),
    'GD (images)' => extension_loaded('gd'),
    'mbstring (accents)' => extension_loaded('mbstring'),
    'SQLite' => extension_loaded('pdo_sqlite'),
    'MySQL' => extension_loaded('pdo_mysql'),
];
$gd_png = extension_loaded('gd') && (gd_info()['PNG Support'] ?? false);
$dossier_ecrit = is_writable(RACINE);
$bloquant = !$php_ok || !extension_loaded('pdo') || !$gd_png || !extension_loaded('mbstring')
    || (!extension_loaded('pdo_sqlite') && !extension_loaded('pdo_mysql'))
    || !$dossier_ecrit;

$valeurs = [
    'sgbd' => extension_loaded('pdo_sqlite') ? 'sqlite' : 'mysql',
    'hote' => 'localhost', 'port' => '3306', 'base' => '', 'utilisateur' => '', 'motdepasse' => '',
    'nom' => '', 'email' => '', 'admin_mdp' => '', 'demo' => '1',
];

/* ---------------- installation ---------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$deja && !$bloquant) {
    foreach (array_keys($valeurs) as $k) {
        $valeurs[$k] = trim((string) ($_POST[$k] ?? $valeurs[$k]));
    }

    if (!filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) {
        $erreur = 'L’adresse de votre compte d’équipe n’est pas valide.';
    } elseif (strlen($valeurs['admin_mdp']) < 10) {
        $erreur = 'Le mot de passe administrateur doit faire au moins 10 caractères : ce compte publie, modère et gère les autres comptes.';
    } elseif ($valeurs['nom'] === '') {
        $erreur = 'Indiquez votre nom.';
    } else {
        try {
            $conf = ['sgbd' => $valeurs['sgbd'], 'dossier_donnees' => RACINE . '/donnees'];
            if ($valeurs['sgbd'] === 'mysql') {
                $conf += [
                    'hote' => $valeurs['hote'],
                    'port' => (int) $valeurs['port'],
                    'base' => $valeurs['base'],
                    'utilisateur' => $valeurs['utilisateur'],
                    'motdepasse' => $valeurs['motdepasse'],
                ];
                $dsn = "mysql:host={$conf['hote']};port={$conf['port']};dbname={$conf['base']};charset=utf8mb4";
                $pdo = new PDO($dsn, $conf['utilisateur'], $conf['motdepasse'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } else {
                @mkdir($conf['dossier_donnees'], 0775, true);
                $conf['fichier'] = $conf['dossier_donnees'] . '/wakabi.sqlite';
                $pdo = new PDO('sqlite:' . $conf['fichier'], null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $pdo->exec('PRAGMA journal_mode = WAL');
            }

            creer_schema($pdo, $valeurs['sgbd'] === 'mysql');

            // config.php AVANT de peupler : les fonctions de l'application le lisent.
            $php = "<?php\n/* Généré par install.php le " . gmdate('Y-m-d H:i') . " UTC. */\nreturn "
                 . var_export($conf, true) . ";\n";
            if (file_put_contents(RACINE . '/config.php', $php) === false) {
                throw new RuntimeException('Impossible d’écrire config.php. Vérifiez les droits du dossier.');
            }
            @chmod(RACINE . '/config.php', 0600);

            /**
             * Le schéma sort d'usine à jour : pas de migration à rejouer.
             *
             * Écrit APRÈS config.php, et depuis $conf plutôt que par
             * `dossier_donnees()` : cette fonction lit la configuration, et
             * la met en cache pour toute la requête. L'appeler avant que le
             * fichier existe fige une configuration vide — l'installation
             * MySQL basculait alors sur SQLite et échouait sur « no such
             * table: utilisateurs ».
             */
            @mkdir($conf['dossier_donnees'], 0775, true);
            @file_put_contents($conf['dossier_donnees'] . '/version-schema.txt', (string) SCHEMA_VERSION);

            require_once RACINE . '/app/auth.php';
            require_once RACINE . '/app/depot.php';
            require_once RACINE . '/app/prevol.php';

            $admin = creer_utilisateur([
                'email' => $valeurs['email'],
                'mot_de_passe' => $valeurs['admin_mdp'],
                'nom' => $valeurs['nom'],
                'role' => 'equipe',
                'ville' => 'lome',
            ]);
            db()->prepare('UPDATE utilisateurs SET email_verifie_le = ? WHERE id = ?')
                ->execute([maintenant(), $admin]);

            if ($valeurs['demo'] === '1') {
                require RACINE . '/app/demonstration.php';
                installer_demonstration($admin);
            }

            @mkdir(RACINE . '/donnees/cadres', 0775, true);
            $fait = true;
        } catch (Throwable $e) {
            @unlink(RACINE . '/config.php');
            $erreur = $e->getMessage();
        }
    }
}

$csrf = '';
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation · Wakabi Boost</title>
<link rel="stylesheet" href="public/wakabi.css">
</head>
<body>
<div class="contenu" style="max-width:640px;padding-top:40px">

<h1 style="margin-bottom:.3em">Installer Wakabi Boost</h1>

<?php if ($fait): ?>

  <div class="msg ok" style="margin-top:20px">
    <strong>C’est installé.</strong>
    <p style="margin:.5em 0 0">Connectez-vous avec <strong><?= e($valeurs['email']) ?></strong>.</p>
  </div>
  <div class="carte">
    <h3>Une dernière chose, maintenant</h3>
    <p style="color:var(--text2)"><strong>Supprimez <code>install.php</code></strong> depuis votre
    gestionnaire de fichiers. Il refuse déjà de se relancer, mais un fichier absent vaut mieux
    qu’un fichier qui refuse.</p>
    <a class="bouton" href="index.php?p=connexion">Ouvrir Wakabi Boost</a>
  </div>

<?php elseif ($deja): ?>

  <div class="msg err" style="margin-top:20px">
    <strong>Déjà installé.</strong>
    <p style="margin:.5em 0 0">Le fichier <code>config.php</code> existe. Pour réinstaller,
    supprimez-le d’abord : vous perdriez l’accès à vos données actuelles.</p>
  </div>
  <a class="bouton" href="index.php">Aller au site</a>

<?php else: ?>

  <p style="color:var(--text2)">Trois minutes. Aucune ligne de commande.</p>

  <div class="carte" style="margin-top:20px">
    <h3 style="margin-bottom:12px">Ce que propose votre hébergement</h3>
    <table>
      <tbody>
        <tr><td>PHP <?= e(PHP_VERSION) ?></td>
            <td style="text-align:right;color:<?= $php_ok ? 'var(--teal)' : 'var(--rouge)' ?>">
              <?= $php_ok ? '✓ suffisant' : '✗ il faut PHP 8.1 ou plus' ?></td></tr>
        <?php foreach ($extensions as $nom => $present): ?>
          <tr><td><?= e($nom) ?></td>
              <td style="text-align:right;color:<?= $present ? 'var(--teal)' : 'var(--text3)' ?>">
                <?= $present ? '✓' : '·' ?></td></tr>
        <?php endforeach; ?>
        <tr><td>Écriture dans ce dossier</td>
            <td style="text-align:right;color:<?= $dossier_ecrit ? 'var(--teal)' : 'var(--rouge)' ?>">
              <?= $dossier_ecrit ? '✓' : '✗ droits insuffisants' ?></td></tr>
      </tbody>
    </table>
  </div>

  <?php if ($bloquant): ?>
    <div class="msg err">
      <strong>Il manque quelque chose d’indispensable.</strong>
      <p style="margin:.5em 0 0">Wakabi Boost a besoin de PHP 8.1+, de PDO, de GD avec le
      support PNG, de mbstring, d’au moins un moteur de base (SQLite ou MySQL), et du droit
      d’écrire dans son dossier. Demandez à votre hébergeur d’activer ce qui manque.</p>
    </div>
  <?php else: ?>

    <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

    <form method="post" class="carte" style="margin-top:16px">
      <h3 style="margin-bottom:14px">1 · La base de données</h3>
      <div class="champ">
        <label for="sgbd">Moteur</label>
        <select id="sgbd" name="sgbd" onchange="document.getElementById('mysql').hidden = this.value !== 'mysql'">
          <?php if (extension_loaded('pdo_sqlite')): ?>
            <option value="sqlite" <?= $valeurs['sgbd'] === 'sqlite' ? 'selected' : '' ?>>
              SQLite : aucun réglage, recommandé pour démarrer
            </option>
          <?php endif; ?>
          <?php if (extension_loaded('pdo_mysql')): ?>
            <option value="mysql" <?= $valeurs['sgbd'] === 'mysql' ? 'selected' : '' ?>>
              MySQL / MariaDB : une base créée dans cPanel
            </option>
          <?php endif; ?>
        </select>
        <p class="aide">SQLite range tout dans un fichier : rien à créer, rien à saisir.
        Vous pourrez passer à MySQL plus tard.</p>
      </div>

      <div id="mysql" <?= $valeurs['sgbd'] === 'mysql' ? '' : 'hidden' ?>>
        <div class="champ"><label for="hote">Serveur</label>
          <input id="hote" name="hote" type="text" value="<?= e($valeurs['hote']) ?>"></div>
        <div class="champ"><label for="base">Nom de la base</label>
          <input id="base" name="base" type="text" value="<?= e($valeurs['base']) ?>"
                 placeholder="moncompte_wakabi"></div>
        <div class="champ"><label for="utilisateur">Utilisateur</label>
          <input id="utilisateur" name="utilisateur" type="text" value="<?= e($valeurs['utilisateur']) ?>"></div>
        <div class="champ"><label for="motdepasse">Mot de passe</label>
          <input id="motdepasse" name="motdepasse" type="password"></div>
      </div>

      <h3 style="margin:22px 0 14px">2 · Votre compte d’équipe</h3>
      <div class="champ"><label for="nom">Votre nom</label>
        <input id="nom" name="nom" type="text" required value="<?= e($valeurs['nom']) ?>"></div>
      <div class="champ"><label for="email">Votre adresse e-mail</label>
        <input id="email" name="email" type="email" required value="<?= e($valeurs['email']) ?>"></div>
      <div class="champ"><label for="admin_mdp">Mot de passe</label>
        <input id="admin_mdp" name="admin_mdp" type="password" required minlength="10">
        <p class="aide">Dix caractères au minimum. Ce compte publie, modère et gère les autres.</p></div>

      <h3 style="margin:22px 0 14px">3 · Contenu de départ</h3>
      <div class="champ">
        <label><input type="checkbox" name="demo" value="1" <?= $valeurs['demo'] === '1' ? 'checked' : '' ?>
               style="width:auto;margin-right:8px">
          Installer trois décors de démonstration</label>
        <p class="aide">Pratique pour voir le produit tourner tout de suite. Supprimables ensuite.</p>
      </div>

      <button class="bouton" type="submit" style="width:100%;justify-content:center">Installer</button>
    </form>
  <?php endif; ?>
<?php endif; ?>

</div>
</body>
</html>
