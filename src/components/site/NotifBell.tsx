import Link from 'next/link';
import { listFor, unreadCount, markAllRead } from '@/server/repo/notifications';

/**
 * Notifications dans l'application.
 *
 * Ouvrir le panneau marque tout comme lu : c'est le geste qui dit « j'ai vu ».
 * L'e-mail viendra quand un SMTP sera configuré (docs/07-BACKEND.md §6).
 */
export async function NotifBell({ userId }: { userId: string }) {
  const items = listFor(userId, 8);
  const unread = unreadCount(userId);

  async function readAll() {
    'use server';
    markAllRead(userId);
  }

  return (
    <details className="relative">
      <summary className="flex cursor-pointer list-none items-center gap-1.5 rounded-wk-sm border border-wk-border bg-white px-3 py-1.5 text-[12.5px] font-semibold text-wk-text2 transition hover:border-wk-border2 [&::-webkit-details-marker]:hidden">
        Notifications
        {unread > 0 && (
          <span className="grid size-[18px] place-items-center rounded-full bg-wk-red text-[10.5px] font-bold text-white">
            {unread}
          </span>
        )}
      </summary>

      <div className="absolute right-0 z-50 mt-2 w-[320px] rounded-wk-lg border border-wk-border bg-white p-2 shadow-wk-lg">
        {items.length === 0 ? (
          <p className="px-3 py-4 text-center text-[13px] text-wk-text3">Rien de neuf.</p>
        ) : (
          <>
            <ul className="max-h-80 overflow-y-auto">
              {items.map((n) => (
                <li key={n.id}>
                  <Link
                    href={n.href ?? '#'}
                    className={`block rounded-wk-sm px-3 py-2.5 transition hover:bg-wk-bg2 ${
                      n.read_at ? '' : 'bg-wk-primary-l'
                    }`}
                  >
                    <b className="block text-[13px] font-bold leading-snug">{n.title}</b>
                    {n.body && (
                      <span className="mt-0.5 block text-[12.5px] leading-snug text-wk-text2">
                        {n.body}
                      </span>
                    )}
                    <span className="mt-1 block text-[11px] text-wk-text3">
                      {new Date(n.created_at).toLocaleString('fr-FR', {
                        dateStyle: 'short',
                        timeStyle: 'short',
                      })}
                    </span>
                  </Link>
                </li>
              ))}
            </ul>
            {unread > 0 && (
              <form action={readAll} className="border-t border-wk-border pt-2">
                <button
                  type="submit"
                  className="w-full rounded-wk-sm px-3 py-1.5 text-[12.5px] font-semibold text-wk-primary transition hover:bg-wk-primary-l"
                >
                  Tout marquer comme lu
                </button>
              </form>
            )}
          </>
        )}
      </div>
    </details>
  );
}
