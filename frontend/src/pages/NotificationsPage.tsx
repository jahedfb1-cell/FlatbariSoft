import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import OwnerNav from '../components/OwnerNav';
import { listNotifications, markAllNotificationsRead, markNotificationRead, type AppNotification } from '../api/notifications';

const when = (iso: string | null, bn: boolean) =>
  iso ? new Date(iso).toLocaleString(bn ? 'bn-BD' : 'en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Dhaka' }) : '';

/** Everything the system has told this person, newest first. Opening one marks it read and takes them to the screen it is about. */
export default function NotificationsPage({ owner = false }: { owner?: boolean }) {
  const { lang } = useLanguage();
  const bn = lang === 'bn';
  const navigate = useNavigate();
  const [items, setItems] = useState<AppNotification[] | null>(null);
  const [unread, setUnread] = useState(0);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    listNotifications()
      .then((d) => {
        setItems(d.items);
        setUnread(d.unread);
      })
      .catch(() => setError(bn ? 'নোটিফিকেশন আনা যায়নি।' : 'Could not load notifications.'));
  }, [bn]);

  useEffect(load, [load]);

  async function open(n: AppNotification) {
    if (!n.read) await markNotificationRead(n.id).catch(() => undefined);
    if (n.link) navigate(n.link);
    else load();
  }

  async function readAll() {
    await markAllNotificationsRead().catch(() => undefined);
    load();
  }

  return (
    <div className={`min-h-screen bg-brand-50 px-4 pt-8 ${owner ? 'pb-20' : 'pb-10'}`}>
      <div className={`mx-auto ${owner ? 'max-w-md' : 'max-w-2xl'}`}>
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <div>
            {!owner && (
              <Link to="/" className="text-sm text-navy-700 hover:underline">
                ← {bn ? 'ড্যাশবোর্ড' : 'Dashboard'}
              </Link>
            )}
            <h1 className="text-xl font-semibold text-navy-900">🔔 {bn ? 'নোটিফিকেশন' : 'Notifications'}</h1>
          </div>
          <LanguageToggle />
        </div>

        {unread > 0 && (
          <div className="mb-3 flex items-center justify-between rounded-lg bg-brand-100 px-3 py-2 text-sm text-navy-900">
            <span>{bn ? `${unread}টি না-পড়া` : `${unread} unread`}</span>
            <button onClick={readAll} className="text-brand-700 hover:underline">
              {bn ? 'সবগুলো পড়া হয়েছে' : 'Mark all as read'}
            </button>
          </div>
        )}

        {error && <p className="mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}

        <div className="space-y-2">
          {items?.map((n) => (
            <button
              key={n.id}
              onClick={() => open(n)}
              className={`block w-full rounded-xl border p-4 text-left shadow-sm transition hover:bg-brand-100 ${n.read ? 'border-brand-200 bg-white' : 'border-brand-400 bg-brand-50'}`}
            >
              <div className="flex items-start gap-2">
                {!n.read && <span className="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-status-due" aria-label={bn ? 'না-পড়া' : 'unread'} />}
                <div className="min-w-0 flex-1">
                  <p className={`text-navy-900 ${n.read ? '' : 'font-semibold'}`}>🧾 {n.title}</p>
                  <p className="mt-1 text-sm text-navy-700">{n.body}</p>
                  <p className="mt-1 text-xs text-navy-500">{when(n.created_at, bn)}</p>
                </div>
              </div>
            </button>
          ))}
          {items && items.length === 0 && <p className="rounded-xl border border-brand-200 bg-white p-6 text-center text-sm text-navy-700">{bn ? 'এখনও কোনো নোটিফিকেশন নেই।' : 'No notifications yet.'}</p>}
        </div>
      </div>
      {owner && <OwnerNav />}
    </div>
  );
}
