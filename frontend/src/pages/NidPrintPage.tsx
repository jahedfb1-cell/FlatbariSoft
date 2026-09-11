import { useLocation, useNavigate } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';

interface NidPrintState {
  front: string;
  back: string;
}

/**
 * Print-only page: NID front + back side by side, sized to sit within the
 * top half of one A4 sheet (owner asked for this explicitly — printing an
 * ID card copy at near-actual size, two-up, saves paper instead of one
 * image per page). object-fit: contain keeps each image undistorted
 * regardless of its original resolution/aspect ratio.
 */
export default function NidPrintPage() {
  const { t } = useLanguage();
  const navigate = useNavigate();
  const location = useLocation();
  const state = location.state as NidPrintState | null;

  if (!state?.front || !state?.back) {
    return (
      <div className="flex min-h-screen items-center justify-center text-navy-700">
        <button onClick={() => navigate(-1)} className="text-brand-700 hover:underline">
          ← {t('flatDetail.back')}
        </button>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-brand-50 py-8">
      <style>{`
        @media print {
          @page { size: A4; margin: 10mm; }
          .no-print { display: none !important; }
          body { background: #fff !important; }
        }
      `}</style>

      <div className="no-print mx-auto mb-6 flex max-w-3xl items-center justify-between px-4">
        <button onClick={() => navigate(-1)} className="text-sm text-navy-700 hover:underline">
          ← {t('flatDetail.back')}
        </button>
        <button
          onClick={() => window.print()}
          className="rounded-lg bg-brand-600 px-4 py-2 font-medium text-white transition hover:bg-brand-700"
        >
          🖨️ {t('documents.printNid')}
        </button>
      </div>

      {/* This box is deliberately sized to the top half of an A4 page
          (A4 = 210mm x 297mm) so the two ID images print two-up. */}
      <div
        className="mx-auto flex items-center justify-center gap-[10mm] border border-dashed border-brand-200 bg-white p-[5mm] print:border-none"
        style={{ width: '190mm', height: '140mm' }}
      >
        <div className="flex h-full w-1/2 items-center justify-center">
          <img src={state.front} alt="NID front" className="max-h-full max-w-full object-contain" />
        </div>
        <div className="flex h-full w-1/2 items-center justify-center">
          <img src={state.back} alt="NID back" className="max-h-full max-w-full object-contain" />
        </div>
      </div>
    </div>
  );
}
