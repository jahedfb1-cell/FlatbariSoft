import { useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import InvoicePicker from '../components/InvoicePicker';
import SupplierPicker from '../components/SupplierPicker';
import InvoiceCell from '../components/InvoiceCell';
import * as materialsApi from '../api/constructionMaterials';
import type { ConstructionMaterialPurchase } from '../api/constructionMaterials';

export default function ConstructionMaterialsPage() {
  const { t, lang } = useLanguage();
  const [purchases, setPurchases] = useState<ConstructionMaterialPurchase[]>([]);
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [itemName, setItemName] = useState('');
  const [quantity, setQuantity] = useState('');
  const [unit, setUnit] = useState('');
  const [unitRate, setUnitRate] = useState('');
  const [supplierId, setSupplierId] = useState('');
  const [supplierName, setSupplierName] = useState('');
  const [transportCost, setTransportCost] = useState('0');
  const [medium, setMedium] = useState<'CASH' | 'BANK'>('CASH');
  const [invoice, setInvoice] = useState<File | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);

  function resetForm() {
    setEditingId(null);
    setInvoice(null);
    setItemName('');
    setQuantity('');
    setUnit('');
    setUnitRate('');
    setSupplierName('');
    setSupplierId('');
    setTransportCost('0');
    setMedium('CASH');
    setDate(new Date().toISOString().slice(0, 10));
  }

  /** Load a pending purchase into the form to correct it. */
  function startEdit(p: ConstructionMaterialPurchase) {
    setError(null);
    setEditingId(p.id);
    setDate(p.date);
    setItemName(p.item_name);
    setQuantity(String(Number(p.quantity)));
    setUnit(p.unit);
    setUnitRate(String(Number(p.unit_rate)));
    setSupplierId(p.supplier_id ? String(p.supplier_id) : '');
    setSupplierName(p.supplier_name ?? '');
    setTransportCost(String(Number(p.transport_cost)));
    setMedium(p.treasury_medium ?? 'CASH');
    setInvoice(null);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  async function handleCancel(p: ConstructionMaterialPurchase) {
    if (!confirm(lang === 'bn' ? `${p.date} — ${p.item_name} এর ক্রয়টি বাতিল করবেন? খরচের এন্ট্রিও মুছে যাবে।` : 'Cancel this purchase? Its pending expense is removed too.')) return;
    setError(null);
    try {
      await materialsApi.cancelMaterialPurchase(p.id);
      if (editingId === p.id) resetForm();
      load();
    } catch (err: any) {
      const errors = err.response?.data?.errors;
      setError(errors ? Object.values(errors).flat().join(' ') : err.response?.data?.message);
    }
  }

  function load() {
    materialsApi.listMaterialPurchases().then(setPurchases);
  }

  useEffect(load, []);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      const payload = {
        date,
        item_name: itemName,
        quantity,
        unit,
        unit_rate: unitRate,
        supplier_id: supplierId || undefined,
        supplier_name: supplierId ? undefined : supplierName || undefined,
        transport_cost: transportCost,
        treasury_medium: medium,
        invoice_image: invoice,
      };
      if (editingId) await materialsApi.updateMaterialPurchase(editingId, payload);
      else await materialsApi.recordMaterialPurchase(payload);
      resetForm();
      load();
    } catch (err: any) {
      const errors = err.response?.data?.errors;
      setError(errors ? Object.values(errors).flat().join(' ') : err.response?.data?.message);
    }
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-4xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('materials.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        <form onSubmit={handleSubmit} className="mb-2 grid grid-cols-2 gap-3 rounded-xl border border-brand-200 bg-white p-4 shadow-sm sm:grid-cols-4">
          {editingId && (
            <div className="col-span-2 flex flex-wrap items-center gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 sm:col-span-4">
              <span>{lang === 'bn' ? 'আপনি একটি অনুমোদনের অপেক্ষায় থাকা ক্রয় সংশোধন করছেন। সংরক্ষণ করলে খরচের পরিমাণও বদলে যাবে। ফটো না দিলে আগেরটাই থাকবে।' : 'You are correcting a purchase that is waiting for approval. Its expense changes with it. Leave the photo empty to keep the old one.'}</span>
              <button type="button" onClick={resetForm} className="ml-auto rounded-lg border border-amber-300 bg-white px-3 py-1 text-xs hover:bg-amber-100">
                {lang === 'bn' ? 'সংশোধন বাদ দিন' : 'Stop editing'}
              </button>
            </div>
          )}
          <input type="date" required value={date} onChange={(e) => setDate(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input required placeholder={t('materials.itemName')} value={itemName} onChange={(e) => setItemName(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input required type="number" step="0.01" placeholder={t('materials.quantity')} value={quantity} onChange={(e) => setQuantity(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input required placeholder={t('materials.unit')} value={unit} onChange={(e) => setUnit(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input required type="number" step="0.01" placeholder={t('materials.unitRate')} value={unitRate} onChange={(e) => setUnitRate(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <div className="col-span-2 sm:col-span-2">
            <SupplierPicker kind="MATERIALS" required={false} value={supplierId} onChange={(id, name) => { setSupplierId(id); setSupplierName(name); }} />
          </div>
          <input type="number" step="0.01" placeholder={t('materials.transportCost')} value={transportCost} onChange={(e) => setTransportCost(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <select value={medium} onChange={(e) => setMedium(e.target.value as 'CASH' | 'BANK')} className="rounded-lg border border-brand-200 px-3 py-2">
            <option value="CASH">CASH</option>
            <option value="BANK">BANK</option>
          </select>
          <button type="submit" className="col-span-2 rounded-lg bg-brand-600 px-3 py-2 text-white hover:bg-brand-700 sm:col-span-1">
            {editingId ? (lang === 'bn' ? 'সংশোধন সংরক্ষণ করুন' : 'Save correction') : t('common.submit')}
          </button>
          <div className="col-span-2 sm:col-span-4">
            <InvoicePicker file={invoice} onChange={setInvoice} />
          </div>
        </form>
        <p className="mb-4 text-xs text-navy-700">{t('materials.approveHint')}</p>
        {error && <p className="mb-4 text-sm text-status-due">{error}</p>}

        <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
          <table className="w-full text-left text-sm">
            <thead className="bg-brand-100 text-navy-900">
              <tr>
                <th className="px-4 py-3">{t('common.date')}</th>
                <th className="px-4 py-3">{t('materials.itemName')}</th>
                <th className="px-4 py-3">{t('materials.quantity')}</th>
                <th className="px-4 py-3">{t('materials.unit')}</th>
                <th className="px-4 py-3">{t('materials.totalCost')}</th>
                <th className="px-4 py-3">{t('common.status')}</th>
                <th className="px-4 py-3">{t('invoice.col')}</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody>
              {purchases.map((p) => (
                <tr key={p.id} className="border-t border-brand-100">
                  <td className="px-4 py-3 text-navy-900">{p.date}</td>
                  <td className="px-4 py-3 text-navy-900">{p.item_name}</td>
                  <td className="px-4 py-3 text-navy-900">{p.quantity}</td>
                  <td className="px-4 py-3 text-navy-900">{p.unit}</td>
                  <td className="px-4 py-3 text-navy-900">৳{Number(p.total_cost).toLocaleString('en-BD')}</td>
                  <td className="px-4 py-3 text-navy-900">{p.expense_status === 'PENDING' ? t('common.pending') : t('common.approved')}</td>
                  <td className="px-4 py-3">
                    <InvoiceCell type="materials" id={p.id} hasInvoice={p.has_invoice} invoiceKb={p.invoice_kb} onChanged={load} />
                  </td>
                  <td className="whitespace-nowrap px-4 py-3 text-right">
                    {p.editable ? (
                      <>
                        <button onClick={() => startEdit(p)} className="rounded-lg border border-brand-200 px-2.5 py-1 text-xs text-brand-700 hover:bg-brand-100">
                          {lang === 'bn' ? 'সংশোধন' : 'Edit'}
                        </button>
                        <button onClick={() => handleCancel(p)} className="ml-1 rounded-lg border border-red-200 px-2.5 py-1 text-xs text-status-due hover:bg-red-50">
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
      </div>
    </div>
  );
}
