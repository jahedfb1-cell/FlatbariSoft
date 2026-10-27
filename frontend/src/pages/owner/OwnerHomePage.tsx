import { useEffect, useState } from 'react';
import { useAuth } from '../../context/AuthContext';
import { useLanguage } from '../../context/LanguageContext';
import { Link } from 'react-router-dom';
import LanguageToggle from '../../components/LanguageToggle';
import OwnerNav from '../../components/OwnerNav';
import * as myApi from '../../api/my';
import type { Flat } from '../../api/flats';

export default function OwnerHomePage() {
  const { user, logout } = useAuth();
  const { t } = useLanguage();
  const [flats, setFlats] = useState<Flat[]>([]);

  useEffect(() => {
    myApi.myFlats().then(setFlats);
  }, []);

  return (
    <div className="min-h-screen bg-brand-50 px-4 pb-20 pt-8">
      <div className="mx-auto max-w-md">
        <div className="mb-4 flex items-center justify-between">
          <h1 className="text-xl font-semibold text-navy-900">{t('appName')}</h1>
          <div className="flex items-center gap-2">
            <LanguageToggle />
            <button onClick={() => logout()} className="text-sm text-navy-700 hover:underline">
              {t('home.logout')}
            </button>
          </div>
        </div>

        <div className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
          <p className="text-sm text-navy-700">{user?.mobile}</p>
          <p className="text-sm text-navy-700">{user?.roles.join(', ')}</p>
        </div>

        <Link to="/owner/parking" className="mt-4 block rounded-xl border border-brand-200 bg-white p-4 text-center font-medium text-navy-900 shadow-sm hover:bg-brand-100">
          🚗 {t('owner.parking')}
        </Link>

        <h2 className="mb-2 mt-6 font-semibold text-navy-900">{t('owner.myFlats')}</h2>
        <div className="space-y-2">
          {flats.map((f) => (
            <div key={f.id} className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
              <p className="font-medium text-navy-900">{f.flat_no}</p>
              <p className="text-sm text-navy-700">
                {f.floor} · {f.occupancy_status}
              </p>
            </div>
          ))}
          {flats.length === 0 && <p className="text-sm text-navy-700">—</p>}
        </div>
      </div>
      <OwnerNav />
    </div>
  );
}
