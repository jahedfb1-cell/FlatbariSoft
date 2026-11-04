import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import { apiError } from '../api/hall';
import { listFlats } from '../api/flats';
import type { Flat } from '../api/flats';
import client from '../api/client';
import { downloadFile, openPdf } from '../utils/download';
import { localToday } from './HallCalendar';
import { useEffect } from 'react';

type Kind = 'dues' | 'collections' | 'expenses' | 'flat' | 'cashbook';

interface Column {
  key: string;
  label: string;
  type: 'text' | 'money' | 'date';
}
interface Report {
  title: string;
  subtitle: string;
  period: string;
  columns: Column[];
  rows: Record<string, string>[];
  summary: { label: string; value: string; type?: string }[];
  totals: Record<string, string>;
}

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
const input = 'rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500';
const btn = 'rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50';
const btnGhost = 'rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700 hover:bg-brand-100 disabled:opacity-50';

const CARDS: { kind: Kind; icon: string; bn: string; en: string; hintBn: string; hintEn: string; dates: boolean }[] = [
  { kind: 'dues', icon: '📋', bn: 'বকেয়া তালিকা', en: 'Dues list', hintBn: 'কোন ফ্ল্যাটের কাছে কত টাকা পাওনা — ফোন নম্বরসহ', hintEn: 'Which flat owes how much, with phone numbers', dates: false },
  { kind: 'collections', icon: '💰', bn: 'আদায় রিপোর্ট', en: 'Collections', hintBn: 'এই সময়ে কত টাকা আদায় হলো, কোন মাধ্যমে', hintEn: 'What was collected in the period, and how', dates: true },
  { kind: 'expenses', icon: '💸', bn: 'খরচের রিপোর্ট', en: 'Expenses', hintBn: 'কোন খাতে কত খরচ — ভাউচার নম্বরসহ', hintEn: 'Spending by head, with voucher numbers', dates: true },
  { kind: 'flat', icon: '🏠', bn: 'ফ্ল্যাটের হিসাব বিবরণী', en: 'Flat statement', hintBn: 'একটি ফ্ল্যাটের মাসওয়ারি বিল, পরিশোধ ও বাকি', hintEn: 'One flat: bills, payments and balance by month', dates: true },
  { kind: 'cashbook', icon: '📒', bn: 'ক্যাশ বই / ব্যাংক বই', en: 'Cash book / Bank book', hintBn: 'জমা-খরচ ও চলতি স্থিতি, তারিখ ধরে', hintEn: 'Money in and out with the running balance', dates: true },
];

const LINKS: { to: string; icon: string; bn: string; en: string; hintBn: string; hintEn: string; perm: string }[] = [
  { to: '/statements', icon: '📊', bn: 'মাসিক তহবিল বিবরণী', en: 'Monthly fund statement', hintBn: 'প্রাপ্তি ও প্রদান হিসাব, তহবিল ধরে', hintEn: 'Receipts & payments per fund', perm: 'accounting.view' },
  { to: '/budget', icon: '🎯', bn: 'বাজেট বনাম খরচ', en: 'Budget vs actual', hintBn: 'বছরের বাজেট, PDF ও Excel সহ', hintEn: 'Yearly budget with PDF and Excel', perm: 'budget.view' },
  { to: '/work-budgets', icon: '🏗️', bn: 'কাজের বাজেট ও আদায়', en: 'Work budgets', hintBn: 'কাজভিত্তিক বরাদ্দ, আদায়, খরচ', hintEn: 'Per-work estimate, collections, spending', perm: 'budget.view' },
  { to: '/gas', icon: '🔥', bn: 'গ্যাস ক্রয় ও ব্যবহার', en: 'Gas ledger', hintBn: 'গ্যাস কেনা, ব্যবহার ও ক্ষতি', hintEn: 'Gas bought, used and lost', perm: 'gas.view' },
  { to: '/suppliers', icon: '🚚', bn: 'সরবরাহকারী খাতা', en: 'Supplier ledger', hintBn: 'কার কাছে কত দেনা', hintEn: 'What is owed to each supplier', perm: 'expense.view' },
  { to: '/contributions', icon: '📒', bn: 'মালিকদের আদায় খাতা', en: 'Owners’ collections', hintBn: 'নির্মাণ ব্যয় ইত্যাদির আদায় ও বাকি', hintEn: 'Construction and other collections', perm: 'contribution.view' },
  { to: '/staff', icon: '👷', bn: 'স্টাফ বেতন শিট', en: 'Salary sheet', hintBn: 'মাসিক বেতন শিট ও বেতন স্লিপ', hintEn: 'Monthly salary sheet and payslips', perm: 'staff.view' },
  { to: '/water', icon: '💧', bn: 'ওয়াসার পানির বিল', en: 'WASA water bills', hintBn: 'মাসওয়ারি বিল ও পরিশোধ', hintEn: 'Month-by-month bills and payment', perm: 'expense.view' },
];

