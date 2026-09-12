import { useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as materialsApi from '../api/constructionMaterials';
import type { ConstructionMaterialPurchase } from '../api/constructionMaterials';

export default function ConstructionMaterialsPage() {
  const { t } = useLanguage();
  const [purchases, setPurchases] = useState<ConstructionMaterialPurchase[]>([]);
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [itemName, setItemName] = useState('');
  const [quantity, setQuantity] = useState('');
  const [unit, setUnit] = useState('');
  const [unitRate, setUnitRate] = useState('');
  const [supplierName, setSupplierName] = useState('');
  const [transportCost, setTransportCost] = useState('0');
  const [medium, setMedium] = useState<'CASH' | 'BANK'>('CASH');
  const [error, setError] = useState<string | null>(null);

  function load() {
    materialsApi.listMaterialPurchases().then(setPurchases);
  }

  useEffect(load, []);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await materialsApi.recordMaterialPurchase({
        date,
        item_name: itemName,
        quantity,
        unit,
        unit_rate: unitRate,
        supplier_name: supplierName || undefined,
        transport_cost: transportCost,
        treasury_medium: medium,
      });
      setItemName('');
      setQuantity('');
      setUnit('');
      setUnitRate('');
      setSupplierName('');
      setTransportCost('0');
      load();
    } catch (err: any) {
      const errors = err.response?.data?.errors;
      setError(errors ? Object.values(errors).flat().join(' ') : err.response?.data?.message);
    }
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-4xl">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('materials.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        <form onSubmit={handleSubmit} className="mb-2 grid grid-cols-2 gap-3 rounded-xl border border-brand-200 bg-white p-4 shadow-sm sm:grid-cols-4">
          <input type="date" required value={date} onChange={(e) => setDate(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input required placeholder={t('materials.itemName')} value={itemName} onChange={(e) => setItemName(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input required type="number" step="0.01" placeholder={t('materials.quantity')} value={quantity} onChange={(e) => setQuantity(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input required placeholder={t('materials.unit')} value={unit} onChange={(e) => setUnit(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input required type="number" step="0.01" placeholder={t('materials.unitRate')} value={unitRate} onChange={(e) => setUnitRate(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input placeholder={t('materials.supplierName')} value={supplierName} onChange={(e) => setSupplierName(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input type="number" step="0.01" placeholder={t('materials.transportCost')} value={transportCost} onChange={(e) => setTransportCost(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <select value={medium} onChange={(e) => setMedium(e.target.value as 'CASH' | 'BANK')} className="rounded-lg border border-brand-200 px-3 py-2">
            <option value="CASH">CASH</option>
            <option value="BANK">BANK</option>
          </select>
          <button type="submit" className="col-span-2 rounded-lg bg-brand-600 px-3 py-2 text-white hover:bg-brand-700 sm:col-span-1">
            {t('common.submit')}
          </button>
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
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
