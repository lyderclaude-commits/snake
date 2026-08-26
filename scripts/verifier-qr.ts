/**
 * Vérifie l'encodeur QR écrit en PHP contre la bibliothèque `qrcode` de Node.
 *
 * Un QR faux ne se voit pas : il s'imprime, se partage, et ne se scanne pas.
 * La seule vérification qui vaille est donc la comparaison module par module
 * avec une implémentation de référence.
 */
import QRCode from 'qrcode';
import { execFileSync } from 'node:child_process';

const CHARGES = [
  'A',
  'https://boost.wakabileguide.com/qr/A7K2M9XQ4P',
  'https://boost.wakabileguide.com/qr/LLYH6GUD5U',
  'https://wakabileguide.com/qr/ZZZZZZZZZZ',
  'Festival des Divinités Noires — Lomé 2026',
  'x'.repeat(20),
  'x'.repeat(40),
  'x'.repeat(60),
  'x'.repeat(80),
  'x'.repeat(110),
  'x'.repeat(150),
  'x'.repeat(190),
];

let ok = 0, ko = 0;

for (const charge of CHARGES) {
  // Mode octet FORCÉ. Laissée libre, la bibliothèque découpe en segments mixtes
  // (octet + alphanumérique) pour gagner quelques modules. Les deux QR sont
  // valides et décodent la même chaîne, mais les matrices diffèrent : comparer
  // à un mode auto ferait échouer un encodeur correct.
  const ref = QRCode.create([{ data: charge, mode: 'byte' }] as never, {
    errorCorrectionLevel: 'M',
  });
  const n = ref.modules.size;
  const attendu: string[] = [];
  for (let r = 0; r < n; r++) {
    let ligne = '';
    for (let c = 0; c < n; c++) ligne += ref.modules.get(r, c) ? '1' : '0';
    attendu.push(ligne);
  }

  const obtenu = execFileSync('php', ['scripts/qr-matrice.php', charge])
    .toString().trim().split('\n');

  const memeTaille = obtenu.length === attendu.length;
  const identique = memeTaille && obtenu.every((l, i) => l === attendu[i]);

  if (identique) {
    ok++;
    console.log(`  ✓ ${String(charge.length).padStart(3)} car. — version ${(n - 17) / 4}, ${n}×${n}`);
  } else {
    ko++;
    console.log(`  ✗ ${String(charge.length).padStart(3)} car. — attendu ${attendu.length}×${attendu.length}, obtenu ${obtenu.length}×${obtenu[0]?.length}`);
    if (memeTaille) {
      const premiere = obtenu.findIndex((l, i) => l !== attendu[i]);
      console.log(`      première ligne différente : ${premiere}`);
      console.log(`      attendu : ${attendu[premiere]}`);
      console.log(`      obtenu  : ${obtenu[premiere]}`);
    }
  }
}
console.log(`\n━━ ${ok} identiques, ${ko} différentes ━━`);
process.exitCode = ko ? 1 : 0;
