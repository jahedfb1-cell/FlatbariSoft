import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as billingApi from '../api/billing';
import type { BillRow } from '../api/billing';

export default function BillingPage() {
  const { t } = useLanguage();
  const now = new Date();
  const [year, setYear] = useState(now.getFullYear());
  const [month, setMonth] = useState(now.getMonth() + 1);
  const [bills, setBills] = useState<BillRow[]>([]);
  const [message, setMessage] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function handleGenerate() {
    setLoading(true);
    setMessage(null);
    try {
      const res = await billingApi.generateBilling(year, month);
      setMessage(res.message + ` (${res.bill_count})`);
      loadRegister();
    } catch (err: any) {
      setMessage(err.response?.data?.errors?.period?.[0] || err.response?.data?.message || 'Error');
    } finally {
      setLoading(false);
    }
  }

  async function loadRegister() {
    try {
      const data = await billingApi.getBillingRegister(year, month);
      setBills(data);
    } catch {
      setBills([]);
    }
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-4xl">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('billing.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        <div className="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
          <div>
            <label className="mb-1 block text-sm text-navy-900">{t('common.year')}</label>
            <input
              type="number"
              value={year}
              onChange={(e) => setYear(Number(e.target.value))}
              className="w-24 rounded-lg border border-brand-200 px-3 py-2"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm text-navy-900">{t('common.month')}</label>
            <input
              type="number"
              min={1}
              max={12}
              value={month}
              onChange={(e) => setMonth(Number(e.target.value))}
              className="w-20 rounded-lg border border-brand-200 px-3 py-2"
            />
          </div>
          <button
            onClick={handleGenerate}
            disabled={loading}
            className="rounded-lg bg-brand-600 px-4 py-2 font-medium text-white transition hover:bg-brand-700 disabled:opacity-50"
          >
            {t('billing.generateBtn')}
          </button>
          <button onClick={loadRegister} className="rounded-lg border border-brand-200 px-4 py-2 text-navy-900 transition hover:bg-brand-100">
            {t('billing.viewRegister')}
          </button>
        </div>

        {message && <p className="mb-4 text-sm text-navy-900">{message}</p>}

        <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
          <table className="w-full text-left text-sm">
            <thead className="bg-brand-100 text-navy-900">
              <tr>
                <th className="px-4 py-3">{t('billing.colFlat')}</th>
                <th className="px-4 py-3">{t('billing.colTotal')}</th>
                <th className="px-4 py-3">{t('billing.colPaid')}</th>
                <th className="px-4 py-3">{t('common.status')}</th>
              </tr>
            </thead>
            <tbody>
              {bills.map((bill) => (
                <tr key={bill.id} className="border-t border-brand-100">
                  <td className="px-4 py-3 text-navy-900">{bill.flat_no ?? bill.flat_id}</td>
                  <td className="px-4 py-3 text-navy-900">৳{Number(bill.total_payable).toLocaleString('en-BD')}</td>
                  <td className="px-4 py-3 text-navy-900">৳{Number(bill.paid_amount).toLocaleString('en-BD')}</td>
                  <td className="px-4 py-3 text-navy-900">{bill.status}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
