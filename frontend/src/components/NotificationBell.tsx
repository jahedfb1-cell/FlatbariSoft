import { useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import { listNotifications } from '../api/notifications';

/** The bell next to the language switch: a red count of unread notifications, a link to the full list. Checks again every minute. */
export default function NotificationBell() {
  const { user } = useAuth();
  const { lang } = useLanguage();
  const location = useLocation();
  const [unread, setUnread] = useState(0);

  useEffect(() => {
    if (!user) return;
    let alive = true;
    const check = () => listNotifications().then((d) => alive && setUnread(d.unread)).catch(() => undefined);
    check();
    const timer = window.setInterval(check, 60000);
    return () => {
      alive = false;
      window.clearInterval(timer);
    };
  }, [user, location.pathname]);

  if (!user) return null;
  // a resident has only the owner screens; everyone else reads them in the office frame
  const residentOnly = user.roles.length > 0 && user.roles.every((r) => r === 'flat_owner' || r === 'tenant');
  const to = residentOnly || location.pathname.startsWith('/owner') ? '/owner/notifications' : '/notifications';

  return (
    <Link to={to} aria-label={lang === 'bn' ? 'নোটিফিকেশন' : 'Notifications'} title={lang === 'bn' ? 'নোটিফিকেশন' : 'Notifications'} className="relative inline-flex h-9 w-9 items-center justify-center rounded-lg border border-brand-200 bg-white text-lg hover:bg-brand-100">
      <span aria-hidden>🔔</span>
      {unread > 0 && (
        <span className="absolute -right-1.5 -top-1.5 min-w-[1.25rem] rounded-full bg-status-due px-1 text-center text-[11px] font-semibold leading-5 text-white">{unread > 99 ? '99+' : unread}</span>
      )}
    </Link>
  );
}
