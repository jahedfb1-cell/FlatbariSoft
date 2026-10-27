import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../../context/LanguageContext';
import type { TranslationKey } from '../../i18n/translations';
import LanguageToggle from '../../components/LanguageToggle';
import OwnerNav from '../../components/OwnerNav';
import { CHARGE_COLOR } from '../ParkingPage';
import * as parkingApi from '../../api/parking';
import type { MyParking } from '../../api/parking';

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD')}`;

/** A resident's own parking: the slots held by their flat and what has been billed on them. */
export default function OwnerParkingPage() {
  const { t } = useLanguage();
  const [data, setData] = useState<MyParking | null>(null);

  useEffect(() => {
    parkingApi.myParking().then(setData).catch(() => setData({ slots: [], charges: [] }));
  }, []);

  return (
    <div className="min-h-screen bg-brand-50 px-4 pb-24 pt-8">
      <div className="mx-auto max-w-md space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <Link to="/owner" className="text-sm text-navy-700 hover:underline">
              ← {t('owner.home')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('parking.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        <h2 className="font-semibold text-navy-900">{t('parking.mySlots')}</h2>
        {data && data.slots.length === 0 && <p className="text-sm text-navy-700">{t('parking.noneMine')}</p>}
        <div className="space-y-2">
          {data?.slots.map((s) => (
            <div key={s.allocation_id} className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
              <p className="font-medium text-navy-900">
                {s.slot_code} <span className="text-sm font-normal text-navy-700">· {t(`parking.type.${s.vehicle_type}` as TranslationKey)}</span>
              </p>
              <p className="text-sm text-navy-700">
                {s.zone ? `${s.zone} · ` : ''}
                {s.vehicle_no ? `${s.vehicle_no} · ` : ''}
                {money(s.monthly_fee)}
              </p>
            </div>
          ))}
        </div>

        {data && data.charges.length > 0 && (
          <>
            <h2 className="font-semibold text-navy-900">{t('parking.myCharges')}</h2>
            <div className="space-y-2">
              {data.charges.map((c) => (
                <div key={c.id} className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
                  <div className="flex items-center justify-between">
                    <p className="font-medium text-navy-900">
                      {c.year}-{String(c.month).padStart(2, '0')} · {c.slot_code}
                    </p>
                    <span className={`rounded-full px-2 py-0.5 text-xs ${CHARGE_COLOR[c.status]}`}>{t(`parking.status.${c.status}` as TranslationKey)}</span>
                  </div>
                  <p className="text-sm text-navy-700">
                    {money(c.amount)} · {t('parking.paid')}: {money(c.paid)} · {t('parking.due')}: {money(c.due)}
                  </p>
                </div>
              ))}
            </div>
          </>
        )}
      </div>
      <OwnerNav />
    </div>
  );
}
