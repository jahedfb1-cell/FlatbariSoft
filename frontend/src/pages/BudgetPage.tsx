import { useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import Modal from '../components/Modal';
import { blobErrorMessage } from '../utils/download';
import * as ops from '../api/operations';
import type { BudgetReport } from '../api/operations';

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { maximumFractionDigits: 0 })}`;
const MONTHS_BN = ['জানু', 'ফেব্রু', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টে', 'অক্টো', 'নভে', 'ডিসে'];
const MONTHS_EN = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const btn = 'rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm text-navy-700 hover:bg-brand-100 disabled:opacity-50';

/** A "+12%" / "−5%" chip: spending this year against last year (more spending is red, less is green). */
function Change({ pct }: { pct: number | null }) {
  const { t } = useLanguage();
  if (pct === null) return <span className="text-xs text-navy-700">{t('budget.noLast')}</span>;
  const up = pct > 0;
  return (
    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${pct === 0 ? 'bg-brand-100 text-navy-700' : up ? 'bg-red-50 text-status-due' : 'bg-green-50 text-status-paid'}`}>
      {up ? '▲' : pct < 0 ? '▼' : '＝'} {Math.abs(pct)}%
    </span>
  );
}

/**
 * The yearly budget (July-June) against what was really spent, head by head. Spent is worked out from the
 * approved expenses. Each head also shows last year, the twelve months show where the money went, a budget can
 * be started from another year's, and the whole thing prints as PDF or Excel.
 */
