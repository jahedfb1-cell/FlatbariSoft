import { useEffect, useState } from 'react';
import { useAuth } from '../../context/AuthContext';
import { useLanguage } from '../../context/LanguageContext';
import LanguageToggle from '../../components/LanguageToggle';
import OwnerNav from '../../components/OwnerNav';
import * as myApi from '../../api/my';

export default function OwnerDocumentsPage() {
  const { user } = useAuth();
  const { t } = useLanguage();
  const [documents, setDocuments] = useState<any[]>([]);

  useEffect(() => {
    myApi.myDocuments().then(setDocuments);
  }, []);

  return (
    <div className="min-h-screen bg-brand-50 px-4 pb-20 pt-8">
      <div className="mx-auto max-w-md">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <h1 className="text-xl font-semibold text-navy-900">{t('owner.myInfo')}</h1>
          <LanguageToggle />
        </div>

        <div className="mb-4 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
          <p className="text-sm text-navy-700">{t('home.mobile')}: {user?.mobile}</p>
        </div>

        <h2 className="mb-2 font-semibold text-navy-900">{t('owner.myDocuments')}</h2>
        <div className="space-y-2">
          {documents.map((d) => (
            <a
              key={d.id}
              href={d.file_url}
              target="_blank"
              rel="noreferrer"
              className="block rounded-xl border border-brand-200 bg-white p-4 text-brand-700 shadow-sm hover:underline"
            >
              {d.document_type} {d.side ? `(${d.side})` : ''}
            </a>
          ))}
          {documents.length === 0 && <p className="text-sm text-navy-700">—</p>}
        </div>
      </div>
      <OwnerNav />
    </div>
  );
}
