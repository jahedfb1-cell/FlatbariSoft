import { useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as noticesApi from '../api/notices';
import type { Notice } from '../api/notices';
import { useAuth } from '../context/AuthContext';

export default function NoticesPage() {
  const { t } = useLanguage();
  const { user } = useAuth();
  const [notices, setNotices] = useState<Notice[]>([]);
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const canManage = user?.permissions.includes('notice.manage') || user?.roles.includes('super_admin');

  function load() {
    noticesApi.listNotices().then(setNotices);
  }

  useEffect(load, []);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    await noticesApi.createNotice({ title, body });
    setTitle('');
    setBody('');
    load();
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-2xl">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('notices.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        {canManage && (
          <form onSubmit={handleSubmit} className="mb-6 space-y-3 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
            <input required placeholder="Title" value={title} onChange={(e) => setTitle(e.target.value)} className="w-full rounded-lg border border-brand-200 px-3 py-2" />
            <textarea required placeholder={t('notices.body')} value={body} onChange={(e) => setBody(e.target.value)} className="w-full rounded-lg border border-brand-200 px-3 py-2" rows={3} />
            <button type="submit" className="rounded-lg bg-brand-600 px-4 py-2 text-white hover:bg-brand-700">
              {t('notices.newNotice')}
            </button>
          </form>
        )}

        <div className="space-y-3">
          {notices.map((n) => (
            <div key={n.id} className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
              <p className="font-medium text-navy-900">{n.title}</p>
              <p className="mt-1 text-sm text-navy-700">{n.body}</p>
              <p className="mt-2 text-xs text-navy-700">{new Date(n.published_at).toLocaleDateString()}</p>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
