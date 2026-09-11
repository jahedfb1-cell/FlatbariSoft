import { useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as paymentsApi from '../api/payments';
import type { PaymentRow } from '../api/payments';

export default function PaymentsPage() {
  const { t } = useLanguage();
  const [payments, setPayments] = useState<PaymentRow[]>([]);
  const [flatId, setFlatId] = useState('');
  const [billId, setBillId] = useState('');
  const [amount, setAmount] = useState('');
  const [method, setMethod] = useState('CASH');
  const [error, setError] = useState<string | null>(null);

  function load() {
    paymentsApi.listPayments().then(setPayments);
  }

  useEffect(load, []);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await paymentsApi.createPayment({
        flat_id: Number(flatId),
        bill_id: billId ? Number(billId) : undefined,
        amount,
        payment_date: new Date().toISOString().slice(0, 10),
        method,
      });
      setFlatId('');
      setBillId('');
      setAmount('');
      load();
    } catch (err: any) {
      const errors = err.response?.data?.errors;
      setError(errors ? Object.values(errors).flat().join(' ') : err.response?.data?.message);
    }
  }

  async function handleApprove(id: number) {
    await paymentsApi.approvePayment(id);
    load();
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-3xl">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('payments.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        <form onSubmit={handleSubmit} className="mb-6 grid grid-cols-2 gap-3 rounded-xl border border-brand-200 bg-white p-4 shadow-sm sm:grid-cols-5">
          <input required placeholder={t('payments.flatId')} value={flatId} onChange={(e) => setFlatId(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input placeholder={t('payments.billId')} value={billId} onChange={(e) => setBillId(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input required type="number" placeholder={t('common.amount')} value={amount} onChange={(e) => setAmount(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <select value={method} onChange={(e) => setMethod(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2">
            <option value="CASH">CASH</option>
            <option value="BANK">BANK</option>
            <option value="BKASH">BKASH</option>
            <option value="NAGAD">NAGAD</option>
          </select>
          <button type="submit" className="rounded-lg bg-brand-600 px-3 py-2 text-white hover:bg-brand-700">
            {t('common.submit')}
          </button>
        </form>
        {error && <p className="mb-4 text-sm text-status-due">{error}</p>}

        <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
          <table className="w-full text-left text-sm">
            <thead className="bg-brand-100 text-navy-900">
              <tr>
                <th className="px-4 py-3">{t('payments.flatId')}</th>
                <th className="px-4 py-3">{t('common.amount')}</th>
                <th className="px-4 py-3">{t('payments.method')}</th>
                <th className="px-4 py-3">{t('payments.receiptNo')}</th>
                <th className="px-4 py-3">{t('common.status')}</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody>
              {payments.map((p) => (
                <tr key={p.id} className="border-t border-brand-100">
                  <td className="px-4 py-3 text-navy-900">{p.flat_no ?? p.flat_id}</td>
                  <td className="px-4 py-3 text-navy-900">৳{Number(p.amount).toLocaleString('en-BD')}</td>
                  <td className="px-4 py-3 text-navy-900">{p.method}</td>
                  <td className="px-4 py-3 text-navy-900">{p.receipt_no ?? '—'}</td>
                  <td className="px-4 py-3 text-navy-900">{p.status === 'PENDING' ? t('common.pending') : t('common.approved')}</td>
                  <td className="px-4 py-3">
                    {p.status === 'PENDING' && (
                      <button onClick={() => handleApprove(p.id)} className="text-brand-700 hover:underline">
                        {t('common.approve')}
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
