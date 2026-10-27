import { useLanguage } from '../context/LanguageContext';
import type { HallAvailability } from '../api/hall';

const WEEKDAYS = {
  bn: ['রবি', 'সোম', 'মঙ্গল', 'বুধ', 'বৃহঃ', 'শুক্র', 'শনি'],
  en: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
};

const pad = (n: number) => String(n).padStart(2, '0');

/** 'YYYY-MM-DD' for a day of the month shown, built by hand so a timezone can never shift the date. */
export const isoDate = (year: number, monthIndex: number, day: number) => `${year}-${pad(monthIndex + 1)}-${pad(day)}`;
/** Today's date in the user's own timezone ('YYYY-MM-DD'); toISOString() would give the UTC date, a day behind in Bangladesh each evening. */
export const localToday = () => {
  const d = new Date();
  return isoDate(d.getFullYear(), d.getMonth(), d.getDate());
};
export const monthKey = (year: number, monthIndex: number) => `${year}-${pad(monthIndex + 1)}`;

/**
 * Month grid: each day is free / partly booked (one of DAY or NIGHT taken) / fully booked.
 * It only ever sees which slots are taken, never who booked them.
 */
export default function HallCalendar({
  year,
  monthIndex,
  availability,
  selected,
  onSelect,
  onMonthChange,
}: {
  year: number;
  monthIndex: number;
  availability: HallAvailability;
  selected: string | null;
  onSelect: (date: string) => void;
  onMonthChange: (year: number, monthIndex: number) => void;
}) {
  const { t, lang } = useLanguage();
  const today = new Date();
  const todayIso = isoDate(today.getFullYear(), today.getMonth(), today.getDate());
  const firstWeekday = new Date(year, monthIndex, 1).getDay();
  const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();
  const title = new Date(year, monthIndex, 1).toLocaleDateString(lang === 'bn' ? 'bn-BD' : 'en-GB', { month: 'long', year: 'numeric' });

  function step(delta: number) {
    const d = new Date(year, monthIndex + delta, 1);
    onMonthChange(d.getFullYear(), d.getMonth());
  }

  function dayState(iso: string): 'free' | 'partial' | 'full' {
    const slots = (availability[iso] ?? []).map((b) => b.slot);
    if (slots.length === 0) return 'free';
    return slots.includes('FULL_DAY') || (slots.includes('DAY') && slots.includes('NIGHT')) ? 'full' : 'partial';
  }

  const cells: (number | null)[] = [...Array(firstWeekday).fill(null), ...Array.from({ length: daysInMonth }, (_, i) => i + 1)];

  return (
    <div className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
      <div className="mb-3 flex items-center justify-between">
        <button type="button" onClick={() => step(-1)} className="rounded-lg px-3 py-1 text-navy-700 hover:bg-brand-100" aria-label="previous month">
          ‹
        </button>
        <p className="font-semibold text-navy-900">{title}</p>
        <button type="button" onClick={() => step(1)} className="rounded-lg px-3 py-1 text-navy-700 hover:bg-brand-100" aria-label="next month">
          ›
        </button>
      </div>

      <div className="grid grid-cols-7 gap-1 text-center text-xs text-navy-700">
        {WEEKDAYS[lang].map((w) => (
          <div key={w} className="py-1">
            {w}
          </div>
        ))}
        {cells.map((day, i) => {
          if (day === null) return <div key={`b${i}`} />;
          const iso = isoDate(year, monthIndex, day);
          const state = dayState(iso);
          const past = iso < todayIso;
          const isSelected = selected === iso;
          const color =
            state === 'full'
              ? 'bg-status-due/15 text-status-due'
              : state === 'partial'
                ? 'bg-status-partial/15 text-status-partial'
                : 'bg-status-paid/10 text-navy-900';
          return (
            <button
              key={iso}
              type="button"
              disabled={past}
              onClick={() => onSelect(iso)}
              className={`aspect-square rounded-lg text-sm transition ${color} ${past ? 'opacity-30' : 'hover:ring-2 hover:ring-brand-300'} ${
                isSelected ? 'ring-2 ring-brand-600' : ''
              } ${iso === todayIso ? 'font-bold underline' : ''}`}
            >
              {day}
            </button>
          );
        })}
      </div>

      <div className="mt-3 flex flex-wrap gap-3 text-xs text-navy-700">
        <span className="flex items-center gap-1">
          <i className="inline-block h-3 w-3 rounded bg-status-paid/40" /> {t('hall.free')}
        </span>
        <span className="flex items-center gap-1">
          <i className="inline-block h-3 w-3 rounded bg-status-partial/50" /> {t('hall.partial')}
        </span>
        <span className="flex items-center gap-1">
          <i className="inline-block h-3 w-3 rounded bg-status-due/50" /> {t('hall.full')}
        </span>
      </div>
    </div>
  );
}
