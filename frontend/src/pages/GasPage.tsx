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
import SupplierPicker from '../components/SupplierPicker';
import GasReadingSheet from '../components/GasReadingSheet';
import { localToday } from '../components/HallCalendar';

const kgFmt = (v: string | number) => Number(v).toLocaleString('en-BD', { maximumFractionDigits: 2 });

export default function GasPage() {
  const { t, lang } = useLanguage();
  const now = new Date();
  const [year] = useState(now.getFullYear());
  const [month] = useState(now.getMonth() + 1);
  // the readings sheet has its own month: usually last month's meters are read at the start of this month
  const [sheetYM, setSheetYM] = useState({ year: now.getFullYear(), month: now.getMonth() + 1 });
  const stepSheet = (by: number) => setSheetYM((v) => {
    const idx = v.year * 12 + (v.month - 1) + by;
    return { year: Math.floor(idx / 12), month: (idx % 12) + 1 };
  });
  const [consumers, setConsumers] = useState<GasConsumer[]>([]);
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
  const [pDate, setPDate] = useState(localToday());
  const [pSupplier, setPSupplier] = useState('');
  const [pCylinders, setPCylinders] = useState('1');
  const [pCylKg, setPCylKg] = useState('45');
  const [pCylPrice, setPCylPrice] = useState('');
  const [pTransport, setPTransport] = useState('0');
  const [pMedium, setPMedium] = useState<'CASH' | 'BANK'>('CASH');
  const [pInvoice, setPInvoice] = useState<File | null>(null);
  const [pError, setPError] = useState<string | null>(null);
  const [editingPurchase, setEditingPurchase] = useState<number | null>(null);

  function resetPurchaseForm() {
    setEditingPurchase(null);
    setPInvoice(null);
    setPSupplier('');
    setPCylinders('1');
    setPCylKg('45');
    setPCylPrice('');
    setPTransport('0');
    setPMedium('CASH');
    setPDate(localToday());
  }

  /** Load a pending purchase into the form to correct it. */
  function startEdit(p: GasPurchase) {
    setPError(null);
    setEditingPurchase(p.id);
    setPDate(p.date);
    setPSupplier(p.supplier_id ? String(p.supplier_id) : '');
    if (p.cylinders) {
      setPCylinders(String(p.cylinders));
      setPCylKg(String(Number(p.cylinder_kg)));
      setPCylPrice(String(Number(p.price_per_cylinder)));
    } else {
      // an older kg-and-rate purchase: show it as one lot, same money
      setPCylinders('1');
      setPCylKg(String(Number(p.quantity_kg)));
      setPCylPrice(String(Number(p.total_cost) - Number(p.transport_cost)));
    }
    setPTransport(String(Number(p.transport_cost)));
    setPMedium(p.treasury_medium ?? 'CASH');
    setPInvoice(null);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  async function handleCancelPurchase(p: GasPurchase) {
    if (!confirm(lang === 'bn' ? `${p.date} — ${p.supplier_name} এর ক্রয়টি বাতিল করবেন? খরচের এন্ট্রিও মুছে যাবে।` : 'Cancel this purchase? Its pending expense is removed too.')) return;
    setPError(null);
    try {
      await gasApi.cancelGasPurchase(p.id);
      if (editingPurchase === p.id) resetPurchaseForm();
      load();
    } catch (err: any) {
      const errors = err.response?.data?.errors;
      setPError(errors ? Object.values(errors).flat().join(' ') : err.response?.data?.message);
    }
  }

  function load() {
    gasApi.listGasConsumers().then(setConsumers).catch(() => undefined);
    gasApi.listGasPurchases().then(setPurchases).catch(() => undefined);
    gasApi.getGasSummary(year, month).then(setSummary).catch(() => undefined);
    listFlats().then(setFlats).catch(() => undefined);
  }

  useEffect(load, []);

  async function handleRecordPurchase(e: FormEvent) {
    e.preventDefault();
    setPError(null);
    try {
      const payload = {
        date: pDate,
        supplier_id: pSupplier,
        cylinders: pCylinders,
        cylinder_kg: pCylKg,
        price_per_cylinder: pCylPrice,
        transport_cost: pTransport,
        treasury_medium: pMedium,
        invoice_image: pInvoice,
      };
      if (editingPurchase) await gasApi.updateGasPurchase(editingPurchase, payload);
      else await gasApi.recordGasPurchase(payload);
      resetPurchaseForm();
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

  async function handleApply() {
    setMessage(null);
    try {
      const period = await billingApi.findOrCreateBillingPeriod(sheetYM.year, sheetYM.month);
      const res = await gasApi.applyGasToBills(period.id);
      setMessage(`${sheetYM.month}/${sheetYM.year}: ${res.message}`);
    } catch (err: any) {
      setMessage(apiMessage(err));
    }
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

        <form onSubmit={handleRecordPurchase} className="mb-4 space-y-3 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
          <p className="text-sm font-semibold text-navy-900">{editingPurchase ? (lang === 'bn' ? 'ক্রয় সংশোধন' : 'Correct purchase') : t('gas.recordPurchase')}</p>
          {editingPurchase && (
            <div className="flex flex-wrap items-center gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
              <span>{lang === 'bn' ? 'আপনি একটি অনুমোদনের অপেক্ষায় থাকা ক্রয় সংশোধন করছেন। সংরক্ষণ করলে খরচের পরিমাণও বদলে যাবে। ফটো না দিলে আগেরটাই থাকবে।' : 'You are correcting a purchase that is waiting for approval. Its expense changes with it. Leave the photo empty to keep the old one.'}</span>
              <button type="button" onClick={resetPurchaseForm} className="ml-auto rounded-lg border border-amber-300 bg-white px-3 py-1 text-xs hover:bg-amber-100">
                {lang === 'bn' ? 'সংশোধন বাদ দিন' : 'Stop editing'}
              </button>
            </div>
          )}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label className="block text-xs text-navy-700">
              {t('gas.supplierName')} *
              <div className="mt-1">
                <SupplierPicker kind="GAS" value={pSupplier} onChange={(id) => setPSupplier(id)} />
              </div>
            </label>
            <label className="block text-xs text-navy-700">
              {lang === 'bn' ? 'তারিখ' : 'Date'} *
              <input type="date" required value={pDate} onChange={(e) => setPDate(e.target.value)} className="mt-1 w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" />
            </label>
          </div>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <label className="block text-xs text-navy-700">
              {lang === 'bn' ? 'সিলিন্ডার (টি) *' : 'Cylinders *'}
              <input required type="number" min={1} step={1} value={pCylinders} onChange={(e) => setPCylinders(e.target.value)} className="mt-1 w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" />
            </label>
            <label className="block text-xs text-navy-700">
              {lang === 'bn' ? 'প্রতি সিলিন্ডারে কেজি *' : 'kg per cylinder *'}
              <input required type="number" min={0.1} step="0.01" value={pCylKg} onChange={(e) => setPCylKg(e.target.value)} className="mt-1 w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" />
            </label>
            <label className="block text-xs text-navy-700">
              {lang === 'bn' ? 'একটি সিলিন্ডারের দাম (৳) *' : 'Price of one cylinder (৳) *'}
              <input required type="number" min={1} step="0.01" value={pCylPrice} onChange={(e) => setPCylPrice(e.target.value)} className="mt-1 w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" />
            </label>
            <label className="block text-xs text-navy-700">
              {t('gas.transportCost')}
              <input type="number" min={0} step="0.01" value={pTransport} onChange={(e) => setPTransport(e.target.value)} className="mt-1 w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" />
            </label>
          </div>
          <div className="flex flex-wrap items-center gap-3 rounded-lg bg-brand-50 p-3 text-sm text-navy-900">
            <span>{lang === 'bn' ? 'মোট গ্যাস' : 'Total gas'}: <b>{(Number(pCylinders || 0) * Number(pCylKg || 0)).toLocaleString('en-BD')} kg</b></span>
            <span>{lang === 'bn' ? 'কেজি প্রতি' : 'Per kg'}: <b>৳{Number(pCylKg) > 0 && Number(pCylPrice) > 0 ? (Number(pCylPrice) / Number(pCylKg)).toLocaleString('en-BD', { maximumFractionDigits: 2 }) : '—'}</b></span>
            <span className="ml-auto text-base">{lang === 'bn' ? 'মোট টাকা' : 'Total'}: <b>৳{(Number(pCylinders || 0) * Number(pCylPrice || 0) + Number(pTransport || 0)).toLocaleString('en-BD', { maximumFractionDigits: 2 })}</b></span>
          </div>
          <div className="flex flex-wrap items-end gap-3">
            <label className="block text-xs text-navy-700">
              {lang === 'bn' ? 'কোথা থেকে দেবেন' : 'Paid from'}
              <select value={pMedium} onChange={(e) => setPMedium(e.target.value as 'CASH' | 'BANK')} className="mt-1 block rounded-lg border border-brand-200 px-3 py-2 text-sm">
                <option value="CASH">{lang === 'bn' ? 'ক্যাশ' : 'Cash'}</option>
                <option value="BANK">{lang === 'bn' ? 'ব্যাংক' : 'Bank'}</option>
              </select>
            </label>
            <button type="submit" disabled={!pSupplier} className="rounded-lg bg-brand-600 px-5 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
              {editingPurchase ? (lang === 'bn' ? 'সংশোধন সংরক্ষণ করুন' : 'Save correction') : t('gas.recordPurchase')}
            </button>
          </div>
          <InvoicePicker file={pInvoice} onChange={setPInvoice} />
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
                <th className="px-4 py-3" />
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
                  <td className="whitespace-nowrap px-4 py-3 text-right">
                    {p.editable ? (
                      <>
                        <button onClick={() => startEdit(p)} className="rounded-lg border border-brand-200 px-2.5 py-1 text-xs text-brand-700 hover:bg-brand-100">
                          {lang === 'bn' ? 'সংশোধন' : 'Edit'}
                        </button>
                        <button onClick={() => handleCancelPurchase(p)} className="ml-1 rounded-lg border border-red-200 px-2.5 py-1 text-xs text-status-due hover:bg-red-50">
                          {lang === 'bn' ? 'বাতিল' : 'Cancel'}
                        </button>
                      </>
                    ) : (
                      <span className="text-xs text-navy-500">{lang === 'bn' ? 'অনুমোদিত' : 'Locked'}</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <h2 className="mb-3 mt-8 text-lg font-semibold text-navy-900">{t('gas.readingsTitle')}</h2>
        <div className="mb-3 flex items-center gap-2">
          <button onClick={() => stepSheet(-1)} className="rounded-lg border border-brand-200 bg-white px-3 py-1.5 text-sm text-navy-700 hover:bg-brand-100" aria-label="previous month">‹</button>
          <span className="text-sm text-navy-900">{sheetYM.month}/{sheetYM.year}</span>
          <button onClick={() => stepSheet(1)} className="rounded-lg border border-brand-200 bg-white px-3 py-1.5 text-sm text-navy-700 hover:bg-brand-100" aria-label="next month">›</button>
        </div>
        <div className="mb-6">
          <GasReadingSheet year={sheetYM.year} month={sheetYM.month} onSaved={() => load()} />
        </div>
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
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
