import { useLanguage } from '../context/LanguageContext';

export default function LanguageToggle() {
  const { lang, toggle, t } = useLanguage();

  return (
    <button
      onClick={toggle}
      className="rounded-lg border border-brand-200 px-3 py-1.5 text-sm font-medium text-navy-700 transition hover:bg-brand-100"
      aria-label="Toggle language"
    >
      {lang === 'bn' ? 'বাং' : 'EN'} / {t('lang.toggleTo')}
    </button>
  );
}
