import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as duesApi from '../api/dues';
import type { ConstructionDue } from '../api/dues';

export default function DuesPage() {
  const { t, lang } = useLanguage();
  const bn = lang === 'bn';
  const [dues, setDues] = useState<ConstructionDue[]>([]);
  const [aging, setAging] = useState<{ '0-30': number; '31-90': number; '90+': number } | null>(null);
  const [payAmounts, setPayAmounts] = useState<Record<number, string>>({});
  const [paying, setPaying] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  function load() {
    duesApi.listDues().then(setDues).catch(() => undefined);
    duesApi.agingReport().then(setAging).catch(() => undefined);
  }

  useEffect(load, []);

  async function handlePay(installmentId: number) {
    const amount = payAmounts[installmentId];
    if (!amount || paying !== null) return; // one tap, one payment
    setError(null);
    setPaying(installmentId);
    try {
      await duesApi.payInstallment(installmentId, amount);
      setPayAmounts((p) => ({ ...p, [installmentId]: '' }));
      load();
    } catch (err: any) {
      const errors = err?.response?.data?.errors;
      setError(errors ? (Object.values(errors) as string[][]).flat().join(' ') : err?.response?.data?.message || t('somethingWrong'));
    } finally {
      setPaying(null);
    }
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-3xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('dues.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        {aging && (
          <div className="mb-6 grid grid-cols-3 gap-3">
            <div className="rounded-xl border border-brand-200 bg-white p-3 text-center shadow-sm">
              <p className="text-xs text-navy-700">0-30 {bn ? 'দিন' : 'days'}</p>
              <p className="font-semibold text-navy-900">৳{aging['0-30'].toLocaleString('en-BD')}</p>
            </div>
            <div className="rounded-xl border border-brand-200 bg-white p-3 text-center shadow-sm">
              <p className="text-xs text-navy-700">31-90 {bn ? 'দিন' : 'days'}</p>
              <p className="font-semibold text-status-partial">৳{aging['31-90'].toLocaleString('en-BD')}</p>
            </div>
            <div className="rounded-xl border border-brand-200 bg-white p-3 text-center shadow-sm">
              <p className="text-xs text-navy-700">90+ {bn ? 'দিন' : 'days'}</p>
              <p className="font-semibold text-status-due">৳{aging['90+'].toLocaleString('en-BD')}</p>
            </div>
          </div>
        )}

        {error && <p className="mb-3 text-sm text-status-due">{error}</p>}

        <div className="space-y-4">
          {dues.map((due) => (
            <div key={due.id} className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
              <div className="mb-2 flex items-center justify-between">
                <p className="font-medium text-navy-900">{due.category}</p>
                <p className="text-sm text-navy-700">
                  {t('dues.balance')}: ৳{Number(due.balance).toLocaleString('en-BD')}
                </p>
              </div>
              {due.installments.map((inst) => (
                <div key={inst.id} className="flex items-center justify-between border-t border-brand-100 py-2 text-sm">
                  <span className="text-navy-900">
                    #{inst.installment_no} — {inst.due_date} — ৳{Number(inst.amount).toLocaleString('en-BD')} ({inst.status})
                  </span>
                  {inst.status !== 'PAID' && (
                    <div className="flex items-center gap-2">
                      <input
                        type="number"
                        min="0.01"
                        step="0.01"
                        placeholder={t('common.amount')}
                        value={payAmounts[inst.id] ?? ''}
                        onChange={(e) => setPayAmounts((p) => ({ ...p, [inst.id]: e.target.value }))}
                        className="w-24 rounded-lg border border-brand-200 px-2 py-1"
                      />
                      <button onClick={() => handlePay(inst.id)} disabled={paying !== null} className="text-brand-700 hover:underline disabled:opacity-50">
                        {t('common.submit')}
                      </button>
                    </div>
                  )}
                </div>
              ))}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
