import { useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import { NAV_GROUPS, canOpen, type NavItem } from '../config/nav';

const KEY = 'gv.sidebar.collapsed';
const read = () => {
  try {
    return localStorage.getItem(KEY) === '1';
  } catch {
    return false;
  }
};

/**
 * The frame around every office screen: a menu down the left side on a computer (it can fold down to icons), and
 * a menu button that opens the same list as a drawer on a phone. Each person only sees the screens they may open.
 */
export default function AppShell() {
  const { user, logout } = useAuth();
  const { t } = useLanguage();
  const location = useLocation();
  const [collapsed, setCollapsed] = useState(read);
  const [open, setOpen] = useState(false); // the phone drawer

  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));
  const groups = NAV_GROUPS.map((g) => ({ ...g, items: g.items.filter((i) => canOpen(i, can)) })).filter((g) => g.items.length > 0);
  const isResident = !!user && user.roles.some((r) => r === 'flat_owner' || r === 'tenant');

  // the drawer closes whenever you go somewhere
  useEffect(() => setOpen(false), [location.pathname, location.search]);
  useEffect(() => {
    try {
      localStorage.setItem(KEY, collapsed ? '1' : '0');
    } catch {
      /* private window: the choice just is not remembered */
    }
  }, [collapsed]);

  // /statements?treasury=1 and /statements are two entries for one page: tell them apart by the query
  const isActive = (item: NavItem) => {
    const [path, query] = item.to.split('?');
    if (location.pathname !== path && !(path !== '/' && location.pathname.startsWith(`${path}/`))) return false;
    return query ? location.search.includes(query) : !location.search.includes('treasury=1') || path !== '/statements';
  };

  const link = (item: NavItem, compact: boolean) => (
    <Link
      key={item.to}
      to={item.to}
      title={compact ? t(item.key) : undefined}
      className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition ${
        isActive(item) ? 'bg-brand-600 font-medium text-white shadow-sm' : 'text-brand-100 hover:bg-white/10'
      } ${compact ? 'justify-center px-2' : ''}`}
    >
      <span className="text-lg leading-none">{item.icon}</span>
      {!compact && <span className="truncate">{t(item.key)}</span>}
    </Link>
  );

  const menu = (compact: boolean) => (
    <nav className="flex-1 overflow-y-auto px-2 pb-4">
      <NavLink
        to="/"
        end
        title={compact ? t('nav.dashboard') : undefined}
        className={({ isActive: a }) => `mb-2 flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition ${a ? 'bg-brand-600 font-medium text-white shadow-sm' : 'text-brand-100 hover:bg-white/10'} ${compact ? 'justify-center px-2' : ''}`}
      >
        <span className="text-lg leading-none">🏠</span>
        {!compact && <span>{t('nav.dashboard')}</span>}
      </NavLink>
      {groups.map((g) => (
        <div key={g.key} className="mt-3">
          {!compact ? <p className="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wide text-brand-300">{t(g.key)}</p> : <div className="mx-2 mb-1 border-t border-white/10" />}
          <div className="space-y-0.5">{g.items.map((i) => link(i, compact))}</div>
        </div>
      ))}
    </nav>
  );

  const footer = (compact: boolean) => (
    <div className="border-t border-white/10 p-2 text-sm text-brand-100">
      {isResident && (
        <Link to="/owner" title={t('users.openPortal')} className={`mb-1 flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-white/10 ${compact ? 'justify-center px-2' : ''}`}>
          <span className="text-lg leading-none">👤</span>
          {!compact && <span>{t('users.openPortal')}</span>}
        </Link>
      )}
      <Link to="/change-password" title={t('password.change')} className={`flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-white/10 ${compact ? 'justify-center px-2' : ''}`}>
        <span className="text-lg leading-none">🔒</span>
        {!compact && <span>{t('password.change')}</span>}
      </Link>
      <button onClick={() => logout()} title={t('home.logout')} className={`flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left hover:bg-white/10 ${compact ? 'justify-center px-2' : ''}`}>
        <span className="text-lg leading-none">⏻</span>
        {!compact && <span>{t('home.logout')}</span>}
      </button>
      {!compact && user && <p className="mt-1 truncate px-3 text-[11px] text-brand-300">{user.mobile}</p>}
    </div>
  );

  const brand = (compact: boolean) => (
    <Link to="/" className={`flex items-center gap-2 px-4 py-4 ${compact ? 'justify-center px-2' : ''}`}>
      <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500 text-lg">🌿</span>
      {!compact && <span className="text-base font-semibold text-white">{t('appName')}</span>}
    </Link>
  );

  return (
    <div className="min-h-screen bg-brand-50">
      {/* computer: fixed menu on the left */}
      <aside className={`fixed inset-y-0 left-0 z-30 hidden flex-col bg-gradient-to-b from-brand-800 to-brand-900 transition-[width] lg:flex ${collapsed ? 'w-16' : 'w-60'}`}>
        {brand(collapsed)}
        {menu(collapsed)}
        {footer(collapsed)}
        <button onClick={() => setCollapsed(!collapsed)} aria-label={collapsed ? 'expand menu' : 'collapse menu'} className="absolute -right-3 top-5 flex h-6 w-6 items-center justify-center rounded-full border border-brand-200 bg-white text-xs text-navy-700 shadow hover:bg-brand-100">
          {collapsed ? '›' : '‹'}
        </button>
      </aside>

      {/* phone: a bar with the menu button, and the drawer */}
      <div className="sticky top-0 z-30 flex items-center gap-3 bg-brand-800 px-3 py-2 text-white lg:hidden">
        <button onClick={() => setOpen(true)} aria-label="open menu" className="flex h-10 w-10 items-center justify-center rounded-lg text-2xl hover:bg-white/10">
          ☰
        </button>
        <Link to="/" className="text-base font-semibold">
          {t('appName')}
        </Link>
      </div>
      {open && (
        <div className="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true">
          <div className="absolute inset-0 bg-navy-900/50" onClick={() => setOpen(false)} />
          <aside className="absolute inset-y-0 left-0 flex w-72 max-w-[85%] flex-col bg-gradient-to-b from-brand-800 to-brand-900 shadow-xl">
            <div className="flex items-center justify-between pr-2">
              {brand(false)}
              <button onClick={() => setOpen(false)} aria-label="close menu" className="rounded-lg px-3 py-2 text-xl text-brand-100 hover:bg-white/10">
                ✕
              </button>
            </div>
            {menu(false)}
            {footer(false)}
          </aside>
        </div>
      )}

      <main className={`transition-[padding] ${collapsed ? 'lg:pl-16' : 'lg:pl-60'}`}>
        <Outlet />
      </main>
    </div>
  );
}
