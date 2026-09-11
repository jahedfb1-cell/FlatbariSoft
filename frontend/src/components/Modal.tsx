import type { ReactNode } from 'react';

export default function Modal({
  title,
  onClose,
  children,
}: {
  title: string;
  onClose: () => void;
  children: ReactNode;
}) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-navy-900/40 px-4">
      <div className="w-full max-w-lg rounded-xl border border-brand-200 bg-white p-6 shadow-lg">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-navy-900">{title}</h2>
          <button
            onClick={onClose}
            className="rounded-lg px-2 py-1 text-navy-700 transition hover:bg-brand-100"
            aria-label="বন্ধ করুন"
          >
            ✕
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}
