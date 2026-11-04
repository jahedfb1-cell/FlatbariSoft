import { useEffect, useState } from 'react';
import { useLanguage } from '../context/LanguageContext';
import { apiError } from '../api/hall';
import client from '../api/client';
import { downloadFile, openPdf } from '../utils/download';

interface Line {
  label: string;
  amount: string;
}
interface Annual {
  fiscal_year: number;
  label: string;
  from: string;
  to: string;
  cash_flow: { opening: string; receipts: Line[]; total_receipts: string; payments: Line[]; total_payments: string; net: string; closing: string; closing_cash: string; closing_bank: string; agrees_with_books: boolean };
  income_expenditure: {
    income: Line[];
    total_income: string;
    expenditure: Line[];
    total_expenditure: string;
    surplus: string;
    capital_spending: Line[];
    total_capital_spending: string;
    special_collections: Line[];
    memo: { billed: string; collected: string; collection_percent: number | null };
  };
  balance_sheet: {
    as_of: string;
    assets: Line[];
    total_assets: string;
    liabilities: Line[];
    total_liabilities: string;
    funds: Line[];
    accrued: Line;
    total_funds: string;
    total_liabilities_and_funds: string;
    balanced: boolean;
  };
}

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const btnGhost = 'rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700 hover:bg-brand-100 disabled:opacity-50';