export default function BudgetPage() {
  const { t, lang } = useLanguage();
  const { user } = useAuth();
  const canEdit = !!user && (user.roles.includes('super_admin') || user.permissions.includes('budget.manage'));
  const now = new Date();
  const [year, setYear] = useState(now.getMonth() >= 6 ? now.getFullYear() : now.getFullYear() - 1);
  const [rep, setRep] = useState<BudgetReport | null>(null);
  const [edit, setEdit] = useState<Record<number, string>>({});
  const [msg, setMsg] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [showMonths, setShowMonths] = useState(false);
  const [copying, setCopying] = useState(false);
  const [busy, setBusy] = useState<string | null>(null);

  useEffect(() => {
    let current = true;
    setRep(null);
    setEdit({});
    setMsg(null);
    setError(null);
    ops.getBudget(year).then((r) => current && setRep(r)).catch(() => current && setError(t('somethingWrong')));
    return () => {
      current = false;
    };
  }, [year, t]);

  const monthName = (m: number) => (lang === 'bn' ? MONTHS_BN : MONTHS_EN)[m - 1];

  async function save() {
    setMsg(null);
    setError(null);
    const items = Object.entries(edit).map(([id, amount]) => ({ expense_head_id: Number(id), amount: amount || '0' }));
    if (items.length === 0) return;
    try {
      setRep(await ops.saveBudget(year, items));
      setEdit({});
      setMsg(t('budget.saved'));
    } catch (e: any) {
      setError(e?.response?.data?.message ?? t('somethingWrong'));
    }
  }

  async function copy(from: number, percent: number, overwrite: boolean) {
    setError(null);
    try {
      const r = await ops.copyBudget({ from_year: from, to_year: year, increase_percent: percent, overwrite });
      setRep(r);
      setEdit({});
      setCopying(false);
      setMsg(`${t('budget.copied')} (${r.copied})`);
    } catch (e: any) {
      const errs = e?.response?.data?.errors;
      throw new Error(errs ? (Object.values(errs) as string[][]).flat().join(' ') : (e?.response?.data?.message ?? t('somethingWrong')));
    }
  }

  async function run(key: string, action: () => Promise<unknown>) {
    setBusy(key);
    setError(null);
    try {
      await action();
    } catch (e) {
      setError(await blobErrorMessage(e, t('somethingWrong')));
    } finally {
      setBusy(null);
    }
  }

  const bar = (pct: number | null, over: boolean) => (
    <div className="mt-1 h-2 overflow-hidden rounded-full bg-brand-100">
      <div className={`h-full rounded-full ${over ? 'bg-status-due' : 'bg-brand-500'}`} style={{ width: `${Math.min(100, pct ?? 0)}%` }} />
    </div>
  );

  const maxMonth = rep ? Math.max(1, ...rep.months.map((m) => Number(m.spent)), Number(rep.monthly_budget_share)) : 1;
  const hasBudget = !!rep && Number(rep.totals.budget) > 0;

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-6 pb-16">
      <div className="mx-auto max-w-3xl">
        <div className="mb-4 flex items-center justify-between gap-3">
          <Link to="/" className="text-sm text-navy-700 hover:underline">
            ← {t('nav.dashboard')}
          </Link>
          <LanguageToggle />
        </div>
        <h1 className="text-xl font-semibold text-navy-900 sm:text-2xl">{t('budget.title')}</h1>
        <p className="mb-4 mt-1 text-sm text-navy-700">{t('budget.intro')}</p>

        <div className="mb-4 flex flex-wrap items-center gap-2">
          <div className="flex items-center overflow-hidden rounded-xl border border-brand-200 bg-white text-sm shadow-sm">
            <button onClick={() => setYear(year - 1)} className="px-4 py-2 text-lg hover:bg-brand-100" aria-label="previous year">
              ‹
            </button>
            <span className="min-w-28 px-2 text-center text-base font-semibold text-navy-900">{rep?.label ?? `${year}-${String(year + 1).slice(2)}`}</span>
            <button onClick={() => setYear(year + 1)} className="px-4 py-2 text-lg hover:bg-brand-100" aria-label="next year">
              ›
            </button>
          </div>
          <div className="ml-auto grid grid-cols-2 gap-2 sm:flex">
            {canEdit && (
              <button className={`${btn} col-span-2 sm:col-span-1`} onClick={() => setCopying(true)}>
                📋 {t('budget.copyBtn')}
              </button>
            )}
            <button className={btn} disabled={busy !== null || !rep} onClick={() => run('pdf', () => ops.printBudget(year))}>
              🖨 PDF
            </button>
            <button className={btn} disabled={busy !== null || !rep} onClick={() => run('xlsx', () => ops.downloadBudgetExcel(year))}>
              Excel
            </button>
          </div>
        </div>

        {msg && <div className="mb-3 rounded-lg bg-green-50 p-3 text-sm text-status-paid">{msg}</div>}
        {error && <div className="mb-3 rounded-lg bg-red-50 p-3 text-sm text-status-due">{error}</div>}
        {!rep && !error && <div className="h-40 animate-pulse rounded-2xl bg-white/70" />}

        {rep && (
          <>
            {!hasBudget && (
              <div className="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-navy-900">
                <p className="font-medium">{t('budget.emptyTitle')}</p>
                <p className="mt-1 text-navy-700">{t('budget.emptyBody')}</p>
              </div>
            )}

            <div className="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
              {[
                [t('budget.budget'), money(rep.totals.budget), '', false],
                [t('budget.spent'), money(rep.totals.spent), rep.totals.percent !== null ? `${rep.totals.percent}% ${t('budget.used')}` : '', false],
                [t('budget.remaining'), money(rep.totals.remaining), '', Number(rep.totals.remaining) < 0],
                [`${t('budget.lastYear')} (${rep.last_label})`, money(rep.totals.last_spent), '', false],
              ].map(([l, v, x, bad], i) => (
                <div key={i} className="rounded-xl border border-brand-100 bg-white p-3 shadow-sm">
                  <p className="text-xs text-navy-700">{l}</p>
                  <p className={`text-lg font-semibold ${bad ? 'text-status-due' : 'text-navy-900'}`}>{v}</p>
                  {i === 3 ? <Change pct={rep.totals.spent_change_percent} /> : x && <p className="text-xs text-navy-700">{x}</p>}
                </div>
              ))}
            </div>

            {/* month by month */}
            <div className="mb-4 rounded-2xl border border-brand-100 bg-white p-4 shadow-sm">
              <div className="flex items-center justify-between gap-2">
                <h2 className="text-sm font-semibold text-navy-900">📅 {t('budget.byMonth')}</h2>
                <button onClick={() => setShowMonths(!showMonths)} className="text-sm text-brand-700 hover:underline">
                  {showMonths ? t('budget.hideDetail') : t('budget.showDetail')}
                </button>
              </div>
              <div className="relative mt-3 flex h-32 items-end gap-1 sm:gap-2">
                {Number(rep.monthly_budget_share) > 0 && (
                  <div className="pointer-events-none absolute inset-x-0 border-t border-dashed border-navy-500/60" style={{ bottom: `${(Number(rep.monthly_budget_share) / maxMonth) * 100}%` }}>
                    <span className="absolute -top-4 right-0 bg-white pl-1 text-[10px] text-navy-700">{t('budget.evenShare')} {money(rep.monthly_budget_share)}</span>
                  </div>
                )}
                {rep.months.map((m) => {
                  const over = Number(rep.monthly_budget_share) > 0 && Number(m.spent) > Number(rep.monthly_budget_share);
                  return (
                    <div key={`${m.year}-${m.month}`} className="flex flex-1 flex-col items-center justify-end" title={`${monthName(m.month)} ${m.year}: ${money(m.spent)}`}>
                      <div className={`w-full rounded-t ${over ? 'bg-status-due' : 'bg-brand-500'}`} style={{ height: `${(Number(m.spent) / maxMonth) * 100}%`, minHeight: Number(m.spent) > 0 ? 2 : 0 }} />
                    </div>
                  );
                })}
              </div>
              <div className="mt-1 flex gap-1 sm:gap-2">
                {rep.months.map((m) => (
                  <span key={`${m.year}-${m.month}`} className="flex-1 text-center text-[10px] text-navy-700">
                    {monthName(m.month)}
                  </span>
                ))}
              </div>
              {showMonths && (
                <div className="mt-3 space-y-3 border-t border-brand-100 pt-3">
                  {rep.rows
                    .filter((r) => Number(r.spent) > 0)
                    .map((r) => (
                      <div key={r.expense_head_id}>
                        <p className="text-sm font-medium text-navy-900">{lang === 'bn' ? r.name_bn : r.name_en}</p>
                        <div className="mt-1 grid grid-cols-4 gap-x-2 gap-y-1 text-xs sm:grid-cols-6">
                          {r.monthly.map((v, i) =>
                            Number(v) > 0 ? (
                              <span key={i} className="rounded bg-brand-50 px-1.5 py-1 text-navy-900">
                                <span className="text-navy-700">{monthName(rep.months[i].month)}</span> {money(v)}
                              </span>
                            ) : null,
                          )}
                        </div>
                      </div>
                    ))}
                  {rep.rows.every((r) => Number(r.spent) === 0) && <p className="text-sm text-navy-700">{t('budget.noSpending')}</p>}
                </div>
              )}
            </div>

            {/* head by head */}
            <div className="space-y-2">
              {rep.rows.map((r) => (
                <div key={r.expense_head_id} className="rounded-xl border border-brand-100 bg-white p-4 shadow-sm">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="font-medium text-navy-900">{lang === 'bn' ? r.name_bn : r.name_en}</span>
                    {canEdit ? (
                      <label className="flex items-center gap-1 text-sm text-navy-700">
                        ৳
                        <input
                          type="number"
                          min={0}
                          className="w-32 rounded-lg border border-brand-200 px-2 py-1 text-right text-base"
                          value={edit[r.expense_head_id] ?? String(Number(r.budget))}
                          onChange={(e) => setEdit({ ...edit, [r.expense_head_id]: e.target.value })}
                        />
                      </label>
                    ) : (
                      <span className="text-sm text-navy-900">{money(r.budget)}</span>
                    )}
                  </div>
                  {bar(r.percent, r.over)}
                  <p className={`mt-1 text-xs ${r.over ? 'text-status-due' : 'text-navy-700'}`}>
                    {t('budget.spent')} {money(r.spent)}
                    {r.percent !== null ? ` (${r.percent}%)` : ''} · {t('budget.remaining')} {money(r.remaining)}
                  </p>
                  {(Number(r.last_spent) > 0 || Number(r.last_budget) > 0 || Number(r.spent) > 0) && (
                    <p className="mt-1 flex flex-wrap items-center gap-2 text-xs text-navy-700">
                      {t('budget.lastYear')}: {t('budget.spent')} {money(r.last_spent)}
                      {Number(r.last_budget) > 0 ? ` · ${t('budget.budget')} ${money(r.last_budget)}` : ''}
                      <Change pct={r.spent_change_percent} />
                    </p>
                  )}
                </div>
              ))}
            </div>

            {canEdit && Object.keys(edit).length > 0 && (
              <div className="sticky bottom-3 mt-4">
                <button onClick={save} className="w-full rounded-xl bg-brand-600 px-4 py-3 font-medium text-white shadow-lg hover:bg-brand-700 sm:w-auto">
                  {t('budget.save')}
                  {` (${Object.keys(edit).length})`}
                </button>
              </div>
            )}
          </>
        )}
      </div>

      {copying && <CopyModal toYear={year} onClose={() => setCopying(false)} onCopy={copy} />}
    </div>
  );
}

