import { useLanguage } from '../context/LanguageContext';
import NotificationBell from './NotificationBell';

/** The language switch, with the notification bell beside it - so the bell is on every screen that has a header. */
export default function LanguageToggle() {
  const { lang, toggle, t } = useLanguage();

  return (
    <span className="inline-flex items-center gap-2">
      <NotificationBell />
      <button
        onClick={toggle}
        className="whitespace-nowrap rounded-lg border border-brand-200 px-3 py-1.5 text-sm font-medium text-navy-700 transition hover:bg-brand-100"
        aria-label="Toggle language"
      >
        {lang === 'bn' ? 'বাং' : 'EN'} / {t('lang.toggleTo')}
      </button>
    </span>
  );
}
