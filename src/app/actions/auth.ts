'use server';

import { redirect } from 'next/navigation';
import { createSession, destroySession, verifyPassword, type Role } from '@/server/auth';
import { createUser, findByEmail } from '@/server/repo/users';
import { clearAttempts, recordAttempt, tooManyAttempts } from '@/server/rateLimit';
import { issueToken } from '@/server/repo/email';
import { push } from '@/server/repo/notifications';

export interface FormState {
  error?: string;
  /** Lien de vérification, faute de SMTP configuré. Voir docs/07-BACKEND.md. */
  verifyUrl?: string;
}

const HOME: Record<Role, string> = {
  user: '/compte',
  partner: '/partenaire',
  admin: '/admin',
};

export async function signIn(_prev: FormState, form: FormData): Promise<FormState> {
  const email = String(form.get('email') ?? '').trim();
  const password = String(form.get('password') ?? '');

  if (!email || !password) return { error: 'Renseignez votre e-mail et votre mot de passe.' };

  // Le blocage précède la vérification du mot de passe : le contraire
  // laisserait un attaquant confirmer un mot de passe malgré la limite.
  const limit = tooManyAttempts(email);
  if (limit.blocked) {
    return {
      error: `Trop de tentatives. Réessayez dans ${limit.retryInMin} minute${limit.retryInMin > 1 ? 's' : ''}.`,
    };
  }

  const user = findByEmail(email);
  // Message volontairement identique dans les deux cas : distinguer
  // « e-mail inconnu » de « mot de passe faux » révélerait quels comptes existent.
  if (!user || !verifyPassword(password, user.password_hash)) {
    recordAttempt(email);
    return { error: 'E-mail ou mot de passe incorrect.' };
  }
  if (user.suspended) return { error: 'Ce compte est suspendu. Contactez l’équipe Wakabi.' };

  clearAttempts(email);
  await createSession(user.id);
  redirect(HOME[user.role]);
}

export async function signUp(_prev: FormState, form: FormData): Promise<FormState> {
  const email = String(form.get('email') ?? '').trim().toLowerCase();
  const password = String(form.get('password') ?? '');
  const name = String(form.get('name') ?? '').trim();
  const wantsPartner = String(form.get('role') ?? '') === 'partner';
  const organisation = String(form.get('organisation') ?? '').trim();
  const city = String(form.get('city') ?? '').trim();

  if (!name) return { error: 'Indiquez votre nom.' };
  if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) return { error: 'Cet e-mail n’est pas valide.' };
  if (password.length < 8) return { error: 'Le mot de passe doit faire au moins 8 caractères.' };
  if (wantsPartner && !organisation) return { error: 'Indiquez le nom de votre structure.' };
  if (findByEmail(email)) return { error: 'Un compte existe déjà avec cet e-mail.' };

  // Le rôle « admin » ne s'obtient jamais par le formulaire : il est attribué
  // depuis l'administration.
  const id = createUser({
    email,
    password,
    name,
    role: wantsPartner ? 'partner' : 'user',
    organisation: organisation || null,
    city: city || null,
  });

  // Le flux de vérification est complet ; seul le transport manque.
  const token = issueToken(id);
  push(
    id,
    'welcome',
    'Bienvenue sur Wakabi Boost',
    wantsPartner
      ? 'Créez votre premier décor, puis soumettez-le à la relecture. Réponse sous 24 h ouvrées.'
      : 'Choisissez un décor, ajoutez votre photo et partagez.',
    wantsPartner ? '/partenaire/nouveau' : '/decors',
  );
  console.info(`[wakabi] lien de vérification pour ${email} : /verifier/${token}`);

  await createSession(id);
  redirect(`${wantsPartner ? '/partenaire' : '/compte'}?verifier=${token}`);
}

export async function signOut() {
  await destroySession();
  redirect('/');
}
