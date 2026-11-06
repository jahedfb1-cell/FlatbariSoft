import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import { apiError } from '../api/hall';
import * as api from '../api/salary';
import type { AdvanceData, SalaryRow, SalarySheet, SalaryState } from '../api/salary';
import type { Staff } from '../api/staff';

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { maximumFractionDigits: 2 })}`;
const input = 'w-full rounded-lg border border-brand-200 bg-white px-2 py-1.5 text-sm outline-none focus:border-brand-500';
const btn = 'rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50';
const btnGhost = 'rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700 hover:bg-brand-100 disabled:opacity-50';
const MONTHS_BN = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
const MONTHS_EN = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const STATE: Record<SalaryState, { bn: string; en: string; tone: string }> = {
  NOT_GENERATED: { bn: 'শিট হয়নি', en: 'No sheet', tone: 'bg-brand-100 text-navy-700' },
  UNPAID: { bn: 'বাকি', en: 'Unpaid', tone: 'bg-status-due/15 text-status-due' },
  AWAITING_APPROVAL: { bn: 'অনুমোদনের অপেক্ষায়', en: 'Awaiting approval', tone: 'bg-status-partial/15 text-status-partial' },
  PAID: { bn: 'পরিশোধিত', en: 'Paid', tone: 'bg-status-paid/15 text-status-paid' },
};

/** Staff pay: the monthly salary sheet (generate, adjust, pay) and the advances given to staff. */
export default function SalaryPanel({ staff }: { staff: Staff[] }) {
  const { user } = useAuth();
  const { lang } = useLanguage();
  const bn = lang === 'bn';
  const L = (b: string, e: string) => (bn ? b : e);
  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));
  const canPay = can('staff.manage') && can('expense.create');

  const now = new Date();
  const [tab, setTab] = useState<'sheet' | 'advances'>('sheet');
  const [year, setYear] = useState(now.getFullYear());
  const [month, setMonth] = useState(now.getMonth() + 1);
  const [sheet, setSheet] = useState<SalarySheet | null>(null);
  const [adv, setAdv] = useState<AdvanceData | null>(null);
  const [medium, setMedium] = useState<'CASH' | 'BANK'>('BANK');
  const [draft, setDraft] = useState<Record<number, { overtime: string; bonus: string; advance_deduction: string }>>({});
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [busy, setBusy] = useState(false);

  const loadSheet = useCallback(() => {
    api.getSheet(year, month).then((s) => { setSheet(s); setDraft({}); }).catch((e) => setMsg({ ok: false, text: apiError(e, L('লোড করা যায়নি।', 'Could not load.')) }));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [year, month]);
  useEffect(loadSheet, [loadSheet]);
  useEffect(() => {
    api.getAdvances().then(setAdv).catch(() => undefined);
  }, []);

  const step = (by: number) => {
    const idx = year * 12 + (month - 1) + by;
    setYear(Math.floor(idx / 12));
    setMonth((idx % 12) + 1);
  };

  async function run<T>(fn: () => Promise<T>, ok: (r: T) => string) {
    setBusy(true);
    setMsg(null);
    try {
      const r = await fn();
      setMsg({ ok: true, text: ok(r) });
      loadSheet();
      api.getAdvances().then(setAdv).catch(() => undefined);
    } catch (e) {
      setMsg({ ok: false, text: apiError(e, L('কিছু একটা সমস্যা হয়েছে।', 'Something went wrong.')) });
    } finally {
      setBusy(false);
    }
  }

  const d = (r: SalaryRow) => draft[r.staff_id] ?? { overtime: String(Number(r.overtime)), bonus: String(Number(r.bonus)), advance_deduction: String(Number(r.advance_deduction)) };
  const edit = (r: SalaryRow, patch: Partial<ReturnType<typeof d>>) => setDraft({ ...draft, [r.staff_id]: { ...d(r), ...patch } });
  const dirty = (r: SalaryRow) => !!draft[r.staff_id];
  const liveNet = (r: SalaryRow) => (dirty(r) ? Number(r.basic) + Number(d(r).overtime || 0) + Number(d(r).bonus || 0) - Number(d(r).advance_deduction || 0) : Number(r.net));

  const unpaidRows = sheet?.rows.filter((r) => r.state === 'UNPAID') ?? [];
  const needsSheet = sheet?.rows.some((r) => r.state === 'NOT_GENERATED');

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        <button className={tab === 'sheet' ? btn : btnGhost} onClick={() => setTab('sheet')}>{L('মাসিক বেতন শিট', 'Monthly salary sheet')}</button>
        <button className={tab === 'advances' ? btn : btnGhost} onClick={() => setTab('advances')}>{L('অগ্রিম বেতন', 'Advances')}</button>
        {tab === 'sheet' && (
          <div className="ml-auto flex items-center gap-1">
            <button className={btnGhost} onClick={() => step(-1)} aria-label="previous">‹</button>
            <span className="min-w-36 text-center text-sm font-medium text-navy-900">{(bn ? MONTHS_BN : MONTHS_EN)[month - 1]} {year}</span>
            <button className={btnGhost} onClick={() => step(1)} aria-label="next">›</button>
          </div>
        )}
      </div>
      {msg && <p className={`rounded-lg p-2 text-sm ${msg.ok ? 'bg-status-paid/10 text-status-paid' : 'bg-status-due/10 text-status-due'}`}>{msg.text}</p>}

      {tab === 'sheet' && sheet && (
        <>
          {!sheet.ready && <p className="rounded-lg bg-amber-50 p-3 text-sm text-status-partial">{L('ডাটাবেস আপগ্রেড বাকি আছে — আপগ্রেড ফাইল ইমপোর্টের পর বেতন শিট ব্যবহার করা যাবে।', 'The database upgrade is pending.')}</p>}
          <div className="grid grid-cols-3 gap-3">
            <div className="rounded-xl border border-brand-200 bg-white p-3"><p className="text-xs text-navy-700">{L('মোট নিট বেতন', 'Total net')}</p><p className="font-semibold text-navy-900">{money(sheet.totals.net)}</p></div>
            <div className="rounded-xl border border-brand-200 bg-white p-3"><p className="text-xs text-navy-700">{L('পরিশোধিত', 'Paid')}</p><p className="font-semibold text-status-paid">{money(sheet.totals.paid)}</p></div>
            <div className="rounded-xl border border-brand-200 bg-white p-3"><p className="text-xs text-navy-700">{L('বাকি', 'Still to pay')}</p><p className="font-semibold text-status-due">{money(sheet.totals.unpaid)}</p></div>
          </div>
          <p className="text-xs text-navy-700">
            {L('বেতনের তারিখ প্রতি মাসের', 'Salary day: the')} {sheet.due_day} {L('তারিখ (সেটিংসে বদলানো যায়)।', 'th of each month (change it in Settings).')}
            {sheet.late_count > 0 && <b className="ml-1 text-status-due">⚠ {sheet.late_count} {L('জনের বেতন দেরিতে', 'overdue')}</b>}
          </p>

          <div className="flex flex-wrap items-center gap-2">
            {can('staff.manage') && needsSheet && (
              <button className={btn} disabled={busy || !sheet.ready} onClick={() => run(() => api.generateSheet(year, month), (r) => `${r.made} ${L('জনের শিট তৈরি হয়েছে।', 'rows created.')}`)}>
                {L('এই মাসের শিট তৈরি করুন', 'Generate this month')}
              </button>
            )}
            {canPay && unpaidRows.length > 0 && (
              <>
                <select className={`${input} w-auto`} value={medium} onChange={(e) => setMedium(e.target.value as 'CASH' | 'BANK')} aria-label="medium">
                  <option value="BANK">{L('ব্যাংক থেকে', 'From bank')}</option>
                  <option value="CASH">{L('ক্যাশ থেকে', 'From cash')}</option>
                </select>
                <button className={btn} disabled={busy || Object.keys(draft).length > 0} title={Object.keys(draft).length ? L('আগে অসংরক্ষিত পরিবর্তন সংরক্ষণ করুন', 'Save your changes first') : ''} onClick={() => confirm(L(`${unpaidRows.length} জনের বেতন দেওয়ার এন্ট্রি হবে। চালিয়ে যাবেন?`, `Pay ${unpaidRows.length} salaries?`)) && run(() => api.payAll(year, month, medium), (r) => `${r.paid} ${L('জনের বেতন এন্ট্রি হয়েছে — অনুমোদনের অপেক্ষায়।', 'salaries entered - awaiting approval.')}`)}>
                  {L('সবার বেতন দিন', 'Pay all')} ({unpaidRows.length})
                </button>
              </>
            )}
            <button className={`${btnGhost} ml-auto`} onClick={() => api.printSheet(year, month).catch((e) => setMsg({ ok: false, text: apiError(e, L('PDF খোলা যায়নি।', 'Could not open the PDF.')) }))}>🖨 {L('শিট PDF', 'Sheet PDF')}</button>
          </div>

          <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
            <table className="w-full text-left text-sm">
              <thead className="bg-brand-100 text-navy-900">
                <tr>
                  <th className="px-3 py-2">{L('নাম', 'Name')}</th>
                  <th className="px-3 py-2 text-right">{L('মূল বেতন', 'Basic')}</th>
                  <th className="px-3 py-2 text-right">{L('ওভারটাইম', 'Overtime')}</th>
                  <th className="px-3 py-2 text-right">{L('বোনাস', 'Bonus')}</th>
                  <th className="px-3 py-2 text-right">{L('অগ্রিম কর্তন', 'Advance')}</th>
                  <th className="px-3 py-2 text-right">{L('নিট', 'Net')}</th>
                  <th className="px-3 py-2">{L('অবস্থা', 'Status')}</th>
                  <th className="px-3 py-2" />
                </tr>
              </thead>
              <tbody>
                {sheet.rows.length === 0 && <tr><td colSpan={8} className="px-3 py-6 text-center text-navy-700">{L('কোনো সক্রিয় স্টাফ নেই।', 'No active staff.')}</td></tr>}
                {sheet.rows.map((r) => {
                  const editable = r.state === 'UNPAID' && can('staff.manage');
                  return (
                    <tr key={r.staff_id} className="border-t border-brand-100 align-top">
                      <td className="px-3 py-2 font-medium">{r.name}<span className="block text-xs font-normal text-navy-700">{r.designation}</span></td>
                      <td className="px-3 py-2 text-right">{money(r.basic)}</td>
                      <td className="px-3 py-2 text-right">{editable ? <input type="number" min={0} className={`${input} w-24 text-right`} value={d(r).overtime} onChange={(e) => edit(r, { overtime: e.target.value })} /> : money(r.overtime)}</td>
                      <td className="px-3 py-2 text-right">{editable ? <input type="number" min={0} className={`${input} w-24 text-right`} value={d(r).bonus} onChange={(e) => edit(r, { bonus: e.target.value })} /> : money(r.bonus)}</td>
                      <td className="px-3 py-2 text-right">
                        {editable ? <input type="number" min={0} className={`${input} w-24 text-right`} value={d(r).advance_deduction} onChange={(e) => edit(r, { advance_deduction: e.target.value })} /> : money(r.advance_deduction)}
                        {Number(r.advance_outstanding) > 0 && <span className="block text-[11px] text-navy-700">{L('বাকি অগ্রিম', 'owes')} {money(r.advance_outstanding)}</span>}
                      </td>
                      <td className="px-3 py-2 text-right font-semibold">{money(liveNet(r))}</td>
                      <td className="px-3 py-2">
                        <span className={`rounded-full px-2 py-0.5 text-xs ${STATE[r.state].tone}`}>{bn ? STATE[r.state].bn : STATE[r.state].en}</span>
                        {r.late && <span className="ml-1 text-[11px] font-medium text-status-due">{L('দেরি', 'late')}</span>}
                        {r.voucher_no && <span className="block text-[11px] text-navy-700">{r.voucher_no}</span>}
                      </td>
                      <td className="whitespace-nowrap px-3 py-2 text-right">
                        {editable && dirty(r) && (
                          <button className="mr-2 font-medium text-brand-700 hover:underline" disabled={busy} onClick={() => run(() => api.updateRow(r.id!, { overtime: d(r).overtime || '0', bonus: d(r).bonus || '0', advance_deduction: d(r).advance_deduction || '0' }), () => L('সংরক্ষিত।', 'Saved.'))}>{L('সংরক্ষণ', 'Save')}</button>
                        )}
                        {canPay && r.state === 'UNPAID' && !dirty(r) && (
                          <button className="mr-2 font-medium text-brand-700 hover:underline" disabled={busy} onClick={() => run(() => api.payRow(r.id!, medium), () => L('বেতন এন্ট্রি হয়েছে — অনুমোদনের অপেক্ষায়।', 'Entered - awaiting approval.'))}>{L('বেতন দিন', 'Pay')}</button>
                        )}
                        {r.id && <button className="text-navy-700 hover:underline" onClick={() => api.printPayslip(r.id!).catch((e) => setMsg({ ok: false, text: apiError(e, L('PDF খোলা যায়নি।', 'Could not open the PDF.')) }))}>🖨</button>}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <p className="text-xs text-navy-700">{L('"বেতন দিন" চাপলে একটা খরচ তৈরি হয় ("স্টাফ বেতন" খাত); খরচ পেজে অনুমোদনের পর খাতায় ওঠে ও "পরিশোধিত" হয়। এন্ট্রির পর সারি আর বদলানো যায় না।', '"Pay" creates an expense; it becomes "Paid" once approved on the Expenses page. A row cannot be changed after that.')}</p>
        </>
      )}

      {tab === 'advances' && adv && <AdvancesTab adv={adv} staff={staff} canPay={canPay} onDone={(a) => { setAdv(a); loadSheet(); }} />}
    </div>
  );
}

function AdvancesTab({ adv, staff, canPay, onDone }: { adv: AdvanceData; staff: Staff[]; canPay: boolean; onDone: (a: AdvanceData) => void }) {
  const { lang } = useLanguage();
  const bn = lang === 'bn';
  const L = (b: string, e: string) => (bn ? b : e);
  const [f, setF] = useState({ staff_id: '', amount: '', monthly_deduction: '', note: '', treasury_medium: 'CASH' as 'CASH' | 'BANK' });
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      onDone(await api.giveAdvance({ staff_id: Number(f.staff_id), amount: f.amount, monthly_deduction: f.monthly_deduction || undefined, note: f.note || undefined, treasury_medium: f.treasury_medium }));
      setF({ ...f, staff_id: '', amount: '', monthly_deduction: '', note: '' });
    } catch (err) {
      setError(apiError(err, L('সংরক্ষণ করা যায়নি।', 'Could not save.')));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-4">
      {!adv.ready && <p className="rounded-lg bg-amber-50 p-3 text-sm text-status-partial">{L('ডাটাবেস আপগ্রেড বাকি আছে।', 'The database upgrade is pending.')}</p>}
      {canPay && adv.ready && (
        <form onSubmit={submit} className="grid grid-cols-2 gap-3 rounded-xl border border-brand-200 bg-white p-4 sm:grid-cols-6">
          <select required className={`${input} sm:col-span-2`} value={f.staff_id} onChange={(e) => setF({ ...f, staff_id: e.target.value })}>
            <option value="">{L('কাকে অগ্রিম *', 'To whom *')}</option>
            {staff.filter((s) => s.status === 'ACTIVE').map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
          </select>
          <input required type="number" min={1} className={input} placeholder={L('পরিমাণ (৳) *', 'Amount (৳) *')} value={f.amount} onChange={(e) => setF({ ...f, amount: e.target.value })} />
          <input type="number" min={0} className={input} placeholder={L('মাসে কর্তন (৳)', 'Deduct / month (৳)')} value={f.monthly_deduction} onChange={(e) => setF({ ...f, monthly_deduction: e.target.value })} />
          <select className={input} value={f.treasury_medium} onChange={(e) => setF({ ...f, treasury_medium: e.target.value as 'CASH' | 'BANK' })}>
            <option value="CASH">{L('ক্যাশ', 'Cash')}</option>
            <option value="BANK">{L('ব্যাংক', 'Bank')}</option>
          </select>
          <button className={btn} disabled={busy}>{busy ? '…' : L('অগ্রিম দিন', 'Give advance')}</button>
          <input className={`${input} col-span-2 sm:col-span-6`} placeholder={L('নোট (ঐচ্ছিক)', 'Note (optional)')} value={f.note} onChange={(e) => setF({ ...f, note: e.target.value })} />
          {error && <p className="col-span-2 text-sm text-status-due sm:col-span-6">{error}</p>}
        </form>
      )}
      <p className="text-xs text-navy-700">{L('অগ্রিম একটা খরচ হিসেবে অনুমোদনের অপেক্ষায় থাকে; অনুমোদনের পর টাকা বের হয়েছে ধরা হয় এবং পরের মাসগুলোর বেতন শিটে নিজে থেকে কর্তন বসে।', 'An advance waits for approval as an expense; once approved it counts as paid out and is deducted automatically on the following sheets.')}</p>

      {adv.owed.length > 0 && (
        <div className="rounded-xl border border-brand-200 bg-white p-3">
          <p className="mb-2 text-sm font-medium text-navy-900">{L('যাদের কাছে অগ্রিম বাকি', 'Advances still owed')}</p>
          <div className="flex flex-wrap gap-2">
            {adv.owed.map((o) => <span key={o.staff_id} className="rounded-full bg-amber-50 px-3 py-1 text-sm text-navy-900">{o.name}: <b>{money(o.outstanding)}</b></span>)}
          </div>
        </div>
      )}

      <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
        <table className="w-full text-left text-sm">
          <thead className="bg-brand-100 text-navy-900">
            <tr><th className="px-3 py-2">{L('তারিখ', 'Date')}</th><th className="px-3 py-2">{L('নাম', 'Name')}</th><th className="px-3 py-2 text-right">{L('অগ্রিম', 'Advance')}</th><th className="px-3 py-2 text-right">{L('মাসে কর্তন', 'Per month')}</th><th className="px-3 py-2">{L('অবস্থা', 'Status')}</th><th className="px-3 py-2">{L('নোট', 'Note')}</th></tr>
          </thead>
          <tbody>
            {adv.advances.length === 0 && <tr><td colSpan={6} className="px-3 py-6 text-center text-navy-700">{L('এখনো কোনো অগ্রিম নেই।', 'No advances yet.')}</td></tr>}
            {adv.advances.map((a) => (
              <tr key={a.id} className="border-t border-brand-100">
                <td className="px-3 py-2">{a.given_date}</td>
                <td className="px-3 py-2 font-medium">{a.staff_name}</td>
                <td className="px-3 py-2 text-right">{money(a.amount)}</td>
                <td className="px-3 py-2 text-right">{Number(a.monthly_deduction) > 0 ? money(a.monthly_deduction) : '—'}</td>
                <td className="px-3 py-2 text-xs"><span className={a.status === 'PAID_OUT' ? 'text-status-paid' : 'text-status-partial'}>{a.status === 'PAID_OUT' ? (a.voucher_no ?? L('দেওয়া হয়েছে', 'Paid out')) : L('অনুমোদনের অপেক্ষায়', 'Awaiting approval')}</span></td>
                <td className="px-3 py-2 text-xs text-navy-700">{a.note ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
