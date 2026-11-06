import { useEffect, useRef, useState } from 'react';
import { Link, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import { ShellContext } from '../context/ShellContext';
import NotificationBell from './NotificationBell';
import { LanguageButton } from './LanguageToggle';

/**
 * The bar across the top of every flat owner / tenant screen: the building on the left; on the right the bell, the language
 * and the signed-in person's menu (notifications, password, log out). The screens below it do not repeat these.
 */
export default function OwnerFrame() {
  const { user, logout } = useAuth();
  const { t, lang } = useLanguage();
  const location = useLocation();
  const [menu, setMenu] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const L = (bn: string, en: string) => (lang === 'bn' ? bn : en);

  useEffect(() => setMenu(false), [location.pathname]);
  useEffect(() => {
    if (!menu) return;
    const away = (e: MouseEvent | TouchEvent) => ref.current && !ref.current.contains(e.target as Node) && setMenu(false);
    const esc = (e: KeyboardEvent) => e.key === 'Escape' && setMenu(false);
    document.addEventListener('mousedown', away);
    document.addEventListener('touchstart', away);
    document.addEventListener('keydown', esc);
    return () => {
      document.removeEventListener('mousedown', away);
      document.removeEventListener('touchstart', away);
      document.removeEventListener('keydown', esc);
    };
  }, [menu]);

  const roles = user?.roles.map((r) => r.replace(/_/g, ' ')).join(', ');
  const isStaff = !!user && user.roles.some((r) => r !== 'flat_owner' && r !== 'tenant');
  const item = 'flex min-h-11 w-full items-center gap-3 rounded-xl px-3 py-2 text-left hover:bg-brand-100';

  return (
    <div className="min-h-screen bg-brand-50">
      <header className="sticky top-0 z-30 flex items-center gap-2 border-b border-white/10 bg-brand-800 px-3 py-2 text-white shadow-sm">
        <Link to="/owner" className="flex min-w-0 items-center gap-2" aria-label={t('appName')}>
          <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-500 text-base">🌿</span>
          <span className="min-w-0 truncate text-base font-semibold">{t('appName')}</span>
        </Link>
        <div className="ml-auto flex shrink-0 items-center gap-2">
          <NotificationBell />
          <LanguageButton compact />
          <div className="relative" ref={ref}>
            <button
              onClick={() => setMenu((v) => !v)}
              aria-haspopup="menu"
              aria-expanded={menu}
              aria-label={L('আমার অ্যাকাউন্ট', 'My account')}
              className="flex h-10 items-center gap-2 rounded-full bg-white/10 pl-1 pr-1 text-sm hover:bg-white/20 sm:pr-3"
            >
              <span className="flex h-8 w-8 items-center justify-center rounded-full bg-brand-500 text-sm font-semibold text-white">{(user?.mobile ?? '?').slice(-2)}</span>
              <span className="hidden max-w-32 truncate sm:inline">{user?.mobile}</span>
            </button>
            {menu && (
              <div role="menu" className="absolute right-0 top-12 z-40 w-64 max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-2xl border border-brand-200 bg-white text-navy-900 shadow-xl">
                <div className="border-b border-brand-100 bg-brand-50 px-4 py-3">
                  <p className="truncate text-sm font-semibold">{user?.mobile}</p>
                  <p className="truncate text-xs capitalize text-navy-700">{roles}</p>
                </div>
                <div className="p-1.5 text-sm">
                  <Link role="menuitem" to="/owner/notifications" className={item}>
                    <span aria-hidden>🔔</span> {L('নোটিফিকেশন', 'Notifications')}
                  </Link>
                  {isStaff && (
                    <Link role="menuitem" to="/" className={item}>
                      <span aria-hidden>🗂️</span> {t('users.openOffice')}
                    </Link>
                  )}
                  <Link role="menuitem" to="/change-password" className={item}>
                    <span aria-hidden>🔒</span> {t('password.change')}
                  </Link>
                  <button role="menuitem" onClick={() => logout()} className={`${item} text-status-due hover:bg-status-due/10`}>
                    <span aria-hidden>⏻</span> {t('home.logout')}
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      </header>
      <ShellContext.Provider value={true}>
        <Outlet />
      </ShellContext.Provider>
    </div>
  );
}
