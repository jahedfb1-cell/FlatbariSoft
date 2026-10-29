import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import type { TranslationKey } from '../i18n/translations';
import LanguageToggle from '../components/LanguageToggle';
import Modal from '../components/Modal';
import FlatLabel from '../components/FlatLabel';
import { localToday } from '../components/HallCalendar';
import { apiError } from '../api/hall';
import { blobErrorMessage } from '../utils/download';
import * as billingApi from '../api/billing';
import * as paymentsApi from '../api/payments';
import type { BillRow } from '../api/billing';

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { maximumFractionDigits: 2 })}`;
const input = 'w-full rounded-lg border border-brand-200 px-3 py-2';
const btn = 'rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50';
const btnGhost = 'rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700 hover:bg-brand-100 disabled:opacity-50';

const STATUS_COLOR: Record<BillRow['status'], string> = {
  UNPAID: 'bg-status-due/15 text-status-due',
  PARTIAL: 'bg-status-partial/15 text-status-partial',
  PAID: 'bg-status-paid/15 text-status-paid',
};

/** What can still be taken on a bill: what is owed less what is already waiting for approval. */
const collectable = (b: BillRow) => Math.max(0, Number(b.due) - Number(b.pending_amount));

/** The month's bills for every flat, with a one-click way to take a flat's payment (service charge, gas and previous due together). */
export default function BillingPage() {
  const { user } = useAuth();
  const { t } = useLanguage();
  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));

  const now = new Date();
  const [year, setYear] = useState(now.getFullYear());
  const [month, setMonth] = useState(now.getMonth() + 1);
  const [bills, setBills] = useState<BillRow[]>([]);
  const [onlyDue, setOnlyDue] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [paying, setPaying] = useState<BillRow | null>(null);

  const loadRegister = useCallback(async () => {
    try {
      setBills(await billingApi.getBillingRegister(year, month));
    } catch {
      setBills([]);
    }
  }, [year, month]);
  useEffect(() => {
    loadRegister();
  }, [loadRegister]);

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

  const sum = (f: (b: BillRow) => string | number) => bills.reduce((c, b) => c + Number(f(b)), 0);
  const rows = bills.filter((b) => !onlyDue || collectable(b) > 0);

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-6xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
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
            <input type="number" value={year} onChange={(e) => setYear(Number(e.target.value))} className="w-24 rounded-lg border border-brand-200 px-3 py-2" />
          </div>
          <div>
            <label className="mb-1 block text-sm text-navy-900">{t('common.month')}</label>
            <input type="number" min={1} max={12} value={month} onChange={(e) => setMonth(Number(e.target.value))} className="w-20 rounded-lg border border-brand-200 px-3 py-2" />
          </div>
          {can('billing.manage') && (
            <button onClick={handleGenerate} disabled={loading} className="rounded-lg bg-brand-600 px-4 py-2 font-medium text-white transition hover:bg-brand-700 disabled:opacity-50">
              {t('billing.generateBtn')}
            </button>
          )}
          <label className="ml-auto flex items-center gap-1 text-sm text-navy-700">
            <input type="checkbox" checked={onlyDue} onChange={(e) => setOnlyDue(e.target.checked)} /> {t('billing.onlyDue')}
          </label>
        </div>

        {message && <p className="mb-4 text-sm text-navy-900">{message}</p>}

        {bills.length > 0 && (
          <div className="mb-4 grid grid-cols-3 gap-3">
            <Card label={t('billing.totalBilled')} value={money(sum((b) => b.total_payable))} />
            <Card label={t('billing.totalCollected')} value={money(sum((b) => b.paid_amount))} tone="paid" />
            <Card label={t('billing.totalDue')} value={money(sum((b) => b.due))} tone="due" />
          </div>
        )}

        <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
          {bills.length === 0 ? (
            <p className="p-4 text-sm text-navy-700">{t('billing.noBills')}</p>
          ) : (
            <table className="w-full text-left text-sm">
              <thead className="bg-brand-100 text-navy-900">
                <tr>
                  <th className="px-3 py-3">{t('billing.colFlat')}</th>
                  <th className="px-3 py-3 text-right">{t('billing.colService')}</th>
                  <th className="px-3 py-3 text-right">{t('billing.colGas')}</th>
                  <th className="px-3 py-3 text-right">{t('billing.colPrevDue')}</th>
                  <th className="px-3 py-3 text-right">{t('billing.colTotal')}</th>
                  <th className="px-3 py-3 text-right">{t('billing.colPaid')}</th>
                  <th className="px-3 py-3 text-right">{t('billing.colDue')}</th>
                  <th className="px-3 py-3">{t('common.status')}</th>
                  <th className="px-3 py-3" />
                </tr>
              </thead>
              <tbody>
                {rows.map((b) => (
                  <tr key={b.id} className="border-t border-brand-100">
                    <td className="px-3 py-2">
                      <FlatLabel flatId={b.flat_id} flatNo={b.flat_no} owner={b.owner} tenant={b.tenant} />
                    </td>
                    <td className="px-3 py-2 text-right">{money(b.service_charge)}</td>
                    <td className="px-3 py-2 text-right">{money(b.gas_amount)}</td>
                    <td className="px-3 py-2 text-right text-navy-700">{money(b.previous_due)}</td>
                    <td className="px-3 py-2 text-right font-medium">{money(b.total_payable)}</td>
                    <td className="px-3 py-2 text-right">{money(b.paid_amount)}</td>
                    <td className="px-3 py-2 text-right font-medium">
                      {money(b.due)}
                      {Number(b.pending_amount) > 0 && (
                        <span className="block text-xs font-normal text-status-partial">
                          {money(b.pending_amount)} {t('billing.pendingNote')}
                        </span>
                      )}
                    </td>
                    <td className="px-3 py-2">
                      <span className={`rounded-full px-2 py-0.5 text-xs ${STATUS_COLOR[b.status]}`}>{t(`billing.status.${b.status}` as TranslationKey)}</span>
                    </td>
                    <td className="whitespace-nowrap px-3 py-2 text-right">
                      {can('payment.create') && collectable(b) > 0 && (
                        <button className="font-medium text-brand-700 hover:underline" onClick={() => setPaying(b)}>
                          {t('billing.pay')}
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>

      {paying && (
        <PayModal
          bill={paying}
          canApprove={can('payment.approve')}
          onClose={() => setPaying(null)}
          onDone={() => {
            setPaying(null);
            loadRegister();
          }}
        />
      )}
    </div>
  );
}

function Card({ label, value, tone }: { label: string; value: string; tone?: 'paid' | 'due' }) {
  const color = tone === 'due' ? 'text-status-due' : tone === 'paid' ? 'text-status-paid' : 'text-navy-900';
  return (
    <div className="rounded-xl border border-brand-200 bg-white p-3 shadow-sm">
      <p className="text-xs text-navy-700">{label}</p>
      <p className={`text-lg font-semibold ${color}`}>{value}</p>
    </div>
  );
}

function PayModal({ bill, canApprove, onClose, onDone }: { bill: BillRow; canApprove: boolean; onClose: () => void; onDone: () => void }) {
  const { t } = useLanguage();
  const [amount, setAmount] = useState(String(collectable(bill)));
  const [method, setMethod] = useState('CASH');
  const [date, setDate] = useState(localToday());
  const [reference, setReference] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<{ id: number; approved: boolean; receipt: string | null } | null>(null);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      let p = await paymentsApi.createPayment({ flat_id: bill.flat_id, bill_id: bill.id, amount, payment_date: date, method, reference_no: reference || undefined });
      // whoever may approve (admin, president) posts it straight away; everyone else leaves it for them
      if (canApprove) p = await paymentsApi.approvePayment(p.id);
      setResult({ id: p.id, approved: p.status === 'APPROVED', receipt: p.receipt_no });
    } catch (err) {
      setError(apiError(err, t('somethingWrong')));
    } finally {
      setBusy(false);
    }
  }

  if (result) {
    return (
      <Modal title={t('billing.payTitle')} onClose={onDone}>
        <div className="space-y-3 text-sm">
          <p className={result.approved ? 'text-status-paid' : 'text-navy-900'}>{result.approved ? t('billing.paidOk') : t('billing.sentForApproval')}</p>
          {result.receipt && <p className="font-medium text-navy-900">{t('billing.receipt')}: {result.receipt}</p>}
          <div className="flex gap-2">
            {result.approved && (
              <button className={btn} onClick={() => paymentsApi.printReceipt(result.id, result.receipt ?? String(result.id)).catch(async (e) => setError(await blobErrorMessage(e, t('somethingWrong'))))}>
                🖨 {t('billing.printReceipt')}
              </button>
            )}
            <button className={btnGhost} onClick={onDone}>
              {t('common.close')}
            </button>
          </div>
          {error && <p className="text-status-due">{error}</p>}
        </div>
      </Modal>
    );
  }

  return (
    <Modal title={`${t('billing.payTitle')} — ${bill.flat_no ?? bill.flat_id}${bill.owner ? ` (${bill.owner})` : ''}`} onClose={onClose}>
      <form onSubmit={submit} className="space-y-3 text-sm text-navy-900">
        <div className="rounded-lg bg-brand-50 p-3">
          <p className="flex justify-between">
            <span>{t('billing.colService')}</span>
            <span>{money(bill.service_charge)}</span>
          </p>
          <p className="flex justify-between">
            <span>{t('billing.colGas')}</span>
            <span>{money(bill.gas_amount)}</span>
          </p>
          <p className="flex justify-between">
            <span>{t('billing.colPrevDue')}</span>
            <span>{money(bill.previous_due)}</span>
          </p>
          <p className="flex justify-between border-t border-brand-200 pt-1 font-medium">
            <span>{t('billing.colTotal')}</span>
            <span>{money(bill.total_payable)}</span>
          </p>
          <p className="flex justify-between">
            <span>{t('billing.colPaid')}</span>
            <span>{money(bill.paid_amount)}</span>
          </p>
          {Number(bill.pending_amount) > 0 && (
            <p className="flex justify-between text-status-partial">
              <span>{t('billing.pendingNote')}</span>
              <span>{money(bill.pending_amount)}</span>
            </p>
          )}
          <p className="flex justify-between font-semibold">
            <span>{t('billing.collectable')}</span>
            <span>{money(collectable(bill))}</span>
          </p>
        </div>
        <p className="text-xs text-navy-700">{t('billing.breakdown')}</p>

        <div className="grid grid-cols-2 gap-2">
          <label className="col-span-2 text-navy-700">
            {t('common.amount')}
            <div className="mt-1 flex gap-2">
              <input required type="number" min="0.01" step="0.01" max={collectable(bill)} className={input} value={amount} onChange={(e) => setAmount(e.target.value)} />
              <button type="button" className={btnGhost} onClick={() => setAmount(String(collectable(bill)))}>
                {t('billing.fullDue')}
              </button>
            </div>
          </label>
          <label className="text-navy-700">
            {t('billing.method')}
            <select className={`${input} mt-1`} value={method} onChange={(e) => setMethod(e.target.value)}>
              {['CASH', 'BANK', 'BKASH', 'NAGAD'].map((m) => (
                <option key={m}>{m}</option>
              ))}
            </select>
          </label>
          <label className="text-navy-700">
            {t('billing.date')}
            <input required type="date" className={`${input} mt-1`} value={date} onChange={(e) => setDate(e.target.value)} />
          </label>
        </div>
        <input className={input} placeholder={t('billing.reference')} value={reference} onChange={(e) => setReference(e.target.value)} />
        {error && <p className="text-status-due">{error}</p>}
        <button type="submit" disabled={busy} className={`${btn} w-full`}>
          {t('billing.pay')}
        </button>
      </form>
    </Modal>
  );
}
