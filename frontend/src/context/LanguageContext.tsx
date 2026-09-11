import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { translations, type Lang, type TranslationKey } from '../i18n/translations';

interface LanguageContextValue {
  lang: Lang;
  toggle: () => void;
  t: (key: TranslationKey) => string;
}

const LanguageContext = createContext<LanguageContextValue | null>(null);

function getInitialLang(): Lang {
  try {
    const stored = localStorage.getItem('lang');
    if (stored === 'bn' || stored === 'en') return stored;
  } catch {
    // localStorage unavailable (private mode etc.) — fall through to default.
  }
  return 'bn';
}

export function LanguageProvider({ children }: { children: ReactNode }) {
  const [lang, setLang] = useState<Lang>(getInitialLang);

  useEffect(() => {
    document.documentElement.lang = lang;
    try {
      localStorage.setItem('lang', lang);
    } catch {
      // Not fatal — the toggle just won't be remembered on next visit.
    }
  }, [lang]);

  function toggle() {
    setLang((prev) => (prev === 'bn' ? 'en' : 'bn'));
  }

  function t(key: TranslationKey): string {
    return translations[lang][key] ?? translations.bn[key] ?? key;
  }

  return <LanguageContext.Provider value={{ lang, toggle, t }}>{children}</LanguageContext.Provider>;
}

export function useLanguage() {
  const ctx = useContext(LanguageContext);
  if (!ctx) throw new Error('useLanguage must be used within a LanguageProvider');
  return ctx;
}
