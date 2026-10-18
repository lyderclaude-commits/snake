<?php
/**
 * Le gabarit du site public. La barre et le pied sont ceux que `cms.js`
 * fabriquait dans le navigateur — rendus ici, ils existent même sans
 * JavaScript, et un moteur de recherche voit enfin les liens du site.
 */
$_titre = $titre ?? reglage('nom_site');
$_desc = trim((string) ($description ?? '')) ?: reglage('meta_description');
$_canon = $canonique ?? url_absolue(chemin_demande());
$_image = absolu($og_image ?? url('assets/images/site/background.jpg'));
$_actif = $page ?? '';
$_menu = [
    'services' => 'Services',
    'campagnes' => 'Campagnes',
    'blog' => 'Blog',
    'a-propos' => 'À Propos',
    'contact' => 'Contact',
];
$_graphe = array_values(array_filter([jsonld_organisation(), $jsonld ?? null]));
?><!DOCTYPE html>
<html lang="fr" class="no-js">
<head>
<meta charset="UTF-8"/>
<script>document.documentElement.className = "js";</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= e($_titre) ?></title>
<meta name="description" content="<?= e($_desc) ?>"/>
<link rel="canonical" href="<?= e($_canon) ?>"/>
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex, follow"/><?php endif; ?>
<meta property="og:site_name" content="<?= e(reglage('nom_site')) ?>"/>
<meta property="og:locale" content="fr_FR"/>
<meta property="og:type" content="<?= e($og_type ?? 'website') ?>"/>
<meta property="og:title" content="<?= e($og_titre ?? $_titre) ?>"/>
<meta property="og:description" content="<?= e($_desc) ?>"/>
<meta property="og:url" content="<?= e($_canon) ?>"/>
<meta property="og:image" content="<?= e($_image) ?>"/>
<meta name="twitter:card" content="summary_large_image"/>
<?php if (reglage('verif_google') !== ''): ?><meta name="google-site-verification" content="<?= e(reglage('verif_google')) ?>"/><?php endif; ?>
<meta name="theme-color" content="#000000"/>
<link rel="icon" href="<?= e(actif('assets/images/favicon.svg')) ?>" type="image/svg+xml"/>
<link rel="alternate" type="application/rss+xml" title="<?= e(reglage('nom_site')) ?> — Blog" href="<?= e(url('blog/flux.xml')) ?>"/>
<link rel="preload" href="<?= e(url('assets/polices/bebas-neue-latin-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin/>
<link rel="stylesheet" href="<?= e(actif('assets/css/site.css')) ?>"/>
<script type="application/ld+json"><?= jsonld(['@context' => 'https://schema.org', '@graph' => $_graphe]) ?></script>
<?= $tete ?>
</head>
<body>
<nav class="site-nav" id="siteNav">
  <div class="nav-inner">
    <a href="<?= url('') ?>" class="nav-logo" aria-label="<?= e(reglage('nom_site')) ?>, accueil">
      LIVE<span class="nav-logo-accent">Y</span>DREAM
    </a>
    <button class="nav-toggle" id="navToggle" type="button" aria-label="Menu" aria-controls="navMenu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
    <ul class="nav-menu" id="navMenu">
      <?php foreach ($_menu as $_r => $_l): ?>
        <li><a href="<?= url($_r) ?>"<?= $_actif === $_r || ($_r === 'blog' && $_actif === 'article') ? ' class="active" aria-current="page"' : '' ?>><?= e($_l) ?></a></li>
      <?php endforeach; ?>
      <li><a href="<?= e(wa_lien()) ?>" class="nav-cta" target="_blank" rel="noopener">Devis Gratuit</a></li>
    </ul>
  </div>
</nav>

<main id="contenu">
<?= $contenu ?>
</main>

