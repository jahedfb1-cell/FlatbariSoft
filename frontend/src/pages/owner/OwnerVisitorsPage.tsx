import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../../context/LanguageContext';
import LanguageToggle from '../../components/LanguageToggle';
import OwnerNav from '../../components/OwnerNav';
import { myVisitors, type Visitor } from '../../api/operations';

/** Who has come to the resident's own flat, from the gate book (last 60 days). */
export default function OwnerVisitorsPage() {
  const { t } = useLanguage();
  const [rows, setRows] = useState<Visitor[]>([]);

  useEffect(() => {
    myVisitors().then(setRows);
  }, []);

  return (
    <div className="min-h-screen bg-brand-50 px-4 pb-20 pt-8">
      <div className="mx-auto max-w-md">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <div>
            <Link to="/owner" className="text-sm text-navy-700 hover:underline">
              ← {t('owner.home')}
            </Link>
            <h1 className="text-xl font-semibold text-navy-900">{t('visitors.mine')}</h1>
          </div>
          <LanguageToggle />
        </div>
        <div className="space-y-2">
          {rows.map((v) => (
            <div key={v.id} className="rounded-xl border border-brand-100 bg-white p-4 shadow-sm">
              <p className="font-medium text-navy-900">
                {v.visitor_name} <span className="text-xs text-navy-700">· {t(`visitors.p.${v.purpose}` as any)}</span>
              </p>
              <p className="text-xs text-navy-700">
                {v.in_at}
                {v.out_at ? ` → ${v.out_at}` : ` · ${t('visitors.inside')}`}
                {v.vehicle_no ? ` · ${v.vehicle_no}` : ''}
              </p>
            </div>
          ))}
          {rows.length === 0 && <p className="text-sm text-navy-700">{t('visitors.none')}</p>}
        </div>
      </div>
      <OwnerNav />
    </div>
  );
}
