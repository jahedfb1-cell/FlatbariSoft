import { useEffect, useState } from 'react';
import { useLanguage } from '../../context/LanguageContext';
import LanguageToggle from '../../components/LanguageToggle';
import OwnerNav from '../../components/OwnerNav';
import * as noticesApi from '../../api/notices';
import type { Notice } from '../../api/notices';

export default function OwnerNoticesPage() {
  const { t } = useLanguage();
  const [notices, setNotices] = useState<Notice[]>([]);

  useEffect(() => {
    noticesApi.listNotices().then(setNotices);
  }, []);

  return (
    <div className="min-h-screen bg-brand-50 px-4 pb-20 pt-8">
      <div className="mx-auto max-w-md">
        <div className="mb-4 flex items-center justify-between">
          <h1 className="text-xl font-semibold text-navy-900">{t('owner.notices')}</h1>
          <LanguageToggle />
        </div>
        <div className="space-y-3">
          {notices.map((n) => (
            <div key={n.id} className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
              <p className="font-medium text-navy-900">{n.title}</p>
              <p className="mt-1 text-sm text-navy-700">{n.body}</p>
            </div>
          ))}
          {notices.length === 0 && <p className="text-sm text-navy-700">—</p>}
        </div>
      </div>
      <OwnerNav />
    </div>
  );
}