const ymd = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

/** The reports people actually ask for, in plain words: pick one, pick the dates, look at it, print it or take it to Excel. */
export default function SimpleReports() {
  const { user } = useAuth();
  const { lang } = useLanguage();
  const bn = lang === 'bn';
  const L = (b: string, e: string) => (bn ? b : e);
  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));

  const [kind, setKind] = useState<Kind | null>(null);
  const today = localToday();
  const [from, setFrom] = useState(`${today.slice(0, 8)}01`);
  const [to, setTo] = useState(today);
  const [flatId, setFlatId] = useState('');
  const [medium, setMedium] = useState<'CASH' | 'BANK'>('CASH');
  const [flats, setFlats] = useState<Flat[]>([]);
  const [report, setReport] = useState<Report | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    listFlats({}).then((f: Flat[]) => setFlats([...f].sort((a, b) => a.flat_no.localeCompare(b.flat_no, undefined, { numeric: true }))));
  }, []);

  const card = CARDS.find((c) => c.kind === kind);
  const params = useMemo(() => {
    const p: Record<string, string> = {};
    if (card?.dates) {
      p.from = from;
      p.to = to;
    }
    if (kind === 'flat' && flatId) p.flat_id = flatId;
    if (kind === 'cashbook') p.medium = medium;
    return p;
  }, [card, kind, from, to, flatId, medium]);

  const preset = (which: 'month' | 'last' | 'fy' | 'year') => {
    const d = new Date();
    if (which === 'month') {
      setFrom(ymd(new Date(d.getFullYear(), d.getMonth(), 1)));
      setTo(ymd(d));
    } else if (which === 'last') {
      setFrom(ymd(new Date(d.getFullYear(), d.getMonth() - 1, 1)));
      setTo(ymd(new Date(d.getFullYear(), d.getMonth(), 0)));
    } else if (which === 'fy') {
      const y = d.getMonth() + 1 >= 7 ? d.getFullYear() : d.getFullYear() - 1; // July - June
      setFrom(`${y}-07-01`);
      setTo(ymd(d));
    } else {
      setFrom(`${d.getFullYear()}-01-01`);
      setTo(ymd(d));
    }
    setReport(null);
  };

  const pick = (k: Kind) => {
    setKind(k);
    setReport(null);
    setError(null);
  };

  async function view() {
    if (!kind) return;
    setBusy(true);
    setError(null);
    try {
      setReport((await client.get<Report>(`/reports/simple/${kind}`, { params })).data);
    } catch (e) {
      setReport(null);
      setError(apiError(e, L('রিপোর্ট আনা যায়নি।', 'Could not load the report.')));
    } finally {
      setBusy(false);
    }
  }

  const needsFlat = kind === 'flat' && !flatId;
  const cell = (c: Column, v: string | undefined) => (v === undefined || v === '' ? '' : c.type === 'money' ? money(v) : v);

  return (
    <section className="print:hidden">
      <h2 className="mb-2 text-sm font-semibold text-navy-900">{L('সহজ রিপোর্ট', 'Everyday reports')}</h2>
      <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {CARDS.map((c) => (
          <button key={c.kind} onClick={() => pick(c.kind)} className={`rounded-xl border-2 bg-white p-4 text-left shadow-sm transition hover:shadow-md ${kind === c.kind ? 'border-brand-600' : 'border-brand-200'}`}>
            <span className="flex items-center gap-2 font-semibold text-navy-900">
              <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-100 text-lg">{c.icon}</span>
              {bn ? c.bn : c.en}
            </span>
            <span className="mt-1 block text-xs text-navy-700">{bn ? c.hintBn : c.hintEn}</span>
          </button>
        ))}
      </div>

      {card && (
        <div className="mb-6 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
          <div className="flex flex-wrap items-end gap-3">
            {card.dates && (
              <>
                <label className="text-xs text-navy-700">
                  {L('থেকে', 'From')}
                  <input type="date" className={`${input} mt-1 block`} value={from} max={to} onChange={(e) => { setFrom(e.target.value); setReport(null); }} />
                </label>
                <label className="text-xs text-navy-700">
                  {L('পর্যন্ত', 'To')}
                  <input type="date" className={`${input} mt-1 block`} value={to} min={from} onChange={(e) => { setTo(e.target.value); setReport(null); }} />
                </label>
                <div className="flex flex-wrap gap-1.5 pb-0.5">
                  {([['month', L('এই মাস', 'This month')], ['last', L('গত মাস', 'Last month')], ['fy', L('এই অর্থবছর', 'This fiscal year')], ['year', L('এই বছর', 'This year')]] as const).map(([k, label]) => (
                    <button key={k} type="button" className="rounded-full border border-brand-200 px-3 py-1.5 text-xs text-navy-700 hover:bg-brand-100" onClick={() => preset(k)}>{label}</button>
                  ))}
                </div>
              </>
            )}
            {kind === 'flat' && (
              <label className="text-xs text-navy-700">
                {L('ফ্ল্যাট', 'Flat')} *
                <select className={`${input} mt-1 block`} value={flatId} onChange={(e) => { setFlatId(e.target.value); setReport(null); }}>
                  <option value="">{L('— বাছুন —', '— choose —')}</option>
                  {flats.map((f) => (
                    <option key={f.id} value={f.id}>{f.flat_no}{f.owner_name ? ` — ${f.owner_name}` : ''}</option>
                  ))}
                </select>
              </label>
            )}
            {kind === 'cashbook' && (
              <label className="text-xs text-navy-700">
                {L('কোনটি', 'Which')}
                <select className={`${input} mt-1 block`} value={medium} onChange={(e) => { setMedium(e.target.value as 'CASH' | 'BANK'); setReport(null); }}>
                  <option value="CASH">{L('ক্যাশ বই (নগদ)', 'Cash book')}</option>
                  <option value="BANK">{L('ব্যাংক বই', 'Bank book')}</option>
                </select>
              </label>
            )}
            <button className={btn} disabled={busy || needsFlat} onClick={view}>{busy ? '…' : L('রিপোর্ট দেখুন', 'Show report')}</button>
            <button className={btnGhost} disabled={needsFlat} onClick={() => openPdf(`/reports/simple/${kind}/pdf`, params, `report-${kind}.pdf`).catch(() => setError(L('PDF খোলা যায়নি।', 'Could not open the PDF.')))}>🖨 PDF</button>
            <button className={btnGhost} disabled={needsFlat} onClick={() => downloadFile(`/reports/simple/${kind}/excel?${new URLSearchParams(params).toString()}`, `report-${kind}.xlsx`).catch(() => setError(L('Excel নামানো যায়নি।', 'Could not download.')))}>📥 Excel</button>
          </div>
          {error && <p className="mt-3 rounded-lg bg-status-due/10 p-2 text-sm text-status-due">{error}</p>}

          {report && (
            <div className="mt-4">
              <div className="mb-3">
                <h3 className="text-base font-semibold text-navy-900">{report.title}</h3>
                <p className="text-xs text-navy-700">{report.subtitle} · {report.period}</p>
              </div>
              <div className="mb-3 flex flex-wrap gap-2">
                {report.summary.map((s) => (
                  <span key={s.label} className="rounded-xl bg-brand-50 px-3 py-1.5 text-sm text-navy-900">
                    {s.label}: <b>{s.type === 'money' ? money(s.value) : s.value}</b>
                  </span>
                ))}
              </div>
              <div className="overflow-x-auto rounded-xl border border-brand-200">
                <table className="w-full text-left text-sm">
                  <thead className="bg-brand-100 text-navy-900">
                    <tr>
                      {report.columns.map((c) => (
                        <th key={c.key} className={`px-3 py-2 ${c.type === 'money' ? 'text-right' : ''}`}>{c.label}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {report.rows.length === 0 && <tr><td colSpan={report.columns.length} className="px-3 py-6 text-center text-navy-700">{L('এই সময়ে কোনো তথ্য নেই।', 'Nothing in this period.')}</td></tr>}
                    {report.rows.map((r, i) => (
                      <tr key={i} className="border-t border-brand-100">
                        {report.columns.map((c) => (
                          <td key={c.key} className={`px-3 py-1.5 ${c.type === 'money' ? 'whitespace-nowrap text-right' : ''}`}>{cell(c, r[c.key])}</td>
                        ))}
                      </tr>
                    ))}
                    {Object.keys(report.totals).length > 0 && (
                      <tr className="border-t-2 border-brand-200 bg-brand-50 font-semibold">
                        {report.columns.map((c, i) => (
                          <td key={c.key} className={`px-3 py-2 ${c.type === 'money' ? 'text-right' : ''}`}>{i === 0 ? L('মোট', 'Total') : report.totals[c.key] !== undefined ? money(report.totals[c.key]) : ''}</td>
                        ))}
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>
      )}

      <h2 className="mb-2 mt-2 text-sm font-semibold text-navy-900">{L('আরও বিবরণী (নিজস্ব পেজে)', 'More statements (on their own pages)')}</h2>
      <div className="mb-8 grid grid-cols-2 gap-3 md:grid-cols-4">
        {LINKS.filter((l) => can(l.perm)).map((l) => (
          <Link key={l.to} to={l.to} className="rounded-xl border border-brand-200 bg-white p-3 text-sm shadow-sm transition hover:shadow-md">
            <span className="block text-lg">{l.icon}</span>
            <span className="block font-medium text-navy-900">{bn ? l.bn : l.en}</span>
            <span className="block text-xs text-navy-700">{bn ? l.hintBn : l.hintEn}</span>
          </Link>
        ))}
      </div>
    </section>
  );
}
