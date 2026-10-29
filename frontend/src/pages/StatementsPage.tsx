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
const input = 'w-full rounded-lg border border-brand-200 px-3 py-2';
const btn = 'rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50';
const btnGhost = 'rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700 hover:bg-brand-100 disabled:opacity-50';

/**
 * The month's Receipts & Payments account of each fund (service charge, gas, project, sinking) - worked out
 * from the cash and bank ledgers - plus a view of all funds together.
 */
export default function StatementsPage() {
  const { user } = useAuth();
  const { t, lang } = useLanguage();
  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));
  const canManage = can('treasury.manage');

  const now = new Date();
  const [ym, setYm] = useState(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`);
  const [fund, setFund] = useState<FundCode | 'ALL'>('OPERATING');
  const [stmt, setStmt] = useState<FundStatement | null>(null);
  const [summary, setSummary] = useState<Summary | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [params, setParams] = useSearchParams();
  const [showEntries, setShowEntries] = useState(params.get('treasury') === '1' && canManage);
  const [busy, setBusy] = useState<string | null>(null);

  const [year, month] = ym.split('-').map(Number);

  const load = useCallback(() => {
    setError(null);
    if (!year || !month) return;
    if (fund === 'ALL') {
      setStmt(null);
      api.getSummary(year, month).then(setSummary).catch((e) => setError(apiError(e, t('somethingWrong'))));
    } else {
      setSummary(null);
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

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-5xl">
        <div className="mb-4 flex items-center justify-between">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('stmt.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        <div className="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-brand-200 bg-white p-3 shadow-sm">
          <input type="month" className="rounded-lg border border-brand-200 px-3 py-2" value={ym} onChange={(e) => setYm(e.target.value)} />
          {(['OPERATING', 'GAS', 'CAPITAL', 'SINKING'] as FundCode[]).map((f) => (
            <button key={f} className={fund === f ? btn : btnGhost} onClick={() => setFund(f)}>
              {fundName(f)}
            </button>
          ))}
          <button className={fund === 'ALL' ? btn : btnGhost} onClick={() => setFund('ALL')}>
            {t('stmt.allFunds')}
          </button>
          <div className="ml-auto flex flex-wrap gap-2">
            <button className={btnGhost} disabled={busy !== null} onClick={() => run('combo', () => api.printCombinedStatement(['OPERATING', 'GAS'], year, month))}>
              {t('stmt.combined')}
            </button>
            <button className={btnGhost} disabled={busy !== null} onClick={() => run('combo4', () => api.printCombinedStatement(['OPERATING', 'GAS', 'CAPITAL', 'SINKING'], year, month))}>
              {t('stmt.combinedAll')}
            </button>
            {fund !== 'ALL' && (
              <>
                <button className={btnGhost} disabled={busy !== null} onClick={() => run('pdf', () => api.printStatement(fund, year, month))}>
                  {t('stmt.pdf')}
                </button>
                <button className={btnGhost} disabled={busy !== null} onClick={() => run('xlsx', () => api.downloadStatementExcel(fund, year, month))}>
                  {t('stmt.excel')}
                </button>
              </>
            )}
            {canManage && (
              <button className={btn} onClick={() => setShowEntries(true)}>
                {t('stmt.entries')}
              </button>
            )}
          </div>
        </div>

        {error && <p className="mb-3 text-sm text-status-due">{error}</p>}

        {summary && <SummaryView s={summary} fundName={fundName} />}
        {stmt && <StatementView s={stmt} headName={headName} />}
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

function StatementView({ s, headName }: { s: FundStatement; headName: (h: { name_bn: string; name_en: string }) => string }) {
  const { t } = useLanguage();
  const lineName = (l: Line) => l.particulars;
  const n = Math.max(s.receipts.length, s.payments.length);

  return (
    <div className="space-y-4">
      <div className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
        <h2 className="mb-3 font-semibold text-navy-900">{t('stmt.summary')}</h2>
        <div className="grid gap-4 md:grid-cols-2">
          <div>
            <p className="mb-1 text-sm font-semibold text-status-paid">{t('stmt.receipts')}</p>
            <table className="w-full text-sm">
              <tbody>
                <Row label={`${t('stmt.opening')} — ${t('stmt.cash')}`} value={money(s.opening.cash)} tone="muted" />
                <Row label={`${t('stmt.opening')} — ${t('stmt.bank')}`} value={money(s.opening.bank)} tone="muted" />
                {s.receipts_by_head.map((h) => (
                  <Row key={h.code} label={headName(h)} value={money(h.amount)} />
                ))}
                <Row label={t('stmt.grandTotal')} value={money(s.left_total)} bold />
              </tbody>
            </table>
          </div>
          <div>
            <p className="mb-1 text-sm font-semibold text-status-due">{t('stmt.payments')}</p>
            <table className="w-full text-sm">
              <tbody>
                {s.payments_by_head.map((h) => (
                  <Row key={h.code} label={headName(h)} value={money(h.amount)} />
                ))}
                <Row label={`${t('stmt.closing')} — ${t('stmt.cash')}`} value={money(s.closing.cash)} tone="muted" />
                <Row label={`${t('stmt.closing')} — ${t('stmt.bank')}`} value={money(s.closing.bank)} tone="muted" />
                <Row label={t('stmt.grandTotal')} value={money(s.right_total)} bold />
              </tbody>
            </table>
          </div>
        </div>
        <p className="mt-2 text-xs text-status-paid">{s.left_total === s.right_total ? `✓ ${t('stmt.balanced')}` : ''}</p>
      </div>

      <div className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
        <h2 className="mb-3 font-semibold text-navy-900">{t('stmt.details')}</h2>
        {n === 0 ? (
          <p className="text-sm text-navy-700">{t('stmt.none')}</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="text-navy-700">
                <tr>
                  <th className="py-1 pr-2">{t('stmt.date')}</th>
                  <th className="pr-2">
                    {t('stmt.receipts')} — {t('stmt.particulars')}
                  </th>
                  <th className="pr-4 text-right">{t('stmt.taka')}</th>
                  <th className="pr-2">{t('stmt.date')}</th>
                  <th className="pr-2">
                    {t('stmt.payments')} — {t('stmt.particulars')}
                  </th>
                  <th className="text-right">{t('stmt.taka')}</th>
                </tr>
              </thead>
              <tbody>
                {Array.from({ length: n }, (_, i) => {
                  const r = s.receipts[i];
                  const p = s.payments[i];
                  return (
                    <tr key={i} className="border-t border-brand-100 align-top">
                      <td className="whitespace-nowrap py-1 pr-2">{r?.date}</td>
                      <td className="pr-2">{r && lineName(r)}</td>
                      <td className="pr-4 text-right">{r && money(r.amount)}</td>
                      <td className="whitespace-nowrap pr-2">{p?.date}</td>
                      <td className="pr-2">{p && lineName(p)}</td>
                      <td className="text-right">{p && money(p.amount)}</td>
                    </tr>
                  );
                })}
                <tr className="border-t-2 border-brand-300 bg-brand-50 font-semibold">
                  <td />
                  <td>{t('stmt.total')}</td>
                  <td className="pr-4 text-right">{money(s.totals.receipts)}</td>
                  <td />
                  <td>{t('stmt.total')}</td>
                  <td className="text-right">{money(s.totals.payments)}</td>
                </tr>
              </tbody>
            </table>
          </div>
        )}
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <div className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
          <h2 className="mb-2 font-semibold text-navy-900">{t('stmt.bankNote')}</h2>
          <table className="w-full text-sm">
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
        </div>
        {s.loans.length > 0 && <LoansCard loans={s.loans} />}
      </div>
    </div>
  );
}

function LoansCard({ loans }: { loans: FundStatement['loans'] }) {
  const { t, lang } = useLanguage();
  return (
    <div className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
      <h2 className="mb-2 font-semibold text-navy-900">{t('stmt.loans')}</h2>
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

function SummaryView({ s, fundName }: { s: Summary; fundName: (c: FundCode) => string }) {
  const { t } = useLanguage();
  return (
    <div className="space-y-4">
      <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
        <h2 className="mb-2 font-semibold text-navy-900">{t('stmt.fundSummary')}</h2>
        <table className="w-full text-left text-sm">
          <thead className="text-navy-700">
            <tr>
              <th className="py-1 pr-3">{t('stmt.fundLabel')}</th>
              <th className="pr-3 text-right">{t('stmt.opening')}</th>
              <th className="pr-3 text-right">{t('stmt.receipts')}</th>
              <th className="pr-3 text-right">{t('stmt.payments')}</th>
              <th className="pr-3 text-right">{t('stmt.closing')}</th>
              <th className="pr-3 text-right">{t('stmt.cash')}</th>
              <th className="text-right">{t('stmt.bank')}</th>
            </tr>
          </thead>
          <tbody>
            {s.funds.map((f) => (
              <tr key={f.fund.code} className="border-t border-brand-100">
                <td className="py-1.5 pr-3 font-medium">{fundName(f.fund.code)}</td>
                <td className="pr-3 text-right">{money(f.opening)}</td>
                <td className="pr-3 text-right">{money(f.receipts)}</td>
                <td className="pr-3 text-right">{money(f.payments)}</td>
                <td className="pr-3 text-right font-medium">{money(f.closing)}</td>
                <td className="pr-3 text-right text-navy-700">{money(f.closing_cash)}</td>
                <td className="text-right text-navy-700">{money(f.closing_bank)}</td>
              </tr>
            ))}
            <tr className="border-t-2 border-brand-300 bg-brand-50 font-semibold">
              <td className="py-1.5 pr-3">{t('stmt.total')}</td>
              <td className="pr-3 text-right">{money(s.total.opening)}</td>
              <td className="pr-3 text-right">{money(s.total.receipts)}</td>
              <td className="pr-3 text-right">{money(s.total.payments)}</td>
              <td className="pr-3 text-right">{money(s.total.closing)}</td>
              <td colSpan={2} />
            </tr>
          </tbody>
        </table>
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
