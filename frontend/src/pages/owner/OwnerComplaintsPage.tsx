import { useEffect, useState, type FormEvent } from 'react';
import { useLanguage } from '../../context/LanguageContext';
import LanguageToggle from '../../components/LanguageToggle';
import OwnerNav from '../../components/OwnerNav';
import * as myApi from '../../api/my';
import type { Flat } from '../../api/flats';

export default function OwnerComplaintsPage() {
  const { t } = useLanguage();
  const [complaints, setComplaints] = useState<any[]>([]);
  const [flats, setFlats] = useState<Flat[]>([]);
  const [flatId, setFlatId] = useState('');
  const [category, setCategory] = useState('');
  const [description, setDescription] = useState('');
  const [showForm, setShowForm] = useState(false);

  function load() {
    myApi.myComplaints().then(setComplaints);
    myApi.myFlats().then((f) => {
      setFlats(f);
      if (f[0]) setFlatId(String(f[0].id));
    });
  }

  useEffect(load, []);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    await myApi.submitMyComplaint({ flat_id: Number(flatId), category, description });
    setCategory('');
    setDescription('');
    setShowForm(false);
    load();
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 pb-20 pt-8">
      <div className="mx-auto max-w-md">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <h1 className="text-xl font-semibold text-navy-900">{t('owner.complaints')}</h1>
          <LanguageToggle />
        </div>

        <button
          onClick={() => setShowForm((s) => !s)}
          className="mb-4 w-full rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700"
        >
          {t('owner.newComplaint')}
        </button>

        {showForm && (
          <form onSubmit={handleSubmit} className="mb-4 space-y-3 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
            <select value={flatId} onChange={(e) => setFlatId(e.target.value)} className="w-full rounded-lg border border-brand-200 px-3 py-2">
              {flats.map((f) => (
                <option key={f.id} value={f.id}>
                  {f.flat_no}
                </option>
              ))}
            </select>
            <input required placeholder={t('common.category')} value={category} onChange={(e) => setCategory(e.target.value)} className="w-full rounded-lg border border-brand-200 px-3 py-2" />
            <textarea required placeholder={t('common.description')} value={description} onChange={(e) => setDescription(e.target.value)} className="w-full rounded-lg border border-brand-200 px-3 py-2" rows={3} />
            <button type="submit" className="w-full rounded-lg bg-brand-600 px-4 py-2 text-white hover:bg-brand-700">
              {t('common.submit')}
            </button>
          </form>
        )}

        <div className="space-y-2">
          {complaints.map((c) => (
            <div key={c.id} className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
              <p className="font-medium text-navy-900">{c.category}</p>
              <p className="text-sm text-navy-700">{c.description}</p>
              <p className="mt-1 text-xs text-navy-700">{c.status}</p>
              {c.resolution_note && <p className="mt-2 rounded-lg bg-green-50 p-2 text-sm text-navy-900">✔ {c.resolution_note}</p>}
            </div>
          ))}
        </div>
      </div>
      <OwnerNav />
    </div>
  );
}
