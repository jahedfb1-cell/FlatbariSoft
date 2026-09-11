import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as reportsApi from '../api/reports';
import type { TrialBalance } from '../api/reports';

export default function ReportsPage() {
  const { t } = useLanguage();
  const [trialBalance, setTrialBalance] = useState<TrialBalance | null>(null);
  const [balanceSheet, setBalanceSheet] = useState<any>(null);

  useEffect(() => {
    reportsApi.getTrialBalance().then(setTrialBalance);
    reportsApi.getBalanceSheet().then(setBalanceSheet);
  }, []);

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-3xl">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('reports.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        {balanceSheet && (
          <div className="mb-6 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
            <div className="mb-2 flex items-center justify-between">
              <h2 className="font-semibold text-navy-900">{t('reports.balanceSheet')}</h2>
              <span className={balanceSheet.balanced ? 'text-status-paid' : 'text-status-due'}>
                {balanceSheet.balanced ? t('reports.balanced') : t('reports.notBalanced')}
              </span>
            </div>
            <div className="grid grid-cols-3 gap-3 text-sm text-navy-900">
              <p>Assets: ৳{balanceSheet.assets.toLocaleString('en-BD')}</p>
              <p>Liabilities: ৳{balanceSheet.liabilities.toLocaleString('en-BD')}</p>
              <p>Funds: ৳{balanceSheet.funds.toLocaleString('en-BD')}</p>
            </div>
          </div>
        )}

        {trialBalance && (
          <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
            <div className="flex items-center justify-between border-b border-brand-100 p-4">
              <h2 className="font-semibold text-navy-900">{t('reports.trialBalance')}</h2>
              <span className={trialBalance.balanced ? 'text-status-paid' : 'text-status-due'}>
                {trialBalance.balanced ? t('reports.balanced') : t('reports.notBalanced')}
              </span>
            </div>
            <table className="w-full text-left text-sm">
              <thead className="bg-brand-100 text-navy-900">
                <tr>
                  <th className="px-4 py-2">Account</th>
                  <th className="px-4 py-2">Debit</th>
                  <th className="px-4 py-2">Credit</th>
                </tr>
              </thead>
              <tbody>
                {trialBalance.lines.map((line) => (
                  <tr key={line.account_code} className="border-t border-brand-100">
                    <td className="px-4 py-2 text-navy-900">
                      {line.account_code} — {line.account_name}
                    </td>
                    <td className="px-4 py-2 text-navy-900">{line.debit ? `৳${line.debit.toLocaleString('en-BD')}` : ''}</td>
                    <td className="px-4 py-2 text-navy-900">{line.credit ? `৳${line.credit.toLocaleString('en-BD')}` : ''}</td>
                  </tr>
                ))}
                <tr className="border-t border-brand-200 font-semibold">
                  <td className="px-4 py-2 text-navy-900">Total</td>
                  <td className="px-4 py-2 text-navy-900">৳{trialBalance.total_debit.toLocaleString('en-BD')}</td>
                  <td className="px-4 py-2 text-navy-900">৳{trialBalance.total_credit.toLocaleString('en-BD')}</td>
                </tr>
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
