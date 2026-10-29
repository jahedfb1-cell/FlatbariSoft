import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../../context/LanguageContext';
import LanguageToggle from '../../components/LanguageToggle';
import OwnerNav from '../../components/OwnerNav';
import TenantRequestPanel from '../../components/TenantRequestPanel';
import * as myApi from '../../api/my';

/** A flat owner tells the office who is renting their flat; the office (an admin) accepts, and the tenant gets a login. */
export default function OwnerTenantRequestPage() {
  const { t } = useLanguage();
  const [flats, setFlats] = useState<{ id: number; flat_no: string }[]>([]);

  useEffect(() => {
    myApi.myFlats().then((f) => setFlats(f.map((x) => ({ id: x.id, flat_no: x.flat_no }))));
  }, []);

  return (
    <div className="min-h-screen bg-brand-50 px-4 pb-24 pt-8">
      <div className="mx-auto max-w-xl">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <div>
            <Link to="/owner" className="text-sm text-navy-700 hover:underline">
              ← {t('owner.home')}
            </Link>
            <h1 className="text-xl font-semibold text-navy-900">{t('tr.title')}</h1>
          </div>
          <LanguageToggle />
        </div>
        <TenantRequestPanel flats={flats} />
      </div>
      <OwnerNav />
    </div>
  );
}
