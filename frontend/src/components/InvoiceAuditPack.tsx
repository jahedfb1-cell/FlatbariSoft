import { useState } from 'react';
import { useLanguage } from '../context/LanguageContext';
import * as invoicesApi from '../api/invoices';
import { blobErrorMessage } from '../utils/download';

const today = () => new Date().toISOString().slice(0, 10);
const monthStart = () => `${new Date().toISOString().slice(0, 7)}-01`;

/** Print every purchase invoice in a date range as one black-and-white A4 PDF - for an audit. */
export default function InvoiceAuditPack() {
  const { t } = useLanguage();
  const [from, setFrom] = useState(monthStart());
  const [to, setTo] = useState(today());
  const [type, setType] = useState<'all' | 'gas' | 'materials'>('all');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handlePrint() {
    setError(null);
    setBusy(true);
    try {
      await invoicesApi.printAuditPack(from, to, type);
    } catch (err) {
      setError(await blobErrorMessage(err, t('somethingWrong')));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mt-6 rounded-xl border border-brand-200 bg-white p-4 shadow-sm print:hidden">
      <h2 className="font-semibold text-navy-900">🧾 {t('audit.title')}</h2>
      <p className="mt-1 text-xs text-navy-700">{t('audit.hint')}</p>
      <div className="mt-3 flex flex-wrap items-end gap-3">
        <label className="text-xs text-navy-700">
          {t('audit.from')}
          <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="mt-1 block rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-900" />
        </label>
        <label className="text-xs text-navy-700">
          {t('audit.to')}
          <input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="mt-1 block rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-900" />
        </label>
        <select value={type} onChange={(e) => setType(e.target.value as 'all' | 'gas' | 'materials')} className="rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-900">
          <option value="all">{t('audit.all')}</option>
          <option value="gas">{t('audit.gas')}</option>
          <option value="materials">{t('audit.materials')}</option>
        </select>
        <button
          type="button"
          onClick={handlePrint}
          disabled={busy || !from || !to}
          className="rounded-lg bg-brand-600 px-4 py-2 text-sm text-white hover:bg-brand-700 disabled:opacity-50"
        >
          🖨 {busy ? t('audit.preparing') : t('audit.print')}
        </button>
      </div>
      {error && <p className="mt-2 text-sm text-status-due">{error}</p>}
    </div>
  );
}
