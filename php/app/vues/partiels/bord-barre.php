<?php
/**
 * La barre de commande du tableau de bord, partagée par les deux vues.
 *
 * Écrite une fois : les deux vues la portent à l'identique, et deux
 * copies auraient divergé au premier ajout — une recherche présente d'un
 * côté, absente de l'autre, sans que personne sache si c'est voulu.
 *
 * Attend $me, $onglet ('faire' ou 'chiffres') et
 * $nouveau (ce qu'on a le droit de créer).
 */
?>
<div class="bord-barre">
  <div class="bord-qui">
    <h1>Tableau de bord</h1>
    <p><?= e((string) $me['nom']) ?> · <?= e(role_libelle((string) $me['role'])) ?></p>
  </div>

  <?php
  /**
   * La recherche traverse les familles, et part en GET.
   *
   * Un formulaire ordinaire : il marche sans script, il se met en favori
   * avec son terme, et le bouton « précédent » du navigateur y ramène.
   * La touche « / » n'est qu'un raccourci posé par-dessus.
   */
  ?>
  <form class="bord-chercher" method="get" action="<?= e(url('?p=recherche')) ?>" role="search">
    <input type="hidden" name="p" value="recherche">
    <label for="bord-q" class="sr-only">Chercher un décor, un compte, un article, un lien</label>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
         aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="16.5" y1="16.5" x2="21" y2="21"></line></svg>
    <input id="bord-q" type="search" name="q" value="<?= e((string) ($cherche ?? '')) ?>"
           placeholder="Chercher un décor, un compte, un article, un lien…"
           autocomplete="off" spellcheck="false">
    <kbd aria-hidden="true">/</kbd>
  </form>

  <?php if ($onglet === 'chiffres'): ?>
    <a class="bouton fant" href="<?= e(url('?p=rapports')) ?>">Le rapport du mois</a>
  <?php elseif ($nouveau): ?>
    <?php
    /* Un seul bouton plutôt que quatre : ce qu'on vient faire ici est
       rarement de créer, et quatre boutons de création se lisent comme un
       menu. Le `<details>` s'ouvre au clic ET au clavier, sans script. */
    ?>
    <details class="bord-nouveau deroulant">
      <summary>+ Nouveau
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
             stroke-linecap="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
      </summary>
      <div class="volet">
        <?php
        /* Une icone par intention, le même jeu que le reste du bord : le
           coup d'œil vise la bonne création avant même de lire le texte. */
        $icone_nouveau = [
            'decors_siens' => 'ticket',
            'articles' => 'affiche',
            'regie' => 'avion',
            'liens' => 'lien',
            'comptes' => 'user',
        ];
        foreach ($nouveau as [$ou, $quoi, $droit]): ?>
          <a href="<?= e(url($ou)) ?>"><?= (icone_guide($icone_nouveau[$droit] ?? 'ticket', 16) ?: icone($icone_nouveau[$droit] ?? 'ticket')) ?><?= e($quoi) ?></a>
        <?php endforeach; ?>
      </div>
    </details>
  <?php endif; ?>
</div>

<script>
/**
 * « / » amène au champ de recherche.
 *
 * Le raccourci ne vaut que s'il ne se déclenche JAMAIS pendant qu'on
 * écrit : taper « et/ou » dans un titre enverrait sinon le curseur
 * ailleurs au milieu du mot. On sort donc dès que le geste vient d'un
 * champ, d'une zone de texte ou d'un bloc éditable.
 */
(function () {
  var champ = document.getElementById('bord-q');
  if (!champ) { return; }
  document.addEventListener('keydown', function (e) {
    if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) { return; }
    var a = document.activeElement;
    if (a && (a.isContentEditable
        || /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName))) { return; }
    e.preventDefault();
    champ.focus();
    champ.select();
  });
})();
</script>
