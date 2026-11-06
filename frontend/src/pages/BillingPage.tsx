import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import type { TranslationKey } from '../i18n/translations';
import LanguageToggle from '../components/LanguageToggle';
import Modal from '../components/Modal';
import Sheet from '../components/Sheet';
import GasReadingSheet from '../components/GasReadingSheet';
import FlatLabel from '../components/FlatLabel';
import { localToday } from '../components/HallCalendar';
import { apiError } from '../api/hall';
import { blobErrorMessage, openPdfAfter } from '../utils/download';
import * as billingApi from '../api/billing';
import * as gasApi from '../api/gas';
import * as paymentsApi from '../api/payments';
import * as workApi from '../api/workBudgets';
import type { FlatCollection } from '../api/workBudgets';
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
  const bn = lang === 'bn';
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
  const [payPeriod, setPayPeriod] = useState('');
  const [payNote, setPayNote] = useState<string | null>(null);
  const [editing, setEditing] = useState<BillRow | null>(null);
  const [askingReadings, setAskingReadings] = useState(false);

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

  // An older month's balance moves on into the flat's newest bill, so money is taken there - with the dues together.
  async function startPay(b: BillRow) {
    const here = `${MONTHS[month - 1]} ${year}`;
    setPayNote(null);
    setPayPeriod(here);
    if (!b.newest_year || !b.newest_month) {
      setPaying(b);
      return;
    }
    try {
      const rows = await billingApi.getBillingRegister(b.newest_year, b.newest_month);
      const latest = rows.find((r) => r.flat_id === b.flat_id);
      if (!latest) {
        setPaying(b);
        return;
      }
      const there = `${MONTHS[b.newest_month - 1]} ${b.newest_year}`;
      setPayNote(
        bn
          ? `${here}-এর বাকি ${there}-এর বিলে যোগ হয়ে গেছে। তাই টাকা ${there}-এর বিলে নেওয়া হচ্ছে — আগের বকেয়া ও এই মাসের চাঁদা একসঙ্গে দেখানো আছে।`
          : `The balance of ${here} has moved into the ${there} bill, so the payment is taken there - the earlier dues and this month's charge are shown together.`,
      );
      setPayPeriod(there);
      setPaying(latest);
    } catch (err) {
      setMessage(apiError(err, t('somethingWrong')));
    }
  }

  async function generateNow(allowWithoutGas: boolean) {
    setLoading(true);
    setMessage(null);
    try {
      const res = await billingApi.generateBilling(year, month, allowWithoutGas);
      setAskingReadings(false);
      setMessage(res.message + ` (${res.bill_count})`);
      loadRegister();
    } catch (err: any) {
      // the server also guards this: readings missing means the sheet opens instead of bills being made
      if (err.response?.data?.errors?.gas) setAskingReadings(true);
      else setMessage(err.response?.data?.errors?.period?.[0] || err.response?.data?.message || 'Error');
    } finally {
      setLoading(false);
    }
  }

  // Generate Bills: the month's gas readings come first. Everyone's reading in -> make the bills; otherwise ask for them.
  async function handleGenerate() {
    setLoading(true);
    setMessage(null);
    try {
      const sheet = await gasApi.getReadingSheet(year, month);
      if (sheet.missing_flats > 0) {
        setAskingReadings(true);
        return;
      }
    } catch (err: any) {
      setMessage(err.response?.data?.message || 'Error');
      return;
    } finally {
      setLoading(false);
    }
    await generateNow(false);
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
                      {b.newest_month && b.due > 0 && (
                        <span className="block text-xs font-normal text-navy-700">
                          ↪ {bn ? `${MONTHS[b.newest_month - 1]}-এর বিলে যোগ` : `moved to ${MONTHS[b.newest_month - 1]} bill`}
                        </span>
                      )}
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
                        <button className="font-medium text-brand-700 hover:underline" onClick={() => startPay(b)}>
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

      {askingReadings && (
        <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy-900/40 px-3 py-6">
          <div className="w-full max-w-3xl rounded-xl border border-brand-200 bg-brand-50 p-4 shadow-lg sm:p-5">
            <div className="mb-3 flex items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-semibold text-navy-900">{bn ? 'আগে গ্যাসের রিডিং দিন' : 'Gas readings first'}</h2>
                <p className="text-sm text-navy-700">
                  {bn
                    ? 'বিল তৈরির আগে এই মাসের গ্যাস মিটারের রিডিং লাগবে। সবার রিডিং দিয়ে সংরক্ষণ করলে বিল নিজে থেকেই তৈরি হবে, গ্যাসের টাকা সহ।'
                    : "This month's gas meter readings are needed before the bills. Save everyone's reading and the bills are made right away, gas included."}
                </p>
              </div>
              <button onClick={() => setAskingReadings(false)} className="rounded-lg px-2 py-1 text-navy-700 hover:bg-brand-100" aria-label="close">
                ✕
              </button>
            </div>
            <GasReadingSheet
              year={year}
              month={month}
              saveLabel={bn ? 'রিডিং সংরক্ষণ করে বিল তৈরি করুন' : 'Save readings and generate bills'}
              onSaved={({ missing }) => {
                if (missing === 0) generateNow(false);
                else setMessage(bn ? `এখনও ${missing}টি ফ্ল্যাটের রিডিং বাকি।` : `${missing} flats still have no reading.`);
              }}
              footer={
                <div className="mt-3 border-t border-brand-100 pt-3 text-right">
                  <button
                    disabled={loading}
                    onClick={() => generateNow(true)}
                    className="text-xs text-navy-700 underline hover:text-navy-900 disabled:opacity-50"
                  >
                    {bn ? 'রিডিং ছাড়াই বিল তৈরি করুন (গ্যাস ০ থাকবে, পরে যোগ হবে)' : 'Generate without readings (gas stays 0, added later)'}
                  </button>
                </div>
              }
            />
          </div>
        </div>
      )}

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
          period={payPeriod || `${MONTHS[month - 1]} ${year}`}
          notice={payNote}
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
const PROVIDERS: { value: string; label: string; tone: string }[] = [
  { value: 'BKASH', label: 'bKash', tone: 'bg-pink-600' },
  { value: 'NAGAD', label: 'Nagad', tone: 'bg-orange-500' },
  { value: 'ROCKET', label: 'Rocket', tone: 'bg-purple-600' },
  { value: 'UPAY', label: 'Upay', tone: 'bg-sky-600' },
];

/**
 * Taking a payment on one bill, in one short sheet: how much (this month's bill, the whole balance, or any amount typed),
 * how it was paid (cash / bank / mobile banking, each with the reference the books need), the date and an optional note.
 * The bill's breakdown folds away until asked for.
 */
function PayModal({ bill, period, notice, canApprove, onClose, onDone }: { bill: BillRow; period: string; notice?: string | null; canApprove: boolean; onClose: () => void; onDone: () => void }) {
  const { t, lang } = useLanguage();
  const bn = lang === 'bn';
  const L = (b: string, e: string) => (bn ? b : e);
  const { user } = useAuth();
  const canCollect = !!user && (user.roles.includes('super_admin') || user.permissions.includes('contribution.collect'));
  // every purpose is paid and owed on its own: the monthly subscription (this bill) or one special collection
  const [purpose, setPurpose] = useState<'BILL' | number>('BILL');
  const [collections, setCollections] = useState<FlatCollection[]>([]);
  useEffect(() => {
    if (canCollect) workApi.collectionsForFlat(bill.flat_id).then(setCollections).catch(() => undefined);
  }, [bill.flat_id, canCollect]);
  const work = typeof purpose === 'number' ? collections.find((c) => c.work_id === purpose) : undefined;
  const owing = work ? Number(work.due) : collectable(bill);
  // this month's own charges as far as they are still unpaid - the previous balance is only taken on request
  const thisMonth = work ? owing : Math.min(owing, Number(bill.service_charge) + Number(bill.gas_amount) + Number(bill.other_charges) + Number(bill.late_fee));
  const [amount, setAmount] = useState(String(owing));
  const choosePurpose = (p: 'BILL' | number) => {
    setPurpose(p);
    const w = typeof p === 'number' ? collections.find((c) => c.work_id === p) : undefined;
    const o = w ? Number(w.due) : collectable(bill);
    setAmount(String(o));
    setError(null);
  };
  const [kind, setKind] = useState<'CASH' | 'BANK' | 'MOBILE'>('CASH');
  const [provider, setProvider] = useState('BKASH');
  const [bankName, setBankName] = useState('');
  const [reference, setReference] = useState('');
  const [date, setDate] = useState(localToday());
  const [remarks, setRemarks] = useState('');
  const [showNote, setShowNote] = useState(false);
  const [showBreakdown, setShowBreakdown] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<{ id: number; approved: boolean; receipt: string | null; special?: string } | null>(null);

  const entered = Number(amount) || 0;
  const over = entered > owing + 0.0001;
  const remaining = Math.max(0, owing - entered);
  const needsRef = kind !== 'CASH';
  const ready = entered > 0 && !over && (kind === 'CASH' || (reference.trim() !== '' && (kind !== 'BANK' || bankName.trim() !== '')));
  const who = [bill.owner, bill.tenant].filter(Boolean).join(' · ');
  const title = `${L('ফ্ল্যাট', 'Flat')} ${bill.flat_no ?? bill.flat_id}`;

  async function submit(e?: FormEvent) {
    e?.preventDefault();
    if (!ready || busy) return;
    setError(null);
    setBusy(true);
    try {
      if (work) {
        // a special collection: its own receipt and its own ledger
        const r = await workApi.collectForWork(work.work_id, {
          flat_id: bill.flat_id, amount, received_on: date, method: kind === 'MOBILE' ? provider : kind,
          reference_no: needsRef ? reference.trim() : undefined,
          notes: [kind === 'BANK' && bankName.trim() ? `${bankName.trim()}` : '', remarks.trim()].filter(Boolean).join(' — ') || undefined,
        });
        setResult({ id: 0, approved: true, receipt: r.receipt_no, special: work.title });
        return;
      }
      const take = async () => {
        let p = await paymentsApi.createPayment({
          flat_id: bill.flat_id,
          bill_id: bill.id,
          amount,
          payment_date: date,
          method: kind === 'MOBILE' ? provider : kind,
          reference_no: needsRef ? reference.trim() : undefined,
          bank_name: kind === 'BANK' ? bankName.trim() : undefined,
          remarks: remarks.trim() || undefined,
        });
      // whoever may approve (admin, president) posts it straight away; everyone else leaves it for them
        if (canApprove) p = await paymentsApi.approvePayment(p.id);
        return p;
      };
      // whoever may approve posts it straight away - and the money receipt opens at once, ready to print or save as PDF
      const p = canApprove
        ? await openPdfAfter(take, (x) => (x.receipt_no ? { path: `/payments/${x.id}/receipt`, filename: `receipt-${x.receipt_no}.pdf` } : null))
        : await take();
      setResult({ id: p.id, approved: p.status === 'APPROVED', receipt: p.receipt_no });
    } catch (err) {
      setError(apiError(err, t('somethingWrong')));
    } finally {
      setBusy(false);
    }
  }

  if (result) {
    return (
      <Sheet title={title} sub={period} onClose={onDone}>
        <div className="space-y-3 py-4 text-center">
          <div className={`mx-auto flex h-16 w-16 items-center justify-center rounded-full text-3xl ${result.approved ? 'bg-status-paid/15 text-status-paid' : 'bg-status-partial/15 text-status-partial'}`}>{result.approved ? '✓' : '⏳'}</div>
          <p className="text-3xl font-bold text-navy-900">{money(amount)}</p>
          <p className={`text-sm font-medium ${result.approved ? 'text-status-paid' : 'text-navy-900'}`}>{result.special ? `${result.special} — ${L('আদায় নেওয়া হয়েছে', 'collected')}` : result.approved ? t('billing.paidOk') : t('billing.sentForApproval')}</p>
          {result.receipt && (
            <p className="text-sm text-navy-700">
              {t('billing.receipt')}: <span className="font-semibold text-navy-900">{result.receipt}</span>
            </p>
          )}
          <div className="flex justify-center gap-2 pt-2">
            {result.approved && !result.special && (
              <button className={btn} onClick={() => paymentsApi.printReceipt(result.id, result.receipt ?? String(result.id)).catch(async (e) => setError(await blobErrorMessage(e, t('somethingWrong'))))}>
                🖨 {t('billing.printReceipt')}
              </button>
            )}
            <button className={btnGhost} onClick={onDone}>
              {t('common.close')}
            </button>
          </div>
          {error && <p className="text-sm text-status-due">{error}</p>}
        </div>
      </Sheet>
    );
  }

  const chip = (label: string, value: number) => (
    <button
      type="button"
      disabled={value <= 0}
      onClick={() => setAmount(String(value))}
      className={`flex-1 rounded-full border px-3 py-1.5 text-xs font-medium transition disabled:opacity-40 ${Number(amount) === value && value > 0 ? 'border-brand-600 bg-brand-600 text-white' : 'border-brand-200 text-navy-900 hover:bg-brand-50'}`}
    >
      {label} · {money(value)}
    </button>
  );
  const seg = (value: typeof kind, icon: string, label: string) => (
    <button
      type="button"
      onClick={() => setKind(value)}
      className={`flex flex-1 items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-xs font-medium transition ${kind === value ? 'bg-white text-brand-700 shadow' : 'text-navy-700 hover:text-navy-900'}`}
    >
      <span className="text-base leading-none">{icon}</span>
      {label}
    </button>
  );
  const small = 'w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500';
  const row = (label: string, value: string, strong = false) => (
    <p className={`flex justify-between ${strong ? 'font-semibold text-navy-900' : 'text-navy-700'}`}>
      <span>{label}</span>
      <span>{value}</span>
    </p>
  );

  return (
    <Sheet
      title={title}
      sub={`${who ? who + ' · ' : ''}${period}`}
      onClose={onClose}
      footer={
        <div className="space-y-2">
          {error && <p className="rounded-lg bg-status-due/10 px-3 py-2 text-xs text-status-due">{error}</p>}
          <button type="button" onClick={() => submit()} disabled={busy || !ready} className="w-full rounded-xl bg-brand-600 py-3 text-sm font-semibold text-white shadow transition hover:bg-brand-700 disabled:opacity-40">
            {busy ? '…' : `${L('পেমেন্ট নিন', 'Take payment')} · ${money(entered)}`}
          </button>
          <p className="text-center text-[11px] text-navy-700">{work ? L('এই খাতের নিজস্ব খাতায় সঙ্গে সঙ্গে উঠবে ও রসিদ নম্বর পাবেন', 'Goes into this collection\u2019s own ledger at once, with a receipt number') : canApprove ? L('সঙ্গে সঙ্গে খাতায় উঠবে ও রসিদ পাবেন', 'Posts at once and a receipt is issued') : L('অনুমোদনের জন্য জমা হবে', 'Goes for approval')}</p>
        </div>
      }
    >
      <form onSubmit={submit} className="space-y-4">
        {notice && <p className="rounded-lg bg-amber-50 p-3 text-xs leading-relaxed text-amber-900">{notice}</p>}
        {collections.length > 0 && (
          <div>
            <p className="mb-1 text-xs font-medium text-navy-700">{L('কীসের জন্য টাকা?', 'What is this payment for?')}</p>
            <div className="flex flex-wrap gap-1.5">
              <button type="button" onClick={() => choosePurpose('BILL')} className={`rounded-full border px-3 py-1.5 text-xs font-medium transition ${purpose === 'BILL' ? 'border-brand-600 bg-brand-600 text-white' : 'border-brand-200 text-navy-700 hover:bg-brand-50'}`}>
                {L('মাসিক চাঁদা', 'Subscription')} · {money(collectable(bill))}
              </button>
              {collections.map((c) => (
                <button key={c.work_id} type="button" onClick={() => choosePurpose(c.work_id)} className={`rounded-full border px-3 py-1.5 text-xs font-medium transition ${purpose === c.work_id ? 'border-brand-600 bg-brand-600 text-white' : 'border-brand-200 text-navy-700 hover:bg-brand-50'}`}>
                  {c.title} · {money(c.due)}
                </button>
              ))}
            </div>
          </div>
        )}

        {work ? (
          <div className="rounded-2xl bg-gradient-to-br from-brand-700 to-brand-600 p-4 text-white">
            <p className="text-xs text-white/80">{work.title} — {L('এই খাতে বাকি', 'still due for this')}</p>
            <p className="text-2xl font-bold leading-tight">{money(work.due)}</p>
            <p className="mt-1 text-xs text-white/85">{L('ভাগ', 'Share')} {money(work.share)} · {L('দিয়েছেন', 'paid')} {money(work.paid)}</p>
          </div>
        ) : (
        /* the balance, in one line; the breakdown folds away */
        <div className="rounded-2xl bg-gradient-to-br from-brand-700 to-brand-600 p-4 text-white">
          <div className="flex items-end justify-between">
            <div>
              <p className="text-xs text-white/80">{t('billing.collectable')}</p>
              <p className="text-2xl font-bold leading-tight">{money(owing)}</p>
            </div>
            <div className="text-right text-xs text-white/85">
              <span className="rounded-full bg-white/20 px-2 py-0.5">{t(`billing.status.${bill.status}` as TranslationKey)}</span>
              <p className="mt-1">{L('শেষ তারিখ', 'Due')} {bill.due_date}</p>
            </div>
          </div>
          <button type="button" onClick={() => setShowBreakdown(!showBreakdown)} className="mt-2 text-xs text-white/85 underline-offset-2 hover:underline">
            {showBreakdown ? '▴' : '▾'} {L('হিসাবের বিস্তারিত', 'Breakdown')}
          </button>
          {showBreakdown && (
            <div className="mt-2 space-y-0.5 border-t border-white/20 pt-2 text-xs text-white/90">
              {Number(bill.previous_due) > 0 && row(t('billing.colPrevDue'), money(bill.previous_due))}
              {row(t('billing.colService'), money(bill.service_charge))}
              {row(t('billing.colGas'), Number(bill.gas_amount) > 0 ? money(bill.gas_amount) : L('যোগ হয়নি', 'not added'))}
              {Number(bill.other_charges) > 0 && row(L('অন্যান্য', 'Other'), money(bill.other_charges))}
              {Number(bill.late_fee) > 0 && row(L('বিলম্ব ফি', 'Late fee'), money(bill.late_fee))}
              {Number(bill.paid_amount) > 0 && row(t('billing.colPaid'), '− ' + money(bill.paid_amount))}
              {Number(bill.pending_amount) > 0 && row(t('billing.pendingNote'), '− ' + money(bill.pending_amount))}
            </div>
          )}
        </div>
        )}

        {/* how much */}
        <div>
          <div className="relative">
            <span className="absolute left-4 top-1/2 -translate-y-1/2 text-xl text-navy-700">৳</span>
            <input
              required
              inputMode="decimal"
              type="number"
              min="0.01"
              step="0.01"
              max={owing}
              aria-label={t('common.amount')}
              className={`w-full rounded-2xl border-2 bg-white py-3 pl-10 pr-4 text-2xl font-bold text-navy-900 outline-none ${over ? 'border-status-due' : 'border-brand-200 focus:border-brand-500'}`}
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
            />
          </div>
          <div className="mt-2 flex gap-2">
            {!work && chip(L('এই মাস', 'This month'), thisMonth)}
            {chip(L('পুরো বকেয়া', 'Full balance'), owing)}
          </div>
          <p className={`mt-1.5 text-xs ${over ? 'text-status-due' : 'text-navy-700'}`}>
            {over ? `${L('বকেয়ার বেশি নেওয়া যাবে না', 'More than the balance')} (${money(owing)})` : `${L('পরে বাকি থাকবে', 'Left after')}: ${money(remaining)}`}
          </p>
        </div>

        {/* how it was paid */}
        <div>
          <div className="flex gap-1 rounded-xl bg-brand-100 p-1">
            {seg('CASH', '💵', L('নগদ', 'Cash'))}
            {seg('BANK', '🏦', L('ব্যাংক', 'Bank'))}
            {seg('MOBILE', '📱', L('মোবাইল', 'Mobile'))}
          </div>

          {kind === 'BANK' && (
            <div className="mt-2 grid grid-cols-2 gap-2">
              <div>
                <input required list="bank-names" aria-label="bank" className={small} placeholder={L('ব্যাংকের নাম *', 'Bank name *')} value={bankName} onChange={(e) => setBankName(e.target.value)} />
                <datalist id="bank-names">
                  {BANKS.map((b) => (
                    <option key={b} value={b} />
                  ))}
                </datalist>
              </div>
              <input required aria-label="cheque" className={small} placeholder={L('চেক / রেফ নং *', 'Cheque / Ref no *')} value={reference} onChange={(e) => setReference(e.target.value)} />
            </div>
          )}
          {kind === 'MOBILE' && (
            <div className="mt-2 space-y-2">
              <div className="flex gap-1.5">
                {PROVIDERS.map((p) => (
                  <button
                    key={p.value}
                    type="button"
                    onClick={() => setProvider(p.value)}
                    className={`flex-1 rounded-lg border px-1 py-1.5 text-xs font-medium transition ${provider === p.value ? `${p.tone} border-transparent text-white` : 'border-brand-200 text-navy-700 hover:bg-brand-50'}`}
                  >
                    {p.label}
                  </button>
                ))}
              </div>
              <input required aria-label="txn" className={small} placeholder={L('ট্রানজেকশন আইডি (TxnID) * — যেমন 9J58AXK2', 'Transaction ID (TxnID) * - e.g. 9J58AXK2')} value={reference} onChange={(e) => setReference(e.target.value)} />
            </div>
          )}
        </div>

        {/* when, and an optional note */}
        <div className="flex items-center gap-2">
          <input required type="date" max={localToday()} aria-label="date" className={`${small} flex-1`} value={date} onChange={(e) => setDate(e.target.value)} />
          {!showNote && (
            <button type="button" onClick={() => setShowNote(true)} className="shrink-0 rounded-lg border border-dashed border-brand-300 px-3 py-2 text-xs text-navy-700 hover:bg-brand-50">
              + {L('নোট', 'Note')}
            </button>
          )}
        </div>
        {showNote && <textarea rows={2} maxLength={500} autoFocus aria-label="remarks" className={small} placeholder={L('মন্তব্য / নোট (ঐচ্ছিক)', 'Remarks / note (optional)')} value={remarks} onChange={(e) => setRemarks(e.target.value)} />}
      </form>
    </Sheet>
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
