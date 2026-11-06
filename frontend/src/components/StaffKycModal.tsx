import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import * as staffApi from '../api/staff';
import type { Staff, StaffDocType, StaffFamilyMember, StaffKyc } from '../api/staff';
import { useLanguage } from '../context/LanguageContext';
import { apiError } from '../api/hall';
import { blobErrorMessage } from '../utils/download';
import { localToday } from './HallCalendar';

const input = 'w-full rounded-lg border border-brand-200 px-3 py-2 text-navy-900 outline-none focus:border-brand-500';
const btn = 'rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50';
const btnGhost = 'rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700 hover:bg-brand-100 disabled:opacity-50';

const RELATIONS: [string, string, string][] = [
  ['SPOUSE', 'স্বামী/স্ত্রী', 'Spouse'],
  ['SON', 'ছেলে', 'Son'],
  ['DAUGHTER', 'মেয়ে', 'Daughter'],
  ['FATHER', 'বাবা', 'Father'],
  ['MOTHER', 'মা', 'Mother'],
  ['BROTHER', 'ভাই', 'Brother'],
  ['SISTER', 'বোন', 'Sister'],
  ['RELATIVE', 'আত্মীয়', 'Relative'],
  ['OTHER', 'অন্যান্য', 'Other'],
];

const DOC_TYPES: [StaffDocType, string, string, string][] = [
  ['CV', 'সিভি (CV)', 'CV / Resume', '📄'],
  ['NID', 'NID কপি', 'NID copy', '🪪'],
  ['PHOTO', 'ছবি', 'Photo', '🖼️'],
  ['CERTIFICATE', 'সনদ / সার্টিফিকেট', 'Certificate', '🎓'],
  ['OTHER', 'অন্যান্য', 'Other', '📎'],
];

const MISSING_LABEL: Record<string, [string, string]> = {
  nid: ['NID নম্বর', 'NID number'],
  father_name: ['বাবার নাম', "Father's name"],
  date_of_birth: ['জন্ম তারিখ', 'Date of birth'],
  present_address: ['বর্তমান ঠিকানা', 'Present address'],
  permanent_address: ['স্থায়ী ঠিকানা', 'Permanent address'],
  emergency_name: ['জরুরি যোগাযোগের নাম', 'Emergency contact'],
  emergency_phone: ['জরুরি যোগাযোগের নম্বর', 'Emergency phone'],
  'doc:CV': ['সিভি ফাইল', 'CV file'],
  'doc:NID': ['NID কপি', 'NID copy'],
  'doc:PHOTO': ['ছবি', 'Photo'],
};

const kb = (n: number) => (n >= 1048576 ? `${(n / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`);

