import { useLanguage } from '../context/LanguageContext';
import { useInShell } from '../context/ShellContext';
import NotificationBell from './NotificationBell';

/** Just the language button; `compact` keeps it to two letters for a phone's top bar. */
export function LanguageButton({ compact = false }: { compact?: boolean }) {
  const { lang, toggle, t } = useLanguage();

  return (
    <button
      onClick={toggle}
      className="h-9 whitespace-nowrap rounded-lg border border-brand-200 bg-white px-3 text-sm font-medium text-navy-700 transition hover:bg-brand-100"
      aria-label="Toggle language"
      title={t('lang.toggleTo')}
    >
      {compact ? (lang === 'bn' ? 'EN' : 'বাং') : `${lang === 'bn' ? 'বাং' : 'EN'} / ${t('lang.toggleTo')}`}
    </button>
  );
}

/**
 * The language switch with the notification bell beside it, for screens that have their own header. Inside the office
 * frame the top bar already shows both, so this renders nothing there.
 */
export default function LanguageToggle() {
  const inShell = useInShell();
  if (inShell) return null;

  return (
    <span className="inline-flex items-center gap-2">
      <NotificationBell />
      <LanguageButton />
    </span>
  );
}