/** The year-end statements of the building: cash flow, income & expenditure (profit & loss) and the balance sheet. */
export default function AnnualStatements() {
  const { lang } = useLanguage();
  const bn = lang === 'bn';
  const L = (b: string, e: string) => (bn ? b : e);
  const now = new Date();
  const [fy, setFy] = useState(now.getMonth() + 1 >= 7 ? now.getFullYear() : now.getFullYear() - 1);
  const [tab, setTab] = useState<'cash' | 'pl' | 'bs'>('cash');
  const [data, setData] = useState<Annual | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setData(null);
    setError(null);
    client.get<Annual>('/reports/annual', { params: { fiscal_year: fy } }).then((r) => setData(r.data)).catch((e) => setError(apiError(e, L('বিবরণী আনা যায়নি।', 'Could not load the statements.'))));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [fy]);

  const section = (title: string, lines: Line[], total?: { label: string; amount: string }) => (
    <>
      <tr className="bg-brand-50"><td colSpan={2} className="px-3 py-1.5 text-xs font-semibold text-navy-900">{title}</td></tr>
      {lines.length === 0 && <tr><td colSpan={2} className="px-6 py-1.5 text-sm text-navy-700">—</td></tr>}
      {lines.map((l) => (
        <tr key={l.label} className="border-t border-brand-100"><td className="py-1.5 pl-6 pr-3 text-sm">{l.label}</td><td className="px-3 py-1.5 text-right text-sm">{money(l.amount)}</td></tr>
      ))}
      {total && <tr className="border-t-2 border-brand-200 font-semibold"><td className="px-3 py-1.5 text-sm">{total.label}</td><td className="px-3 py-1.5 text-right text-sm">{money(total.amount)}</td></tr>}
    </>
  );
  const big = (label: string, amount: string, tone = '') => (
    <tr className="border-t-2 border-brand-300 bg-brand-100/60 font-semibold"><td className="px-3 py-2 text-sm">{label}</td><td className={`px-3 py-2 text-right text-sm ${tone}`}>{money(amount)}</td></tr>
  );

  const cf = data?.cash_flow;
  const ie = data?.income_expenditure;
  const bs = data?.balance_sheet;
  const params = { fiscal_year: String(fy) };

  return (
    <section className="mb-8 print:hidden">
      <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-sm font-semibold text-navy-900">{L('বার্ষিক আর্থিক বিবরণী', 'Annual financial statements')}</h2>
        <div className="flex items-center gap-1">
          <button className={btnGhost} onClick={() => setFy(fy - 1)} aria-label="previous year">‹</button>
          <span className="min-w-28 text-center text-sm font-medium text-navy-900">{L('অর্থবছর', 'FY')} {data?.label ?? `${fy}-${String(fy + 1).slice(2)}`}</span>
          <button className={btnGhost} onClick={() => setFy(fy + 1)} aria-label="next year">›</button>
          <button className={btnGhost} disabled={!data} onClick={() => openPdf('/reports/annual/pdf', params, `financial-statements-${fy}.pdf`).catch(() => setError(L('PDF খোলা যায়নি।', 'Could not open the PDF.')))}>🖨 PDF</button>
          <button className={btnGhost} disabled={!data} onClick={() => downloadFile(`/reports/annual/excel?fiscal_year=${fy}`, `financial-statements-${fy}.xlsx`).catch(() => setError(L('Excel নামানো যায়নি।', 'Could not download.')))}>📥 Excel</button>
        </div>
      </div>

      <div className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
        <div className="mb-3 flex flex-wrap gap-2">
          {([['cash', L('নগদ প্রবাহ', 'Cash flow')], ['pl', L('আয়-ব্যয় (লাভ-ক্ষতি)', 'Income & expenditure')], ['bs', L('আর্থিক অবস্থা (স্থিতিপত্র)', 'Balance sheet')]] as const).map(([k, label]) => (
            <button key={k} onClick={() => setTab(k)} className={`rounded-lg px-4 py-2 text-sm font-medium ${tab === k ? 'bg-brand-600 text-white' : 'border border-brand-200 text-navy-700 hover:bg-brand-100'}`}>{label}</button>
          ))}
        </div>
        {error && <p className="mb-3 rounded-lg bg-status-due/10 p-2 text-sm text-status-due">{error}</p>}
        {!data && !error && <p className="py-8 text-center text-sm text-navy-700">…</p>}

        {data && tab === 'cash' && cf && (
          <>
            <p className="mb-2 text-xs text-navy-700">{L('যে টাকা সত্যিই এল ও গেল — ক্যাশ ও ব্যাংক মিলিয়ে', 'Money that actually came in and went out - cash and bank together')} · {data.from} → {data.to}</p>
            <table className="w-full">
              <tbody>
                {big(L('শুরুর স্থিতি (ক্যাশ + ব্যাংক)', 'Opening balance (cash + bank)'), cf.opening)}
                {section(L('প্রাপ্তি', 'Receipts'), cf.receipts, { label: L('মোট প্রাপ্তি', 'Total receipts'), amount: cf.total_receipts })}
                {section(L('প্রদান', 'Payments'), cf.payments, { label: L('মোট প্রদান', 'Total payments'), amount: cf.total_payments })}
                {big(L('নিট নগদ প্রবাহ', 'Net cash flow'), cf.net, Number(cf.net) < 0 ? 'text-status-due' : 'text-status-paid')}
                {big(L('শেষ স্থিতি', 'Closing balance'), cf.closing)}
              </tbody>
            </table>
            <p className="mt-2 text-xs text-navy-700">
              {L('তার মধ্যে নগদ', 'of which cash')} {money(cf.closing_cash)} · {L('ব্যাংক', 'bank')} {money(cf.closing_bank)} ·{' '}
              <span className={cf.agrees_with_books ? 'text-status-paid' : 'text-status-due'}>{cf.agrees_with_books ? L('✓ ক্যাশ ও ব্যাংক বইয়ের সঙ্গে মিলেছে', '✓ agrees with the cash and bank books') : L('⚠ বইয়ের সঙ্গে মিলছে না', '⚠ does not agree with the books')}</span>
            </p>
          </>
        )}

        {data && tab === 'pl' && ie && (
          <>
            <p className="mb-2 text-xs text-navy-700">{L('বিল করা আয় ও দায়সহ ব্যয় — বকেয়াসহ হিসাব', 'Income as billed and spending as incurred - dues included')} · {data.from} → {data.to}</p>
            <table className="w-full">
              <tbody>
                {section(L('আয়', 'Income'), ie.income, { label: L('মোট আয়', 'Total income'), amount: ie.total_income })}
                {section(L('ব্যয়', 'Expenditure'), ie.expenditure, { label: L('মোট ব্যয়', 'Total expenditure'), amount: ie.total_expenditure })}
                {big(L('উদ্বৃত্ত / (ঘাটতি)', 'Surplus / (deficit)'), ie.surplus, Number(ie.surplus) < 0 ? 'text-status-due' : 'text-status-paid')}
                {(ie.capital_spending.length > 0 || ie.special_collections.length > 0) && section(L('মূলধনী লেনদেন (প্রকল্প / কাজ — আয়-ব্যয়ের বাইরে)', 'Capital items (projects / works - outside income & expenditure)'), [...ie.special_collections, ...ie.capital_spending.map((l) => ({ label: `${L('খরচ', 'Spent')}: ${l.label}`, amount: l.amount }))])}
              </tbody>
            </table>
            <p className="mt-2 text-xs text-navy-700">
              {L('স্মারক: এ বছরে বিল করা', 'Memo: billed this year')} {money(ie.memo.billed)} · {L('আদায় হয়েছে', 'collected')} {money(ie.memo.collected)}
              {ie.memo.collection_percent !== null && ` (${ie.memo.collection_percent}%)`}
            </p>
          </>
        )}

        {data && tab === 'bs' && bs && (
          <>
            <p className="mb-2 text-xs text-navy-700">{bs.as_of} {L('তারিখে', 'as at')}</p>
            <table className="w-full">
              <tbody>
                {section(L('সম্পদ', 'Assets'), bs.assets, { label: L('মোট সম্পদ', 'Total assets'), amount: bs.total_assets })}
                {section(L('দায়', 'Liabilities'), bs.liabilities, { label: L('মোট দায়', 'Total liabilities'), amount: bs.total_liabilities })}
                {section(L('তহবিল', 'Funds'), [...bs.funds, bs.accrued], { label: L('মোট তহবিল', 'Total funds'), amount: bs.total_funds })}
                {big(L('মোট দায় ও তহবিল', 'Total liabilities and funds'), bs.total_liabilities_and_funds)}
              </tbody>
            </table>
            <p className="mt-2 text-xs text-navy-700">{L('প্রতিটি তহবিলের অঙ্ক সেই তহবিলের ক্যাশ ও ব্যাংকের টাকা; ফ্ল্যাটদের কাছে পাওনা ও স্টাফের অগ্রিম (দেনা বাদে) আলাদা সারিতে।', 'Each fund shows its own cash and bank money; what flats owe and staff advances (less what is owed) is on its own line.')}</p>
          </>
        )}
      </div>
    </section>
  );
}
