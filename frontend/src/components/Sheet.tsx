import { useEffect, type ReactNode } from 'react';

/** A bottom sheet on a phone, a centred card on a computer: header and action bar stay put, only the middle scrolls. */
export default function Sheet({ title, sub, onClose, children, footer, wide }: { title: string; sub?: string; onClose: () => void; children: ReactNode; footer?: ReactNode; wide?: boolean }) {
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
      <div className={`flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl ${wide ? 'sm:max-w-2xl' : 'sm:max-w-md'} sm:rounded-3xl`} role="dialog" aria-modal="true">
        <div className="flex shrink-0 items-center justify-between gap-3 border-b border-brand-100 px-5 py-3">
          <div className="min-w-0">
            <h2 className="truncate text-base font-semibold text-navy-900">{title}</h2>
            {sub && <p className="truncate text-xs text-navy-700">{sub}</p>}
          </div>
          <button onClick={onClose} aria-label="close" className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-navy-700 hover:bg-brand-100">
            ✕
          </button>
        </div>
        <div className="min-h-0 flex-1 overflow-y-auto px-5 py-4">{children}</div>
        {footer && <div className="shrink-0 border-t border-brand-100 bg-white px-5 py-3">{footer}</div>}
      </div>
    </div>
  );
}

