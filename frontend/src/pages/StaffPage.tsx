import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import * as staffApi from '../api/staff';
import type { Staff } from '../api/staff';
import StaffFormModal from '../components/StaffFormModal';
import StaffKycModal from '../components/StaffKycModal';
import SalaryPanel from '../components/SalaryPanel';
import LanguageToggle from '../components/LanguageToggle';
import { useLanguage } from '../context/LanguageContext';

export default function StaffPage() {
  const { t, lang } = useLanguage();
  const [kycFor, setKycFor] = useState<Staff | null>(null);
  const [tab, setTab] = useState<'list' | 'salary'>('list');
  const [staff, setStaff] = useState<Staff[]>([]);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState<Staff | null | undefined>(undefined);

  function load() {
    setLoading(true);
    staffApi.listStaff().then((data) => {
      setStaff(data);
    }).catch(() => undefined).finally(() => setLoading(false));
  }

  useEffect(load, []);

  async function handleDelete(id: number) {
    if (!confirm(t('staff.confirmDelete'))) return;
    await staffApi.deleteStaff(id);
    load();
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-5xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('flats.home')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('staff.title')}</h1>
          </div>
          <div className="flex items-center gap-2">
            <LanguageToggle />
            <button
              onClick={() => setEditing(null)}
              className="rounded-lg bg-brand-600 px-4 py-2 font-medium text-white transition hover:bg-brand-700"
            >
              {t('staff.addNew')}
            </button>
          </div>
        </div>

        <div className="mb-4 flex gap-2">
          <button className={`rounded-lg px-4 py-2 text-sm font-medium ${tab === 'list' ? 'bg-brand-600 text-white' : 'border border-brand-200 text-navy-700 hover:bg-brand-100'}`} onClick={() => setTab('list')}>
            {lang === 'bn' ? 'স্টাফ তালিকা' : 'Staff list'}
          </button>
          <button className={`rounded-lg px-4 py-2 text-sm font-medium ${tab === 'salary' ? 'bg-brand-600 text-white' : 'border border-brand-200 text-navy-700 hover:bg-brand-100'}`} onClick={() => setTab('salary')}>
            💰 {lang === 'bn' ? 'বেতন ও অগ্রিম' : 'Salary & advances'}
          </button>
        </div>

        {tab === 'salary' && <SalaryPanel staff={staff} />}

        <div className={`overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm ${tab === 'salary' ? 'hidden' : ''}`}>
          <table className="w-full text-left text-sm">
            <thead className="bg-brand-100 text-navy-900">
              <tr>
                <th className="px-4 py-3">{t('staff.name')}</th>
                <th className="px-4 py-3">{t('staff.designation')}</th>
                <th className="px-4 py-3">{t('staff.mobile')}</th>
                <th className="px-4 py-3">{t('staff.salary')}</th>
                <th className="px-4 py-3">KYC</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody>
              {loading && (
                <tr>
                  <td colSpan={6} className="px-4 py-6 text-center text-navy-700">
                    {t('loading')}
                  </td>
                </tr>
              )}
              {!loading && staff.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-6 text-center text-navy-700">
                    {t('staff.empty')}
                  </td>
                </tr>
              )}
              {staff.map((s) => (
                <tr key={s.id} className="border-t border-brand-100">
                  <td className="px-4 py-3 text-navy-900">{s.name}</td>
                  <td className="px-4 py-3 text-navy-900">{s.designation}</td>
                  <td className="px-4 py-3 text-navy-900">{s.mobile}</td>
                  <td className="px-4 py-3 text-navy-900">৳{Number(s.monthly_salary).toLocaleString('en-BD')}</td>
                  <td className="px-4 py-3">
                    <button onClick={() => setKycFor(s)} className="text-left hover:underline">
                      <span className={`rounded-full px-2 py-0.5 text-xs ${s.kyc_updated_at ? 'bg-status-paid/15 text-status-paid' : 'bg-status-due/15 text-status-due'}`}>
                        {s.kyc_updated_at ? (lang === 'bn' ? 'KYC আছে' : 'KYC filled') : lang === 'bn' ? 'KYC নেই' : 'No KYC'}
                      </span>
                      {!!s.documents_count && <span className="ml-1 text-xs text-navy-700">📎 {s.documents_count}</span>}
                    </button>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button onClick={() => setKycFor(s)} className="mr-3 text-brand-700 hover:underline">
                      {lang === 'bn' ? 'KYC ও ফাইল' : 'KYC & files'}
                    </button>
                    <button onClick={() => setEditing(s)} className="mr-3 text-brand-700 hover:underline">
                      {t('flatDetail.edit')}
                    </button>
                    <button onClick={() => handleDelete(s.id)} className="text-status-due hover:underline">
                      {t('documents.delete')}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {kycFor && <StaffKycModal staff={kycFor} onClose={() => { setKycFor(null); load(); }} />}

      {editing !== undefined && (
        <StaffFormModal
          staff={editing ?? undefined}
          onClose={() => setEditing(undefined)}
          onSaved={() => {
            setEditing(undefined);
            load();
          }}
        />
      )}
    </div>
  );
}
