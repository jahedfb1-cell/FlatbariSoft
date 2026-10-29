import type { ReactNode } from 'react';
import { useLanguage } from '../context/LanguageContext';

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

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-navy-900/40 px-3 py-6 sm:items-center">
      <div className="w-full max-w-lg rounded-xl border border-brand-200 bg-white p-4 shadow-lg sm:p-6">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-navy-900">{title}</h2>
          <button
            onClick={onClose}
            className="rounded-lg px-2 py-1 text-navy-700 transition hover:bg-brand-100"
            aria-label={t('modal.close')}
          >
            ✕
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}
