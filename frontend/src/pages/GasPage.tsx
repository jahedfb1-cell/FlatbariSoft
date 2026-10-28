import { useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import InvoicePicker from '../components/InvoicePicker';
import InvoiceCell from '../components/InvoiceCell';
import * as gasApi from '../api/gas';
import * as billingApi from '../api/billing';
import type { GasConsumer, GasPurchase } from '../api/gas';

export default function GasPage() {
  const { t } = useLanguage();
  const now = new Date();
  const [year] = useState(now.getFullYear());
  const [month] = useState(now.getMonth() + 1);
  const [consumers, setConsumers] = useState<GasConsumer[]>([]);
  const [readings, setReadings] = useState<Record<number, string>>({});
  const [message, setMessage] = useState<string | null>(null);

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
        <div className="mb-6 flex items-center justify-between">
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

        <div className="space-y-2">
          {consumers.map((c) => (
            <div key={c.id} className="flex items-center justify-between rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
              <div>
                <p className="font-medium text-navy-900">
                  {c.name} {c.flat_no ? `(${c.flat_no})` : ''} {c.is_internal && '· অভ্যন্তরীণ'}
                </p>
                <p className="text-sm text-navy-700">
                  {t('gas.lastReading')}: {c.last_reading ?? '—'}
                </p>
              </div>
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
            </div>
          ))}
        </div>

        <div className="mb-3 mt-8 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-navy-900">{t('gas.purchasesTitle')}</h2>
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
      </div>
    </div>
  );
}
