import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import { useAuth } from '../context/AuthContext';
import LanguageToggle from '../components/LanguageToggle';
import { blobErrorMessage } from '../utils/download';
import * as api from '../api/suppliers';
import type { SupplierKind, SupplierLedger, SupplierList } from '../api/suppliers';

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { maximumFractionDigits: 2 })}`;
const btnGhost = 'rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700 hover:bg-brand-100 disabled:opacity-50';

/**
 * Who we buy from and what is owed to each: one tab for building construction material suppliers, one for gas
 * suppliers. A supplier opens into its own ledger - every bill (credit), every payment (debit) and the balance
 * still owed - printable as PDF and Excel.
 */
export default function SuppliersPage() {
  const { t } = useLanguage();
  const { user } = useAuth();
  const [params, setParams] = useSearchParams();
  const isAdmin = !!user?.roles.includes('super_admin');
  const can = (p: string) => isAdmin || !!user?.permissions.includes(p);
  const allowed: SupplierKind[] = [...(can('expense.view') ? (['materials'] as const) : []), ...(can('gas.view') ? (['gas'] as const) : [])];
  const asked = params.get('kind') as SupplierKind | null;
  const kind: SupplierKind = asked && allowed.includes(asked) ? asked : (allowed[0] ?? 'materials');
  const supplier = params.get('supplier');

  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [list, setList] = useState<SupplierList | null>(null);
  const [ledger, setLedger] = useState<SupplierLedger | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let current = true;
    const fail = (e: any) => current && setError(e?.response?.data?.message ?? t('somethingWrong'));
    setError(null);
    if (supplier) {
      setLedger(null);
      api.getSupplierLedger(kind, supplier, from, to).then((d) => current && setLedger(d)).catch(fail);
    } else {
      setList(null);
      api.listSuppliers(kind, from, to).then((d) => current && setList(d)).catch(fail);
    }
    return () => {
      current = false;
    };
  }, [kind, supplier, from, to, t]);

  const go = useCallback((next: Record<string, string>) => setParams(next), [setParams]);

  async function run(action: () => Promise<unknown>) {
    setError(null);
    setBusy(true);
    try {
      await action();
    } catch (e) {
      setError(await blobErrorMessage(e, t('somethingWrong')));
    } finally {
      setBusy(false);
    }
  }

  const tab = (k: SupplierKind) => (
    <button
      key={k}
      onClick={() => go({ kind: k })}
      className={`rounded-lg px-4 py-2 text-sm font-medium ${kind === k ? 'bg-brand-600 text-white' : 'border border-brand-200 bg-white text-navy-700 hover:bg-brand-100'}`}
    >
      {t(k === 'gas' ? 'suppliers.gas' : 'suppliers.materials')}
    </button>
  );

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-5xl">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('suppliers.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        <div className="mb-4 flex flex-wrap items-end gap-2">
          {allowed.map(tab)}
          <label className="ml-auto text-sm text-navy-700">
            {t('gasLedger.from')}
            <input type="date" className="mt-1 block rounded-lg border border-brand-200 px-3 py-2" value={from} onChange={(e) => setFrom(e.target.value)} />
          </label>
          <label className="text-sm text-navy-700">
            {t('gasLedger.to')}
            <input type="date" className="mt-1 block rounded-lg border border-brand-200 px-3 py-2" value={to} onChange={(e) => setTo(e.target.value)} />
          </label>
        </div>

        {error && <div className="mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</div>}

        {!supplier && list && (
          <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead className="bg-brand-100 text-left text-navy-700">
                <tr>
                  <th className="p-3">{t('suppliers.name')}</th>
                  <th className="p-3 text-right">{t('suppliers.purchases')}</th>
                  <th className="p-3 text-right">{t('suppliers.billed')}</th>
                  <th className="p-3 text-right">{t('suppliers.paid')}</th>
                  <th className="p-3 text-right">{t('suppliers.owed')}</th>
                  <th className="p-3">{t('suppliers.last')}</th>
                </tr>
              </thead>
              <tbody>
                {list.suppliers.map((s) => (
                  <tr key={s.key} className="border-t border-brand-100">
                    <td className="p-3">
                      <button className="font-medium text-brand-700 hover:underline" onClick={() => go({ kind, supplier: s.name })}>
                        {s.name}
                      </button>
                    </td>
                    <td className="p-3 text-right">{s.count}</td>
                    <td className="p-3 text-right">{money(s.billed)}</td>
                    <td className="p-3 text-right">{money(s.paid)}</td>
                    <td className={`p-3 text-right font-semibold ${Number(s.owed) > 0 ? 'text-red-700' : ''}`}>{money(s.owed)}</td>
                    <td className="p-3">{s.last_purchase}</td>
                  </tr>
                ))}
                {list.suppliers.length === 0 && (
                  <tr>
                    <td colSpan={6} className="p-6 text-center text-navy-700">
                      {t('suppliers.none')}
                    </td>
                  </tr>
                )}
              </tbody>
              {list.suppliers.length > 0 && (
                <tfoot className="border-t-2 border-brand-200 bg-brand-50 font-semibold">
                  <tr>
                    <td className="p-3">{t('suppliers.total')}</td>
                    <td className="p-3 text-right">{list.totals.count}</td>
                    <td className="p-3 text-right">{money(list.totals.billed)}</td>
                    <td className="p-3 text-right">{money(list.totals.paid)}</td>
                    <td className="p-3 text-right">{money(list.totals.owed)}</td>
                    <td />
                  </tr>
                </tfoot>
              )}
            </table>
          </div>
        )}

        {supplier && (
          <div>
            <div className="mb-3 flex flex-wrap items-center gap-2">
              <button className={btnGhost} onClick={() => go({ kind })}>
                ← {t('suppliers.allSuppliers')}
              </button>
              <h2 className="text-lg font-semibold text-navy-900">{ledger?.supplier ?? supplier}</h2>
              <div className="ml-auto flex gap-2">
                <button className={btnGhost} disabled={busy} onClick={() => run(() => api.printSupplierLedger(kind, supplier, from, to))}>
                  🖨 PDF
                </button>
                <button className={btnGhost} disabled={busy} onClick={() => run(() => api.downloadSupplierLedgerExcel(kind, supplier, from, to))}>
                  Excel
                </button>
              </div>
            </div>
            {ledger && (
              <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
                <table className="w-full text-sm">
                  <thead className="bg-brand-100 text-left text-navy-700">
                    <tr>
                      <th className="p-3">{t('suppliers.date')}</th>
                      <th className="p-3">{t('suppliers.ref')}</th>
                      <th className="p-3">{t('suppliers.particulars')}</th>
                      <th className="p-3">{t('suppliers.voucher')}</th>
                      <th className="p-3 text-right">{t('suppliers.credit')}</th>
                      <th className="p-3 text-right">{t('suppliers.debit')}</th>
                      <th className="p-3 text-right">{t('suppliers.balance')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr className="border-t border-brand-100 italic">
                      <td className="p-3" colSpan={6}>
                        {t('suppliers.opening')}
                      </td>
                      <td className="p-3 text-right">{money(ledger.opening)}</td>
                    </tr>
                    {ledger.rows.map((r, i) => (
                      <tr key={i} className="border-t border-brand-100">
                        <td className="whitespace-nowrap p-3">{r.date}</td>
                        <td className="p-3">{r.ref}</td>
                        <td className="p-3">{r.particulars}</td>
                        <td className="p-3">{r.voucher_no ?? '—'}</td>
                        <td className="p-3 text-right">{Number(r.credit) ? money(r.credit) : ''}</td>
                        <td className="p-3 text-right text-green-700">{Number(r.debit) ? money(r.debit) : ''}</td>
                        <td className="p-3 text-right font-medium">{money(r.balance)}</td>
                      </tr>
                    ))}
                  </tbody>
                  <tfoot className="border-t-2 border-brand-200 bg-brand-50 font-semibold">
                    <tr>
                      <td className="p-3" colSpan={4}>
                        {t('suppliers.total')}
                      </td>
                      <td className="p-3 text-right">{money(ledger.totals.purchased)}</td>
                      <td className="p-3 text-right">{money(ledger.totals.paid)}</td>
                      <td className={`p-3 text-right ${Number(ledger.totals.closing) > 0 ? 'text-red-700' : ''}`}>{money(ledger.totals.closing)}</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            )}
            <p className="mt-2 text-xs text-navy-700">{t('suppliers.note')}</p>
          </div>
        )}
      </div>
    </div>
  );
}
