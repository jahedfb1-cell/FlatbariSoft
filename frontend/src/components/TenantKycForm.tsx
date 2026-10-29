import { useState, type FormEvent } from 'react';
import { useLanguage } from '../context/LanguageContext';
import { RELATIONS, type FamilyMember, type TenantKyc, type TenantKycInput } from '../api/kyc';

const input = 'w-full rounded-lg border border-brand-200 px-3 py-2 text-base outline-none focus:border-brand-500';
const label = 'mb-1 block text-sm font-medium text-navy-900';

const s = (v: string | null | undefined) => v ?? '';
const n = (v: string) => (v.trim() === '' ? null : v.trim());

interface FamilyRow {
  name: string;
  relation: FamilyMember['relation'];
  age: string;
  nid: string;
  mobile: string;
  occupation: string;
}

/**
 * The tenant KYC form. The tenant fills it on their own phone; the office can fill it in for them from the
 * flat's page. NID, address, profession and an emergency contact are required; the family list is free
 * (add one row per person who will live in the flat). The server checks everything again.
 */
export default function TenantKycForm({ kyc, onSave, busy }: { kyc: TenantKyc; onSave: (d: TenantKycInput) => void; busy?: boolean }) {
  const { t } = useLanguage();
  const [f, setF] = useState({
    name_bn: kyc.person.name_bn,
    name_en: s(kyc.person.name_en),
    nid: s(kyc.person.nid),
    alt_mobile: s(kyc.person.alt_mobile),
    email: s(kyc.person.email),
    address: s(kyc.person.address),
    occupation: s(kyc.occupation),
    workplace: s(kyc.workplace),
    emergency_name: s(kyc.emergency_name),
    emergency_phone: s(kyc.emergency_phone),
    previous_address: s(kyc.previous_address),
    reference_name: s(kyc.reference_name),
    reference_phone: s(kyc.reference_phone),
    other_info: s(kyc.other_info),
  });
  const [family, setFamily] = useState<FamilyRow[]>(
    kyc.family_members.map((m) => ({ name: m.name, relation: m.relation, age: m.age === null ? '' : String(m.age), nid: s(m.nid), mobile: s(m.mobile), occupation: s(m.occupation) })),
  );

  const set = (k: keyof typeof f, v: string) => setF({ ...f, [k]: v });
  const row = (i: number, k: keyof FamilyRow, v: string) => setFamily(family.map((r, idx) => (idx === i ? { ...r, [k]: v } : r)));

  function submit(e: FormEvent) {
    e.preventDefault();
    onSave({
      person: { name_bn: f.name_bn.trim(), name_en: n(f.name_en), mobile: kyc.person.mobile, alt_mobile: n(f.alt_mobile), nid: f.nid.trim(), email: n(f.email), address: f.address.trim() },
      occupation: f.occupation.trim(),
      workplace: n(f.workplace),
      emergency_name: f.emergency_name.trim(),
      emergency_phone: f.emergency_phone.trim(),
      previous_address: n(f.previous_address),
      reference_name: n(f.reference_name),
      reference_phone: n(f.reference_phone),
      other_info: n(f.other_info),
      family_members: family.map((r) => ({
        name: r.name.trim(),
        relation: r.relation,
        age: r.age === '' ? null : Number(r.age),
        nid: n(r.nid),
        mobile: n(r.mobile),
        occupation: n(r.occupation),
      })),
    });
  }

  const section = 'rounded-2xl border border-brand-100 bg-white p-4 shadow-sm';

  return (
    <form onSubmit={submit} className="space-y-4">
      <div className={section}>
        <h2 className="mb-3 font-semibold text-navy-900">{t('kyc.personal')}</h2>
        <div className="grid gap-3 sm:grid-cols-2">
          <div>
            <label className={label}>{t('kyc.nameBn')} *</label>
            <input required className={input} value={f.name_bn} onChange={(e) => set('name_bn', e.target.value)} />
          </div>
          <div>
            <label className={label}>{t('kyc.nameEn')}</label>
            <input className={input} value={f.name_en} onChange={(e) => set('name_en', e.target.value)} />
          </div>
          <div>
            <label className={label}>{t('kyc.nid')} *</label>
            <input required inputMode="numeric" className={input} placeholder="10 / 13 / 17" value={f.nid} onChange={(e) => set('nid', e.target.value.replace(/\D/g, ''))} />
          </div>
          <div>
            <label className={label}>{t('kyc.mobile')}</label>
            <input disabled className={`${input} bg-brand-50`} value={kyc.person.mobile} />
          </div>
          <div>
            <label className={label}>{t('kyc.altMobile')}</label>
            <input inputMode="tel" className={input} placeholder="01XXXXXXXXX" value={f.alt_mobile} onChange={(e) => set('alt_mobile', e.target.value)} />
          </div>
          <div>
            <label className={label}>{t('kyc.email')}</label>
            <input type="email" className={input} value={f.email} onChange={(e) => set('email', e.target.value)} />
          </div>
          <div className="sm:col-span-2">
            <label className={label}>{t('kyc.address')} *</label>
            <textarea required rows={2} className={input} value={f.address} onChange={(e) => set('address', e.target.value)} />
          </div>
          <div className="sm:col-span-2">
            <label className={label}>{t('kyc.prevAddress')}</label>
            <textarea rows={2} className={input} value={f.previous_address} onChange={(e) => set('previous_address', e.target.value)} />
          </div>
        </div>
      </div>

      <div className={section}>
        <h2 className="mb-3 font-semibold text-navy-900">{t('kyc.work')}</h2>
        <div className="grid gap-3 sm:grid-cols-2">
          <div>
            <label className={label}>{t('kyc.occupation')} *</label>
            <input required className={input} value={f.occupation} onChange={(e) => set('occupation', e.target.value)} />
          </div>
          <div>
            <label className={label}>{t('kyc.workplace')}</label>
            <input className={input} value={f.workplace} onChange={(e) => set('workplace', e.target.value)} />
          </div>
        </div>
      </div>

      <div className={section}>
        <h2 className="mb-3 font-semibold text-navy-900">{t('kyc.contacts')}</h2>
        <div className="grid gap-3 sm:grid-cols-2">
          <div>
            <label className={label}>{t('kyc.emergencyName')} *</label>
            <input required className={input} value={f.emergency_name} onChange={(e) => set('emergency_name', e.target.value)} />
          </div>
          <div>
            <label className={label}>{t('kyc.emergencyPhone')} *</label>
            <input required inputMode="tel" className={input} placeholder="01XXXXXXXXX" value={f.emergency_phone} onChange={(e) => set('emergency_phone', e.target.value)} />
          </div>
          <div>
            <label className={label}>{t('kyc.refName')}</label>
            <input className={input} value={f.reference_name} onChange={(e) => set('reference_name', e.target.value)} />
          </div>
          <div>
            <label className={label}>{t('kyc.refPhone')}</label>
            <input inputMode="tel" className={input} placeholder="01XXXXXXXXX" value={f.reference_phone} onChange={(e) => set('reference_phone', e.target.value)} />
          </div>
        </div>
      </div>

      <div className={section}>
        <div className="mb-3 flex items-center justify-between gap-2">
          <h2 className="font-semibold text-navy-900">
            {t('kyc.family')} ({family.length})
          </h2>
          {family.length < 15 && (
            <button type="button" onClick={() => setFamily([...family, { name: '', relation: 'SPOUSE', age: '', nid: '', mobile: '', occupation: '' }])} className="rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-brand-700 hover:bg-brand-100">
              + {t('kyc.addMember')}
            </button>
          )}
        </div>
        <p className="mb-3 text-xs text-navy-700">{t('kyc.familyHint')}</p>
        <div className="space-y-3">
          {family.map((r, i) => (
            <div key={i} className="rounded-xl border border-brand-100 bg-brand-50 p-3">
              <div className="grid gap-2 sm:grid-cols-3">
                <input required className={input} placeholder={`${t('kyc.memberName')} *`} value={r.name} onChange={(e) => row(i, 'name', e.target.value)} />
                <select className={input} value={r.relation} onChange={(e) => row(i, 'relation', e.target.value)}>
                  {RELATIONS.map((rel) => (
                    <option key={rel} value={rel}>
                      {t(`kyc.rel.${rel}` as any)}
                    </option>
                  ))}
                </select>
                <input type="number" min={0} max={120} className={input} placeholder={t('kyc.age')} value={r.age} onChange={(e) => row(i, 'age', e.target.value)} />
                <input inputMode="numeric" className={input} placeholder={t('kyc.nidOptional')} value={r.nid} onChange={(e) => row(i, 'nid', e.target.value.replace(/\D/g, ''))} />
                <input inputMode="tel" className={input} placeholder={t('kyc.mobile')} value={r.mobile} onChange={(e) => row(i, 'mobile', e.target.value)} />
                <input className={input} placeholder={t('kyc.occupation')} value={r.occupation} onChange={(e) => row(i, 'occupation', e.target.value)} />
              </div>
              <button type="button" onClick={() => setFamily(family.filter((_, idx) => idx !== i))} className="mt-2 text-xs text-status-due hover:underline">
                ✕ {t('kyc.removeMember')}
              </button>
            </div>
          ))}
          {family.length === 0 && <p className="text-sm text-navy-700">{t('kyc.noFamily')}</p>}
        </div>
      </div>

      <div className={section}>
        <label className={label}>{t('kyc.other')}</label>
        <textarea rows={3} className={input} placeholder={t('kyc.otherHint')} value={f.other_info} onChange={(e) => set('other_info', e.target.value)} />
      </div>

      <button disabled={busy} className="w-full rounded-xl bg-brand-600 px-4 py-3 font-medium text-white hover:bg-brand-700 disabled:opacity-50 sm:w-auto">
        {t('kyc.save')}
      </button>
    </form>
  );
}
