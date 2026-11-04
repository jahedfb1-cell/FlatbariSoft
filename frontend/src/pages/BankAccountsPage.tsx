import { useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import Modal from '../components/Modal';
import * as bankApi from '../api/bankAccounts';
import type { BankAccount, BankAccountInput } from '../api/bankAccounts';

const input = 'w-full rounded-lg border border-brand-200 px-3 py-2 text-sm';

interface FormState {
  bank_name: string;
  branch: string;
  account_no: string;
  account_title: string;
  opening_balance: string;
  sms_phone: string;
  holders: { name: string; phone: string; person_id: string; manual: boolean }[];
}

const blank = (): FormState => ({
  bank_name: '',
  branch: '',
  account_no: '',
  account_title: '',
  opening_balance: '0',
  sms_phone: '',
  holders: [0, 1, 2].map(() => ({ name: '', phone: '', person_id: '', manual: false })),
});

const fromAccount = (a: BankAccount): FormState => ({
  bank_name: a.bank_name,
  branch: a.branch ?? '',
  account_no: a.account_no,
  account_title: a.account_title,
  opening_balance: a.opening_balance,
  sms_phone: a.sms_phone ?? '',
  holders: [0, 1, 2].map((i) => ({ name: a.holders[i]?.name ?? '', phone: a.holders[i]?.phone ?? '', person_id: a.holders[i]?.person_id ? String(a.holders[i]!.person_id) : '', manual: !a.holders[i]?.person_id && !!(a.holders[i]?.name || a.holders[i]?.phone) })),
});

/** Empty text boxes are sent as null so an emptied holder really is cleared. */
const toInput = (f: FormState, creating: boolean): BankAccountInput => {
  const n = (v: string) => (v.trim() === '' ? null : v.trim());
  const out: BankAccountInput = {
    bank_name: f.bank_name.trim(),
    branch: n(f.branch),
    account_no: f.account_no.trim(),
    account_title: f.account_title.trim(),
    sms_phone: n(f.sms_phone),
    holder_1_name: n(f.holders[0].name),
    holder_1_phone: n(f.holders[0].phone),
    holder_2_name: n(f.holders[1].name),
    holder_2_phone: n(f.holders[1].phone),
    holder_3_name: n(f.holders[2].name),
    holder_3_phone: n(f.holders[2].phone),
    // a holder picked from the owners is linked to that person (the server then fills in the name and phone)
    holder_1_person_id: f.holders[0].person_id ? Number(f.holders[0].person_id) : null,
    holder_2_person_id: f.holders[1].person_id ? Number(f.holders[1].person_id) : null,
    holder_3_person_id: f.holders[2].person_id ? Number(f.holders[2].person_id) : null,
  };
  if (creating) out.opening_balance = f.opening_balance || 0;
  return out;
};

/** The society's bank accounts: bank, branch, account number, up to three account holders with phones, and the SMS number. */
export default function BankAccountsPage() {
  const { t, lang } = useLanguage();
  const { user } = useAuth();
  const canEdit = !!user && (user.roles.includes('super_admin') || user.permissions.includes('accounting.manage'));
  const [accounts, setAccounts] = useState<BankAccount[]>([]);
  const [editing, setEditing] = useState<BankAccount | 'new' | null>(null);
  const [form, setForm] = useState<FormState>(blank());
  const [error, setError] = useState<string | null>(null);
  const [owners, setOwners] = useState<bankApi.OwnerOption[]>([]);
  useEffect(() => {
    bankApi.listOwnerOptions().then(setOwners).catch(() => undefined);
  }, []);

  function load() {
    bankApi.listBankAccounts().then(setAccounts);
  }
  useEffect(load, []);

  function open(target: BankAccount | 'new') {
    setError(null);
    setForm(target === 'new' ? blank() : fromAccount(target));
    setEditing(target);
  }

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      if (editing === 'new') await bankApi.createBankAccount(toInput(form, true));
      else if (editing) await bankApi.updateBankAccount(editing.id, toInput(form, false));
      setEditing(null);
      load();
    } catch (err: any) {
      const errs = err?.response?.data?.errors;
      setError(errs ? (Object.values(errs) as string[][]).flat().join(' ') : (err?.response?.data?.message ?? t('somethingWrong')));
    }
  }

  const setHolder = (i: number, patch: Partial<FormState['holders'][number]>) =>
    setForm({ ...form, holders: form.holders.map((h, idx) => (idx === i ? { ...h, ...patch } : h)) });
  const pickOwner = (i: number, personId: string) => {
    const o = owners.find((x) => String(x.person_id) === personId);
    setHolder(i, { person_id: personId, manual: false, name: o?.name ?? '', phone: o?.mobile ?? '' });
  };
  // an owner can sign for only one slot of the same account
  const taken = (i: number) => form.holders.filter((_, idx) => idx !== i).map((h) => h.person_id).filter(Boolean);

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-2xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('bank.title')}</h1>
          </div>
          <div className="flex items-center gap-2">
            <LanguageToggle />
            {canEdit && (
              <button onClick={() => open('new')} className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm text-white hover:bg-brand-700">
                + {t('bank.add')}
              </button>
            )}
          </div>
        </div>

        {accounts.length === 0 && <p className="mb-4 text-sm text-status-due">{t('bank.none')}</p>}

        <div className="space-y-3">
          {accounts.map((a) => (
            <div key={a.id} className="rounded-2xl border border-brand-100 bg-white p-5 shadow-sm">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p className="font-semibold text-navy-900">{a.bank_name}</p>
                  <p className="text-sm text-navy-700">{a.branch ? `${t('bank.branch')}: ${a.branch}` : ''}</p>
                </div>
                {canEdit && (
                  <button onClick={() => open(a)} className="rounded-lg border border-brand-200 px-3 py-1 text-sm text-brand-700 hover:bg-brand-100">
                    {t('bank.edit')}
                  </button>
                )}
              </div>

              <div className="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                <p>
                  <span className="text-navy-700">{t('bank.accountTitle')}:</span> <b className="text-navy-900">{a.account_title}</b>
                </p>
                <p>
                  <span className="text-navy-700">{t('bank.accountNo')}:</span> <b className="text-navy-900">{a.account_no}</b>
                </p>
              </div>

              <div className="mt-3 rounded-xl bg-brand-50 p-3">
                <p className="mb-1 text-xs font-medium text-navy-700">{t('bank.holders')}</p>
                {a.holders.every((h) => !h.name && !h.phone) && <p className="text-sm text-navy-700">—</p>}
                {a.holders.map(
                  (h, i) =>
                    (h.name || h.phone) && (
                      <p key={i} className="flex flex-wrap justify-between gap-2 text-sm text-navy-900">
                        <span>
                          {i + 1}. {h.name ?? '—'}
                        </span>
                        {h.phone && (
                          <a href={`tel:${h.phone}`} className="text-brand-700 hover:underline">
                            {h.phone}
                          </a>
                        )}
                      </p>
                    ),
                )}
                {a.sms_phone && (
                  <p className="mt-2 border-t border-brand-100 pt-2 text-sm text-navy-900">
                    📱 {t('bank.smsPhone')}: <b>{a.sms_phone}</b>
                  </p>
                )}
              </div>

              <p className="mt-3 text-sm text-navy-900">
                {t('bank.current')}: <b className="text-lg">৳{Number(a.current_balance).toLocaleString('en-BD')}</b>
              </p>
            </div>
          ))}
        </div>
      </div>

      {editing && (
        <Modal title={editing === 'new' ? t('bank.add') : t('bank.edit')} onClose={() => setEditing(null)}>
          <form onSubmit={submit} className="space-y-3">
            <input required className={input} placeholder={t('bank.name')} value={form.bank_name} onChange={(e) => setForm({ ...form, bank_name: e.target.value })} />
            <input className={input} placeholder={t('bank.branch')} value={form.branch} onChange={(e) => setForm({ ...form, branch: e.target.value })} />
            <input required className={input} placeholder={t('bank.accountTitle')} value={form.account_title} onChange={(e) => setForm({ ...form, account_title: e.target.value })} />
            <input required className={input} placeholder={t('bank.accountNo')} value={form.account_no} onChange={(e) => setForm({ ...form, account_no: e.target.value })} />
            {editing === 'new' && <input type="number" className={input} placeholder={t('bank.opening')} value={form.opening_balance} onChange={(e) => setForm({ ...form, opening_balance: e.target.value })} />}

            <p className="pt-1 text-sm font-medium text-navy-900">{t('bank.holders')}</p>
            <p className="-mt-1 text-xs text-navy-700">{lang === 'bn' ? 'অ্যাকাউন্ট হোল্ডার ফ্ল্যাট মালিকদের মধ্য থেকে বাছুন — নাম ও ফোন মালিকের তথ্য থেকে নিজে আসবে।' : 'Choose the holders from the flat owners - name and phone come from the owner\u2019s record.'}</p>
            {form.holders.map((h, i) => (
              <div key={i} className="rounded-lg border border-brand-100 p-2">
                {!h.manual ? (
                  <>
                    <select className={input} value={h.person_id} onChange={(e) => pickOwner(i, e.target.value)} aria-label={`holder ${i + 1}`}>
                      <option value="">{`${t('bank.holderName')} ${i + 1} — ${lang === 'bn' ? 'ফ্ল্যাট মালিক বাছুন' : 'choose a flat owner'}`}</option>
                      {owners.filter((o) => !taken(i).includes(String(o.person_id))).map((o) => (
                        <option key={o.person_id} value={o.person_id}>{o.flats} — {o.name} · {o.mobile}{o.has_login ? ' ✓' : ''}</option>
                      ))}
                    </select>
                    {h.person_id && <p className="mt-1 text-xs text-navy-700">📱 {h.phone}{owners.find((o) => String(o.person_id) === h.person_id)?.has_login ? (lang === 'bn' ? ' · লগইন আছে' : ' · has a login') : ''}</p>}
                    <button type="button" className="mt-1 text-xs text-navy-700 hover:underline" onClick={() => setHolder(i, { manual: true, person_id: '' })}>
                      {lang === 'bn' ? 'ফ্ল্যাট মালিক নন? হাতে লিখুন' : 'Not a flat owner? Type by hand'}
                    </button>
                  </>
                ) : (
                  <>
                    <div className="grid grid-cols-2 gap-2">
                      <input className={input} placeholder={`${t('bank.holderName')} ${i + 1}`} value={h.name} onChange={(e) => setHolder(i, { name: e.target.value })} />
                      <input className={input} inputMode="tel" placeholder={`${t('bank.holderPhone')} ${i + 1}`} value={h.phone} onChange={(e) => setHolder(i, { phone: e.target.value })} />
                    </div>
                    <button type="button" className="mt-1 text-xs text-navy-700 hover:underline" onClick={() => setHolder(i, { manual: false, name: '', phone: '' })}>
                      {lang === 'bn' ? '← ফ্ল্যাট মালিকদের তালিকা থেকে বাছুন' : '← Choose from the flat owners'}
                    </button>
                  </>
                )}
              </div>
            ))}

            <label className="block text-sm text-navy-700">
              {t('bank.smsPhone')}
              <input className={`${input} mt-1`} inputMode="tel" placeholder="01XXXXXXXXX" value={form.sms_phone} onChange={(e) => setForm({ ...form, sms_phone: e.target.value })} />
              <span className="text-xs">{t('bank.smsPhoneHint')}</span>
            </label>

            {error && <p className="text-sm text-status-due">{error}</p>}
            <div className="flex justify-end gap-2">
              <button type="button" onClick={() => setEditing(null)} className="rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700">
                {t('complaints.cancel')}
              </button>
              <button className="rounded-lg bg-brand-600 px-4 py-2 text-sm text-white hover:bg-brand-700">{t('settings.save')}</button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
