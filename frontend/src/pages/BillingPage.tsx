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
  const { t, lang } = useLanguage();
  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));

  const now = new Date();
  const [year, setYear] = useState(now.getFullYear());
  const [month, setMonth] = useState(now.getMonth() + 1);
  const [bills, setBills] = useState<BillRow[]>([]);
  const [fiscal, setFiscal] = useState<billingApi.BillingMonths | null>(null);
  const fiscalYearOf = (y: number, m: number) => (m >= 7 ? y : y - 1);
  const [onlyDue, setOnlyDue] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [paying, setPaying] = useState<BillRow | null>(null);
  const [editing, setEditing] = useState<BillRow | null>(null);

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

  // the strip of twelve months for the July-June year the chosen month falls in
  const fy = fiscalYearOf(year, month);
  const loadMonths = useCallback(async () => {
    try {
      setFiscal(await billingApi.getBillingMonths(fy));
    } catch {
      setFiscal(null);
    }
  }, [fy]);
  useEffect(() => {
    loadMonths();
  }, [loadMonths, bills.length]);
  const MONTHS = lang === 'bn'
    ? ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর']
    : ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  const step = (by: number) => {
    const idx = year * 12 + (month - 1) + by;
    setYear(Math.floor(idx / 12));
    setMonth((idx % 12) + 1);
  };

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
          <div className="flex items-end gap-1">
            <button onClick={() => step(-1)} className={btnGhost} aria-label="previous month">‹</button>
            <div>
              <label className="mb-1 block text-sm text-navy-900">{t('common.month')}</label>
              <select value={month} onChange={(e) => setMonth(Number(e.target.value))} className="rounded-lg border border-brand-200 px-3 py-2">
                {MONTHS.map((n, i) => (
                  <option key={n} value={i + 1}>{n}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="mb-1 block text-sm text-navy-900">{t('common.year')}</label>
              <select value={year} onChange={(e) => setYear(Number(e.target.value))} className="rounded-lg border border-brand-200 px-3 py-2">
                {Array.from({ length: 12 }, (_, i) => now.getFullYear() + 2 - i).map((y) => (
                  <option key={y} value={y}>{y}</option>
                ))}
              </select>
            </div>
            <button onClick={() => step(1)} className={btnGhost} aria-label="next month">›</button>
            <button onClick={() => { setYear(now.getFullYear()); setMonth(now.getMonth() + 1); }} className={btnGhost}>
              {lang === 'bn' ? 'এই মাস' : 'This month'}
            </button>
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

        {fiscal && (
          <div className="mb-4 rounded-xl border border-brand-200 bg-white p-3 shadow-sm">
            <p className="mb-2 text-xs font-medium text-navy-700">
              {lang === 'bn' ? 'অর্থবছর' : 'Fiscal year'} {fiscal.label} {lang === 'bn' ? '(জুলাই–জুন)' : '(July–June)'}
            </p>
            <div className="grid grid-cols-3 gap-2 sm:grid-cols-6 lg:grid-cols-12">
              {fiscal.months.map((m) => {
                const current = m.year === year && m.month === month;
                const billed = m.bill_count > 0;
                const owing = billed && Number(m.due) > 0;
                return (
                  <button
                    key={`${m.year}-${m.month}`}
                    onClick={() => { setYear(m.year); setMonth(m.month); }}
                    className={`rounded-lg border px-2 py-2 text-left text-xs transition ${current ? 'border-brand-600 bg-brand-600 text-white' : 'border-brand-200 hover:bg-brand-100'}`}
                  >
                    <span className="block font-medium">{MONTHS[m.month - 1].slice(0, lang === 'bn' ? 6 : 3)} {String(m.year).slice(2)}</span>
                    <span className={`block ${current ? 'text-white/90' : billed ? (owing ? 'text-status-due' : 'text-status-paid') : 'text-navy-700'}`}>
                      {billed ? (owing ? money(m.due) + (lang === 'bn' ? ' বাকি' : ' due') : lang === 'bn' ? 'পরিশোধিত' : 'Paid') : lang === 'bn' ? 'বিল হয়নি' : 'Not billed'}
                    </span>
                  </button>
                );
              })}
            </div>
          </div>
        )}

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
                  <th className="px-3 py-3 text-right">{t('billing.colPrevDue')}</th>
                  <th className="px-3 py-3 text-right">{t('billing.colService')}</th>
                  <th className="px-3 py-3 text-right">{t('billing.colGas')}</th>
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
                    <td className="px-3 py-2 text-right text-navy-700">{money(b.previous_due)}</td>
                    <td className="px-3 py-2 text-right">{money(b.service_charge)}</td>
                    <td className="px-3 py-2 text-right">{Number(b.gas_amount) > 0 ? money(b.gas_amount) : <span className="text-navy-700" title={lang === 'bn' ? 'গ্যাস বিল এখনো যোগ হয়নি' : 'Gas not added yet'}>—</span>}</td>
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
                      {can('billing.manage') && (
                        <button className="mr-3 font-medium text-navy-700 hover:underline" onClick={() => setEditing(b)}>
                          {lang === 'bn' ? 'সংশোধন' : 'Edit'}
                        </button>
                      )}
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
        {bills.length > 0 && bills.some((b) => Number(b.gas_amount) === 0) && (
          <p className="mt-2 text-xs text-navy-700">
            {lang === 'bn'
              ? '"—" মানে এই বিলে গ্যাস এখনো যোগ হয়নি। গ্যাস পেজে মিটার রিডিং দিয়ে "বিলে যোগ করুন" চাপলে যোগ হবে; ভুল হলে ডানের "সংশোধন" দিয়ে হাতে ঠিক করা যায়।'
              : '"—" means gas is not on this bill yet. Enter the meter readings on the Gas page and apply them to the bills; a wrong bill can be fixed with "Edit".'}
          </p>
        )}
      </div>

      {editing && (
        <EditBillModal
          bill={editing}
          onClose={() => setEditing(null)}
          onDone={() => {
            setEditing(null);
            loadRegister();
            loadMonths();
          }}
        />
      )}

      {paying && (
        <PayModal
          bill={paying}
          period={`${MONTHS[month - 1]} ${year}`}
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

const BANKS = ['Dutch-Bangla Bank', 'Islami Bank Bangladesh', 'Sonali Bank', 'Janata Bank', 'Agrani Bank', 'BRAC Bank', 'City Bank', 'Eastern Bank', 'Prime Bank', 'Mutual Trust Bank', 'Southeast Bank', 'Standard Chartered'];
const PROVIDERS: { value: string; label: string }[] = [
  { value: 'BKASH', label: 'bKash' },
  { value: 'NAGAD', label: 'Nagad' },
  { value: 'ROCKET', label: 'Rocket' },
  { value: 'UPAY', label: 'Upay' },
];

/**
 * Taking a payment on one bill: what the bill is made of, how much to take now (this month's bill, the whole balance, or any amount
 * typed in), and how it was paid - cash, bank / cheque, or mobile banking, each with the reference the books need to match it later.
 */
function PayModal({ bill, period, canApprove, onClose, onDone }: { bill: BillRow; period: string; canApprove: boolean; onClose: () => void; onDone: () => void }) {
  const { t, lang } = useLanguage();
  const bn = lang === 'bn';
  const owing = collectable(bill);
  // this month's own charges, as far as they are still unpaid - the previous balance is only taken on request
  const thisMonth = Math.min(owing, Number(bill.service_charge) + Number(bill.gas_amount) + Number(bill.other_charges) + Number(bill.late_fee));
  const [amount, setAmount] = useState(String(thisMonth > 0 ? thisMonth : owing));
  const [kind, setKind] = useState<'CASH' | 'BANK' | 'MOBILE'>('CASH');
  const [provider, setProvider] = useState('BKASH');
  const [bankName, setBankName] = useState('');
  const [reference, setReference] = useState('');
  const [date, setDate] = useState(localToday());
  const [remarks, setRemarks] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<{ id: number; approved: boolean; receipt: string | null } | null>(null);

  const entered = Number(amount) || 0;
  const over = entered > owing + 0.0001;
  const remaining = Math.max(0, owing - entered);
  const needsRef = kind !== 'CASH';
  const ready = entered > 0 && !over && (kind === 'CASH' || (reference.trim() !== '' && (kind !== 'BANK' || bankName.trim() !== '')));

  async function submit(e: FormEvent) {
    e.preventDefault();
    if (!ready) return;
    setError(null);
    setBusy(true);
    try {
      const method = kind === 'MOBILE' ? provider : kind;
      let p = await paymentsApi.createPayment({
        flat_id: bill.flat_id,
        bill_id: bill.id,
        amount,
        payment_date: date,
        method,
        reference_no: needsRef ? reference.trim() : undefined,
        bank_name: kind === 'BANK' ? bankName.trim() : undefined,
        remarks: remarks.trim() || undefined,
      });
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
        <div className="space-y-4 text-center text-sm">
          <div className={`mx-auto flex h-14 w-14 items-center justify-center rounded-full text-3xl ${result.approved ? 'bg-status-paid/15 text-status-paid' : 'bg-status-partial/15 text-status-partial'}`}>{result.approved ? '✓' : '⏳'}</div>
          <p className={`text-base font-medium ${result.approved ? 'text-status-paid' : 'text-navy-900'}`}>{result.approved ? t('billing.paidOk') : t('billing.sentForApproval')}</p>
          <p className="text-2xl font-semibold text-navy-900">{money(amount)}</p>
          {result.receipt && (
            <p className="text-navy-700">
              {t('billing.receipt')}: <span className="font-medium text-navy-900">{result.receipt}</span>
            </p>
          )}
          <div className="flex justify-center gap-2">
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

  const line = (label: string, value: string, strong = false) => (
    <p className={`flex justify-between ${strong ? 'border-t border-brand-200 pt-1 font-semibold text-navy-900' : 'text-navy-700'}`}>
      <span>{label}</span>
      <span>{value}</span>
    </p>
  );
  const tile = (value: typeof kind, icon: string, label: string) => (
    <button
      type="button"
      onClick={() => setKind(value)}
      className={`flex flex-col items-center gap-1 rounded-xl border-2 px-2 py-3 text-xs font-medium transition ${kind === value ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-brand-200 text-navy-700 hover:bg-brand-50'}`}
    >
      <span className="text-2xl leading-none">{icon}</span>
      {label}
    </button>
  );
  const quick = (label: string, value: number) => (
    <button
      type="button"
      disabled={value <= 0}
      onClick={() => setAmount(String(value))}
      className={`rounded-lg border px-3 py-2 text-left text-xs transition disabled:opacity-40 ${Number(amount) === value && value > 0 ? 'border-brand-600 bg-brand-600 text-white' : 'border-brand-200 text-navy-900 hover:bg-brand-50'}`}
    >
      <span className="block">{label}</span>
      <span className="block text-sm font-semibold">{money(value)}</span>
    </button>
  );

  return (
    <Modal title={t('billing.payTitle')} onClose={onClose}>
      <form onSubmit={submit} className="space-y-4 text-sm text-navy-900">
        {/* who and which month */}
        <div className="rounded-xl bg-gradient-to-r from-brand-700 to-brand-600 p-3 text-white">
          <div className="flex items-start justify-between gap-2">
            <div>
              <p className="text-lg font-semibold">{bn ? 'ফ্ল্যাট' : 'Flat'} {bill.flat_no ?? bill.flat_id}</p>
              {bill.owner && <p className="text-xs text-white/85">{bn ? 'মালিক' : 'Owner'}: {bill.owner}</p>}
              {bill.tenant && <p className="text-xs text-white/85">{bn ? 'ভাড়াটিয়া' : 'Tenant'}: {bill.tenant}</p>}
            </div>
            <div className="text-right text-xs">
              <p className="font-medium">{period}</p>
              <p className="text-white/85">{bn ? 'শেষ তারিখ' : 'Due date'}: {bill.due_date}</p>
              <span className="mt-1 inline-block rounded-full bg-white/20 px-2 py-0.5">{t(`billing.status.${bill.status}` as TranslationKey)}</span>
            </div>
          </div>
        </div>

        {/* what the bill is made of */}
        <div className="space-y-1 rounded-xl border border-brand-200 bg-brand-50/60 p-3">
          {Number(bill.previous_due) > 0 && line(t('billing.colPrevDue'), money(bill.previous_due))}
          {line(t('billing.colService'), money(bill.service_charge))}
          {line(t('billing.colGas'), Number(bill.gas_amount) > 0 ? money(bill.gas_amount) : bn ? 'যোগ হয়নি' : 'not added')}
          {Number(bill.other_charges) > 0 && line(bn ? 'অন্যান্য চার্জ' : 'Other charges', money(bill.other_charges))}
          {Number(bill.late_fee) > 0 && line(bn ? 'বিলম্ব ফি' : 'Late fee', money(bill.late_fee))}
          {line(t('billing.colTotal'), money(bill.total_payable), true)}
          {Number(bill.paid_amount) > 0 && line(t('billing.colPaid'), '− ' + money(bill.paid_amount))}
          {Number(bill.pending_amount) > 0 && <p className="flex justify-between text-status-partial"><span>{t('billing.pendingNote')}</span><span>− {money(bill.pending_amount)}</span></p>}
          <p className="flex items-center justify-between border-t border-brand-200 pt-1 text-base font-semibold text-status-due">
            <span>{t('billing.collectable')}</span>
            <span>{money(owing)}</span>
          </p>
        </div>

        {/* how much */}
        <div>
          <p className="mb-1 font-medium">{t('common.amount')} *</p>
          <div className="mb-2 grid grid-cols-2 gap-2">
            {quick(bn ? 'এই মাসের বিল' : "This month's bill", thisMonth)}
            {quick(bn ? 'পুরো বকেয়া' : 'Full balance', owing)}
          </div>
          <div className="relative">
            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-navy-700">৳</span>
            <input required type="number" min="0.01" step="0.01" max={owing} className={`${input} pl-8 text-lg font-semibold`} value={amount} onChange={(e) => setAmount(e.target.value)} />
          </div>
          {over ? (
            <p className="mt-1 text-xs text-status-due">{bn ? 'বকেয়ার বেশি নেওয়া যাবে না' : 'More than the balance'} ({money(owing)})</p>
          ) : (
            <p className="mt-1 text-xs text-navy-700">{bn ? 'এই পেমেন্টের পর বাকি থাকবে' : 'Balance after this payment'}: <span className="font-medium">{money(remaining)}</span></p>
          )}
        </div>

        {/* how it was paid */}
        <div>
          <p className="mb-1 font-medium">{t('billing.method')} *</p>
          <div className="grid grid-cols-3 gap-2">
            {tile('CASH', '💵', bn ? 'নগদ' : 'Cash')}
            {tile('BANK', '🏦', bn ? 'ব্যাংক / চেক' : 'Bank / Cheque')}
            {tile('MOBILE', '📱', bn ? 'মোবাইল ব্যাংকিং' : 'Mobile banking')}
          </div>
        </div>

        {kind === 'BANK' && (
          <div className="grid grid-cols-1 gap-2 rounded-xl border border-brand-200 p-3 sm:grid-cols-2">
            <label className="text-navy-700">
              {bn ? 'ব্যাংকের নাম' : 'Bank name'} *
              <input required list="bank-names" className={`${input} mt-1`} placeholder="e.g. Dutch-Bangla Bank" value={bankName} onChange={(e) => setBankName(e.target.value)} />
              <datalist id="bank-names">
                {BANKS.map((b) => (
                  <option key={b} value={b} />
                ))}
              </datalist>
            </label>
            <label className="text-navy-700">
              {bn ? 'চেক / রেফারেন্স নম্বর' : 'Cheque / Ref number'} *
              <input required className={`${input} mt-1`} placeholder={bn ? 'চেক নং' : 'Cheque No.'} value={reference} onChange={(e) => setReference(e.target.value)} />
            </label>
          </div>
        )}
        {kind === 'MOBILE' && (
          <div className="grid grid-cols-1 gap-2 rounded-xl border border-brand-200 p-3 sm:grid-cols-2">
            <label className="text-navy-700">
              {bn ? 'মোবাইল ব্যাংকিং সেবা' : 'Mobile provider'} *
              <select className={`${input} mt-1`} value={provider} onChange={(e) => setProvider(e.target.value)}>
                {PROVIDERS.map((p) => (
                  <option key={p.value} value={p.value}>{p.label}</option>
                ))}
              </select>
            </label>
            <label className="text-navy-700">
              {bn ? 'ট্রানজেকশন আইডি (TxnID)' : 'Transaction ID (TxnID)'} *
              <input required className={`${input} mt-1`} placeholder="e.g. 9J58AXK2" value={reference} onChange={(e) => setReference(e.target.value)} />
            </label>
          </div>
        )}

        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
          <label className="text-navy-700">
            {bn ? 'পেমেন্টের তারিখ' : 'Payment date'} *
            <input required type="date" max={localToday()} className={`${input} mt-1`} value={date} onChange={(e) => setDate(e.target.value)} />
          </label>
          <label className="text-navy-700 sm:col-span-2">
            {bn ? 'মন্তব্য / নোট' : 'Remarks / Notes'}
            <textarea rows={2} maxLength={500} className={`${input} mt-1`} placeholder={bn ? 'ঐচ্ছিক — যেমন কে টাকা দিলেন' : 'Optional - e.g. who handed over the money'} value={remarks} onChange={(e) => setRemarks(e.target.value)} />
          </label>
        </div>

        {error && <p className="rounded-lg bg-status-due/10 p-2 text-status-due">{error}</p>}
        <p className="text-xs text-navy-700">{canApprove ? (bn ? 'আপনি অনুমোদন করতে পারেন — পেমেন্ট সঙ্গে সঙ্গে খাতায় উঠবে ও রসিদ পাবেন।' : 'You can approve - the payment posts at once and a receipt is issued.') : bn ? 'পেমেন্টটি অনুমোদনের জন্য জমা হবে।' : 'The payment goes for approval.'}</p>
        <button type="submit" disabled={busy || !ready} className={`${btn} w-full py-3 text-base`}>
          {busy ? '…' : `${bn ? 'পেমেন্ট নিন' : 'Take payment'} · ${money(entered)}`}
        </button>
      </form>
    </Modal>
  );
}

/** Fix a bill's amounts by hand. The server recomputes the total; a written reason is required and kept in the audit log. */
function EditBillModal({ bill, onClose, onDone }: { bill: BillRow; onClose: () => void; onDone: () => void }) {
  const { lang } = useLanguage();
  const bn = lang === 'bn';
  const num = (v: string | number) => String(Number(v));
  const [f, setF] = useState({ service_charge: num(bill.service_charge), gas_amount: num(bill.gas_amount), other_charges: num(bill.other_charges), late_fee: num(bill.late_fee) });
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const total = Number(bill.previous_due) + Number(f.service_charge || 0) + Number(f.gas_amount || 0) + Number(f.other_charges || 0) + Number(f.late_fee || 0);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await billingApi.correctBill(bill.id, { ...f, reason });
      onDone();
    } catch (err) {
      setError(apiError(err, bn ? 'কিছু একটা সমস্যা হয়েছে।' : 'Something went wrong.'));
    } finally {
      setBusy(false);
    }
  }

  const field = (key: keyof typeof f, label: string) => (
    <label className="block">
      <span className="mb-1 block text-navy-900">{label}</span>
      <input type="number" min={0} step="0.01" required value={f[key]} onChange={(e) => setF({ ...f, [key]: e.target.value })} className={input} />
    </label>
  );

  return (
    <Modal title={`${bn ? 'বিল সংশোধন' : 'Correct bill'} — ${bill.flat_no ?? bill.flat_id}`} onClose={onClose}>
      <form onSubmit={submit} className="space-y-3 text-sm text-navy-900">
        <p className="rounded-lg bg-brand-50 p-2 text-navy-700">
          {bn ? 'আগের বাকি' : 'Previous due'}: {money(bill.previous_due)} · {bn ? 'ইতিমধ্যে পরিশোধিত' : 'Already paid'}: {money(bill.paid_amount)}
        </p>
        <div className="grid grid-cols-2 gap-3">
          {field('service_charge', bn ? 'সার্ভিস চার্জ' : 'Service charge')}
          {field('gas_amount', bn ? 'গ্যাস বিল' : 'Gas bill')}
          {field('other_charges', bn ? 'অন্যান্য চার্জ' : 'Other charges')}
          {field('late_fee', bn ? 'বিলম্ব ফি' : 'Late fee')}
        </div>
        <p className="flex justify-between border-t border-brand-200 pt-2 font-medium">
          <span>{bn ? 'নতুন মোট' : 'New total'}</span>
          <span>{money(total)}</span>
        </p>
        <label className="block">
          <span className="mb-1 block">{bn ? 'সংশোধনের কারণ (আবশ্যক)' : 'Reason (required)'}</span>
          <input value={reason} onChange={(e) => setReason(e.target.value)} required minLength={3} maxLength={500} className={input} />
        </label>
        {error && <p className="text-status-due">{error}</p>}
        <div className="flex justify-end gap-2">
          <button type="button" className={btnGhost} onClick={onClose}>
            {bn ? 'বাতিল' : 'Cancel'}
          </button>
          <button className={btn} disabled={busy}>
            {bn ? 'সংরক্ষণ' : 'Save'}
          </button>
        </div>
      </form>
    </Modal>
  );
}
