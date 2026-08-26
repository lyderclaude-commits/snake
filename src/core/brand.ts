/**
 * Constantes de marque — sans dépendance.
 *
 * Elles vivent à part du schéma pour une raison mesurable : `renderScene`
 * n'a besoin que de la signature, et l'importer depuis `template.schema`
 * embarquait zod entier dans le paquet du navigateur — une cinquantaine de
 * kilo-octets envoyés à un téléphone qui n'en fera rien.
 */

/** Signature officielle, incrustée dans le filigrane de chaque visuel. */
export const WAKABI_TAGLINE = 'LE GUIDE DES BONS PLANS';

/**
 * Domaines vers lesquels un décor de partenaire peut rediriger.
 * Le garde-fou qui empêche un décor de devenir une passerelle vers n'importe quoi.
 */
export const WAKABI_REDIRECT_HOSTS = [
  'wakabileguide.com',
  'studio.wakabileguide.com',
] as const;
