import { useEffect, useState, type FormEvent } from 'react';
import client from '../api/client';
import { apiError } from '../api/hall';
import { useLanguage } from '../context/LanguageContext';

export interface MasterSupplier {
  id: number;
  name: string;
  kind: 'GAS' | 'MATERIALS' | 'OTHER';
  phone: string | null;
  address: string | null;
  is_active: boolean;
}

export const listMasterSuppliers = (kind?: string, active = true) =>
  client.get<MasterSupplier[]>('/supplier-master', { params: { ...(kind ? { kind } : {}), ...(active ? { active: 1 } : {}) } }).then((r) => r.data);
export const createMasterSupplier = (d: { name: string; kind: string; phone?: string; address?: string }) => client.post<MasterSupplier>('/supplier-master', d).then((r) => r.data);

const input = 'w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500';

/** Create a supplier: name, phone, address. Used by the picker and the suppliers list. */
export function NewSupplierForm({ kind, onCreated, onCancel }: { kind: 'GAS' | 'MATERIALS' | 'OTHER'; onCreated: (s: MasterSupplier) => void; onCancel?: () => void }) {
  const { lang } = useLanguage();
  const bn = lang === 'bn';
  const L = (b: string, e: string) => (bn ? b : e);
  const [f, setF] = useState({ name: '', phone: '', address: '' });
  const [type, setType] = useState(kind);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(e: FormEvent) {
    e.preventDefault();
    e.stopPropagation();
    setBusy(true);
    setError(null);
    try {
      onCreated(await createMasterSupplier({ name: f.name.trim(), kind: type, phone: f.phone.trim() || undefined, address: f.address.trim() || undefined }));
    } catch (err) {
      setError(apiError(err, L('সরবরাহকারী তৈরি করা যায়নি।', 'Could not create the supplier.')));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-2 rounded-xl border border-brand-200 bg-brand-50 p-3">
      <p className="text-sm font-medium text-navy-900">{L('নতুন সরবরাহকারী', 'New supplier')}</p>
      <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
        <input className={input} placeholder={L('নাম *', 'Name *')} value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} />
        <input className={input} inputMode="tel" placeholder={L('ফোন', 'Phone')} value={f.phone} onChange={(e) => setF({ ...f, phone: e.target.value })} />
        <input className={`${input} sm:col-span-2`} placeholder={L('ঠিকানা', 'Address')} value={f.address} onChange={(e) => setF({ ...f, address: e.target.value })} />
        {kind === 'OTHER' && (
          <select className={input} value={type} onChange={(e) => setType(e.target.value as typeof type)}>
            <option value="GAS">{L('গ্যাস সরবরাহকারী', 'Gas supplier')}</option>
            <option value="MATERIALS">{L('নির্মাণ সামগ্রী সরবরাহকারী', 'Material supplier')}</option>
          </select>
        )}
      </div>
      {error && <p className="text-xs text-status-due">{error}</p>}
      <div className="flex gap-2">
        <button type="button" disabled={busy || !f.name.trim()} onClick={(e) => submit(e as unknown as FormEvent)} className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
          {busy ? '…' : L('তৈরি করুন', 'Create')}
        </button>
        {onCancel && (
          <button type="button" onClick={onCancel} className="rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-navy-700 hover:bg-brand-100">
            {L('বাতিল', 'Cancel')}
          </button>
        )}
      </div>
    </div>
  );
}

/** Pick a supplier from the list, or create one on the spot. */
export default function SupplierPicker({ kind, value, onChange, required = true }: { kind: 'GAS' | 'MATERIALS'; value: string; onChange: (id: string, name: string) => void; required?: boolean }) {
  const { lang } = useLanguage();
  const bn = lang === 'bn';
  const L = (b: string, e: string) => (bn ? b : e);
  const [list, setList] = useState<MasterSupplier[]>([]);
  const [adding, setAdding] = useState(false);

  useEffect(() => {
    listMasterSuppliers(kind).then(setList).catch(() => undefined);
  }, [kind]);

  return (
    <div className="space-y-2">
      <div className="flex gap-2">
        <select
          required={required}
          className={input}
          value={value}
          onChange={(e) => {
            const s = list.find((x) => String(x.id) === e.target.value);
            onChange(e.target.value, s?.name ?? '');
          }}
        >
          <option value="">{L('— সরবরাহকারী বাছুন —', '— choose a supplier —')}</option>
          {list.map((s) => (
            <option key={s.id} value={s.id}>{s.name}{s.phone ? ` · ${s.phone}` : ''}</option>
          ))}
        </select>
        <button type="button" onClick={() => setAdding(!adding)} className="shrink-0 rounded-lg border border-brand-200 px-3 py-2 text-sm text-brand-700 hover:bg-brand-100">
          + {L('নতুন', 'New')}
        </button>
      </div>
      {adding && (
        <NewSupplierForm
          kind={kind}
          onCancel={() => setAdding(false)}
          onCreated={(s) => {
            setList([...list, s].sort((a, b) => a.name.localeCompare(b.name)));
            onChange(String(s.id), s.name);
            setAdding(false);
          }}
        />
      )}
    </div>
  );
}
