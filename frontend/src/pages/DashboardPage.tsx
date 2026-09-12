import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as reportsApi from '../api/reports';
import type { DashboardKpis } from '../api/reports';

const navItems: { to: string; key: any }[] = [
  { to: '/flats', key: 'home.flatManagement' },
  { to: '/staff', key: 'staff.title' },
  { to: '/billing', key: 'nav.billing' },
  { to: '/gas', key: 'nav.gas' },
  { to: '/payments', key: 'nav.payments' },
  { to: '/bank-accounts', key: 'bank.title' },
  { to: '/expenses', key: 'nav.expenses' },
  { to: '/construction-materials', key: 'nav.materials' },
  { to: '/dues', key: 'nav.dues' },
  { to: '/assets', key: 'nav.assets' },
  { to: '/meetings', key: 'nav.meetings' },
  { to: '/complaints', key: 'nav.complaints' },
  { to: '/notices', key: 'nav.notices' },
  { to: '/reports', key: 'nav.reports' },
];

export default function DashboardPage() {
  const { user, logout } = useAuth();
  const { t } = useLanguage();
  const [kpis, setKpis] = useState<DashboardKpis | null>(null);

  useEffect(() => {
    const now = new Date();
    reportsApi.getDashboard(now.getFullYear(), now.getMonth() + 1).then(setKpis).catch(() => {});
  }, []);

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
          {navItems.map((item) => (
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
