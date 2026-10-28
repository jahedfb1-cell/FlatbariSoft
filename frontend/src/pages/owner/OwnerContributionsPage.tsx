import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../../context/LanguageContext';
import LanguageToggle from '../../components/LanguageToggle';
import OwnerNav from '../../components/OwnerNav';
import * as api from '../../api/contributions';
import type { FlatLedger } from '../../api/contributions';

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { maximumFractionDigits: 0 })}`;

/** An owner's own construction-cost ledger: the target, every payment, and what is still owed. */
export default function OwnerContributionsPage() {
  const { t, lang } = useLanguage();
  const [ledgers, setLedgers] = useState<FlatLedger[] | null>(null);

  useEffect(() => {
    api.myLedgers().then(setLedgers).catch(() => setLedgers([]));
  }, []);

  return (
    <div className="min-h-screen bg-brand-50 px-4 pb-24 pt-8">
      <div className="mx-auto max-w-md space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <Link to="/owner" className="text-sm text-navy-700 hover:underline">
              ← {t('owner.home')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('owner.contrib')}</h1>
          </div>
          <LanguageToggle />
        </div>

        {ledgers && ledgers.length === 0 && <p className="text-sm text-navy-700">{t('contrib.myNone')}</p>}

        {ledgers && ledgers.length > 0 && (
          <button className="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm text-navy-700 hover:bg-brand-100" onClick={() => api.printMyLedger()}>
            🖨 {t('contrib.ledgerPdf')}
          </button>
        )}

        {ledgers?.map((l) => {
          const bal = Number(l.balance);
          return (
            <div key={l.flat_id} className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
              <p className="font-semibold text-navy-900">{l.flat_no}</p>
              <div className="mt-2 space-y-1 rounded-lg bg-brand-50 p-3 text-sm text-navy-900">
                <p className="flex justify-between">
                  <span>{t('contrib.target')}</span>
                  <b>{money(l.target)}</b>
                </p>
                <p className="flex justify-between">
                  <span>{t('contrib.collected')}</span>
                  <b>{money(l.collected)}</b>
                </p>
                <p className="flex justify-between">
                  <span>{bal >= 0 ? t('contrib.stillDue') : t('contrib.paidExcess')}</span>
                  <b className={bal > 0 ? 'text-status-due' : 'text-status-paid'}>{money(Math.abs(bal))}</b>
                </p>
              </div>

              {l.entries.length === 0 ? (
                <p className="mt-3 text-sm text-navy-700">{t('contrib.noEntries')}</p>
              ) : (
                <ul className="mt-3 divide-y divide-brand-100 text-sm">
                  {l.entries.map((e) => (
                    <li key={e.id} className={`flex items-start justify-between py-2 ${e.is_reversal || e.is_reversed ? 'text-navy-700 line-through' : ''}`}>
                      <span>
                        {lang === 'bn' ? e.head_name_bn : e.head_name_en}
                        <span className="block text-xs text-navy-700">
                          {e.date} · {e.receipt_no}
                          {e.method === 'OPENING' ? ` · ${t('contrib.opening')}` : ''}
                        </span>
                      </span>
                      <span className="text-right">
                        {money(e.amount)}
                        <span className="block text-xs text-navy-700">{money(e.balance_after)}</span>
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          );
        })}
      </div>
      <OwnerNav />
    </div>
  );
}
