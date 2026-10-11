import { useEffect, useRef, useState } from 'react';
import { useLanguage } from '../context/LanguageContext';
import { compressImage, formatSize, isImageFile } from '../utils/compressImage';

/**
 * File picker for an invoice photo. Shrinks the photo in the browser the moment
 * it is chosen (see utils/compressImage) and shows what that saved, e.g.
 * "5.2 MB -> 288 KB (94% smaller)". Hands the already-compressed File to the form.
 */
export default function InvoicePicker({ file, onChange }: { file: File | null; onChange: (file: File | null) => void }) {
  const { t } = useLanguage();
  const inputRef = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [stats, setStats] = useState<{ before: number; after: number } | null>(null);
  const [preview, setPreview] = useState<string | null>(null);

  // The parent clears the form after a successful save - clear our own state with it.
  useEffect(() => {
    if (!file) {
      setStats(null);
      setPreview(null);
      if (inputRef.current) inputRef.current.value = '';
    }
  }, [file]);

  useEffect(() => {
    if (!file) return;
    const url = URL.createObjectURL(file);
    setPreview(url);
    return () => URL.revokeObjectURL(url);
  }, [file]);

  async function handlePick(picked: File | null) {
    setError(null);
    if (!picked) return;
    if (!isImageFile(picked)) {
      setError(t('invoice.notImage'));
      return;
    }
    setBusy(true);
    try {
      const result = await compressImage(picked);
      setStats({ before: result.originalBytes, after: result.compressedBytes });
      onChange(result.file);
    } catch {
      setError(t('invoice.notImage'));
    } finally {
      setBusy(false);
    }
  }

  const saved = stats && stats.before > 0 ? Math.max(0, Math.round((1 - stats.after / stats.before) * 100)) : 0;

  return (
    <div className="flex flex-wrap items-center gap-3">
      <input
        ref={inputRef}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        className="hidden"
        onChange={(e) => handlePick(e.target.files?.[0] ?? null)}
      />
      <button
        type="button"
        onClick={() => inputRef.current?.click()}
        disabled={busy}
        className="rounded-lg border border-dashed border-brand-200 bg-brand-50 px-3 py-2 text-sm text-navy-900 hover:bg-brand-100 disabled:opacity-50"
      >
        🧾 {busy ? t('invoice.compressing') : t('invoice.pick')}
      </button>

      {file && preview && (
        <>
          <img src={preview} alt="" className="h-12 w-12 rounded-lg border border-brand-200 object-cover" />
          {stats && (
            <span className="text-xs text-navy-700">
              {formatSize(stats.before)} → <b className="text-navy-900">{formatSize(stats.after)}</b>
              {saved > 0 && ` (${saved}% ${t('invoice.smaller')})`}
            </span>
          )}
          <button
            type="button"
            onClick={() => {
              onChange(null);
            }}
            className="text-xs text-status-due hover:underline"
          >
            {t('invoice.remove')}
          </button>
        </>
      )}
      {error && <span className="text-xs text-status-due">{error}</span>}
    </div>
  );
}
