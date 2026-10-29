import { useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import InvoicePicker from '../components/InvoicePicker';
import InvoiceCell from '../components/InvoiceCell';
import * as gasApi from '../api/gas';
import * as billingApi from '../api/billing';
import { listFlats, type Flat } from '../api/flats';
import type { GasConsumer, GasPurchase, GasSummary } from '../api/gas';

const kgFmt = (v: string | number) => Number(v).toLocaleString('en-BD', { maximumFractionDigits: 2 });

export default function GasPage() {
  const { t } = useLanguage();
  const now = new Date();
  const [year] = useState(now.getFullYear());
  const [month] = useState(now.getMonth() + 1);
  const [consumers, setConsumers] = useState<GasConsumer[]>([]);
  const [readings, setReadings] = useState<Record<number, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [summary, setSummary] = useState<GasSummary | null>(null);

  // adding / editing a gas consumer by hand
  const [flats, setFlats] = useState<Flat[]>([]);
  const [showAdd, setShowAdd] = useState(false);
  const [cFlat, setCFlat] = useState('');
  const [cName, setCName] = useState('');
  const [cMeter, setCMeter] = useState('');
  const [cInternal, setCInternal] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [eName, setEName] = useState('');
  const [eMeter, setEMeter] = useState('');

  const [purchases, setPurchases] = useState<GasPurchase[]>([]);
  const [pDate, setPDate] = useState(new Date().toISOString().slice(0, 10));
  const [pSupplier, setPSupplier] = useState('');
  const [pQty, setPQty] = useState('');
  const [pRate, setPRate] = useState('');
  const [pTransport, setPTransport] = useState('0');
  const [pMedium, setPMedium] = useState<'CASH' | 'BANK'>('CASH');
  const [pInvoice, setPInvoice] = useState<File | null>(null);
  const [pError, setPError] = useState<string | null>(null);

  function load() {
    gasApi.listGasConsumers().then(setConsumers);
    gasApi.listGasPurchases().then(setPurchases);
    gasApi.getGasSummary(year, month).then(setSummary).catch(() => undefined);
    listFlats().then(setFlats).catch(() => undefined);
  }

  useEffect(load, []);

  async function handleRecordPurchase(e: FormEvent) {
    e.preventDefault();
    setPError(null);
    try {
      await gasApi.recordGasPurchase({
        date: pDate,
        supplier_name: pSupplier,
        quantity_kg: pQty,
        unit_price: pRate,
        transport_cost: pTransport,
        treasury_medium: pMedium,
        invoice_image: pInvoice,
      });
      setPInvoice(null);
      setPSupplier('');
      setPQty('');
      setPRate('');
      setPTransport('0');
      load();
    } catch (err: any) {
      const errors = err.response?.data?.errors;
      setPError(errors ? Object.values(errors).flat().join(' ') : err.response?.data?.message);
    }
  }

  const apiMessage = (err: any) =>
    err.response?.data?.errors ? (Object.values(err.response.data.errors) as string[][]).flat().join(' ') : err.response?.data?.message || 'Error';

  async function handleAddConsumer(e: FormEvent) {
    e.preventDefault();
    setMessage(null);
    const flat = flats.find((f) => String(f.id) === cFlat);
    try {
      await gasApi.createGasConsumer({
        flat_id: flat ? flat.id : null,
        name: cName.trim() || (flat ? `Flat ${flat.flat_no}` : ''),
        meter_no: cMeter.trim() || undefined,
        is_internal: !flat && cInternal,
      });
      setCFlat('');
      setCName('');
      setCMeter('');
      setCInternal(false);
      setShowAdd(false);
      setMessage(t('gas.consumerAdded'));
      load();
    } catch (err: any) {
      setMessage(apiMessage(err));
    }
  }

  async function saveEdit(id: number) {
    try {
      await gasApi.updateGasConsumer(id, { name: eName.trim(), meter_no: eMeter.trim() || null });
      setEditId(null);
      load();
    } catch (err: any) {
      setMessage(apiMessage(err));
    }
  }

  async function toggleActive(c: GasConsumer) {
    try {
      await gasApi.updateGasConsumer(c.id, { active: !c.active });
      load();
    } catch (err: any) {
      setMessage(apiMessage(err));
    }
  }

  async function handleSubmit(consumerId: number) {
    const value = readings[consumerId];
    if (!value) return;
    try {
      const period = await billingApi.findOrCreateBillingPeriod(year, month);
      await gasApi.recordGasReading({
        gas_consumer_id: consumerId,
        billing_period_id: period.id,
        current_reading: value,
        reading_date: new Date().toISOString().slice(0, 10),
      });
      setMessage('রিডিং সংরক্ষিত হয়েছে।');
      setReadings((r) => ({ ...r, [consumerId]: '' }));
      load();
    } catch (err: any) {
      setMessage(err.response?.data?.errors?.current_reading?.[0] || err.response?.data?.message || 'Error');
    }
  }

  async function handleApply() {
    const period = await billingApi.findOrCreateBillingPeriod(year, month);
    const res = await gasApi.applyGasToBills(period.id);
    setMessage(res.message);
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-3xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('gas.title')}</h1>
          </div>
          <div className="flex items-center gap-2">
            <LanguageToggle />
            <button onClick={handleApply} className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm text-white hover:bg-brand-700">
              {t('gas.applyToBills')}
            </button>
          </div>
        </div>

        {message && <p className="mb-4 text-sm text-navy-900">{message}</p>}

        {summary && (
          <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            {[
              [t('gas.sumTotalPurchase'), `${kgFmt(summary.total_kg)} kg`, `৳${Number(summary.total_cost).toLocaleString('en-BD', { maximumFractionDigits: 2 })} · ${summary.total_purchases} ${t('gas.sumTimes')}`],
              [t('gas.sumMonthPurchase'), `${kgFmt(summary.month_kg)} kg`, `৳${Number(summary.month_cost).toLocaleString('en-BD', { maximumFractionDigits: 2 })}`],
              [t('gas.sumFlatsUsed'), `${kgFmt(summary.flats_used_m3)} m³`, `${summary.flats_read}/${summary.flats_total} ${t('gas.sumFlatsRead')}`],
              [t('gas.sumLoss'), summary.loss_percent === null ? '—' : `${summary.loss_percent}%`, `${t('gas.sumOffice')}: ${kgFmt(summary.office_used_m3)} m³`],
            ].map(([label, value, sub]) => (
              <div key={label} className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
                <p className="text-xs text-navy-700">{label}</p>
                <p className="mt-1 text-xl font-semibold text-navy-900">{value}</p>
                <p className="mt-1 text-xs text-navy-700">{sub}</p>
              </div>
            ))}
          </div>
        )}

        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
          <h2 className="text-lg font-semibold text-navy-900">{t('gas.purchasesTitle')}</h2>
          <Link to="/suppliers?kind=gas" className="ml-auto mr-2 rounded-lg border border-brand-200 bg-white px-3 py-1.5 text-sm text-brand-700 hover:bg-brand-100">
            {t('suppliers.gas')}
          </Link>
          <Link to="/gas/ledger" className="rounded-lg border border-brand-200 bg-white px-3 py-1.5 text-sm text-brand-700 hover:bg-brand-100">
            📒 {t('gasLedger.open')}
          </Link>
        </div>

        <form onSubmit={handleRecordPurchase} className="mb-4 grid grid-cols-2 gap-3 rounded-xl border border-brand-200 bg-white p-4 shadow-sm sm:grid-cols-6">
          <input type="date" required value={pDate} onChange={(e) => setPDate(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input required placeholder={t('gas.supplierName')} value={pSupplier} onChange={(e) => setPSupplier(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input required type="number" step="0.01" placeholder={t('gas.quantityKg')} value={pQty} onChange={(e) => setPQty(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input required type="number" step="0.01" placeholder={t('gas.unitPrice')} value={pRate} onChange={(e) => setPRate(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input type="number" step="0.01" placeholder={t('gas.transportCost')} value={pTransport} onChange={(e) => setPTransport(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <select value={pMedium} onChange={(e) => setPMedium(e.target.value as 'CASH' | 'BANK')} className="rounded-lg border border-brand-200 px-3 py-2">
            <option value="CASH">CASH</option>
            <option value="BANK">BANK</option>
          </select>
          <button type="submit" className="col-span-2 rounded-lg bg-brand-600 px-3 py-2 text-white hover:bg-brand-700 sm:col-span-1">
            {t('gas.recordPurchase')}
          </button>
          <div className="col-span-2 sm:col-span-6">
            <InvoicePicker file={pInvoice} onChange={setPInvoice} />
          </div>
        </form>
        {pError && <p className="mb-4 text-sm text-status-due">{pError}</p>}

        <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
          <table className="w-full text-left text-sm">
            <thead className="bg-brand-100 text-navy-900">
              <tr>
                <th className="px-4 py-3">{t('common.date')}</th>
                <th className="px-4 py-3">{t('gas.supplierName')}</th>
                <th className="px-4 py-3">{t('gas.quantityKg')}</th>
                <th className="px-4 py-3">{t('gas.totalCost')}</th>
                <th className="px-4 py-3">{t('common.status')}</th>
                <th className="px-4 py-3">{t('invoice.col')}</th>
              </tr>
            </thead>
            <tbody>
              {purchases.map((p) => (
                <tr key={p.id} className="border-t border-brand-100">
                  <td className="px-4 py-3 text-navy-900">{p.date}</td>
                  <td className="px-4 py-3 text-navy-900">{p.supplier_name}</td>
                  <td className="px-4 py-3 text-navy-900">{p.quantity_kg}</td>
                  <td className="px-4 py-3 text-navy-900">৳{Number(p.total_cost).toLocaleString('en-BD')}</td>
                  <td className="px-4 py-3 text-navy-900">{p.expense_status === 'PENDING' ? t('common.pending') : t('common.approved')}</td>
                  <td className="px-4 py-3">
                    <InvoiceCell type="gas" id={p.id} hasInvoice={p.has_invoice} invoiceKb={p.invoice_kb} onChanged={load} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <h2 className="mb-3 mt-8 text-lg font-semibold text-navy-900">{t('gas.readingsTitle')}</h2>
        <div className="mb-3">
          <button onClick={() => setShowAdd((v) => !v)} className="rounded-lg border border-brand-200 bg-white px-3 py-1.5 text-sm text-brand-700 hover:bg-brand-100">
            + {t('gas.addConsumer')}
          </button>
        </div>

        {showAdd && (
          <form onSubmit={handleAddConsumer} className="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-brand-200 bg-white p-4 shadow-sm sm:grid-cols-4">
            <label className="text-sm text-navy-700">
              {t('gas.consumerFlat')}
              <select className="mt-1 block w-full rounded-lg border border-brand-200 px-3 py-2" value={cFlat} onChange={(e) => setCFlat(e.target.value)}>
                <option value="">{t('gas.noFlat')}</option>
                {flats.map((f) => (
                  <option key={f.id} value={f.id}>
                    {f.flat_no}
                  </option>
                ))}
              </select>
            </label>
            <label className="text-sm text-navy-700">
              {t('gas.consumerName')}
              <input className="mt-1 block w-full rounded-lg border border-brand-200 px-3 py-2" value={cName} onChange={(e) => setCName(e.target.value)} required={!cFlat} />
            </label>
            <label className="text-sm text-navy-700">
              {t('gas.meterNo')}
              <input className="mt-1 block w-full rounded-lg border border-brand-200 px-3 py-2" value={cMeter} onChange={(e) => setCMeter(e.target.value)} />
            </label>
            <div className="flex items-end gap-3">
              {!cFlat && (
                <label className="flex items-center gap-1 text-sm text-navy-700">
                  <input type="checkbox" checked={cInternal} onChange={(e) => setCInternal(e.target.checked)} />
                  {t('gas.internalUse')}
                </label>
              )}
              <button className="rounded-lg bg-brand-600 px-3 py-2 text-sm text-white hover:bg-brand-700">{t('gas.save')}</button>
            </div>
          </form>
        )}

        <div className="space-y-2">
          {consumers.map((c) => (
            <div key={c.id} className={`flex items-center justify-between rounded-xl border border-brand-200 bg-white p-4 shadow-sm ${c.active ? '' : 'opacity-60'}`}>
              <div>
                {editId === c.id ? (
                  <div className="flex flex-wrap gap-2">
                    <input className="w-40 rounded-lg border border-brand-200 px-2 py-1 text-sm" value={eName} onChange={(e) => setEName(e.target.value)} />
                    <input className="w-32 rounded-lg border border-brand-200 px-2 py-1 text-sm" placeholder={t('gas.meterNo')} value={eMeter} onChange={(e) => setEMeter(e.target.value)} />
                    <button onClick={() => saveEdit(c.id)} className="rounded-lg bg-brand-600 px-2 py-1 text-xs text-white">
                      {t('gas.save')}
                    </button>
                    <button onClick={() => setEditId(null)} className="rounded-lg border border-brand-200 px-2 py-1 text-xs text-navy-700">
                      ✕
                    </button>
                  </div>
                ) : (
                  <p className="font-medium text-navy-900">
                    {c.name} {c.flat_no ? `(${c.flat_no})` : ''} {c.is_internal && '· অভ্যন্তরীণ'} {!c.active && `· ${t('gas.off')}`}
                  </p>
                )}
                <p className="text-sm text-navy-700">
                  {t('gas.lastReading')}: {c.last_reading ?? '—'}
                  {c.meter_no ? ` · ${t('gas.meterNo')}: ${c.meter_no}` : ''}
                </p>
                <p className="mt-1 text-xs">
                  <button
                    className="text-brand-700 hover:underline"
                    onClick={() => {
                      setEditId(c.id);
                      setEName(c.name);
                      setEMeter(c.meter_no ?? '');
                    }}
                  >
                    {t('gas.edit')}
                  </button>
                  {' · '}
                  <button className="text-navy-700 hover:underline" onClick={() => toggleActive(c)}>
                    {c.active ? t('gas.switchOff') : t('gas.switchOn')}
                  </button>
                </p>
              </div>
              {c.active && (
              <div className="flex items-center gap-2">
                <input
                  type="number"
                  placeholder={t('gas.currentReading')}
                  value={readings[c.id] ?? ''}
                  onChange={(e) => setReadings((r) => ({ ...r, [c.id]: e.target.value }))}
                  className="w-32 rounded-lg border border-brand-200 px-3 py-2"
                />
                <button
                  onClick={() => handleSubmit(c.id)}
                  className="rounded-lg bg-brand-600 px-3 py-2 text-sm text-white hover:bg-brand-700"
                >
                  {t('gas.enterReading')}
                </button>
              </div>
              )}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
