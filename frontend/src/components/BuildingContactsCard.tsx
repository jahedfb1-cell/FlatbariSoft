import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { useLanguage } from '../context/LanguageContext';
import { apiError } from '../api/hall';
import * as api from '../api/contacts';
import type { BuildingContact } from '../api/contacts';

/** The building's phone numbers (manager, guard, lift...) - what every flat owner and tenant sees under "Building info". */
export default function BuildingContactsCard() {
  const { lang } = useLanguage();
  const L = (bn: string, en: string) => (lang === 'bn' ? bn : en);
  const [rows, setRows] = useState<BuildingContact[] | null>(null);
  const [editing, setEditing] = useState<number | null>(null); // null = adding a new one
  const [name, setName] = useState('');
  const [role, setRole] = useState('');
  const [phone, setPhone] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    api.listBuildingContacts().then(setRows).catch((e) => setError(apiError(e, L('কিছু একটা সমস্যা হয়েছে।', 'Something went wrong.'))));
  }, []);
  useEffect(load, [load]);

  function reset() {
    setEditing(null);
    setName('');
    setRole('');
    setPhone('');
  }

  async function save(e: FormEvent) {
    e.preventDefault();
    if (busy) return;
    setBusy(true);
    setError(null);
    try {
      const body = { name: name.trim(), role: role.trim() || undefined, phone: phone.trim() };
      if (editing) await api.updateBuildingContact(editing, { ...body, is_active: rows?.find((r) => r.id === editing)?.is_active ?? true });
      else await api.createBuildingContact(body);
      reset();
      load();
    } catch (err) {
      setError(apiError(err, L('কিছু একটা সমস্যা হয়েছে।', 'Something went wrong.')));
    } finally {
      setBusy(false);
    }
  }

  async function run(action: () => Promise<unknown>) {
    setError(null);
    try {
      await action();
      load();
    } catch (err) {
      setError(apiError(err, L('কিছু একটা সমস্যা হয়েছে।', 'Something went wrong.')));
    }
  }

  const input = 'w-full rounded-lg border border-brand-200 px-3 py-2 text-sm';

  return (
    <div className="mt-6 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
      <h2 className="mb-1 font-semibold text-navy-900">📞 {L('ভবনের জরুরি নম্বর', 'Building phone numbers')}</h2>
      <p className="mb-3 text-xs text-navy-700">{L('এগুলো প্রতিটি ফ্ল্যাট মালিক ও ভাড়াটিয়া "ভবনের তথ্য" পাতায় দেখেন।', 'Every flat owner and tenant sees these under "Building info".')}</p>

      <form onSubmit={save} className="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-4">
        <input required className={input} placeholder={L('নাম *', 'Name *')} value={name} onChange={(e) => setName(e.target.value)} />
        <input className={input} placeholder={L('পদ/কাজ (গার্ড, লিফট...)', 'Role (guard, lift...)')} value={role} onChange={(e) => setRole(e.target.value)} />
        <input required inputMode="tel" className={input} placeholder={L('ফোন নম্বর *', 'Phone *')} value={phone} onChange={(e) => setPhone(e.target.value)} />
        <div className="flex gap-2">
          <button disabled={busy} className="flex-1 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
            {editing ? L('সংরক্ষণ', 'Save') : L('যোগ করুন', 'Add')}
          </button>
          {editing && (
            <button type="button" onClick={reset} className="rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700">
              ✕
            </button>
          )}
        </div>
      </form>
      {error && <p className="mb-2 text-sm text-status-due">{error}</p>}

      <div className="divide-y divide-brand-100">
        {rows?.map((c) => (
          <div key={c.id} className={`flex flex-wrap items-center justify-between gap-2 py-2 text-sm ${c.is_active ? '' : 'opacity-60'}`}>
            <span>
              <b className="text-navy-900">{c.name}</b>
              {c.role && <span className="text-navy-700"> · {c.role}</span>}
              <span className="block text-xs text-navy-700">
                {c.phone}
                {!c.is_active && ` · ${L('লুকানো', 'hidden')}`}
              </span>
            </span>
            <span className="flex gap-3 text-xs">
              <button
                className="text-brand-700 hover:underline"
                onClick={() => {
                  setEditing(c.id);
                  setName(c.name);
                  setRole(c.role ?? '');
                  setPhone(c.phone);
                }}
              >
                ✎ {L('বদলান', 'Edit')}
              </button>
              <button className="text-brand-700 hover:underline" onClick={() => run(() => api.updateBuildingContact(c.id, { name: c.name, role: c.role ?? undefined, phone: c.phone, is_active: !c.is_active }))}>
                {c.is_active ? L('লুকান', 'Hide') : L('দেখান', 'Show')}
              </button>
              <button className="text-status-due hover:underline" onClick={() => window.confirm(L('এই নম্বর মুছবেন?', 'Delete this number?')) && run(() => api.deleteBuildingContact(c.id))}>
                {L('মুছুন', 'Delete')}
              </button>
            </span>
          </div>
        ))}
        {rows && rows.length === 0 && <p className="py-2 text-sm text-navy-700">{L('এখনও কোনো নম্বর নেই।', 'No numbers yet.')}</p>}
      </div>
    </div>
  );
}
