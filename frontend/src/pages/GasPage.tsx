import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as gasApi from '../api/gas';
import * as billingApi from '../api/billing';
import type { GasConsumer } from '../api/gas';

export default function GasPage() {
  const { t } = useLanguage();
  const now = new Date();
  const [year] = useState(now.getFullYear());
  const [month] = useState(now.getMonth() + 1);
  const [consumers, setConsumers] = useState<GasConsumer[]>([]);
  const [readings, setReadings] = useState<Record<number, string>>({});
  const [message, setMessage] = useState<string | null>(null);

  function load() {
    gasApi.listGasConsumers().then(setConsumers);
  }

  useEffect(load, []);

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
      </div>
    </div>
  );
}
