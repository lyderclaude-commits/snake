'use server';

import { redirect } from 'next/navigation';
import { createSession, destroySession, verifyPassword, type Role } from '@/server/auth';
import { createUser, findByEmail } from '@/server/repo/users';

export interface FormState {
  error?: string;
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

  const user = findByEmail(email);
  // Message volontairement identique dans les deux cas : distinguer
  // « e-mail inconnu » de « mot de passe faux » révélerait quels comptes existent.
  if (!user || !verifyPassword(password, user.password_hash)) {
    return { error: 'E-mail ou mot de passe incorrect.' };
  }
  if (user.suspended) return { error: 'Ce compte est suspendu. Contactez l’équipe Wakabi.' };

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

  await createSession(id);
  redirect(wantsPartner ? '/partenaire' : '/compte');
}

export async function signOut() {
  await destroySession();
  redirect('/');
}
