import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import Sheet from '../components/Sheet';
import { apiError } from '../api/hall';
import * as api from '../api/water';
import type { WaterBill, WaterMonth, WaterStatus, WaterYear } from '../api/water';

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { maximumFractionDigits: 2 })}`;
const input = 'w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500';
const btnGhost = 'rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700 hover:bg-brand-100 disabled:opacity-50';
const MONTHS_BN = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
const MONTHS_EN = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const STATUS: Record<WaterStatus, { bn: string; en: string; tone: string }> = {
  NOT_POSTED: { bn: 'পোস্ট হয়নি', en: 'Not posted', tone: 'bg-brand-100 text-navy-700' },
  PENDING: { bn: 'অনুমোদনের অপেক্ষায়', en: 'Awaiting approval', tone: 'bg-status-partial/15 text-status-partial' },
  OVERDUE: { bn: 'মেয়াদ পেরিয়েছে', en: 'Overdue', tone: 'bg-status-due/15 text-status-due' },
  PAID: { bn: 'পরিশোধিত', en: 'Paid', tone: 'bg-status-paid/15 text-status-paid' },
};

/**
 * The monthly Dhaka WASA water bill. Every month the bill is entered as it is printed (meter readings, water, sewerage, VAT ...)
 * and posted as an expense; this page shows the whole July-June year so a month with no bill posted stands out.
 */
export default function WaterBillsPage() {
  const { user } = useAuth();
  const { t, lang } = useLanguage();
  const bn = lang === 'bn';
  const L = (b: string, e: string) => (bn ? b : e);
  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));

  const now = new Date();
  const [fy, setFy] = useState(now.getMonth() + 1 >= 7 ? now.getFullYear() : now.getFullYear() - 1);
  const [data, setData] = useState<WaterYear | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState<{ month: WaterMonth; bill: WaterBill | null } | null>(null);

  const load = useCallback(() => {
    api.getWaterYear(fy).then(setData).catch((e) => setError(apiError(e, t('somethingWrong'))));
  }, [fy, t]);
  useEffect(load, [load]);

  const monthName = (m: number) => (bn ? MONTHS_BN : MONTHS_EN)[m - 1];
  const missing = data?.months.filter((m) => m.status === 'NOT_POSTED' && (m.year < now.getFullYear() || (m.year === now.getFullYear() && m.month <= now.getMonth() + 1))).length ?? 0;

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-5xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">💧 {L('ঢাকা ওয়াসার পানির বিল', 'Dhaka WASA water bill')}</h1>
            <p className="text-sm text-navy-700">{L('প্রতি মাসের বিল যেমন ছাপা আছে তেমন তুলে পোস্ট করুন; অনুমোদনের পর খরচ হিসেবে খাতায় উঠবে।', 'Enter each month’s bill as printed and post it; once approved it goes into the books as an expense.')}</p>
          </div>
          <div className="flex items-center gap-2">
            <LanguageToggle />
            <button className={btnGhost} onClick={() => setFy(fy - 1)} aria-label="previous year">‹</button>
            <span className="text-sm font-medium text-navy-900">{data?.label ?? `${fy}-${String(fy + 1).slice(2)}`}</span>
            <button className={btnGhost} onClick={() => setFy(fy + 1)} aria-label="next year">›</button>
          </div>
        </div>
        {error && <p className="mb-4 text-sm text-status-due">{error}</p>}
        {data && !data.ready && <p className="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-status-partial">{L('ডাটাবেস আপগ্রেড বাকি আছে — আপগ্রেড ফাইল ইমপোর্টের পর বিল পোস্ট করা যাবে।', 'The database upgrade is pending - bills can be posted after it is imported.')}</p>}
        {missing > 0 && <p className="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-status-partial">⚠ {missing} {L('টি মাসের বিল এখনো পোস্ট হয়নি।', 'month(s) have no bill posted yet.')}</p>}

        {data && (
          <div className="mb-4 grid grid-cols-2 gap-3">
            <div className="rounded-xl border border-brand-200 bg-white p-3">
              <p className="text-xs text-navy-700">{L('এই বছরে পোস্ট করা মোট বিল', 'Billed this year')}</p>
              <p className="text-lg font-semibold text-navy-900">{money(data.total)}</p>
            </div>
            <div className="rounded-xl border border-brand-200 bg-white p-3">
              <p className="text-xs text-navy-700">{L('পরিশোধিত (অনুমোদিত)', 'Paid (approved)')}</p>
              <p className="text-lg font-semibold text-status-paid">{money(data.paid)}</p>
            </div>
          </div>
        )}

        <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
          <table className="w-full text-left text-sm">
            <thead className="bg-brand-100 text-navy-900">
              <tr>
                <th className="px-3 py-3">{L('মাস', 'Month')}</th>
                <th className="px-3 py-3">{L('বিল নং / অ্যাকাউন্ট', 'Bill / account')}</th>
                <th className="px-3 py-3 text-right">{L('ব্যবহার', 'Used')}</th>
                <th className="px-3 py-3 text-right">{L('পানি', 'Water')}</th>
                <th className="px-3 py-3 text-right">{L('পয়ঃ', 'Sewer')}</th>
                <th className="px-3 py-3 text-right">{L('মোট', 'Total')}</th>
                <th className="px-3 py-3">{L('শেষ তারিখ', 'Due')}</th>
                <th className="px-3 py-3">{L('অবস্থা', 'Status')}</th>
                <th className="px-3 py-3" />
              </tr>
            </thead>
            <tbody>
              {data?.months.map((m) =>
                m.bills.length === 0 ? (
                  <tr key={`${m.year}-${m.month}`} className="border-t border-brand-100 text-navy-700">
                    <td className="px-3 py-2 font-medium">{monthName(m.month)} {m.year}</td>
                    <td className="px-3 py-2" colSpan={6}>—</td>
                    <td className="px-3 py-2"><span className={`rounded-full px-2 py-0.5 text-xs ${STATUS.NOT_POSTED.tone}`}>{bn ? STATUS.NOT_POSTED.bn : STATUS.NOT_POSTED.en}</span></td>
                    <td className="px-3 py-2 text-right">
                      {can('expense.create') && data.ready && (
                        <button className="font-medium text-brand-700 hover:underline" onClick={() => setForm({ month: m, bill: null })}>
                          + {L('পোস্ট', 'Post')}
                        </button>
                      )}
                    </td>
                  </tr>
                ) : (
                  m.bills.map((b) => (
                    <tr key={b.id} className="border-t border-brand-100">
                      <td className="px-3 py-2 font-medium">{monthName(m.month)} {m.year}</td>
                      <td className="px-3 py-2 text-xs text-navy-700">{b.bill_no ?? '—'}<span className="block">{b.account_no || '—'}</span></td>
                      <td className="px-3 py-2 text-right">{b.consumption !== null ? Number(b.consumption).toLocaleString('en-BD') : '—'}</td>
                      <td className="px-3 py-2 text-right">{money(b.water_charge)}</td>
                      <td className="px-3 py-2 text-right">{money(b.sewerage_charge)}</td>
                      <td className="px-3 py-2 text-right font-semibold">{money(b.total)}</td>
                      <td className="px-3 py-2 text-xs">{b.due_date ?? '—'}</td>
                      <td className="px-3 py-2">
                        <span className={`rounded-full px-2 py-0.5 text-xs ${STATUS[b.status].tone}`}>{bn ? STATUS[b.status].bn : STATUS[b.status].en}</span>
                        {b.voucher_no && <span className="block text-[11px] text-navy-700">{b.voucher_no}</span>}
                      </td>
                      <td className="px-3 py-2 text-right">
                        {can('expense.create') && b.expense_status === 'PENDING' && (
                          <button className="font-medium text-brand-700 hover:underline" onClick={() => setForm({ month: m, bill: b })}>{L('সংশোধন', 'Edit')}</button>
                        )}
                      </td>
                    </tr>
                  ))
                ),
              )}
            </tbody>
          </table>
        </div>
        <p className="mt-3 text-xs text-navy-700">{L('অনুমোদন ও টাকা দেওয়ার পর বিলটি "পরিশোধিত" হয়। অনুমোদন হয় "খরচ" পেজ থেকে। অনুমোদিত বিল আর বদলানো যায় না।', 'A bill becomes "Paid" once its expense is approved on the Expenses page; an approved bill can no longer be changed.')}</p>
      </div>

      {form && (
        <BillSheet
          month={form.month}
          bill={form.bill}
          last={data?.last ?? null}
          onClose={() => setForm(null)}
          onDone={() => {
            setForm(null);
            load();
          }}
        />
      )}
    </div>
  );
}

function BillSheet({ month, bill, last, onClose, onDone }: { month: WaterMonth; bill: WaterBill | null; last: WaterYear['last']; onClose: () => void; onDone: () => void }) {
  const { lang } = useLanguage();
  const bn = lang === 'bn';
  const L = (b: string, e: string) => (bn ? b : e);
  const n = (v: string | number | null | undefined) => (v === null || v === undefined ? '' : String(Number(v)));

  const [f, setF] = useState({
    account_no: bill?.account_no ?? last?.account_no ?? '',
    bill_no: bill?.bill_no ?? '',
    previous_reading: bill ? n(bill.previous_reading) : n(last?.current_reading),
    current_reading: n(bill?.current_reading),
    water_charge: n(bill?.water_charge),
    sewerage_charge: n(bill?.sewerage_charge),
    vat: n(bill?.vat),
    other_charge: n(bill?.other_charge),
    late_fee: n(bill?.late_fee),
    due_date: bill?.due_date ?? '',
    notes: bill?.notes ?? '',
    treasury_medium: (bill?.treasury_medium ?? 'BANK') as 'CASH' | 'BANK',
  });
  const [files, setFiles] = useState<File[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const set = (k: keyof typeof f) => (e: { target: { value: string } }) => setF({ ...f, [k]: e.target.value });
  const total = ['water_charge', 'sewerage_charge', 'vat', 'other_charge', 'late_fee'].reduce((c, k) => c + (Number(f[k as keyof typeof f]) || 0), 0);
  const used = f.previous_reading !== '' && f.current_reading !== '' ? Number(f.current_reading) - Number(f.previous_reading) : null;

  async function submit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const payload = { ...f, bill_year: month.year, bill_month: month.month, attachments: files };
      if (bill) await api.correctWaterBill(bill.id, payload);
      else await api.postWaterBill(payload);
      onDone();
    } catch (err) {
      setError(apiError(err, L('সংরক্ষণ করা যায়নি।', 'Could not save.')));
    } finally {
      setBusy(false);
    }
  }

  const field = (k: keyof typeof f, label: string, extra: { type?: string; required?: boolean; placeholder?: string } = {}) => (
    <label className="block text-xs text-navy-700">
      {label}
      <input className={`${input} mt-1`} type={extra.type ?? 'text'} step={extra.type === 'number' ? '0.01' : undefined} min={extra.type === 'number' ? 0 : undefined} required={extra.required} placeholder={extra.placeholder} value={f[k]} onChange={set(k)} />
    </label>
  );

  return (
    <Sheet
      title={`💧 ${bill ? L('বিল সংশোধন', 'Correct bill') : L('ওয়াসার বিল পোস্ট', 'Post WASA bill')} — ${(bn ? MONTHS_BN : MONTHS_EN)[month.month - 1]} ${month.year}`}
      onClose={onClose}
      footer={
        <div className="space-y-2">
          {error && <p className="rounded-lg bg-status-due/10 px-3 py-2 text-xs text-status-due">{error}</p>}
          <button form="water-form" className="w-full rounded-xl bg-brand-600 py-3 text-sm font-semibold text-white shadow hover:bg-brand-700 disabled:opacity-40" disabled={busy || !(total > 0)}>
            {busy ? '…' : `${bill ? L('সংরক্ষণ', 'Save') : L('পোস্ট করুন', 'Post')} · ${money(total)}`}
          </button>
          <p className="text-center text-[11px] text-navy-700">{L('পোস্ট করলে খরচ "অনুমোদনের অপেক্ষায়" থাকে; অনুমোদনের পর খাতায় উঠবে।', 'Posting leaves the expense awaiting approval; it reaches the books once approved.')}</p>
        </div>
      }
    >
      <form id="water-form" onSubmit={submit} className="space-y-3">
        <div className="grid grid-cols-2 gap-3">
          {field('account_no', L('অ্যাকাউন্ট / কানেকশন নং', 'Account / connection no'))}
          {field('bill_no', L('বিল নং', 'Bill no'))}
          {field('previous_reading', L('আগের রিডিং', 'Previous reading'), { type: 'number' })}
          {field('current_reading', L('বর্তমান রিডিং', 'Current reading'), { type: 'number' })}
        </div>
        {used !== null && <p className={`text-xs ${used < 0 ? 'text-status-due' : 'text-navy-700'}`}>{L('এই মাসে ব্যবহার', 'Used this month')}: <b>{used}</b></p>}

        <div className="rounded-xl border border-brand-200 p-3">
          <p className="mb-2 text-xs font-medium text-navy-900">{L('বিলে যেমন ছাপা আছে', 'As printed on the bill')}</p>
          <div className="grid grid-cols-2 gap-3">
            {field('water_charge', L('পানির বিল (৳) *', 'Water charge (৳) *'), { type: 'number', required: true })}
            {field('sewerage_charge', L('পয়ঃনিষ্কাশন (৳)', 'Sewerage (৳)'), { type: 'number' })}
            {field('vat', L('ভ্যাট (৳)', 'VAT (৳)'), { type: 'number' })}
            {field('other_charge', L('অন্যান্য / বকেয়া (৳)', 'Other / arrears (৳)'), { type: 'number' })}
            {field('late_fee', L('বিলম্ব জরিমানা (৳)', 'Late surcharge (৳)'), { type: 'number' })}
          </div>
          <p className="mt-2 flex justify-between border-t border-brand-100 pt-2 text-sm font-semibold text-navy-900">
            <span>{L('মোট', 'Total')}</span>
            <span>{money(total)}</span>
          </p>
        </div>

        <div className="grid grid-cols-2 gap-3">
          {field('due_date', L('পরিশোধের শেষ তারিখ', 'Due date'), { type: 'date' })}
          <label className="block text-xs text-navy-700">
            {L('কোথা থেকে দেবেন', 'Paid from')}
            <select className={`${input} mt-1`} value={f.treasury_medium} onChange={set('treasury_medium')}>
              <option value="BANK">{L('ব্যাংক', 'Bank')}</option>
              <option value="CASH">{L('ক্যাশ', 'Cash')}</option>
            </select>
          </label>
        </div>
        {!bill && (
          <label className="block text-xs text-navy-700">
            {L('বিলের কপি (PDF / ছবি)', 'Copy of the bill (PDF / image)')}
            <input type="file" multiple accept=".pdf,image/jpeg,image/png,image/webp" className={`${input} mt-1`} onChange={(e) => setFiles(Array.from(e.target.files ?? []))} />
          </label>
        )}
        <label className="block text-xs text-navy-700">
          {L('নোট', 'Notes')}
          <textarea rows={2} maxLength={1000} className={`${input} mt-1`} value={f.notes} onChange={set('notes')} />
        </label>
      </form>
    </Sheet>
  );
}
