import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as reportsApi from '../api/reports';
import type { DashboardKpis } from '../api/reports';

// `perm` is the permission that opens the screen (the API enforces it too); super_admin sees every tile.
const navItems: { to: string; key: any; perm: string }[] = [
  { to: '/flats', key: 'home.flatManagement', perm: 'flat.view' },
  { to: '/staff', key: 'staff.title', perm: 'staff.view' },
  { to: '/billing', key: 'nav.billing', perm: 'billing.view' },
  { to: '/gas', key: 'nav.gas', perm: 'gas.view' },
  { to: '/payments', key: 'nav.payments', perm: 'payment.view' },
  { to: '/bank-accounts', key: 'bank.title', perm: 'accounting.view' },
  { to: '/expenses', key: 'nav.expenses', perm: 'expense.view' },
  { to: '/construction-materials', key: 'nav.materials', perm: 'expense.view' },
  { to: '/suppliers', key: 'nav.suppliers', perm: 'expense.view' },
  { to: '/contributions', key: 'nav.contrib', perm: 'contribution.view' },
  { to: '/dues', key: 'nav.dues', perm: 'due.view' },
  { to: '/assets', key: 'nav.assets', perm: 'asset.view' },
  { to: '/hall', key: 'nav.hall', perm: 'hall.view' },
  { to: '/parking', key: 'nav.parking', perm: 'parking.view' },
  { to: '/meetings', key: 'nav.meetings', perm: 'meeting.view' },
  { to: '/complaints', key: 'nav.complaints', perm: 'complaint.view' },
  { to: '/notices', key: 'nav.notices', perm: 'notice.view' },
  { to: '/statements', key: 'nav.statements', perm: 'accounting.view' },
  { to: '/reports', key: 'nav.reports', perm: 'accounting.view' },
  { to: '/users', key: 'nav.users', perm: 'user.view' },
];

export default function DashboardPage() {
  const { user, logout } = useAuth();
  const { t } = useLanguage();
  const [kpis, setKpis] = useState<DashboardKpis | null>(null);
  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));
  const visibleItems = navItems.filter((item) => can(item.perm));
  // a committee member is usually also a flat owner: let them hop to their own flat's portal
  const isResident = !!user && user.roles.some((r) => r === 'flat_owner' || r === 'tenant');

  useEffect(() => {
    if (!can('accounting.view')) return; // the KPI numbers are accounting data
    const now = new Date();
    reportsApi.getDashboard(now.getFullYear(), now.getMonth() + 1).then(setKpis).catch(() => {});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user]);

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-4xl">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <h1 className="text-xl font-semibold text-navy-900">{t('appName')}</h1>
            <p className="text-sm text-navy-700">{user?.mobile}</p>
          </div>
          <div className="flex items-center gap-2">
            <LanguageToggle />
            {isResident && (
              <Link to="/owner" className="rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-navy-700 transition hover:bg-brand-100">
                {t('users.openPortal')}
              </Link>
            )}
            <Link
              to="/change-password"
              className="rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-navy-700 transition hover:bg-brand-100"
            >
              {t('password.change')}
            </Link>
            <button
              onClick={() => logout()}
              className="rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-navy-700 transition hover:bg-brand-100"
            >
              {t('home.logout')}
            </button>
          </div>
        </div>

        {kpis && (
          <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
            <KpiCard label={t('kpi.collection')} value={`৳${kpis.collection.toLocaleString('en-BD')}`} />
            <KpiCard label={t('kpi.outstandingDue')} value={`৳${kpis.outstanding_due.toLocaleString('en-BD')}`} accent="due" />
            <KpiCard label={t('kpi.expense')} value={`৳${kpis.expense.toLocaleString('en-BD')}`} />
            <KpiCard label={t('kpi.sinkingFund')} value={`৳${kpis.sinking_fund_balance.toLocaleString('en-BD')}`} accent="paid" />
            <KpiCard label={t('kpi.gasConsumers')} value={String(kpis.gas_consumer_count)} />
          </div>
        )}

        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          {visibleItems.map((item) => (
            <Link
              key={item.to}
              to={item.to}
              className="rounded-xl border border-brand-200 bg-white px-4 py-4 text-center font-medium text-navy-900 shadow-sm transition hover:bg-brand-100"
            >
              {t(item.key)}
            </Link>
          ))}
        </div>
      </div>
    </div>
  );
}

function KpiCard({ label, value, accent }: { label: string; value: string; accent?: 'due' | 'paid' }) {
  const color = accent === 'due' ? 'text-status-due' : accent === 'paid' ? 'text-status-paid' : 'text-navy-900';
  return (
    <div className="rounded-xl border border-brand-200 bg-white p-3 shadow-sm">
      <p className="text-xs text-navy-700">{label}</p>
      <p className={`mt-1 text-lg font-semibold ${color}`}>{value}</p>
    </div>
  );
}
