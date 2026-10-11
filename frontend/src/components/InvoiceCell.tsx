import { useRef, useState } from 'react';
import { useLanguage } from '../context/LanguageContext';
import Modal from './Modal';
import * as invoicesApi from '../api/invoices';
import type { InvoiceType } from '../api/invoices';
import { blobErrorMessage } from '../utils/download';
import { compressImage, isImageFile } from '../utils/compressImage';

/**
 * The "Invoice" column of a purchase row: view the stored photo, print it as a
 * black-and-white A4 page, or attach/replace it afterwards (e.g. the bill
 * arrived after the purchase was entered).
 */
export default function InvoiceCell({
  type,
  id,
  hasInvoice,
  invoiceKb,
  onChanged,
}: {
  type: InvoiceType;
  id: number;
  hasInvoice: boolean;
  invoiceKb: number | null;
  onChanged: () => void;
}) {
  const { t } = useLanguage();
  const inputRef = useRef<HTMLInputElement>(null);
  const [viewUrl, setViewUrl] = useState<string | null>(null);
  const [busy, setBusy] = useState<'upload' | 'print' | 'view' | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function handleView() {
    setError(null);
    setBusy('view');
    try {
      setViewUrl(await invoicesApi.fetchInvoiceImageUrl(type, id));
    } catch {
      setError(t('invoice.loadFailed'));
    } finally {
      setBusy(null);
    }
  }

  function closeView() {
    if (viewUrl) URL.revokeObjectURL(viewUrl);
    setViewUrl(null);
  }

  async function handlePrint() {
    setError(null);
    setBusy('print');
    try {
      await invoicesApi.printInvoice(type, id);
    } catch (err) {
      setError(await blobErrorMessage(err, t('somethingWrong')));
    } finally {
      setBusy(null);
    }
  }

  async function handleFile(picked: File | null) {
    if (inputRef.current) inputRef.current.value = '';
    if (!picked) return;
    setError(null);
    if (!isImageFile(picked)) {
      setError(t('invoice.notImage'));
      return;
    }
    setBusy('upload');
    try {
      const { file } = await compressImage(picked);
      await invoicesApi.uploadInvoice(type, id, file);
      onChanged();
    } catch (err: any) {
      const errors = err?.response?.data?.errors;
      setError(errors ? (Object.values(errors).flat() as string[]).join(' ') : t('invoice.failed'));
    } finally {
      setBusy(null);
    }
  }

  const pick = (
    <>
      <input ref={inputRef} type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={(e) => handleFile(e.target.files?.[0] ?? null)} />
      <button type="button" onClick={() => inputRef.current?.click()} disabled={busy !== null} className="text-brand-700 hover:underline disabled:opacity-50">
        {busy === 'upload' ? t('invoice.uploading') : hasInvoice ? t('invoice.replace') : t('invoice.attach')}
      </button>
    </>
  );

  return (
    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
      {hasInvoice ? (
        <>
          <button type="button" onClick={handleView} disabled={busy !== null} className="text-brand-700 hover:underline disabled:opacity-50">
            🧾 {t('invoice.view')}
            {invoiceKb !== null && <span className="ml-1 text-xs text-navy-700">({Math.round(invoiceKb)} KB)</span>}
          </button>
          <button type="button" onClick={handlePrint} disabled={busy !== null} className="text-brand-700 hover:underline disabled:opacity-50">
            🖨 {busy === 'print' ? t('audit.preparing') : t('invoice.printBw')}
          </button>
          {pick}
        </>
      ) : (
        pick
      )}
      {error && <span className="w-full text-xs text-status-due">{error}</span>}

      {viewUrl && (
        <Modal title={t('invoice.col')} onClose={closeView}>
          <img src={viewUrl} alt={t('invoice.col')} className="max-h-[65vh] w-full rounded-lg border border-brand-200 object-contain" />
          <a href={viewUrl} target="_blank" rel="noreferrer" className="mt-3 inline-block text-sm text-brand-700 hover:underline">
            {t('invoice.openFull')} ↗
          </a>
        </Modal>
      )}
    </div>
  );
}
