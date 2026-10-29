import { useEffect, useState, type FormEvent } from 'react';
import { useLanguage } from '../context/LanguageContext';
import * as api from '../api/tenantRequests';
import type { TenantRequest } from '../api/tenantRequests';

const input = 'w-full rounded-lg border border-brand-200 px-3 py-2 text-base';
const label = 'mb-1 block text-sm font-medium text-navy-900';
const statusStyle: Record<TenantRequest['status'], string> = {
  PENDING: 'bg-amber-50 text-status-partial',
  APPROVED: 'bg-green-50 text-status-paid',
  REJECTED: 'bg-red-50 text-status-due',
  CANCELLED: 'bg-brand-100 text-navy-700',
};
const today = () => new Date(Date.now() + 6 * 3600 * 1000).toISOString().slice(0, 10); // Dhaka date

/**
 * "This person is renting my flat": the form to tell the office, and the list of requests with their state.
 * An owner picks among their own flats; the manager among all. An admin sees every request here and accepts or
 * rejects it - accepting is what creates the tenancy and the tenant's login.
 */
export default function TenantRequestPanel({ flats, onDecided }: { flats: { id: number; flat_no: string }[]; onDecided?: () => void }) {
  const { t } = useLanguage();
  const [rows, setRows] = useState<TenantRequest[]>([]);
  const [canDecide, setCanDecide] = useState(false);
  const [f, setF] = useState({ flat_id: '', name_bn: '', name_en: '', mobile: '', nid: '', start_date: today(), monthly_rent: '', security_deposit: '', occupation: '', family_members_count: '', note: '' });
  const [msg, setMsg] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [rejecting, setRejecting] = useState<number | null>(null);
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);

  function load() {
    api.listTenantRequests().then((r) => {
      setRows(r.requests);
      setCanDecide(r.can_decide);
    });
  }
  useEffect(load, []);
  useEffect(() => {
    if (!f.flat_id && flats[0]) setF((cur) => ({ ...cur, flat_id: String(flats[0].id) }));
  }, [flats, f.flat_id]);

  const fail = (e: any) => {
    const errs = e?.response?.data?.errors;
    setError(errs ? (Object.values(errs) as string[][]).flat().join(' ') : (e?.response?.data?.message ?? t('somethingWrong')));
  };

  async function submit(e: FormEvent) {
    e.preventDefault();
    setMsg(null);
    setError(null);
    setBusy(true);
    try {
      await api.sendTenantRequest({
        flat_id: Number(f.flat_id),
        name_bn: f.name_bn.trim(),
        name_en: f.name_en.trim() || undefined,
        mobile: f.mobile.trim(),
        nid: f.nid.trim() || undefined,
        start_date: f.start_date,
        monthly_rent: f.monthly_rent || undefined,
        security_deposit: f.security_deposit || undefined,
        occupation: f.occupation.trim() || undefined,
        family_members_count: f.family_members_count ? Number(f.family_members_count) : undefined,
        note: f.note.trim() || undefined,
      });
      setF({ ...f, name_bn: '', name_en: '', mobile: '', nid: '', monthly_rent: '', security_deposit: '', occupation: '', family_members_count: '', note: '' });
      setMsg(t('tr.sent'));
      load();
    } catch (err) {
      fail(err);
    } finally {
      setBusy(false);
    }
  }

  async function act(fn: () => Promise<unknown>, done?: string) {
    setMsg(null);
    setError(null);
    try {
      await fn();
      if (done) setMsg(done);
      setRejecting(null);
      setReason('');
      load();
      onDecided?.();
    } catch (err) {
      fail(err);
    }
  }

  const card = 'rounded-2xl border border-brand-100 bg-white p-4 shadow-sm';

  return (
    <div className="space-y-5">
      {flats.length > 0 && (
        <form onSubmit={submit} className={`${card} space-y-3`}>
          <h2 className="font-semibold text-navy-900">{t('tr.formTitle')}</h2>
          <p className="text-sm text-navy-700">{t('tr.hint')}</p>
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <label className={label}>{t('tr.flat')} *</label>
              <select required className={input} value={f.flat_id} onChange={(e) => setF({ ...f, flat_id: e.target.value })}>
                {flats.map((fl) => (
                  <option key={fl.id} value={fl.id}>
                    {fl.flat_no}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className={label}>{t('tr.startDate')} *</label>
              <input required type="date" className={input} value={f.start_date} onChange={(e) => setF({ ...f, start_date: e.target.value })} />
            </div>
            <div>
              <label className={label}>{t('tr.name')} *</label>
              <input required className={input} value={f.name_bn} onChange={(e) => setF({ ...f, name_bn: e.target.value })} />
            </div>
            <div>
              <label className={label}>{t('tr.mobile')} *</label>
              <input required inputMode="tel" placeholder="01XXXXXXXXX" className={input} value={f.mobile} onChange={(e) => setF({ ...f, mobile: e.target.value })} />
              <p className="mt-1 text-xs text-navy-700">{t('tr.mobileHint')}</p>
            </div>
            <div>
              <label className={label}>{t('tr.nid')}</label>
              <input inputMode="numeric" className={input} value={f.nid} onChange={(e) => setF({ ...f, nid: e.target.value.replace(/\D/g, '') })} />
            </div>
            <div>
              <label className={label}>{t('tr.occupation')}</label>
              <input className={input} value={f.occupation} onChange={(e) => setF({ ...f, occupation: e.target.value })} />
            </div>
            <div>
              <label className={label}>{t('tr.rent')}</label>
              <input type="number" min={0} className={input} value={f.monthly_rent} onChange={(e) => setF({ ...f, monthly_rent: e.target.value })} />
            </div>
            <div>
              <label className={label}>{t('tr.deposit')}</label>
              <input type="number" min={0} className={input} value={f.security_deposit} onChange={(e) => setF({ ...f, security_deposit: e.target.value })} />
            </div>
            <div>
              <label className={label}>{t('tr.family')}</label>
              <input type="number" min={0} max={50} className={input} value={f.family_members_count} onChange={(e) => setF({ ...f, family_members_count: e.target.value })} />
            </div>
            <div className="sm:col-span-2">
              <label className={label}>{t('tr.note')}</label>
              <textarea rows={2} className={input} value={f.note} onChange={(e) => setF({ ...f, note: e.target.value })} />
            </div>
          </div>
          {msg && <p className="rounded-lg bg-green-50 p-2 text-sm text-status-paid">{msg}</p>}
          {error && <p className="rounded-lg bg-red-50 p-2 text-sm text-status-due">{error}</p>}
          <button disabled={busy} className="w-full rounded-xl bg-brand-600 px-4 py-3 font-medium text-white hover:bg-brand-700 disabled:opacity-50 sm:w-auto">
            {t('tr.send')}
          </button>
        </form>
      )}

      <div>
        <h2 className="mb-2 font-semibold text-navy-900">{canDecide ? t('tr.allRequests') : t('tr.myRequests')}</h2>
        {!flats.length && error && <p className="mb-2 text-sm text-status-due">{error}</p>}
        <div className="space-y-3">
          {rows.map((r) => (
            <div key={r.id} className={card}>
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p className="font-medium text-navy-900">
                    {t('tr.flat')} {r.flat_no} · {r.name_bn}
                  </p>
                  <p className="text-sm text-navy-700">
                    {r.mobile}
                    {r.nid ? ` · NID ${r.nid}` : ''} · {t('tr.startDate')} {r.start_date}
                  </p>
                  <p className="text-xs text-navy-700">
                    {r.monthly_rent ? `${t('tr.rent')}: ৳${Number(r.monthly_rent).toLocaleString('en-BD')}` : ''}
                    {r.security_deposit ? ` · ${t('tr.deposit')}: ৳${Number(r.security_deposit).toLocaleString('en-BD')}` : ''}
                  </p>
                </div>
                <span className={`rounded-full px-3 py-1 text-xs font-medium ${statusStyle[r.status]}`}>{t(`tr.status.${r.status}` as any)}</span>
              </div>
              {r.note && <p className="mt-2 text-sm text-navy-900">“{r.note}”</p>}
              <p className="mt-2 text-xs text-navy-700">
                {t('tr.sentBy')}: {r.requested_by ?? '—'} · {r.requested_at}
                {r.decided_at ? ` · ${t('tr.decided')}: ${r.decided_by ?? '—'} ${r.decided_at}` : ''}
              </p>
              {r.status === 'REJECTED' && r.reject_reason && <p className="mt-2 rounded-lg bg-red-50 p-2 text-sm text-status-due">{r.reject_reason}</p>}
              {r.status === 'APPROVED' && <p className="mt-2 rounded-lg bg-green-50 p-2 text-sm text-status-paid">{t('tr.approvedNote')}</p>}

              {r.status === 'PENDING' && (
                <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-brand-100 pt-3">
                  {canDecide && (
                    <>
                      <button onClick={() => act(() => api.approveTenantRequest(r.id), t('tr.approved'))} className="rounded-lg bg-status-paid px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                        ✔ {t('tr.approve')}
                      </button>
                      {rejecting === r.id ? (
                        <>
                          <input className="min-w-40 flex-1 rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder={t('tr.reasonHint')} value={reason} onChange={(e) => setReason(e.target.value)} />
                          <button disabled={reason.trim().length < 3} onClick={() => act(() => api.rejectTenantRequest(r.id, reason.trim()))} className="rounded-lg bg-status-due px-3 py-2 text-sm text-white disabled:opacity-50">
                            {t('tr.rejectConfirm')}
                          </button>
                        </>
                      ) : (
                        <button onClick={() => setRejecting(r.id)} className="rounded-lg border border-status-due/40 px-4 py-2 text-sm text-status-due hover:bg-red-50">
                          {t('tr.reject')}
                        </button>
                      )}
                    </>
                  )}
                  {!canDecide && (
                    <button onClick={() => act(() => api.cancelTenantRequest(r.id))} className="rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700 hover:bg-brand-100">
                      {t('tr.cancel')}
                    </button>
                  )}
                </div>
              )}
            </div>
          ))}
          {rows.length === 0 && <p className="rounded-xl bg-white p-6 text-center text-sm text-navy-700 shadow-sm">{t('tr.none')}</p>}
        </div>
      </div>
    </div>
  );
}
