import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import TenantVacatePanel from '../components/TenantVacatePanel';
import TenantRequestPanel from '../components/TenantRequestPanel';
import { listFlats } from '../api/flats';

/** The office side: the building manager (or admin) sends a request; an admin accepts or rejects every request. */
export default function TenantRequestsPage() {
  const { t } = useLanguage();
  const { user } = useAuth();
  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));
  const [flats, setFlats] = useState<{ id: number; flat_no: string }[]>([]);

  useEffect(() => {
    if (can('tenant.request') || can('tenant.manage')) listFlats().then((f) => setFlats(f.map((x) => ({ id: x.id, flat_no: x.flat_no })))).catch(() => undefined);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user]);

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-3xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('tr.title')}</h1>
          </div>
          <LanguageToggle />
        </div>
        <TenantRequestPanel flats={flats} />
        <div className="mt-8">
          <TenantVacatePanel />
        </div>
      </div>
    </div>
  );
}
