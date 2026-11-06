import { useCallback, useEffect, useState, type FormEvent } from 'react';
import Modal from './Modal';
import { useLanguage } from '../context/LanguageContext';
import { apiError } from '../api/hall';
import * as api from '../api/contributions';
import type { HeadRow } from '../api/contributions';

/**
 * The heads money is collected for in one book: add a new one, rename, switch off, delete.
 * A head that money was collected under is never deleted (every balance would move) - it can only be switched off:
 * no new payment goes to it, but what was collected stays in every statement and ledger.
 */
export default function ContributionHeadsModal({ bookCode, bookName, onClose, onChanged }: { bookCode: string; bookName: string; onClose: () => void; onChanged: () => void }) {
  const { lang } = useLanguage();
  const L = (bn: string, en: string) => (lang === 'bn' ? bn : en);
  const [heads, setHeads] = useState<HeadRow[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [nameBn, setNameBn] = useState('');
  const [nameEn, setNameEn] = useState('');
  const [counts, setCounts] = useState(true);
  const [editing, setEditing] = useState<number | null>(null);
  const [eBn, setEBn] = useState('');
  const [eEn, setEEn] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    api.listHeads(bookCode).then(setHeads).catch((e) => setError(apiError(e, L('কিছু একটা সমস্যা হয়েছে।', 'Something went wrong.'))));
  }, [bookCode]);
  useEffect(load, [load]);

  async function run(action: () => Promise<HeadRow[]>, after?: () => void) {
    if (busy) return;
    setError(null);
    setBusy(true);
    try {
      setHeads(await action());
      after?.();
      onChanged();
    } catch (e) {
      setError(apiError(e, L('কিছু একটা সমস্যা হয়েছে।', 'Something went wrong.')));
    } finally {
      setBusy(false);
    }
  }

  function add(e: FormEvent) {
    e.preventDefault();
    run(() => api.createHead(bookCode, { name_bn: nameBn.trim(), name_en: nameEn.trim() || undefined, counts_toward_target: counts }), () => {
      setNameBn('');
      setNameEn('');
      setCounts(true);
    });
  }

  const input = 'rounded-lg border border-brand-200 px-2 py-1.5 text-sm';
  const link = 'text-xs text-brand-700 hover:underline disabled:opacity-40';

  return (
    <Modal title={`${L('খাত পরিচালনা', 'Manage heads')} — ${bookName}`} onClose={onClose}>
      <div className="space-y-4 text-sm text-navy-900">
        <form onSubmit={add} className="space-y-2 rounded-lg border border-brand-200 p-3">
          <p className="font-medium">+ {L('নতুন খাত', 'New head')}</p>
          <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <input required className={input} placeholder={L('খাতের নাম (বাংলা) *', 'Name (Bengali) *')} value={nameBn} onChange={(e) => setNameBn(e.target.value)} />
            <input className={input} placeholder={L('ইংরেজি নাম (ঐচ্ছিক)', 'English name (optional)')} value={nameEn} onChange={(e) => setNameEn(e.target.value)} />
          </div>
          <label className="flex items-center gap-2 text-xs text-navy-700">
            <input type="checkbox" checked={counts} onChange={(e) => setCounts(e.target.checked)} />
            {L('এই খাতের টাকা টার্গেটের হিসাবে গোনা হবে', 'Money under this head counts toward the target')}
          </label>
          <button disabled={busy || !nameBn.trim()} className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
            {L('খাত যোগ করুন', 'Add head')}
          </button>
        </form>

        {error && <p className="rounded-lg bg-status-due/10 px-3 py-2 text-status-due">{error}</p>}

        {!heads ? (
          <p>…</p>
        ) : (
          <div className="space-y-2">
            {heads.map((h) => (
              <div key={h.id} className={`rounded-lg border border-brand-200 p-3 ${h.is_active ? '' : 'bg-brand-50 opacity-80'}`}>
                {editing === h.id ? (
                  <div className="space-y-2">
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                      <input className={input} value={eBn} onChange={(e) => setEBn(e.target.value)} />
                      <input className={input} value={eEn} onChange={(e) => setEEn(e.target.value)} />
                    </div>
                    <div className="flex gap-3">
                      <button disabled={busy || !eBn.trim()} className="rounded-lg bg-brand-600 px-3 py-1 text-xs font-medium text-white disabled:opacity-50" onClick={() => run(() => api.updateHead(h.id, { name_bn: eBn.trim(), name_en: eEn.trim() || eBn.trim() }), () => setEditing(null))}>
                        {L('সংরক্ষণ', 'Save')}
                      </button>
                      <button className={link} onClick={() => setEditing(null)}>
                        {L('বাদ দিন', 'Cancel')}
                      </button>
                    </div>
                  </div>
                ) : (
                  <>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <div>
                        <p className="font-medium">
                          {lang === 'bn' ? h.name_bn : h.name_en}
                          {!h.is_active && <span className="ml-2 rounded-full bg-navy-700/10 px-2 py-0.5 text-xs font-normal text-navy-700">{L('বন্ধ', 'switched off')}</span>}
                        </p>
                        <p className="text-xs text-navy-700">
                          {lang === 'bn' ? h.name_en : h.name_bn} · {h.counts_toward_target ? L('টার্গেটে গোনা হয়', 'counts toward target') : L('টার্গেটে গোনা হয় না', 'not counted in target')} ·{' '}
                          {h.used ? L('টাকা আদায় হয়েছে', 'money collected') : L('এখনও কোনো আদায় নেই', 'nothing collected yet')}
                        </p>
                      </div>
                      <div className="flex gap-3">
                        <button
                          className={link}
                          onClick={() => {
                            setEditing(h.id);
                            setEBn(h.name_bn);
                            setEEn(h.name_en);
                          }}
                        >
                          ✎ {L('নাম বদলান', 'Rename')}
                        </button>
                        <button className={link} disabled={busy} onClick={() => run(() => api.updateHead(h.id, { is_active: !h.is_active }))}>
                          {h.is_active ? L('বন্ধ করুন', 'Switch off') : L('চালু করুন', 'Switch on')}
                        </button>
                        <button
                          className="text-xs text-status-due hover:underline disabled:opacity-40"
                          disabled={busy || h.used}
                          title={h.used ? L('টাকা আদায় হয়ে গেছে — মোছা যাবে না, শুধু বন্ধ করা যাবে', 'Money was collected - it cannot be deleted, only switched off') : ''}
                          onClick={() => window.confirm(L('এই খাত মুছে ফেলবেন?', 'Delete this head?')) && run(() => api.deleteHead(h.id))}
                        >
                          {L('মুছুন', 'Delete')}
                        </button>
                      </div>
                    </div>
                  </>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </Modal>
  );
}
