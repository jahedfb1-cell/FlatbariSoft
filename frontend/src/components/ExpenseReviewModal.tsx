import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { useLanguage } from '../context/LanguageContext';
import { blobErrorMessage } from '../utils/download';
import { fetchInvoiceImageUrl } from '../api/invoices';
import * as api from '../api/expenses';
import type { ExpenseDetail, ExpenseFile } from '../api/expenses';

const ACCEPT = 'application/pdf,image/jpeg,image/png,image/webp';
const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { maximumFractionDigits: 2 })}`;
const kb = (n: number) => (n >= 1048576 ? `${(n / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`);

/** A thumbnail for a photo; a button for anything else. Both open the full file in a new tab. */
function FileCard({ file, onOpen, onRemove }: { file: ExpenseFile; onOpen: () => void; onRemove?: () => void }) {
  const [thumb, setThumb] = useState<string | null>(null);
  const isImage = file.mime.startsWith('image/');

  useEffect(() => {
    if (!isImage) return;
    let url: string | null = null;
    let alive = true;
    api.fetchExpenseFile(file.id).then((b) => {
      url = URL.createObjectURL(b);
      if (alive) setThumb(url);
    }).catch(() => undefined);
    return () => {
      alive = false;
      if (url) URL.revokeObjectURL(url);
    };
  }, [file.id, isImage]);

  return (
    <div className="w-32 overflow-hidden rounded-xl border border-brand-100 bg-white shadow-sm">
      <button type="button" onClick={onOpen} className="block h-24 w-full bg-brand-50">
        {isImage ? thumb ? <img src={thumb} alt={file.name} className="h-full w-full object-cover" /> : <span className="text-xs text-navy-700">…</span> : <span className="text-3xl">📄</span>}
      </button>
      <div className="p-2">
        <p className="truncate text-xs text-navy-900" title={file.name}>
          {file.name}
        </p>
        <p className="text-[11px] text-navy-700">{kb(file.size)}</p>
        {onRemove && (
          <button type="button" onClick={onRemove} className="mt-1 text-[11px] text-status-due hover:underline">
            ✕
          </button>
        )}
      </div>
    </div>
  );
}

/**
 * What the approver sees before approving: the expense itself, the papers behind it (the voucher, the shop's
 * bill - photos or PDFs - and the bill photo of the purchase it came from), and what earlier approvers wrote.
 * Approving needs a written note. A finished expense opens the same way, read-only.
 */
export default function ExpenseReviewModal({ id, canApprove, canUpload, onClose, onChanged }: { id: number; canApprove: boolean; canUpload: boolean; onClose: () => void; onChanged: () => void }) {
  const { t, lang } = useLanguage();
  const [d, setD] = useState<ExpenseDetail | null>(null);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    api.getExpense(id).then(setD).catch(() => setError(t('somethingWrong')));
  }, [id, t]);
  useEffect(load, [load]);

  async function open(file: ExpenseFile) {
    const tab = window.open('', '_blank');
    try {
      const blob = await api.fetchExpenseFile(file.id);
      const url = URL.createObjectURL(blob);
      if (tab) tab.location.href = url;
      else window.location.href = url;
    } catch (e) {
      tab?.close();
      setError(await blobErrorMessage(e, t('somethingWrong')));
    }
  }

  async function openSource() {
    if (!d?.source) return;
    const tab = window.open('', '_blank');
    try {
      const url = await fetchInvoiceImageUrl(d.source.type, d.source.purchase_id);
      if (tab) tab.location.href = url;
    } catch (e) {
      tab?.close();
      setError(await blobErrorMessage(e, t('somethingWrong')));
    }
  }

  async function upload(files: FileList | null) {
    if (!files || files.length === 0) return;
    setError(null);
    try {
      setD(await api.addExpenseFiles(id, Array.from(files)));
      onChanged();
    } catch (e: any) {
      const errs = e?.response?.data?.errors;
      setError(errs ? (Object.values(errs) as string[][]).flat().join(' ') : (e?.response?.data?.message ?? t('somethingWrong')));
    }
  }

  async function remove(file: ExpenseFile) {
    try {
      await api.removeExpenseFile(file.id);
      load();
      onChanged();
    } catch (e: any) {
      setError(e?.response?.data?.message ?? t('somethingWrong'));
    }
  }

  async function approve(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await api.approveExpense(id, note.trim());
      onChanged();
      load();
      setNote('');
    } catch (err: any) {
      const errs = err?.response?.data?.errors;
      setError(errs ? (Object.values(errs) as string[][]).flat().join(' ') : (err?.response?.data?.message ?? t('somethingWrong')));
    } finally {
      setBusy(false);
    }
  }

  const pending = d?.status === 'PENDING';

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy-900/40 px-3 py-6">
      <div className="w-full max-w-2xl rounded-2xl border border-brand-200 bg-white p-4 shadow-lg sm:p-6">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div>
            <h2 className="text-lg font-semibold text-navy-900">{pending ? t('review.titlePending') : t('review.title')}</h2>
            {d && (
              <p className="text-sm text-navy-700">
                {d.voucher_no ?? `#${d.id}`} · {d.date}
              </p>
            )}
          </div>
          <button onClick={onClose} aria-label="close" className="rounded-lg px-2 py-1 text-xl text-navy-700 hover:bg-brand-100">
            ✕
          </button>
        </div>

        {!d && !error && <p className="text-navy-700">{t('loading')}</p>}
        {error && <p className="mb-3 rounded-lg bg-red-50 p-3 text-sm text-status-due">{error}</p>}

        {d && (
          <div className="space-y-4">
            <div className="rounded-xl bg-brand-50 p-4">
              <p className="text-3xl font-semibold text-navy-900">{money(d.amount)}</p>
              <p className="mt-1 text-sm text-navy-700">
                {t('expenses.payee')}: <b className="text-navy-900">{d.payee}</b>
              </p>
              <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                <dt className="text-navy-700">{t('expenses.head')}</dt>
                <dd className="text-navy-900">{lang === 'bn' ? d.head_bn : (d.head_en ?? d.head_bn)}</dd>
                <dt className="text-navy-700">{t('expenses.fundType')}</dt>
                <dd className="text-navy-900">{d.fund_type}</dd>
                <dt className="text-navy-700">{t('expenses.medium')}</dt>
                <dd className="text-navy-900">{d.treasury_medium}</dd>
                <dt className="text-navy-700">{t('review.enteredBy')}</dt>
                <dd className="text-navy-900">
                  {d.created_by ?? '—'}
                  {d.created_at ? ` · ${d.created_at}` : ''}
                </dd>
                {d.resolution_id && (
                  <>
                    <dt className="text-navy-700">{t('review.resolution')}</dt>
                    <dd className="text-navy-900">#{d.resolution_id}</dd>
                  </>
                )}
              </dl>
              {d.description && <p className="mt-3 border-t border-brand-100 pt-2 text-sm text-navy-900">{d.description}</p>}
            </div>

            <div>
              <h3 className="mb-2 text-sm font-semibold text-navy-900">{t('review.papers')}</h3>
              <div className="flex flex-wrap gap-3">
                {d.source?.has_invoice && (
                  <button type="button" onClick={openSource} className="w-32 rounded-xl border border-brand-200 bg-brand-50 p-3 text-center hover:bg-brand-100">
                    <span className="block text-3xl">🧾</span>
                    <span className="mt-1 block text-xs text-navy-900">{t('review.purchaseBill')}</span>
                    {d.source.item && <span className="block truncate text-[11px] text-navy-700">{d.source.item}</span>}
                  </button>
                )}
                {d.attachments.map((f) => (
                  <FileCard key={f.id} file={f} onOpen={() => open(f)} onRemove={pending && canUpload ? () => remove(f) : undefined} />
                ))}
              </div>
              {d.attachments.length === 0 && !d.source?.has_invoice && <p className="rounded-lg bg-amber-50 p-3 text-sm text-status-partial">{t('review.noPapers')}</p>}
              {pending && canUpload && (
                <label className="mt-3 inline-block cursor-pointer rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-brand-700 hover:bg-brand-100">
                  📎 {t('review.addPapers')}
                  <input type="file" multiple accept={ACCEPT} className="hidden" onChange={(e) => upload(e.target.files)} />
                </label>
              )}
            </div>

            <div>
              <h3 className="mb-2 text-sm font-semibold text-navy-900">
                {t('review.approvals')} ({d.approvals.length}/{d.approvals_needed})
              </h3>
              {d.approvals.length === 0 && <p className="text-sm text-navy-700">{t('review.noApprovals')}</p>}
              <div className="space-y-2">
                {d.approvals.map((a, i) => (
                  <div key={i} className="rounded-lg border border-green-100 bg-green-50 p-3 text-sm">
                    <p className="font-medium text-navy-900">
                      ✔ {a.by ?? '—'} <span className="text-xs font-normal text-navy-700">{a.at}</span>
                    </p>
                    {a.note && <p className="mt-1 text-navy-900">“{a.note}”</p>}
                  </div>
                ))}
              </div>
            </div>

            {pending && canApprove && (
              <form onSubmit={approve} className="space-y-2 rounded-xl border border-brand-200 p-4">
                <label className="block text-sm font-medium text-navy-900">{t('review.noteLabel')} *</label>
                <textarea required minLength={3} maxLength={1000} rows={3} className="w-full rounded-lg border border-brand-200 px-3 py-2 text-base" placeholder={t('review.notePlaceholder')} value={note} onChange={(e) => setNote(e.target.value)} />
                <button disabled={busy || note.trim().length < 3} className="w-full rounded-xl bg-status-paid px-4 py-3 font-medium text-white hover:opacity-90 disabled:opacity-50">
                  ✔ {t('review.approveBtn')}
                </button>
              </form>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
