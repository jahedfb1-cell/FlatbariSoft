import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as ops from '../api/operations';
import type { BudgetReport } from '../api/operations';

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { maximumFractionDigits: 0 })}`;

/** The yearly budget (July-June) against what was really spent, head by head. Spent is worked out from the approved expenses. */
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

  useEffect(() => {
    let current = true;
    setRep(null);
    setEdit({});
    ops.getBudget(year).then((r) => current && setRep(r)).catch(() => current && setError(t('somethingWrong')));
    return () => {
      current = false;
    };
  }, [year, t]);

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

  const bar = (pct: number | null, over: boolean) => (
    <div className="mt-1 h-2 overflow-hidden rounded-full bg-brand-100">
      <div className={`h-full rounded-full ${over ? 'bg-status-due' : 'bg-brand-500'}`} style={{ width: `${Math.min(100, pct ?? 0)}%` }} />
    </div>
  );

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-3xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('budget.title')}</h1>
          </div>
          <div className="flex items-center gap-2">
            <LanguageToggle />
            <div className="flex items-center rounded-lg border border-brand-200 bg-white text-sm">
              <button onClick={() => setYear(year - 1)} className="px-3 py-1.5 hover:bg-brand-100">
                ‹
              </button>
              <span className="px-2 font-medium">{rep?.label ?? `${year}-${String(year + 1).slice(2)}`}</span>
              <button onClick={() => setYear(year + 1)} className="px-3 py-1.5 hover:bg-brand-100">
                ›
              </button>
            </div>
          </div>
        </div>

        {msg && <div className="mb-3 rounded-lg bg-green-50 p-3 text-sm text-status-paid">{msg}</div>}
        {error && <div className="mb-3 rounded-lg bg-red-50 p-3 text-sm text-status-due">{error}</div>}

        {rep && (
          <>
            <div className="mb-4 grid grid-cols-3 gap-3">
              {[
                [t('budget.budget'), money(rep.totals.budget), ''],
                [t('budget.spent'), money(rep.totals.spent), rep.totals.percent !== null ? `${rep.totals.percent}%` : ''],
                [t('budget.remaining'), money(rep.totals.remaining), Number(rep.totals.remaining) < 0 ? 'due' : ''],
              ].map(([l, v, x]) => (
                <div key={l} className="rounded-xl border border-brand-100 bg-white p-3 shadow-sm">
                  <p className="text-xs text-navy-700">{l}</p>
                  <p className={`text-lg font-semibold ${x === 'due' ? 'text-status-due' : 'text-navy-900'}`}>{v}</p>
                  {x && x !== 'due' && <p className="text-xs text-navy-700">{x}</p>}
                </div>
              ))}
            </div>

            <div className="space-y-2">
              {rep.rows.map((r) => (
                <div key={r.expense_head_id} className="rounded-xl border border-brand-100 bg-white p-4 shadow-sm">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="font-medium text-navy-900">{lang === 'bn' ? r.name_bn : r.name_en}</span>
                    {canEdit ? (
                      <input
                        type="number"
                        min={0}
                        className="w-32 rounded-lg border border-brand-200 px-2 py-1 text-right text-sm"
                        value={edit[r.expense_head_id] ?? String(Number(r.budget))}
                        onChange={(e) => setEdit({ ...edit, [r.expense_head_id]: e.target.value })}
                      />
                    ) : (
                      <span className="text-sm text-navy-900">{money(r.budget)}</span>
                    )}
                  </div>
                  {bar(r.percent, r.over)}
                  <p className={`mt-1 text-xs ${r.over ? 'text-status-due' : 'text-navy-700'}`}>
                    {t('budget.spent')} {money(r.spent)}
                    {r.percent !== null ? ` (${r.percent}%)` : ''} · {t('budget.remaining')} {money(r.remaining)}
                  </p>
                </div>
              ))}
            </div>

            {canEdit && (
              <button onClick={save} disabled={Object.keys(edit).length === 0} className="mt-4 w-full rounded-xl bg-brand-600 px-4 py-3 font-medium text-white hover:bg-brand-700 disabled:opacity-40 sm:w-auto">
                {t('budget.save')}
              </button>
            )}
          </>
        )}
      </div>
    </div>
  );
}
