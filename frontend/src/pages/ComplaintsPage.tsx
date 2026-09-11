import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as governanceApi from '../api/governance';
import type { Complaint } from '../api/governance';

const statusOptions = ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'];

export default function ComplaintsPage() {
  const { t } = useLanguage();
  const [complaints, setComplaints] = useState<Complaint[]>([]);

  function load() {
    governanceApi.listComplaints().then(setComplaints);
  }

  useEffect(load, []);

  async function handleStatusChange(id: number, status: string) {
    await governanceApi.updateComplaintStatus(id, status);
    load();
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-3xl">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('complaints.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        <div className="space-y-2">
          {complaints.map((c) => (
            <div key={c.id} className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
              <div className="flex items-start justify-between">
                <div>
                  <p className="font-medium text-navy-900">{c.category}</p>
                  <p className="text-sm text-navy-700">{c.description}</p>
                </div>
                <select
                  value={c.status}
                  onChange={(e) => handleStatusChange(c.id, e.target.value)}
                  className="rounded-lg border border-brand-200 px-2 py-1 text-sm"
                >
                  {statusOptions.map((s) => (
                    <option key={s} value={s}>
                      {s}
                    </option>
                  ))}
                </select>
              </div>
            </div>
          ))}
          {complaints.length === 0 && <p className="text-sm text-navy-700">—</p>}
        </div>
      </div>
    </div>
  );
}
