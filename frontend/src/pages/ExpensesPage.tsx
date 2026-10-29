import { useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as expensesApi from '../api/expenses';
import type { ExpenseRow, ExpenseHead } from '../api/expenses';

export default function ExpensesPage() {
  const { t } = useLanguage();
  const [expenses, setExpenses] = useState<ExpenseRow[]>([]);
  const [heads, setHeads] = useState<ExpenseHead[]>([]);
  const [headId, setHeadId] = useState('');
  const [payee, setPayee] = useState('');
  const [amount, setAmount] = useState('');
  const [medium, setMedium] = useState('CASH');
  const [fundType, setFundType] = useState('OPERATING');
  const [error, setError] = useState<string | null>(null);
  const [closingMsg, setClosingMsg] = useState<string | null>(null);

  function load() {
    expensesApi.listExpenses().then(setExpenses);
    expensesApi.listExpenseHeads().then((h) => {
      setHeads(h);
      if (h[0]) setHeadId(String(h[0].id));
    });
  }

  useEffect(load, []);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await expensesApi.createExpense({
        date: new Date().toISOString().slice(0, 10),
        expense_head_id: Number(headId),
        payee,
        amount,
        treasury_medium: medium,
        fund_type: fundType,
      });
      setPayee('');
      setAmount('');
      load();
    } catch (err: any) {
      const errors = err.response?.data?.errors;
      setError(errors ? Object.values(errors).flat().join(' ') : err.response?.data?.message);
    }
  }

  async function handleApprove(id: number) {
    try {
      await expensesApi.approveExpense(id);
      load();
    } catch (err: any) {
      setError(err.response?.data?.errors?.resolution_id?.[0] || err.response?.data?.errors?.approved_by?.[0] || err.response?.data?.message);
    }
  }

  async function handleClose() {
    const now = new Date();
    try {
      const res = await expensesApi.closeMonth(now.getFullYear(), now.getMonth() + 1);
      setClosingMsg(`${res.message} (৳${res.surplus_transferred ?? 0})`);
    } catch (err: any) {
      setClosingMsg(err.response?.data?.errors?.period?.[0] || err.response?.data?.message);
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
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('expenses.title')}</h1>
          </div>
          <div className="flex items-center gap-2">
            <LanguageToggle />
            <button onClick={handleClose} className="rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-navy-900 hover:bg-brand-100">
              {t('expenses.closeBtn')}
            </button>
          </div>
        </div>

        {closingMsg && <p className="mb-4 text-sm text-navy-900">{closingMsg}</p>}

        <form onSubmit={handleSubmit} className="mb-6 grid grid-cols-2 gap-3 rounded-xl border border-brand-200 bg-white p-4 shadow-sm sm:grid-cols-5">
          <select value={headId} onChange={(e) => setHeadId(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2">
            {heads.map((h) => (
              <option key={h.id} value={h.id}>
                {h.name_bn}
              </option>
            ))}
          </select>
          <input required placeholder={t('expenses.payee')} value={payee} onChange={(e) => setPayee(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input required type="number" placeholder={t('common.amount')} value={amount} onChange={(e) => setAmount(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <select value={medium} onChange={(e) => setMedium(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2">
            <option value="CASH">CASH</option>
            <option value="BANK">BANK</option>
          </select>
          <select value={fundType} onChange={(e) => setFundType(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2">
            <option value="OPERATING">OPERATING</option>
            <option value="SINKING">SINKING</option>
            <option value="CAPITAL">CAPITAL</option>
            <option value="GAS">GAS</option>
          </select>
          <button type="submit" className="col-span-2 rounded-lg bg-brand-600 px-3 py-2 text-white hover:bg-brand-700 sm:col-span-1">
            {t('common.submit')}
          </button>
        </form>
        {error && <p className="mb-4 text-sm text-status-due">{error}</p>}

        <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
          <table className="w-full text-left text-sm">
            <thead className="bg-brand-100 text-navy-900">
              <tr>
                <th className="px-4 py-3">{t('expenses.payee')}</th>
                <th className="px-4 py-3">{t('common.amount')}</th>
                <th className="px-4 py-3">{t('expenses.fundType')}</th>
                <th className="px-4 py-3">{t('expenses.voucherNo')}</th>
                <th className="px-4 py-3">{t('common.status')}</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody>
              {expenses.map((ex) => (
                <tr key={ex.id} className="border-t border-brand-100">
                  <td className="px-4 py-3 text-navy-900">{ex.payee}</td>
                  <td className="px-4 py-3 text-navy-900">৳{Number(ex.amount).toLocaleString('en-BD')}</td>
                  <td className="px-4 py-3 text-navy-900">
                    {ex.fund_type} {ex.fund_type === 'SINKING' && `(${ex.approvals_count}/2)`}
                  </td>
                  <td className="px-4 py-3 text-navy-900">{ex.voucher_no ?? '—'}</td>
                  <td className="px-4 py-3 text-navy-900">{ex.status === 'PENDING' ? t('common.pending') : t('common.approved')}</td>
                  <td className="px-4 py-3">
                    {ex.status === 'PENDING' && (
                      <button onClick={() => handleApprove(ex.id)} className="text-brand-700 hover:underline">
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
