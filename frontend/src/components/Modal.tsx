import { useEffect, type ReactNode } from 'react';
import { useLanguage } from '../context/LanguageContext';

/**
 * A pop-up. On a phone it slides up from the bottom and uses the height it needs (never more than the screen), with its
 * header fixed and ONE scroll area; on a computer it is a centred card. The page behind does not scroll while it is open.
 */
export default function Modal({
  title,
  onClose,
  children,
}: {
  title: string;
  onClose: () => void;
  children: ReactNode;
}) {
  const { t } = useLanguage();

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    document.addEventListener('keydown', onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = prev;
    };
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-navy-900/50 backdrop-blur-[2px] sm:items-center sm:p-4" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div className="flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-3xl border border-brand-200 bg-white shadow-2xl sm:max-w-lg sm:rounded-2xl" role="dialog" aria-modal="true">
        <div className="flex shrink-0 items-center justify-between gap-3 border-b border-brand-100 px-5 py-3">
          <h2 className="min-w-0 truncate text-base font-semibold text-navy-900">{title}</h2>
          <button
            onClick={onClose}
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-navy-700 transition hover:bg-brand-100"
            aria-label={t('modal.close')}
          >
            ✕
          </button>
        </div>
        <div className="min-h-0 flex-1 overflow-y-auto px-5 py-4">{children}</div>
      </div>
    </div>
  );
}
