import { useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as assetsApi from '../api/assets';
import type { AssetRow } from '../api/assets';

export default function AssetsPage() {
  const { t } = useLanguage();
  const [assets, setAssets] = useState<AssetRow[]>([]);
  const [name, setName] = useState('');
  const [category, setCategory] = useState('LIFT');
  const [nextService, setNextService] = useState('');

  function load() {
    assetsApi.listAssets().then(setAssets);
  }

  useEffect(load, []);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    await assetsApi.createAsset({ name, category, next_service_date: nextService || undefined } as any);
    setName('');
    setNextService('');
    load();
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-3xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('assets.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        <form onSubmit={handleSubmit} className="mb-6 grid grid-cols-2 gap-3 rounded-xl border border-brand-200 bg-white p-4 shadow-sm sm:grid-cols-4">
          <input required placeholder="Name" value={name} onChange={(e) => setName(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <select value={category} onChange={(e) => setCategory(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2">
            {['LIFT', 'SOLAR', 'SUBSTATION', 'PUMP', 'GENERATOR', 'OTHER'].map((c) => (
              <option key={c} value={c}>
                {c}
              </option>
            ))}
          </select>
          <input type="date" value={nextService} onChange={(e) => setNextService(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <button type="submit" className="rounded-lg bg-brand-600 px-3 py-2 text-white hover:bg-brand-700">
            {t('common.add')}
          </button>
        </form>

        <div className="space-y-2">
          {assets.map((a) => (
            <div key={a.id} className="flex items-center justify-between rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
              <div>
                <p className="font-medium text-navy-900">
                  {a.name} <span className="text-xs text-navy-700">({a.category})</span>
                </p>
                <p className="text-sm text-navy-700">
                  {t('assets.nextService')}: {a.next_service_date ?? '—'}
                </p>
              </div>
              {a.service_overdue && (
                <span className="rounded-full bg-status-due/15 px-2 py-1 text-xs font-medium text-status-due">
                  {t('assets.overdue')}
                </span>
              )}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