/** A staff member's full KYC: personal details, addresses, emergency contact, family, work history - and their papers (CV, NID, photo ...). */
export default function StaffKycModal({ staff, onClose }: { staff: Staff; onClose: () => void }) {
  const { lang } = useLanguage();
  const bn = lang === 'bn';
  const L = (b: string, e: string) => (bn ? b : e);

  const [kyc, setKyc] = useState<StaffKyc | null>(null);
  const [f, setF] = useState<Record<string, string>>({});
  const [family, setFamily] = useState<StaffFamilyMember[]>([]);
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [docType, setDocType] = useState<StaffDocType>('CV');
  const [files, setFiles] = useState<File[]>([]);

  const apply = (k: StaffKyc) => {
    setKyc(k);
    const next: Record<string, string> = {};
    ['nid', 'father_name', 'mother_name', 'spouse_name', 'date_of_birth', 'gender', 'marital_status', 'blood_group', 'religion', 'alt_mobile', 'email', 'present_address', 'permanent_address', 'emergency_name', 'emergency_phone', 'emergency_relation', 'education', 'experience', 'reference_name', 'reference_phone', 'other_info'].forEach((key) => {
      next[key] = ((k as unknown as Record<string, string | null>)[key] ?? '') as string;
    });
    setF(next);
    setFamily(k.family_members ?? []);
  };

  useEffect(() => {
    staffApi.getStaffKyc(staff.id).then(apply).catch((e) => setMsg({ ok: false, text: apiError(e, L('লোড করা যায়নি।', 'Could not load.')) }));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [staff.id]);

  const set = (key: string) => (e: { target: { value: string } }) => setF({ ...f, [key]: e.target.value });

  async function save(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setMsg(null);
    try {
      const payload: Record<string, unknown> = {};
      Object.entries(f).forEach(([k, v]) => (payload[k] = v.trim() === '' ? null : v.trim()));
      payload.family_members = family.filter((m) => m.name.trim() !== '').map((m) => ({ ...m, age: m.age === '' || m.age == null ? null : Number(m.age), mobile: m.mobile || null, occupation: m.occupation || null }));
      apply(await staffApi.saveStaffKyc(staff.id, payload));
      setMsg({ ok: true, text: L('KYC সংরক্ষিত হয়েছে।', 'KYC saved.') });
    } catch (err) {
      setMsg({ ok: false, text: apiError(err, L('কিছু একটা সমস্যা হয়েছে।', 'Something went wrong.')) });
    } finally {
      setBusy(false);
    }
  }

  async function upload() {
    if (files.length === 0) return;
    setBusy(true);
    setMsg(null);
    try {
      apply(await staffApi.uploadStaffDocs(staff.id, docType, files));
      setFiles([]);
      setMsg({ ok: true, text: L('ফাইল জমা হয়েছে।', 'File uploaded.') });
    } catch (err) {
      setMsg({ ok: false, text: apiError(err, L('ফাইল জমা হয়নি।', 'Upload failed.')) });
    } finally {
      setBusy(false);
    }
  }

  async function remove(id: number) {
    if (!confirm(L('ফাইলটি মুছে ফেলবেন?', 'Delete this file?'))) return;
    try {
      apply(await staffApi.removeStaffDoc(id));
    } catch (err) {
      setMsg({ ok: false, text: apiError(err, L('মোছা যায়নি।', 'Could not delete.')) });
    }
  }

  const section = (title: string, children: ReactNode) => (
    <section className="rounded-xl border border-brand-200 bg-white p-4">
      <h3 className="mb-3 text-sm font-semibold text-navy-900">{title}</h3>
      {children}
    </section>
  );
  const field = (key: string, label: string, props: { type?: string; placeholder?: string; max?: string } = {}) => (
    <label className="block text-sm text-navy-700">
      {label}
      <input className={`${input} mt-1`} type={props.type ?? 'text'} placeholder={props.placeholder} max={props.max} value={f[key] ?? ''} onChange={set(key)} />
    </label>
  );
  const choice = (key: string, label: string, options: [string, string][]) => (
    <label className="block text-sm text-navy-700">
      {label}
      <select className={`${input} mt-1`} value={f[key] ?? ''} onChange={set(key)}>
        <option value="">—</option>
        {options.map(([v, text]) => (
          <option key={v} value={v}>{text}</option>
        ))}
      </select>
    </label>
  );
  const area = (key: string, label: string, rows = 2) => (
    <label className="block text-sm text-navy-700">
      {label}
      <textarea rows={rows} className={`${input} mt-1`} value={f[key] ?? ''} onChange={set(key)} />
    </label>
  );

  const updateMember = (i: number, patch: Partial<StaffFamilyMember>) => setFamily(family.map((m, idx) => (idx === i ? { ...m, ...patch } : m)));
  const today = localToday();

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-navy-900/40 px-3 py-6">
      <div className="mx-auto w-full max-w-4xl rounded-xl border border-brand-200 bg-brand-50 p-4 shadow-lg sm:p-6">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div>
            <h2 className="text-lg font-semibold text-navy-900">{L('স্টাফ KYC ও ফাইল', 'Staff KYC & files')} — {staff.name}</h2>
            <p className="text-sm text-navy-700">{staff.designation} · {staff.mobile}</p>
          </div>
          <button onClick={onClose} className="rounded-lg px-2 py-1 text-navy-700 hover:bg-brand-100" aria-label="close">✕</button>
        </div>

        {kyc && !kyc.ready && (
          <p className="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-status-partial">{L('ডাটাবেস আপগ্রেড বাকি আছে — আপগ্রেড ফাইল ইমপোর্ট না হওয়া পর্যন্ত KYC সেভ করা যাবে না।', 'The database upgrade is pending - KYC cannot be saved until it is imported.')}</p>
        )}

        {kyc && (
          <div className={`mb-4 rounded-xl p-3 text-sm ${kyc.complete ? 'bg-status-paid/10 text-status-paid' : 'bg-amber-50 text-navy-900'}`}>
            {kyc.complete ? (
              <p className="font-medium">✓ {L('KYC সম্পূর্ণ', 'KYC complete')}</p>
            ) : (
              <>
                <p className="font-medium">{L('এখনো বাকি:', 'Still missing:')}</p>
                <p className="mt-1 flex flex-wrap gap-1">
                  {kyc.missing.map((m) => (
                    <span key={m} className="rounded-full bg-white px-2 py-0.5 text-xs text-status-due">{MISSING_LABEL[m]?.[bn ? 0 : 1] ?? m}</span>
                  ))}
                </p>
              </>
            )}
          </div>
        )}

        <form onSubmit={save} className="space-y-4">
          {section(L('ব্যক্তিগত তথ্য', 'Personal details'),
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              {field('nid', L('NID নম্বর', 'NID number'), { placeholder: '10 / 13 / 17' })}
              {field('date_of_birth', L('জন্ম তারিখ', 'Date of birth'), { type: 'date', max: today })}
              {choice('gender', L('লিঙ্গ', 'Gender'), [['MALE', L('পুরুষ', 'Male')], ['FEMALE', L('নারী', 'Female')], ['OTHER', L('অন্যান্য', 'Other')]])}
              {field('father_name', L('বাবার নাম', "Father's name"))}
              {field('mother_name', L('মায়ের নাম', "Mother's name"))}
              {choice('marital_status', L('বৈবাহিক অবস্থা', 'Marital status'), [['SINGLE', L('অবিবাহিত', 'Single')], ['MARRIED', L('বিবাহিত', 'Married')], ['WIDOWED', L('বিধবা/বিপত্নীক', 'Widowed')], ['DIVORCED', L('বিবাহবিচ্ছিন্ন', 'Divorced')]])}
              {f.marital_status === 'MARRIED' && field('spouse_name', L('স্বামী/স্ত্রীর নাম', 'Spouse name'))}
              {choice('blood_group', L('রক্তের গ্রুপ', 'Blood group'), ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'].map((g) => [g, g] as [string, string]))}
              {field('religion', L('ধর্ম', 'Religion'))}
            </div>,
          )}

          {section(L('যোগাযোগ ও ঠিকানা', 'Contact & address'),
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              {field('alt_mobile', L('বিকল্প মোবাইল', 'Alternative mobile'), { placeholder: '01XXXXXXXXX' })}
              {field('email', L('ইমেইল', 'Email'), { type: 'email' })}
              {area('present_address', L('বর্তমান ঠিকানা', 'Present address'))}
              {area('permanent_address', L('স্থায়ী ঠিকানা', 'Permanent address'))}
            </div>,
          )}

          {section(L('জরুরি যোগাযোগ', 'Emergency contact'),
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              {field('emergency_name', L('নাম', 'Name'))}
              {field('emergency_phone', L('মোবাইল', 'Mobile'), { placeholder: '01XXXXXXXXX' })}
              {field('emergency_relation', L('সম্পর্ক', 'Relation'))}
            </div>,
          )}

          {section(L('পরিবারের সদস্য', 'Family members'),
            <div className="space-y-2">
              {family.map((m, i) => (
                <div key={i} className="grid grid-cols-2 gap-2 rounded-lg border border-brand-100 p-2 sm:grid-cols-6">
                  <input className={`${input} col-span-2`} placeholder={L('নাম', 'Name')} value={m.name} onChange={(e) => updateMember(i, { name: e.target.value })} />
                  <select className={input} value={m.relation} onChange={(e) => updateMember(i, { relation: e.target.value })}>
                    {RELATIONS.map(([v, b, en]) => (
                      <option key={v} value={v}>{bn ? b : en}</option>
                    ))}
                  </select>
                  <input className={input} type="number" min={0} max={120} placeholder={L('বয়স', 'Age')} value={m.age ?? ''} onChange={(e) => updateMember(i, { age: e.target.value })} />
                  <input className={input} placeholder={L('পেশা', 'Occupation')} value={m.occupation ?? ''} onChange={(e) => updateMember(i, { occupation: e.target.value })} />
                  <button type="button" className="text-status-due hover:underline" onClick={() => setFamily(family.filter((_, idx) => idx !== i))}>{L('মুছুন', 'Remove')}</button>
                </div>
              ))}
              {family.length < 15 && (
                <button type="button" className={btnGhost} onClick={() => setFamily([...family, { name: '', relation: 'SPOUSE' }])}>
                  + {L('সদস্য যোগ করুন', 'Add member')}
                </button>
              )}
            </div>,
          )}

          {section(L('শিক্ষা, অভিজ্ঞতা ও রেফারেন্স', 'Education, experience & reference'),
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              {field('education', L('শিক্ষাগত যোগ্যতা', 'Education'))}
              {area('experience', L('আগের কাজের অভিজ্ঞতা', 'Previous work experience'))}
              {field('reference_name', L('রেফারেন্সের নাম', 'Reference name'))}
              {field('reference_phone', L('রেফারেন্সের মোবাইল', 'Reference mobile'), { placeholder: '01XXXXXXXXX' })}
              <div className="sm:col-span-2">{area('other_info', L('অন্যান্য তথ্য', 'Other information'))}</div>
            </div>,
          )}

          {msg && <p className={`rounded-lg p-2 text-sm ${msg.ok ? 'bg-status-paid/10 text-status-paid' : 'bg-status-due/10 text-status-due'}`}>{msg.text}</p>}
          <div className="flex justify-end gap-2">
            <button type="button" className={btnGhost} onClick={onClose}>{L('বন্ধ', 'Close')}</button>
            <button className={btn} disabled={busy || !kyc?.ready}>{L('KYC সংরক্ষণ', 'Save KYC')}</button>
          </div>
        </form>

        <div className="mt-4">
          {section(L('ফাইল (CV, NID, ছবি ...)', 'Files (CV, NID, photo ...)'),
            <div className="space-y-3">
              <div className="flex flex-wrap items-end gap-2">
                <label className="text-sm text-navy-700">
                  {L('ফাইলের ধরন', 'File type')}
                  <select className={`${input} mt-1`} value={docType} onChange={(e) => setDocType(e.target.value as StaffDocType)}>
                    {DOC_TYPES.map(([v, b, en]) => (
                      <option key={v} value={v}>{bn ? b : en}</option>
                    ))}
                  </select>
                </label>
                <label className="min-w-0 flex-1 text-sm text-navy-700">
                  {L('PDF বা ছবি (সর্বোচ্চ ১০ MB)', 'PDF or image (max 10 MB)')}
                  <input type="file" multiple accept=".pdf,image/jpeg,image/png,image/webp" className={`${input} mt-1`} onChange={(e) => setFiles(Array.from(e.target.files ?? []))} />
                </label>
                <button type="button" className={btn} disabled={busy || files.length === 0 || !kyc?.ready} onClick={upload}>
                  ⬆ {L('জমা দিন', 'Upload')}
                </button>
              </div>

              {kyc && kyc.documents.length === 0 && <p className="text-sm text-navy-700">{L('এখনো কোনো ফাইল নেই।', 'No files yet.')}</p>}
              <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                {kyc?.documents.map((d) => {
                  const meta = DOC_TYPES.find((x) => x[0] === d.type);
                  return (
                    <div key={d.id} className="flex items-center gap-3 rounded-lg border border-brand-200 p-2">
                      <span className="text-2xl">{d.mime.startsWith('image/') ? '🖼️' : meta?.[3] ?? '📎'}</span>
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium text-navy-900">{d.name}</p>
                        <p className="text-xs text-navy-700">{bn ? meta?.[1] : meta?.[2]} · {kb(d.size)}</p>
                      </div>
                      <button type="button" className="text-sm text-brand-700 hover:underline" onClick={() => staffApi.openStaffDoc(d).catch(async (e) => setMsg({ ok: false, text: await blobErrorMessage(e, L('খোলা যায়নি।', 'Could not open.')) }))}>
                        {L('খুলুন', 'Open')}
                      </button>
                      <button type="button" className="text-sm text-status-due hover:underline" onClick={() => remove(d.id)}>{L('মুছুন', 'Delete')}</button>
                    </div>
                  );
                })}
              </div>
            </div>,
          )}
        </div>
      </div>
    </div>
  );
}
