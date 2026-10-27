import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';

const items = [
  { to: '/owner', key: 'owner.home', icon: '🏠' },
  { to: '/owner/bills', key: 'owner.bills', icon: '🧾' },
  { to: '/owner/notices', key: 'owner.notices', icon: '📢' },
  { to: '/owner/hall', key: 'owner.hall', icon: '🏛️' },
  { to: '/owner/complaints', key: 'owner.complaints', icon: '🛠️' },
  { to: '/owner/documents', key: 'owner.myInfo', icon: '📄' },
] as const;

export default function OwnerNav() {
  const { t } = useLanguage();
  const { user } = useAuth();
  const location = useLocation();
  // committee members / the manager can also be flat owners: give them a way back to the office screens
  const isStaff = !!user && user.roles.some((r) => r !== 'flat_owner' && r !== 'tenant');

  return (
    <nav className="fixed inset-x-0 bottom-0 z-40 flex border-t border-brand-200 bg-white">
      {items.map((item) => {
        const active = location.pathname === item.to;
        return (
          <Link
            key={item.to}
            to={item.to}
            className={`flex flex-1 flex-col items-center gap-0.5 py-2 text-xs ${
              active ? 'text-brand-700' : 'text-navy-700'
            }`}
          >
            <span className="text-lg">{item.icon}</span>
            {t(item.key)}
          </Link>
        );
      })}
      {isStaff && (
        <Link to="/" className="flex flex-1 flex-col items-center gap-0.5 py-2 text-xs text-navy-700">
          <span className="text-lg">🗂️</span>
          {t('users.openOffice')}
        </Link>
      )}
    </nav>
  );
}
