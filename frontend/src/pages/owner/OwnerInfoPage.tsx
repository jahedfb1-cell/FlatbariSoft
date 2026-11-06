import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../../context/LanguageContext';
import OwnerNav from '../../components/OwnerNav';
import * as myApi from '../../api/my';
import type { Contact, PortalDecision } from '../../api/my';

/** The building's own phone numbers (tap to call) and what the committee has decided. */
export default function OwnerInfoPage() {
  const { t, lang } = useLanguage();
  const L = (bn: string, en: string) => (lang === 'bn' ? bn : en);
  const [contacts, setContacts] = useState<Contact[] | null>(null);
  const [decisions, setDecisions] = useState<PortalDecision[] | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    myApi.myContacts().then(setContacts).catch(() => { setFailed(true); setContacts([]); });
    myApi.myDecisions().then(setDecisions).catch(() => { setFailed(true); setDecisions([]); });
  }, []);

  const status = (s: PortalDecision['status']) => (s === 'DONE' ? L('সম্পন্ন', 'Done') : s === 'ONGOING' ? L('চলছে', 'In progress') : L('অপেক্ষমাণ', 'Pending'));

  return (
    <div className="px-4 pb-24 pt-4">
      <div className="mx-auto max-w-md space-y-5">
        <div>
          <Link to="/owner" className="text-sm text-navy-700 hover:underline">
            ← {t('owner.home')}
          </Link>
          <h1 className="mt-1 text-xl font-semibold text-navy-900">{L('ভবনের তথ্য', 'Building info')}</h1>
        </div>

        {failed && <p className="rounded-xl bg-white p-3 text-sm text-status-due shadow-sm">{t('somethingWrong')}</p>}

        <section>
          <h2 className="mb-2 font-semibold text-navy-900">📞 {L('জরুরি নম্বর', 'Phone numbers')}</h2>
          <div className="space-y-2">
            {contacts?.map((c) => (
              <a key={c.id} href={`tel:${c.phone.replace(/[^0-9+]/g, '')}`} className="flex min-h-14 items-center justify-between gap-3 rounded-2xl border border-brand-200 bg-white px-4 py-2 shadow-sm hover:bg-brand-50">
                <span>
                  <span className="block font-medium text-navy-900">{c.name}</span>
                  {c.role && <span className="block text-xs text-navy-700">{c.role}</span>}
                </span>
                <span className="whitespace-nowrap rounded-full bg-brand-600 px-3 py-1.5 text-sm font-medium text-white">📞 {c.phone}</span>
              </a>
            ))}
            {contacts && contacts.length === 0 && !failed && <p className="rounded-xl bg-white p-4 text-sm text-navy-700 shadow-sm">{L('অফিস এখনও কোনো নম্বর যোগ করেনি।', 'The office has not added any numbers yet.')}</p>}
            {!contacts && <div className="h-16 animate-pulse rounded-2xl bg-white/70" />}
          </div>
        </section>

        <section>
          <h2 className="mb-2 font-semibold text-navy-900">🗳 {L('কমিটির সিদ্ধান্ত', "Committee's decisions")}</h2>
          <div className="space-y-2">
            {decisions?.map((d) => (
              <div key={d.id} className="rounded-2xl border border-brand-200 bg-white p-4 shadow-sm">
                <div className="flex items-start justify-between gap-2">
                  <p className="text-xs text-navy-700">
                    {d.meeting_title} · {d.meeting_date}
                    {d.resolution_no ? ` · ${d.resolution_no}` : ''}
                  </p>
                  <span className="shrink-0 rounded-full bg-brand-100 px-2 py-0.5 text-xs text-navy-700">{status(d.status)}</span>
                </div>
                <p className="mt-1 text-sm font-medium text-navy-900">{d.description}</p>
                {d.decision && <p className="mt-1 text-sm text-navy-700">→ {d.decision}</p>}
              </div>
            ))}
            {decisions && decisions.length === 0 && !failed && <p className="rounded-xl bg-white p-4 text-sm text-navy-700 shadow-sm">{L('এখনও কোনো সিদ্ধান্ত প্রকাশিত হয়নি।', 'No decisions have been published yet.')}</p>}
            {!decisions && <div className="h-16 animate-pulse rounded-2xl bg-white/70" />}
          </div>
        </section>
      </div>
      <OwnerNav />
    </div>
  );
}
