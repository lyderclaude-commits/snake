'use server';

import { revalidatePath } from 'next/cache';
import { requireRole, type Role } from '@/server/auth';
import { setRole, setSuspended } from '@/server/repo/users';

export async function changeRole(form: FormData) {
  const me = await requireRole('admin');
  const id = String(form.get('id') ?? '');
  const role = String(form.get('role') ?? '') as Role;
  // Un administrateur ne peut pas se rétrograder lui-même : cela pourrait
  // laisser l'installation sans aucun administrateur.
  if (id === me.id) return;
  if (!['user', 'partner', 'admin'].includes(role)) return;
  setRole(id, role);
  revalidatePath('/admin/comptes');
}

export async function toggleSuspend(form: FormData) {
  const me = await requireRole('admin');
  const id = String(form.get('id') ?? '');
  if (id === me.id) return;
  setSuspended(id, String(form.get('suspended') ?? '') === '1');
  revalidatePath('/admin/comptes');
}
