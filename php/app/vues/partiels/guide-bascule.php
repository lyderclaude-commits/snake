<?php
/**
 * La bascule liste / grille.
 *
 * Deux puces collées, celle de la vue courante en bleu. La vue voyage
 * dans l'adresse comme les autres filtres : une grille est une adresse
 * qu'on peut coller.
 *
 * Avec JavaScript, le clic permute les deux vues SUR PLACE, sans
 * recharger : les deux sont rendues par le serveur dans la zone
 * `.guide-zone` voisine, l'une cachée, et `guide.js` les échange en
 * mettant l'adresse à jour. Sans JavaScript, les liens rechargent la
 * page comme avant — rien ne casse.
 *
 * Variables attendues :
 *   $bascule_page   la page ('agenda', 'lieux', 'sortir-lome'...)
 *   $bascule_cle    le nom du paramètre ('vue', 'evts', 'lieux')
 *   $bascule_vue    la vue courante ('liste' ou 'grille')
 *   $bascule_params les autres filtres à préserver
 */
$bascule_cle = $bascule_cle ?? 'vue';
$bascule_params = $bascule_params ?? [];
?>
<div class="guide-bascule" role="group" aria-label="Changer de vue" data-cle="<?= e($bascule_cle) ?>">
  <a class="<?= $bascule_vue === 'liste' ? 'actif' : '' ?>"
     data-vue="liste"
     href="<?= e(api_url_filtres($bascule_page, array_merge($bascule_params, [$bascule_cle => 'liste']))) ?>"
     title="Vue liste" aria-label="Vue liste">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"></line><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="18" x2="20" y2="18"></line></svg>
    Liste
  </a>
  <a class="<?= $bascule_vue === 'grille' ? 'actif' : '' ?>"
     data-vue="grille"
     href="<?= e(api_url_filtres($bascule_page, array_merge($bascule_params, [$bascule_cle => 'grille']))) ?>"
     title="Vue grille" aria-label="Vue grille">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="4" y="4" width="7" height="7" rx="1.5"></rect><rect x="13" y="4" width="7" height="7" rx="1.5"></rect><rect x="4" y="13" width="7" height="7" rx="1.5"></rect><rect x="13" y="13" width="7" height="7" rx="1.5"></rect></svg>
    Grille
  </a>
</div>
