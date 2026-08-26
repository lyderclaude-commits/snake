<?php
/** Sort la matrice d'un QR. Un second argument force le masque (débogage). */
require __DIR__ . '/../php/app/qr.php';
$masque = isset($argv[2]) ? (int) $argv[2] : null;
foreach (Qr::matrix($argv[1], $masque) as $ligne) {
    echo implode('', $ligne), "\n";
}
