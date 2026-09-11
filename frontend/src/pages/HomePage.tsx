import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import client from '../api/client';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import type { TranslationKey } from '../i18n/translations';

interface HealthResponse {
  status: string;
  app: string;
  time: string;
}

/**
 * Placeholder post-login screen. Replaced by the real dashboard/sidebar
 * shell in Phase 4.3 — for now it just proves the logged-in session works
 * (shows who you are, still reaches the API).
 */
export default function HomePage() {
  const { user, logout } = useAuth();
  const { t } = useLanguage();
  const [health, setHealth] = useState<HealthResponse | null>(null);

  useEffect(() => {
    client.get<HealthResponse>('/health').then((res) => setHealth(res.data));
  }, []);

  return (
    <div className="flex min-h-screen items-center justify-center bg-brand-50 px-4">
      <div className="w-full max-w-sm rounded-xl border border-brand-200 bg-white p-8 shadow-sm">
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-xl font-semibold text-navy-900">{t('appName')}</h1>
            <p className="mt-1 text-sm text-navy-700">{t('home.loggedIn')}</p>
          </div>
          <div className="flex flex-col items-end gap-2">
            <LanguageToggle />
            <button
              onClick={() => logout()}
              className="rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-navy-700 transition hover:bg-brand-100"
            >
              {t('home.logout')}
            </button>
          </div>
        </div>

        <div className="mt-6 rounded-lg bg-brand-100 px-4 py-3 text-sm text-navy-900">
          <p>
            <span className="font-medium">{t('home.mobile')}:</span> {user?.mobile}
          </p>
          <p>
            <span className="font-medium">{t('home.role')}:</span>{' '}
            {user?.roles.map((role) => t(`role.${role}` as TranslationKey)).join(', ')}
          </p>
          {health && (
            <p>
              <span className="font-medium">{t('home.serverTime')}:</span> {health.time}
            </p>
          )}
        </div>

        <Link
          to="/flats"
          className="mt-4 block rounded-lg border border-brand-200 px-4 py-2 text-center text-sm font-medium text-brand-700 transition hover:bg-brand-100"
        >
          {t('home.flatManagement')} →
        </Link>
        <Link
          to="/staff"
          className="mt-2 block rounded-lg border border-brand-200 px-4 py-2 text-center text-sm font-medium text-brand-700 transition hover:bg-brand-100"
        >
          {t('staff.title')} →
        </Link>
      </div>
    </div>
  );
}
