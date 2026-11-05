import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import ExpenseReviewModal from '../components/ExpenseReviewModal';
import SupplierPicker from '../components/SupplierPicker';
import * as expensesApi from '../api/expenses';
import * as workApi from '../api/workBudgets';
import type { ExpenseRow, ExpenseHead } from '../api/expenses';

const ACCEPT = 'application/pdf,image/jpeg,image/png,image/webp';
const input = 'rounded-lg border border-brand-200 px-3 py-2 text-base';

export default function ExpensesPage() {
  const { t, lang } = useLanguage();
  const { user } = useAuth();
  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));
  const [expenses, setExpenses] = useState<ExpenseRow[]>([]);
  const [heads, setHeads] = useState<ExpenseHead[]>([]);
  const [headId, setHeadId] = useState('');
  const [date, setDate] = useState(() => new Date(Date.now() + 6 * 3600 * 1000).toISOString().slice(0, 10)); // Dhaka date
  const [payee, setPayee] = useState('');
  const [supplierId, setSupplierId] = useState('');
  const [amount, setAmount] = useState('');
  const [description, setDescription] = useState('');
  const [files, setFiles] = useState<File[]>([]);
  const [medium, setMedium] = useState('CASH');
  const [fundType, setFundType] = useState('OPERATING');
  const [workId, setWorkId] = useState(() => new URLSearchParams(window.location.search).get('work') || '');
  const [works, setWorks] = useState<{ id: number; title: string }[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [closingMsg, setClosingMsg] = useState<string | null>(null);
  const [reviewing, setReviewing] = useState<number | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const todayDhaka = () => new Date(Date.now() + 6 * 3600 * 1000).toISOString().slice(0, 10);

  function resetForm() {
    setEditingId(null);
    setPayee('');
    setSupplierId('');
    setAmount('');
    setDescription('');
    setFiles([]);
    setMedium('CASH');
    setFundType('OPERATING');
    setWorkId('');
    setDate(todayDhaka());
    if (fileInput.current) fileInput.current.value = '';
  }

  /** Load a pending, unsigned expense into the form to correct it. */
  function startEdit(ex: ExpenseRow) {
    setError(null);
    setEditingId(ex.id);
    setDate(ex.date);
    setHeadId(String(ex.expense_head_id));
    setPayee(ex.payee);
    setSupplierId('');
    setAmount(String(Number(ex.amount)));
    setDescription(ex.description ?? '');
    setMedium(ex.treasury_medium);
    setFundType(ex.fund_type);
    setWorkId(ex.work_budget_id ? String(ex.work_budget_id) : '');
    setFiles([]);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  const fileInput = useRef<HTMLInputElement>(null);

  function load() {
    expensesApi.listExpenses().then(setExpenses);
    expensesApi.listExpenseHeads().then((h) => {
      setHeads(h);
      setHeadId((cur) => cur || (h[0] ? String(h[0].id) : ''));
    });
  }

  useEffect(load, []);
  useEffect(() => {
    workApi.listWorks().then((w) => setWorks(w.filter((x) => x.status === 'PLANNED' || x.status === 'ONGOING'))).catch(() => undefined);
  }, []);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      const body = {
        date,
        expense_head_id: Number(headId),
        payee,
        amount,
        description: description || undefined,
        treasury_medium: medium,
        fund_type: workId ? 'CAPITAL' : fundType,
        work_budget_id: workId ? Number(workId) : undefined,
      };
      if (editingId) {
        await expensesApi.updateExpense(editingId, { ...body, description: description || '', work_budget_id: workId ? Number(workId) : null });
        if (files.length) await expensesApi.addExpenseFiles(editingId, files);
      } else {
        await expensesApi.createExpense({ ...body, attachments: files });
      }
      resetForm();
      load();
    } catch (err: any) {
      const errors = err.response?.data?.errors;
      setError(errors ? (Object.values(errors) as string[][]).flat().join(' ') : err.response?.data?.message);
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

        {can('expense.create') && (
          <form onSubmit={handleSubmit} className="mb-6 grid grid-cols-2 gap-3 rounded-xl border border-brand-200 bg-white p-4 shadow-sm sm:grid-cols-4">
            {editingId && (
              <div className="col-span-2 flex flex-wrap items-center gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 sm:col-span-4">
                <span>{lang === 'bn' ? 'আপনি একটি অনুমোদনের অপেক্ষায় থাকা খরচ সংশোধন করছেন। সংরক্ষণ করলে খরচের তথ্য বদলে যাবে।' : 'You are correcting an expense that is waiting for approval.'}</span>
                <button type="button" onClick={resetForm} className="ml-auto rounded-lg border border-amber-300 bg-white px-3 py-1 text-xs hover:bg-amber-100">
                  {lang === 'bn' ? 'সংশোধন বাদ দিন' : 'Stop editing'}
                </button>
              </div>
            )}
            <input required type="date" value={date} onChange={(e) => setDate(e.target.value)} className={input} />
            <select value={headId} onChange={(e) => setHeadId(e.target.value)} className={input}>
              {heads.map((h) => (
                <option key={h.id} value={h.id}>
                  {h.name_bn}
                </option>
              ))}
            </select>
            <div className="col-span-2">
              <SupplierPicker kind="ANY" required={false} value={supplierId} onChange={(id, name) => { setSupplierId(id); if (name) setPayee(name); }} />
            </div>
            <input required placeholder={t('expenses.payee')} value={payee} onChange={(e) => { setPayee(e.target.value); setSupplierId(''); }} className={`${input} col-span-2`} />
            <input required type="number" step="0.01" placeholder={t('common.amount')} value={amount} onChange={(e) => setAmount(e.target.value)} className={input} />
            <select value={medium} onChange={(e) => setMedium(e.target.value)} className={input}>
              <option value="CASH">CASH</option>
              <option value="BANK">BANK</option>
            </select>
            <select value={workId} onChange={(e) => setWorkId(e.target.value)} className={input} aria-label="work">
              <option value="">— কাজ নয় (সাধারণ খরচ) —</option>
              {works.map((w) => (
                <option key={w.id} value={w.id}>🏗️ {w.title}</option>
              ))}
            </select>
            <select value={workId ? 'CAPITAL' : fundType} disabled={!!workId} onChange={(e) => setFundType(e.target.value)} className={input}>
              <option value="OPERATING">OPERATING</option>
              <option value="SINKING">RESERVE</option>
              <option value="CAPITAL">CAPITAL</option>
              <option value="GAS">GAS</option>
            </select>
            <textarea rows={2} placeholder={t('expenses.description')} value={description} onChange={(e) => setDescription(e.target.value)} className={`${input} col-span-2`} />
            <div className="col-span-2 sm:col-span-4">
              <label className="inline-block cursor-pointer rounded-lg border border-dashed border-brand-300 bg-brand-50 px-4 py-2 text-sm text-navy-700 hover:bg-brand-100">
                📎 {files.length ? `${files.length} ${t('expenses.filesChosen')}` : t('expenses.attach')}
                <input ref={fileInput} type="file" multiple accept={ACCEPT} className="hidden" onChange={(e) => setFiles(Array.from(e.target.files ?? []).slice(0, 5))} />
              </label>
              <span className="ml-2 text-xs text-navy-700">{t('expenses.attachHint')}</span>
              {files.length > 0 && <p className="mt-1 text-xs text-navy-700">{files.map((f) => f.name).join(', ')}</p>}
            </div>
            <button type="submit" className="col-span-2 rounded-lg bg-brand-600 px-3 py-2 text-white hover:bg-brand-700 sm:col-span-4">
              {editingId ? (lang === 'bn' ? 'সংশোধন সংরক্ষণ করুন' : 'Save correction') : t('common.submit')}
            </button>
          </form>
        )}
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
                  <td className="px-4 py-3 text-navy-900">
                    {ex.payee}
                    {ex.attachments_count > 0 && <span className="ml-2 text-xs text-navy-700">📎 {ex.attachments_count}</span>}
                  </td>
                  <td className="px-4 py-3 text-navy-900">৳{Number(ex.amount).toLocaleString('en-BD')}</td>
                  <td className="px-4 py-3 text-navy-900">
                    {ex.fund_type === 'SINKING' ? 'RESERVE' : ex.fund_type} {ex.fund_type === 'SINKING' && `(${ex.approvals_count}/2)`}
                  </td>
                  <td className="px-4 py-3 text-navy-900">{ex.voucher_no ?? '—'}</td>
                  <td className="px-4 py-3 text-navy-900">{ex.status === 'PENDING' ? t('common.pending') : t('common.approved')}</td>
                  <td className="whitespace-nowrap px-4 py-3">
                    {ex.status === 'PENDING' && can('expense.approve') ? (
                      <button onClick={() => setReviewing(ex.id)} className="rounded-lg bg-brand-600 px-3 py-1 text-white hover:bg-brand-700">
                        {t('common.approve')}
                      </button>
                    ) : (
                      <button onClick={() => setReviewing(ex.id)} className="text-brand-700 hover:underline">
                        {t('review.view')}
                      </button>
                    )}
                    {ex.editable && can('expense.create') && (
                      <button onClick={() => startEdit(ex)} className="ml-2 rounded-lg border border-brand-200 px-2.5 py-1 text-xs text-brand-700 hover:bg-brand-100">
                        {lang === 'bn' ? 'সংশোধন' : 'Edit'}
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {reviewing !== null && <ExpenseReviewModal id={reviewing} canApprove={can('expense.approve')} canUpload={can('expense.create')} onClose={() => setReviewing(null)} onChanged={load} />}
    </div>
  );
}
