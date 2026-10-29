import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import * as staffApi from '../api/staff';
import type { Staff } from '../api/staff';
import StaffFormModal from '../components/StaffFormModal';
import LanguageToggle from '../components/LanguageToggle';
import { useLanguage } from '../context/LanguageContext';

export default function StaffPage() {
  const { t } = useLanguage();
  const [staff, setStaff] = useState<Staff[]>([]);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState<Staff | null | undefined>(undefined);

  function load() {
    setLoading(true);
    staffApi.listStaff().then((data) => {
      setStaff(data);
      setLoading(false);
    });
  }

  useEffect(load, []);

  async function handleDelete(id: number) {
    if (!confirm(t('staff.confirmDelete'))) return;
    await staffApi.deleteStaff(id);
    load();
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-3xl">
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

        <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
          <table className="w-full text-left text-sm">
            <thead className="bg-brand-100 text-navy-900">
              <tr>
                <th className="px-4 py-3">{t('staff.name')}</th>
                <th className="px-4 py-3">{t('staff.designation')}</th>
                <th className="px-4 py-3">{t('staff.mobile')}</th>
                <th className="px-4 py-3">{t('staff.salary')}</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody>
              {loading && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-navy-700">
                    {t('loading')}
                  </td>
                </tr>
              )}
              {!loading && staff.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-navy-700">
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
                  <td className="px-4 py-3 text-right">
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
