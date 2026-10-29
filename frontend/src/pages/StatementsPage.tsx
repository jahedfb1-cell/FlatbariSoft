import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import type { TranslationKey } from '../i18n/translations';
import LanguageToggle from '../components/LanguageToggle';
import Modal from '../components/Modal';
import { localToday } from '../components/HallCalendar';
import { apiError } from '../api/hall';
import { blobErrorMessage } from '../utils/download';
import * as api from '../api/treasury';
import type { Entry, FundCode, FundStatement, Line, Summary } from '../api/treasury';

const money = (v: string | number) => Number(v).toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const taka = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { maximumFractionDigits: 0 })}`;
const input = 'w-full rounded-lg border border-brand-200 px-3 py-2';
const btn = 'rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50';
const btnGhost = 'rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700 hover:bg-brand-100 disabled:opacity-50';

const FUND_LOOK: Record<FundCode, { icon: string; ring: string; text: string }> = {
  OPERATING: { icon: '🏢', ring: 'border-brand-500 bg-brand-50', text: 'text-brand-700' },
  GAS: { icon: '🔥', ring: 'border-orange-400 bg-orange-50', text: 'text-orange-700' },
  CAPITAL: { icon: '🏗️', ring: 'border-indigo-400 bg-indigo-50', text: 'text-indigo-700' },
  SINKING: { icon: '🛡️', ring: 'border-emerald-500 bg-emerald-50', text: 'text-emerald-700' },
};
const FUND_ORDER: FundCode[] = ['OPERATING', 'GAS', 'CAPITAL', 'SINKING'];
const MONTHS_BN = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
const MONTHS_EN = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

/**
 * The month's Receipts & Payments account of each fund (service charge, gas, project, reserve), worked out from
 * the cash and bank ledgers. Laid out to be read like a story: what we started with, what came in, what went
 * out, what is left - one fund at a time, or all funds together.
 */
export default function StatementsPage() {
  const { user } = useAuth();
  const { t, lang } = useLanguage();
  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));
  const canManage = can('treasury.manage');

  const now = new Date();
  const [year, setYear] = useState(now.getFullYear());
  const [month, setMonth] = useState(now.getMonth() + 1);
  const [fund, setFund] = useState<FundCode | 'ALL'>('OPERATING');
  const [stmt, setStmt] = useState<FundStatement | null>(null);
  const [summary, setSummary] = useState<Summary | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [params, setParams] = useSearchParams();
  const [showEntries, setShowEntries] = useState(params.get('treasury') === '1' && canManage);
  const [busy, setBusy] = useState<string | null>(null);

  const monthName = (m: number) => (lang === 'bn' ? MONTHS_BN : MONTHS_EN)[m - 1];
  const isThisMonth = year === now.getFullYear() && month === now.getMonth() + 1;
  const shift = (d: number) => {
    const x = new Date(year, month - 1 + d, 1);
    setYear(x.getFullYear());
    setMonth(x.getMonth() + 1);
  };

  // all four funds' balances sit on the fund buttons, so the summary is always loaded for the month
  const load = useCallback(() => {
    setError(null);
    api.getSummary(year, month).then(setSummary).catch((e) => setError(apiError(e, t('somethingWrong'))));
    if (fund === 'ALL') setStmt(null);
    else {
      setStmt(null);
      api.getStatement(fund, year, month).then(setStmt).catch((e) => setError(apiError(e, t('somethingWrong'))));
    }
  }, [fund, year, month, t]);
  useEffect(load, [load]);

  async function run(key: string, action: () => Promise<unknown>) {
    setBusy(key);
    try {
      await action();
    } catch (e) {
      setError(await blobErrorMessage(e, t('somethingWrong')));
    } finally {
      setBusy(null);
    }
  }

  const fundName = (code: FundCode) => t(`stmt.fund.${code}` as TranslationKey);
  const headName = (h: { name_bn: string; name_en: string }) => (lang === 'bn' ? h.name_bn : h.name_en);
  const closingOf = (code: FundCode) => summary?.funds.find((f) => f.fund.code === code)?.closing;

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-6 pb-16">
      <div className="mx-auto max-w-5xl">
        <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-2xl font-semibold text-navy-900">{t('stmt.title')}</h1>
            <p className="mt-1 max-w-xl text-sm text-navy-700">{t('stmt.intro')}</p>
          </div>
          <LanguageToggle />
        </div>

        {/* 1. which month */}
        <div className="mb-4 flex flex-wrap items-center gap-3">
          <div className="flex items-center overflow-hidden rounded-xl border border-brand-200 bg-white shadow-sm">
            <button onClick={() => shift(-1)} aria-label="previous month" className="px-4 py-2 text-lg text-navy-700 hover:bg-brand-100">
              ‹
            </button>
            <span className="min-w-36 px-2 text-center text-base font-semibold text-navy-900">
              {monthName(month)} {year}
            </span>
            <button onClick={() => shift(1)} disabled={isThisMonth} aria-label="next month" className="px-4 py-2 text-lg text-navy-700 hover:bg-brand-100 disabled:opacity-30">
              ›
            </button>
          </div>
          {!isThisMonth && (
            <button
              onClick={() => {
                setYear(now.getFullYear());
                setMonth(now.getMonth() + 1);
              }}
              className="text-sm text-brand-700 hover:underline"
            >
              {t('stmt.thisMonth')}
            </button>
          )}
        </div>

        {/* 2. which fund - each button shows what is left in it */}
        <div className="mb-5 grid grid-cols-2 gap-3 md:grid-cols-5">
          {FUND_ORDER.map((f) => (
            <button
              key={f}
              onClick={() => setFund(f)}
              className={`rounded-2xl border-2 p-3 text-left transition ${fund === f ? `${FUND_LOOK[f].ring} shadow` : 'border-transparent bg-white shadow-sm hover:border-brand-200'}`}
            >
              <span className="text-xl">{FUND_LOOK[f].icon}</span>
              <span className="mt-1 block text-sm font-medium text-navy-900">{fundName(f)}</span>
              <span className={`block text-base font-semibold ${Number(closingOf(f) ?? 0) < 0 ? 'text-status-due' : FUND_LOOK[f].text}`}>{summary ? taka(closingOf(f) ?? 0) : '…'}</span>
              <span className="block text-[11px] text-navy-700">{t('stmt.leftNow')}</span>
            </button>
          ))}
          <button onClick={() => setFund('ALL')} className={`rounded-2xl border-2 p-3 text-left transition ${fund === 'ALL' ? 'border-navy-500 bg-white shadow' : 'border-transparent bg-white shadow-sm hover:border-brand-200'}`}>
            <span className="text-xl">📊</span>
            <span className="mt-1 block text-sm font-medium text-navy-900">{t('stmt.allFunds')}</span>
            <span className="block text-base font-semibold text-navy-900">{summary ? taka(summary.total.closing) : '…'}</span>
            <span className="block text-[11px] text-navy-700">{t('stmt.leftNow')}</span>
          </button>
        </div>

        {/* 3. print / download / enter */}
        <div className="mb-5 flex flex-wrap items-center gap-2 rounded-2xl border border-brand-100 bg-white p-3 shadow-sm">
          <span className="mr-1 text-sm font-medium text-navy-900">{t('stmt.getCopy')}</span>
          {fund !== 'ALL' && (
            <>
              <button className={btn} disabled={busy !== null} onClick={() => run('pdf', () => api.printStatement(fund, year, month))}>
                🖨 {t('stmt.pdf')}
              </button>
              <button className={btnGhost} disabled={busy !== null} onClick={() => run('xlsx', () => api.downloadStatementExcel(fund, year, month))}>
                {t('stmt.excel')}
              </button>
            </>
          )}
          <button className={fund === 'ALL' ? btn : btnGhost} disabled={busy !== null} onClick={() => run('combo', () => api.printCombinedStatement(['OPERATING', 'GAS'], year, month))}>
            {t('stmt.combined')}
          </button>
          <button className={btnGhost} disabled={busy !== null} onClick={() => run('combo4', () => api.printCombinedStatement(FUND_ORDER, year, month))}>
            {t('stmt.combinedAll')}
          </button>
          {canManage && (
            <button className="ml-auto rounded-lg border border-brand-600 px-3 py-2 text-sm font-medium text-brand-700 hover:bg-brand-100" onClick={() => setShowEntries(true)}>
              ✎ {t('stmt.entries')}
            </button>
          )}
        </div>

        {error && <p className="mb-3 rounded-lg bg-red-50 p-3 text-sm text-status-due">{error}</p>}

        {fund === 'ALL' && summary && <AllFunds s={summary} fundName={fundName} onPick={setFund} />}
        {fund !== 'ALL' && !stmt && !error && <div className="h-48 animate-pulse rounded-2xl bg-white/70" />}
        {stmt && <StatementView s={stmt} headName={headName} canManage={canManage} onEntries={() => setShowEntries(true)} />}
      </div>

      {showEntries && (
        <EntriesModal
          onClose={() => {
            setShowEntries(false);
            if (params.has('treasury')) setParams({}, { replace: true });
          }}
          onChanged={load}
        />
      )}
    </div>
  );
}

function Row({ label, value, bold, tone }: { label: string; value: string; bold?: boolean; tone?: 'muted' }) {
  return (
    <tr className={`border-t border-brand-100 ${bold ? 'bg-brand-50 font-semibold' : ''}`}>
      <td className={`py-1.5 pr-3 ${tone === 'muted' ? 'text-navy-700' : ''}`}>{label}</td>
      <td className="py-1.5 text-right">{value}</td>
    </tr>
  );
}

/** One kind of money (a head) with a bar showing how big it is among the others. */
function HeadBars({ heads, total, color, headName }: { heads: FundStatement['receipts_by_head']; total: number; color: string; headName: (h: { name_bn: string; name_en: string }) => string }) {
  return (
    <div className="space-y-3">
      {heads.map((h) => {
        const pct = total > 0 ? Math.min(100, (Number(h.amount) / total) * 100) : 0;
        return (
          <div key={h.code}>
            <div className="flex justify-between gap-2 text-sm">
              <span className="text-navy-900">{headName(h)}</span>
              <span className="font-medium text-navy-900">{taka(h.amount)}</span>
            </div>
            <div className="mt-1 h-2 overflow-hidden rounded-full bg-brand-100">
              <div className={`h-full rounded-full ${color}`} style={{ width: `${pct}%` }} />
            </div>
          </div>
        );
      })}
    </div>
  );
}

function StatementView({ s, headName, canManage, onEntries }: { s: FundStatement; headName: (h: { name_bn: string; name_en: string }) => string; canManage: boolean; onEntries: () => void }) {
  const { t } = useLanguage();
  const [showAll, setShowAll] = useState(false);
  const noOpening = Number(s.opening.total) === 0 && s.receipts.length === 0 && s.payments.length === 0;
  const recTotal = Number(s.totals.receipts);
  const payTotal = Number(s.totals.payments);

  const Step = ({ label, value, sub, tone }: { label: string; value: string; sub?: string; tone: 'start' | 'in' | 'out' | 'end' }) => {
    const look = { start: 'bg-white', in: 'bg-green-50', out: 'bg-red-50', end: 'bg-brand-700 text-white' }[tone];
    const accent = { start: 'text-navy-900', in: 'text-status-paid', out: 'text-status-due', end: 'text-white' }[tone];
    return (
      <div className={`rounded-2xl p-4 shadow-sm ${look}`}>
        <p className={`text-xs ${tone === 'end' ? 'text-brand-100' : 'text-navy-700'}`}>{label}</p>
        <p className={`mt-1 text-2xl font-semibold ${accent}`}>{value}</p>
        {sub && <p className={`mt-1 text-xs ${tone === 'end' ? 'text-brand-100' : 'text-navy-700'}`}>{sub}</p>}
      </div>
    );
  };

  return (
    <div className="space-y-5">
      {noOpening && (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-navy-900">
          <p className="font-medium">{t('stmt.noOpeningTitle')}</p>
          <p className="mt-1 text-navy-700">{t('stmt.noOpeningBody')}</p>
          {canManage && (
            <button onClick={onEntries} className="mt-2 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">
              {t('stmt.setOpening')}
            </button>
          )}
        </div>
      )}

      {/* the whole month in four steps */}
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Step tone="start" label={t('stmt.stepStart')} value={taka(s.opening.total)} sub={`${t('stmt.cash')} ${taka(s.opening.cash)} · ${t('stmt.bank')} ${taka(s.opening.bank)}`} />
        <Step tone="in" label={`＋ ${t('stmt.stepIn')}`} value={taka(s.totals.receipts)} sub={`${s.receipts.length} ${t('stmt.times')}`} />
        <Step tone="out" label={`－ ${t('stmt.stepOut')}`} value={taka(s.totals.payments)} sub={`${s.payments.length} ${t('stmt.times')}`} />
        <Step tone="end" label={`＝ ${t('stmt.stepEnd')}`} value={taka(s.closing.total)} sub={`${t('stmt.cash')} ${taka(s.closing.cash)} · ${t('stmt.bank')} ${taka(s.closing.bank)}`} />
      </div>

      {/* where it came from / where it went */}
      <div className="grid gap-4 md:grid-cols-2">
        <div className="rounded-2xl border border-brand-100 bg-white p-4 shadow-sm">
          <h2 className="mb-3 font-semibold text-status-paid">⬇ {t('stmt.whereFrom')}</h2>
          {s.receipts_by_head.length === 0 ? <p className="text-sm text-navy-700">{t('stmt.nothingIn')}</p> : <HeadBars heads={s.receipts_by_head} total={recTotal} color="bg-status-paid" headName={headName} />}
        </div>
        <div className="rounded-2xl border border-brand-100 bg-white p-4 shadow-sm">
          <h2 className="mb-3 font-semibold text-status-due">⬆ {t('stmt.whereTo')}</h2>
          {s.payments_by_head.length === 0 ? <p className="text-sm text-navy-700">{t('stmt.nothingOut')}</p> : <HeadBars heads={s.payments_by_head} total={payTotal} color="bg-status-due" headName={headName} />}
        </div>
      </div>

      {/* every transaction, on demand */}
      <div className="rounded-2xl border border-brand-100 bg-white p-4 shadow-sm">
        <button onClick={() => setShowAll(!showAll)} className="flex w-full items-center justify-between text-left">
          <span className="font-semibold text-navy-900">
            🧾 {t('stmt.allTxns')} ({s.receipts.length + s.payments.length})
          </span>
          <span className="text-sm text-brand-700">{showAll ? t('stmt.hide') : t('stmt.show')}</span>
        </button>
        {showAll && (
          <div className="mt-3 grid gap-4 md:grid-cols-2">
            <TxnList title={t('stmt.receipts')} lines={s.receipts} color="text-status-paid" empty={t('stmt.nothingIn')} />
            <TxnList title={t('stmt.payments')} lines={s.payments} color="text-status-due" empty={t('stmt.nothingOut')} />
          </div>
        )}
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <details className="rounded-2xl border border-brand-100 bg-white p-4 shadow-sm">
          <summary className="cursor-pointer font-semibold text-navy-900">🏦 {t('stmt.bankNote')}</summary>
          <table className="mt-2 w-full text-sm">
            <tbody>
              <Row label={t('stmt.bankOpening')} value={money(s.bank_note.opening)} />
              <Row label={`+ ${t('stmt.bankDeposited')}`} value={money(s.bank_note.cash_deposited)} tone="muted" />
              <Row label={`+ ${t('stmt.bankOther')}`} value={money(s.bank_note.other_receipts)} tone="muted" />
              <Row label={`+ ${t('stmt.bankProfit')}`} value={money(s.bank_note.profit)} tone="muted" />
              <Row label={`− ${t('stmt.bankWithdrawn')}`} value={money(s.bank_note.withdrawn)} tone="muted" />
              <Row label={`− ${t('stmt.bankPayments')}`} value={money(s.bank_note.payments)} tone="muted" />
              <Row label={t('stmt.bankClosing')} value={money(s.bank_note.closing)} bold />
            </tbody>
          </table>
        </details>
        {s.loans.length > 0 && <LoansCard loans={s.loans} />}
      </div>

      <p className="text-center text-xs text-navy-700">{Number(s.left_total) === Number(s.right_total) ? `✓ ${t('stmt.balanced')}` : ''}</p>
    </div>
  );
}

/** Every line of one side, newest last, one small card per transaction (reads well on a phone). */
function TxnList({ title, lines, color, empty }: { title: string; lines: Line[]; color: string; empty: string }) {
  return (
    <div>
      <p className={`mb-2 text-sm font-semibold ${color}`}>{title}</p>
      {lines.length === 0 && <p className="text-sm text-navy-700">{empty}</p>}
      <div className="divide-y divide-brand-100">
        {lines.map((l, i) => (
          <div key={i} className="flex items-start justify-between gap-3 py-2 text-sm">
            <div>
              <p className="text-navy-900">{l.particulars}</p>
              <p className="text-xs text-navy-700">
                {l.date} · {l.medium === 'CASH' ? '💵' : '🏦'}
              </p>
            </div>
            <span className={`shrink-0 font-medium ${color}`}>{taka(l.amount)}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

function LoansCard({ loans }: { loans: FundStatement['loans'] }) {
  const { t, lang } = useLanguage();
  return (
    <div className="rounded-2xl border border-brand-100 bg-white p-4 shadow-sm">
      <h2 className="mb-2 font-semibold text-navy-900">🔁 {t('stmt.loans')}</h2>
      <table className="w-full text-sm">
        <tbody>
          {loans.map((l, i) => (
            <Row key={i} label={`${lang === 'bn' ? l.borrower_name_bn : l.borrower_name_en} → ${lang === 'bn' ? l.lender_name_bn : l.lender_name_en}`} value={money(l.owed)} />
          ))}
        </tbody>
      </table>
    </div>
  );
}

/** All four funds side by side: tap one to open its full statement. */
function AllFunds({ s, fundName, onPick }: { s: Summary; fundName: (c: FundCode) => string; onPick: (c: FundCode) => void }) {
  const { t } = useLanguage();
  return (
    <div className="space-y-4">
      <div className="rounded-2xl bg-brand-700 p-5 text-white shadow-sm">
        <p className="text-sm text-brand-100">{t('stmt.allLeft')}</p>
        <p className="mt-1 text-3xl font-semibold">{taka(s.total.closing)}</p>
        <p className="mt-2 text-sm text-brand-100">
          {t('stmt.stepStart')} {taka(s.total.opening)} · ＋ {taka(s.total.receipts)} · － {taka(s.total.payments)}
        </p>
      </div>
      <div className="grid gap-3 md:grid-cols-2">
        {s.funds.map((f) => (
          <button key={f.fund.code} onClick={() => onPick(f.fund.code)} className="rounded-2xl border border-brand-100 bg-white p-4 text-left shadow-sm transition hover:border-brand-300 hover:shadow">
            <div className="flex items-center justify-between">
              <span className="font-semibold text-navy-900">
                {FUND_LOOK[f.fund.code].icon} {fundName(f.fund.code)}
              </span>
              <span className="text-xs text-brand-700">{t('stmt.open')} ›</span>
            </div>
            <p className={`mt-2 text-2xl font-semibold ${Number(f.closing) < 0 ? 'text-status-due' : 'text-navy-900'}`}>{taka(f.closing)}</p>
            <p className="text-xs text-navy-700">
              {t('stmt.cash')} {taka(f.closing_cash)} · {t('stmt.bank')} {taka(f.closing_bank)}
            </p>
            <div className="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
              <div className="rounded-lg bg-brand-50 p-2">
                <p className="text-navy-700">{t('stmt.stepStart')}</p>
                <p className="font-medium text-navy-900">{taka(f.opening)}</p>
              </div>
              <div className="rounded-lg bg-green-50 p-2">
                <p className="text-navy-700">{t('stmt.stepIn')}</p>
                <p className="font-medium text-status-paid">{taka(f.receipts)}</p>
              </div>
              <div className="rounded-lg bg-red-50 p-2">
                <p className="text-navy-700">{t('stmt.stepOut')}</p>
                <p className="font-medium text-status-due">{taka(f.payments)}</p>
              </div>
            </div>
          </button>
        ))}
      </div>
      {s.loans.length > 0 && <LoansCard loans={s.loans} />}
    </div>
  );
}

type Action = 'OPENING' | 'DEPOSIT' | 'WITHDRAW' | 'PROFIT' | 'LOAN' | 'REPAY';

/** Record the entries no other screen creates, and undo a wrong one. */
function EntriesModal({ onClose, onChanged }: { onClose: () => void; onChanged: () => void }) {
  const { t } = useLanguage();
  const [action, setAction] = useState<Action>('OPENING');
  const [fund, setFund] = useState<FundCode>('OPERATING');
  const [toFund, setToFund] = useState<FundCode>('GAS');
  const [medium, setMedium] = useState<'CASH' | 'BANK'>('CASH');
  const [cash, setCash] = useState('');
  const [bank, setBank] = useState('');
  const [amount, setAmount] = useState('');
  const [date, setDate] = useState(localToday());
  const [notes, setNotes] = useState('');
  const [entries, setEntries] = useState<Entry[]>([]);
  const [msg, setMsg] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [reversing, setReversing] = useState<number | null>(null);
  const [reason, setReason] = useState('');

  const loadEntries = useCallback(() => {
    api.listEntries().then(setEntries).catch(() => {});
  }, []);
  useEffect(loadEntries, [loadEntries]);

  const funds: FundCode[] = ['OPERATING', 'GAS', 'CAPITAL', 'SINKING'];
  const fundName = (c: FundCode) => t(`stmt.fund.${c}` as TranslationKey);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setMsg(null);
    const common = { date, notes: notes || undefined };
    try {
      if (action === 'OPENING') await api.openingBalance({ fund, cash: cash || '0', bank: bank || '0', ...common });
      else if (action === 'DEPOSIT') await api.bankDeposit({ fund, amount, ...common });
      else if (action === 'WITHDRAW') await api.bankWithdrawal({ fund, amount, ...common });
      else if (action === 'PROFIT') await api.bankProfit({ fund, amount, ...common });
      else if (action === 'LOAN') await api.lend({ from_fund: fund, to_fund: toFund, medium, amount, ...common });
      else await api.repay({ from_fund: fund, to_fund: toFund, medium, amount, ...common });
      setMsg(t('stmt.saved'));
      setAmount('');
      setCash('');
      setBank('');
      setNotes('');
      loadEntries();
      onChanged();
    } catch (err) {
      setError(apiError(err, t('somethingWrong')));
    }
  }

  async function reverse(id: number) {
    setError(null);
    try {
      await api.reverseEntry(id, reason);
      setReversing(null);
      setReason('');
      loadEntries();
      onChanged();
    } catch (err) {
      setError(apiError(err, t('somethingWrong')));
    }
  }

  const twoFunds = action === 'LOAN' || action === 'REPAY';
  const actionLabels: Record<Action, string> = {
    OPENING: t('stmt.kind.OPENING'),
    DEPOSIT: t('stmt.kind.DEPOSIT_TO_BANK'),
    WITHDRAW: t('stmt.kind.WITHDRAW_FROM_BANK'),
    PROFIT: t('stmt.kind.BANK_PROFIT'),
    LOAN: t('stmt.kind.FUND_LOAN'),
    REPAY: t('stmt.kind.LOAN_REPAYMENT'),
  };

  return (
    <Modal title={t('stmt.entries')} onClose={onClose}>
      <div className="max-h-[72vh] space-y-4 overflow-y-auto text-sm text-navy-900">
        <p className="text-navy-700">{t('stmt.entriesHelp')}</p>

        <form onSubmit={submit} className="space-y-2 rounded-lg border border-brand-200 p-3">
          <select className={input} value={action} onChange={(e) => setAction(e.target.value as Action)}>
            {(Object.keys(actionLabels) as Action[]).map((a) => (
              <option key={a} value={a}>
                {actionLabels[a]}
              </option>
            ))}
          </select>

          <div className="grid grid-cols-2 gap-2">
            <label className="text-navy-700">
              {twoFunds ? (action === 'LOAN' ? t('stmt.from') : t('stmt.borrower')) : t('stmt.fundLabel')}
              <select className={`${input} mt-1`} value={fund} onChange={(e) => setFund(e.target.value as FundCode)}>
                {funds.map((f) => (
                  <option key={f} value={f}>
                    {fundName(f)}
                  </option>
                ))}
              </select>
            </label>
            {twoFunds && (
              <label className="text-navy-700">
                {action === 'LOAN' ? t('stmt.to') : t('stmt.lender')}
                <select className={`${input} mt-1`} value={toFund} onChange={(e) => setToFund(e.target.value as FundCode)}>
                  {funds.filter((f) => f !== fund).map((f) => (
                    <option key={f} value={f}>
                      {fundName(f)}
                    </option>
                  ))}
                </select>
              </label>
            )}
            {twoFunds && (
              <label className="text-navy-700">
                {t('stmt.medium')}
                <select className={`${input} mt-1`} value={medium} onChange={(e) => setMedium(e.target.value as 'CASH' | 'BANK')}>
                  <option value="CASH">{t('stmt.cashLabel')}</option>
                  <option value="BANK">{t('stmt.bankLabel')}</option>
                </select>
              </label>
            )}
            <label className="text-navy-700">
              {t('stmt.date')}
              <input required type="date" className={`${input} mt-1`} value={date} onChange={(e) => setDate(e.target.value)} />
            </label>
          </div>

          {action === 'OPENING' ? (
            <div className="grid grid-cols-2 gap-2">
              <input type="number" min={0} step="0.01" className={input} placeholder={t('stmt.openingCash')} value={cash} onChange={(e) => setCash(e.target.value)} />
              <input type="number" min={0} step="0.01" className={input} placeholder={t('stmt.openingBank')} value={bank} onChange={(e) => setBank(e.target.value)} />
            </div>
          ) : (
            <input required type="number" min="0.01" step="0.01" className={input} placeholder={t('stmt.amount')} value={amount} onChange={(e) => setAmount(e.target.value)} />
          )}
          <input className={input} placeholder={t('stmt.notes')} value={notes} onChange={(e) => setNotes(e.target.value)} />
          {error && <p className="text-status-due">{error}</p>}
          {msg && <p className="text-status-paid">{msg}</p>}
          <button type="submit" className={`${btn} w-full`}>
            {t('stmt.save')}
          </button>
        </form>

        <div>
          <p className="mb-1 font-medium">{t('stmt.recent')}</p>
          {entries.length === 0 && <p className="text-navy-700">{t('stmt.none')}</p>}
          <ul className="divide-y divide-brand-100">
            {entries.map((e) => (
              <li key={e.id} className={`py-2 ${e.is_reversal || e.is_reversed ? 'text-navy-700 line-through decoration-1' : ''}`}>
                <div className="flex items-start justify-between gap-2">
                  <span>
                    {t(`stmt.kind.${e.kind}` as TranslationKey)} · {fundName(e.fund)}
                    {e.to_fund ? ` → ${fundName(e.to_fund)}` : ''}
                    {e.medium ? ` · ${e.medium === 'CASH' ? t('stmt.cashLabel') : t('stmt.bankLabel')}` : ''}
                    <span className="block text-xs">
                      {e.date} · {e.receipt_no}
                      {e.notes ? ` · ${e.notes}` : ''}
                    </span>
                  </span>
                  <span className="whitespace-nowrap text-right">
                    ৳{money(e.amount)}
                    {!e.is_reversal && !e.is_reversed && (
                      <button className="ml-2 text-status-due no-underline hover:underline" onClick={() => setReversing(e.id)}>
                        {t('stmt.reverse')}
                      </button>
                    )}
                    {e.is_reversed && <span className="ml-2 text-xs">{t('stmt.reversed')}</span>}
                  </span>
                </div>
                {reversing === e.id && (
                  <div className="mt-2 flex gap-2 no-underline">
                    <input className={input} placeholder={t('stmt.reason')} value={reason} onChange={(ev) => setReason(ev.target.value)} />
                    <button className={btn} disabled={!reason.trim()} onClick={() => reverse(e.id)}>
                      {t('hall.confirmAction')}
                    </button>
                  </div>
                )}
              </li>
            ))}
          </ul>
        </div>
      </div>
    </Modal>
  );
}