<footer class="site-footer">
  <div class="footer-top">
    <div class="footer-brand">
      <div class="nav-logo" style="color:#fff">
        <div class="nav-logo-dot" style="background:var(--gold)"></div>
        LIVE<span style="color:var(--gold)">Y</span>DREAM
      </div>
      <p>L'agence de communication qui transforme vos ambitions en résultats concrets. Depuis 2013, au service des meilleures marques d'Afrique de l'Ouest.</p>
      <div class="footer-socials">
        <?php if (reglage('facebook') !== ''): ?>
        <a href="<?= e(reglage('facebook')) ?>" class="footer-social" target="_blank" rel="noopener" aria-label="Facebook">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/></svg>
        </a>
        <?php endif; ?>
        <?php if (reglage('linkedin') !== ''): ?>
        <a href="<?= e(reglage('linkedin')) ?>" class="footer-social" target="_blank" rel="noopener" aria-label="LinkedIn">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.063 2.063 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
        </a>
        <?php endif; ?>
        <?php if (reglage('instagram') !== ''): ?>
        <a href="<?= e(reglage('instagram')) ?>" class="footer-social" target="_blank" rel="noopener" aria-label="Instagram">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919C8.416 2.175 8.796 2.163 12 2.163zm0-2.163C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
        </a>
        <?php endif; ?>
        <?php if (reglage('tiktok') !== ''): ?>
        <a href="<?= e(reglage('tiktok')) ?>" class="footer-social" target="_blank" rel="noopener" aria-label="TikTok">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005.8 20.1a6.34 6.34 0 0010.86-4.43V8.78a8.16 8.16 0 004.77 1.52V6.87a4.79 4.79 0 01-1.84-.18z"/></svg>
        </a>
        <?php endif; ?>
      </div>
    </div>
    <div class="footer-col">
      <h5>Services</h5>
      <ul>
        <li><a href="<?= url('services#community') ?>">Community Management</a></li>
        <li><a href="<?= url('services#video') ?>">Production Vidéo & Photo</a></li>
        <li><a href="<?= url('services#graphisme') ?>">Conception Graphique</a></li>
        <li><a href="<?= url('services#evenement') ?>">Événementiel</a></li>
        <li><a href="<?= url('services#regie') ?>">Régie Publicitaire</a></li>
        <li><a href="<?= url('services#abonnement') ?>">Abonnement PME</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h5>Agence</h5>
      <ul>
        <li><a href="<?= url('a-propos') ?>">Notre histoire</a></li>
        <li><a href="<?= url('a-propos#equipe') ?>">Notre équipe</a></li>
        <li><a href="<?= url('campagnes') ?>">Nos réalisations</a></li>
        <li><a href="<?= url('blog') ?>">Blog & Ressources</a></li>
        <li><a href="<?= url('contact') ?>">Rejoindre LYD</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h5>Contact</h5>
      <ul>
        <li><a href="<?= e(tel_lien(reglage('telephone'))) ?>"><?= e(reglage('telephone')) ?></a></li>
        <li><a href="mailto:<?= e(reglage('email')) ?>"><?= e(reglage('email')) ?></a></li>
        <li><a href="<?= e(wa_lien()) ?>" target="_blank" rel="noopener">WhatsApp Direct</a></li>
        <li><a href="<?= url('contact') ?>">Demander un devis</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <span>© <?= date('Y') ?> <?= e(reglage('nom_site')) ?> — Tous droits réservés — Lomé, Togo</span>
    <div style="display:flex;gap:20px">
      <a href="<?= url('mentions-legales') ?>">Mentions légales</a>
      <a href="<?= url('confidentialite') ?>">Confidentialité</a>
    </div>
  </div>
</footer>
<a href="<?= e(wa_lien('Bonjour Live Y Dream, je souhaite un devis.')) ?>" class="wa-float" target="_blank" rel="noopener" aria-label="WhatsApp">
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
</a>
<script src="<?= e(actif('assets/js/site.js')) ?>" defer></script>
</body>
</html>
