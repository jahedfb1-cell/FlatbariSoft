import { useEffect, useRef, useState } from 'react';
import { useLanguage } from '../context/LanguageContext';

type Kind = 'network' | 'server';

/**
 * When the phone has lost the connection, or the server itself fails (5xx), say so once, in plain words, on any screen -
 * instead of leaving an empty list that looks like "no data". The API client raises the event; wrong input (4xx) is
 * explained by the form that sent it and never shown here.
 */
export default function ApiProblemToast() {
  const { lang } = useLanguage();
  const [kind, setKind] = useState<Kind | null>(null);
  const timer = useRef<number | undefined>(undefined);

  useEffect(() => {
    const show = (e: Event) => {
      setKind((e as CustomEvent<Kind>).detail);
      window.clearTimeout(timer.current);
      timer.current = window.setTimeout(() => setKind(null), 7000);
    };
    window.addEventListener('api:problem', show);
    return () => {
      window.removeEventListener('api:problem', show);
      window.clearTimeout(timer.current);
    };
  }, []);

  if (!kind) return null;
  const bn = lang === 'bn';
  const text =
    kind === 'network'
      ? bn ? 'ইন্টারনেট সংযোগে সমস্যা। সংযোগ দেখে আবার চেষ্টা করুন।' : 'There is a problem with the internet connection. Check it and try again.'
      : bn ? 'সার্ভারে সমস্যা হয়েছে। একটু পরে আবার চেষ্টা করুন।' : 'The server had a problem. Please try again in a moment.';

  return (
    <div className="pointer-events-none fixed inset-x-0 top-3 z-[60] flex justify-center px-3" role="alert" aria-live="assertive">
      <div className="pointer-events-auto flex max-w-md items-start gap-3 rounded-2xl border border-status-due/30 bg-white px-4 py-3 text-sm text-navy-900 shadow-xl">
        <span aria-hidden className="text-lg leading-none">
          {kind === 'network' ? '📡' : '⚠️'}
        </span>
        <p className="min-w-0 flex-1">{text}</p>
        <button onClick={() => setKind(null)} aria-label="close" className="-mr-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-navy-700 hover:bg-brand-100">
          ✕
        </button>
      </div>
    </div>
  );
}