/** Start this year's budget from another year's, raised by a percentage if wanted. */
function CopyModal({ toYear, onClose, onCopy }: { toYear: number; onClose: () => void; onCopy: (from: number, percent: number, overwrite: boolean) => Promise<void> }) {
  const { t } = useLanguage();
  const [from, setFrom] = useState(toYear - 1);
  const [percent, setPercent] = useState('0');
  const [overwrite, setOverwrite] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const label = (y: number) => `${y}-${String(y + 1).slice(2)}`;

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await onCopy(from, Number(percent) || 0, overwrite);
    } catch (err: any) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal title={t('budget.copyTitle')} onClose={onClose}>
      <form onSubmit={submit} className="space-y-3 text-sm text-navy-900">
        <p className="text-navy-700">
          {t('budget.copyHelp')} <b>{label(toYear)}</b>
        </p>
        <label className="block">
          {t('budget.copyFrom')}
          <select className="mt-1 w-full rounded-lg border border-brand-200 px-3 py-2 text-base" value={from} onChange={(e) => setFrom(Number(e.target.value))}>
            {[1, 2, 3, 4].map((i) => (
              <option key={i} value={toYear - i}>
                {label(toYear - i)}
              </option>
            ))}
          </select>
        </label>
        <label className="block">
          {t('budget.increase')}
          <div className="mt-1 flex items-center gap-2">
            <input type="number" step="0.5" className="w-28 rounded-lg border border-brand-200 px-3 py-2 text-base" value={percent} onChange={(e) => setPercent(e.target.value)} />
            <span>%</span>
            {[0, 5, 10].map((p) => (
              <button type="button" key={p} onClick={() => setPercent(String(p))} className="rounded-lg border border-brand-200 px-2 py-1 text-xs hover:bg-brand-100">
                {p}%
              </button>
            ))}
          </div>
          <span className="text-xs text-navy-700">{t('budget.increaseHint')}</span>
        </label>
        <label className="flex items-start gap-2">
          <input type="checkbox" className="mt-1" checked={overwrite} onChange={(e) => setOverwrite(e.target.checked)} />
          <span>
            {t('budget.overwrite')}
            <span className="block text-xs text-navy-700">{t('budget.overwriteHint')}</span>
          </span>
        </label>
        {error && <p className="rounded-lg bg-red-50 p-2 text-status-due">{error}</p>}
        <div className="flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-brand-200 px-3 py-2 text-navy-700">
            {t('complaints.cancel')}
          </button>
          <button disabled={busy} className="rounded-lg bg-brand-600 px-4 py-2 text-white hover:bg-brand-700 disabled:opacity-50">
            {t('budget.copyDo')}
          </button>
        </div>
      </form>
    </Modal>
  );
}
