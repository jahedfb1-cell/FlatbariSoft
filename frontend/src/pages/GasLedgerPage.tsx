import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import InvoiceCell from '../components/InvoiceCell';
import { localToday } from '../components/HallCalendar';
import { blobErrorMessage } from '../utils/download';
import * as ledgerApi from '../api/gasLedger';
import type { GasLedger } from '../api/gasLedger';

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { maximumFractionDigits: 2 })}`;
const kg = (v: string | number) => Number(v).toLocaleString('en-BD', { maximumFractionDigits: 2 });
const btn = 'rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50';
const btnGhost = 'rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700 hover:bg-brand-100 disabled:opacity-50';

/** The fiscal year is July-June (BR-02): its first day for a given date. */
function fiscalYearStart(today: string): string {
  const [y, m] = today.split('-').map(Number);
  return `${m >= 7 ? y : y - 1}-07-01`;
}

/**
 * Every gas purchase in date order with a running total, the voucher number it was booked under and the
 * bill photo - view it, print it, or attach a missing one right from the row. Also prints/exports the
 * whole book, optionally with every voucher photo behind it.
 */
export default function GasLedgerPage() {
  const { t } = useLanguage();
  const today = localToday();
  const [from, setFrom] = useState(fiscalYearStart(today));
  const [to, setTo] = useState(today);
  const [data, setData] = useState<GasLedger | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    ledgerApi
      .getGasLedger(from, to)
      .then(setData)
      .catch((e) => setError(e?.response?.data?.errors ? (Object.values(e.response.data.errors) as string[][]).flat().join(' ') : t('somethingWrong')));
  }, [from, to, t]);
  useEffect(load, [load]);

  async function run(key: string, action: () => Promise<unknown>) {
    setError(null);
    setBusy(key);
    try {
      await action();
    } catch (e) {
      setError(await blobErrorMessage(e, t('somethingWrong')));
    } finally {
      setBusy(null);
    }
  }

  const totals = data?.totals;

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-6xl">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <Link to="/gas" className="text-sm text-navy-700 hover:underline">
              ← {t('gasLedger.back')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('gasLedger.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        <div className="mb-4 flex flex-wrap items-end gap-2 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
          <label className="text-sm text-navy-700">
            {t('gasLedger.from')}
            <input type="date" className="mt-1 block rounded-lg border border-brand-200 px-3 py-2" value={from} onChange={(e) => setFrom(e.target.value)} />
          </label>
          <label className="text-sm text-navy-700">
            {t('gasLedger.to')}
            <input type="date" className="mt-1 block rounded-lg border border-brand-200 px-3 py-2" value={to} onChange={(e) => setTo(e.target.value)} />
          </label>
          <button
            className={btnGhost}
            onClick={() => {
              setFrom(fiscalYearStart(today));
              setTo(today);
            }}
          >
            {t('gasLedger.thisFiscalYear')}
          </button>
          <button
            className={btnGhost}
            onClick={() => {
              setFrom('');
              setTo('');
            }}
          >
            {t('gasLedger.allTime')}
          </button>
          <div className="ml-auto flex flex-wrap gap-2">
            <button className={btnGhost} disabled={busy !== null} onClick={() => run('pdf', () => ledgerApi.printGasLedger(from, to, false))}>
              {t('gasLedger.pdf')}
            </button>
            <button className={btn} disabled={busy !== null} onClick={() => run('pdfv', () => ledgerApi.printGasLedger(from, to, true))}>
              {t('gasLedger.pdfVouchers')}
            </button>
            <button className={btnGhost} disabled={busy !== null} onClick={() => run('xlsx', () => ledgerApi.downloadGasLedgerExcel(from, to))}>
              {t('gasLedger.excel')}
            </button>
          </div>
        </div>

        {error && <p className="mb-3 text-sm text-status-due">{error}</p>}

        {totals && data && (
          <>
            <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
              <Card label={t('gasLedger.count')} value={String(totals.count)} />
              <Card label={t('gasLedger.totalKg')} value={kg(totals.quantity_kg)} />
              <Card label={t('gasLedger.totalCost')} value={money(totals.total_cost)} />
              <Card label={t('gasLedger.avgPerKg')} value={money(totals.avg_cost_per_kg)} />
              <Card label={t('gasLedger.gasCost')} value={money(totals.gas_cost)} />
              <Card label={t('gasLedger.transport')} value={money(totals.transport_cost)} />
              <Card label={`${t('gasLedger.approved')} / ${t('gasLedger.pending')}`} value={`${money(totals.approved_cost)} / ${money(totals.pending_cost)}`} />
              <Card label={`${t('gasLedger.billsHave')} / ${t('gasLedger.billsMissing')}`} value={`${totals.with_invoice} / ${totals.without_invoice}`} tone={totals.without_invoice > 0 ? 'due' : undefined} />
            </div>
            <p className="mb-4 rounded-lg border border-brand-200 bg-white px-4 py-2 text-sm text-navy-900">
              {t('gasLedger.overall')}: <b>{kg(data.overall.quantity_kg)} kg</b> · <b>{money(data.overall.total_cost)}</b> · {data.overall.count} {t('gasLedger.purchases')}
            </p>

            {data.by_month.length > 0 && (
              <div className="mb-4 overflow-x-auto rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
                <h2 className="mb-2 font-semibold text-navy-900">{t('gasLedger.byMonth')}</h2>
                <table className="w-full text-left text-sm">
                  <tbody>
                    {data.by_month.map((m) => (
                      <tr key={m.month} className="border-t border-brand-100">
                        <td className="py-1.5 pr-3 font-medium">{m.month}</td>
                        <td className="pr-3">
                          {m.count} {t('gasLedger.purchases')}
                        </td>
                        <td className="pr-3 text-right">{kg(m.quantity_kg)} kg</td>
                        <td className="text-right">{money(m.total_cost)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
              {data.rows.length === 0 ? (
                <p className="p-4 text-sm text-navy-700">{t('gasLedger.none')}</p>
              ) : (
                <table className="w-full text-left text-sm">
                  <thead className="bg-brand-100 text-navy-900">
                    <tr>
                      <th className="px-3 py-2">{t('gasLedger.ref')}</th>
                      <th className="px-3 py-2">{t('common.date')}</th>
                      <th className="px-3 py-2">{t('gas.supplierName')}</th>
                      <th className="px-3 py-2 text-right">{t('gas.quantityKg')}</th>
                      <th className="px-3 py-2 text-right">{t('gas.unitPrice')}</th>
                      <th className="px-3 py-2 text-right">{t('gas.transportCost')}</th>
                      <th className="px-3 py-2 text-right">{t('gasLedger.total')}</th>
                      <th className="px-3 py-2">{t('gasLedger.voucherNo')}</th>
                      <th className="px-3 py-2 text-right">{t('gasLedger.runningKg')}</th>
                      <th className="px-3 py-2 text-right">{t('gasLedger.runningTotal')}</th>
                      <th className="px-3 py-2">{t('gasLedger.bill')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.rows.map((r) => (
                      <tr key={r.id} className="border-t border-brand-100 align-top">
                        <td className="px-3 py-2 font-medium">{r.ref}</td>
                        <td className="whitespace-nowrap px-3 py-2">{r.date}</td>
                        <td className="px-3 py-2">{r.supplier_name}</td>
                        <td className="px-3 py-2 text-right">{kg(r.quantity_kg)}</td>
                        <td className="px-3 py-2 text-right">{kg(r.unit_price)}</td>
                        <td className="px-3 py-2 text-right">{kg(r.transport_cost)}</td>
                        <td className="px-3 py-2 text-right font-medium">{money(r.total_cost)}</td>
                        <td className="px-3 py-2">
                          {r.voucher_no ?? <span className="text-xs text-status-partial">{t('gasLedger.voucherPending')}</span>}
                        </td>
                        <td className="px-3 py-2 text-right text-navy-700">{kg(r.running_kg)}</td>
                        <td className="px-3 py-2 text-right text-navy-700">{money(r.running_cost)}</td>
                        <td className="px-3 py-2">
                          <InvoiceCell type="gas" id={r.id} hasInvoice={r.has_invoice} invoiceKb={null} onChanged={load} />
                        </td>
                      </tr>
                    ))}
                    <tr className="border-t-2 border-brand-300 bg-brand-50 font-semibold">
                      <td className="px-3 py-2" colSpan={3}>
                        {t('gasLedger.total')}
                      </td>
                      <td className="px-3 py-2 text-right">{kg(totals.quantity_kg)}</td>
                      <td />
                      <td className="px-3 py-2 text-right">{kg(totals.transport_cost)}</td>
                      <td className="px-3 py-2 text-right">{money(totals.total_cost)}</td>
                      <td colSpan={4} />
                    </tr>
                  </tbody>
                </table>
              )}
            </div>
          </>
        )}
      </div>
    </div>
  );
}

function Card({ label, value, tone }: { label: string; value: string; tone?: 'due' }) {
  return (
    <div className="rounded-xl border border-brand-200 bg-white p-3 shadow-sm">
      <p className="text-xs text-navy-700">{label}</p>
      <p className={`text-lg font-semibold ${tone === 'due' ? 'text-status-due' : 'text-navy-900'}`}>{value}</p>
    </div>
  );
}
