import { useEffect, useState } from 'react';
import { useLanguage } from '../context/LanguageContext';
import client from '../api/client';

interface VacateTenant {
  tenant_id: number;
  flat_id: number;
  flat_no: string | null;
  name: string | null;
  mobile: string | null;
  start_date: string | null;
  has_pending: boolean;
}
interface VacateRequest {
  id: number;
  flat_no: string | null;
  tenant_name: string | null;
  tenant_mobile: string | null;
  end_date: string;
  note: string | null;
  status: 'PENDING' | 'APPROVED' | 'REJECTED' | 'CANCELLED';
  reject_reason: string | null;
  requested_by: string | null;
  requested_at: string | null;
  decided_by: string | null;
  decided_at: string | null;
}
interface Listing {
  can_decide: boolean;
  tenants: VacateTenant[];
  requests: VacateRequest[];
}

const statusStyle: Record<VacateRequest['status'], string> = {
  PENDING: 'bg-amber-50 text-status-partial',
  APPROVED: 'bg-green-50 text-status-paid',
  REJECTED: 'bg-red-50 text-status-due',
  CANCELLED: 'bg-brand-100 text-navy-700',
};
const input = 'w-full rounded-lg border border-brand-200 px-3 py-2 text-base';
const card = 'rounded-2xl border border-brand-100 bg-white p-4 shadow-sm';
const today = () => new Date(Date.now() + 6 * 3600 * 1000).toISOString().slice(0, 10); // Dhaka date

const get = () => client.get<Listing>('/tenant-vacate-requests').then((r) => r.data);

/**
 * "My tenant is leaving": the owner (or manager) picks the tenant who is going and the date, and sends it to the
 * office; an admin accepts, which ends the tenancy and empties the flat. The admin sees every request here.
 */
export default function TenantVacatePanel({ onDecided }: { onDecided?: () => void }) {
  const { t } = useLanguage();
  const [data, setData] = useState<Listing | null>(null);
  const [open, setOpen] = useState<number | null>(null);
  const [date, setDate] = useState(today());
  const [note, setNote] = useState('');
  const [rejecting, setRejecting] = useState<number | null>(null);
  const [reason, setReason] = useState('');
  const [msg, setMsg] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = () => get().then(setData).catch(() => undefined);
  useEffect(() => {
    load();
  }, []);

  const fail = (e: any) => {
    const errs = e?.response?.data?.errors;
    setError(errs ? (Object.values(errs) as string[][]).flat().join(' ') : (e?.response?.data?.message ?? t('somethingWrong')));
  };

  async function act(fn: () => Promise<unknown>, done?: string) {
    setMsg(null);
    setError(null);
    try {
      await fn();
      if (done) setMsg(done);
      setOpen(null);
      setRejecting(null);
      setReason('');
      setNote('');
      await load();
      onDecided?.();
    } catch (e) {
      fail(e);
    }
  }

  if (!data) return null;
  if (data.tenants.length === 0 && data.requests.length === 0) return null;

  return (
    <div className="space-y-4">
      <h2 className="font-semibold text-navy-900">{t('tv.title')}</h2>
      {msg && <p className="rounded-lg bg-green-50 p-2 text-sm text-status-paid">{msg}</p>}
      {error && <p className="rounded-lg bg-red-50 p-2 text-sm text-status-due">{error}</p>}

      {data.tenants.length > 0 && (
        <div className="space-y-2">
          <p className="text-sm text-navy-700">{t('tv.hint')}</p>
          {data.tenants.map((x) => (
            <div key={x.tenant_id} className={card}>
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="font-medium text-navy-900">
                    {t('tr.flat')} {x.flat_no} · {x.name}
                  </p>
                  <p className="text-xs text-navy-700">
                    {x.mobile} · {t('tr.startDate')} {x.start_date}
                  </p>
                </div>
                {x.has_pending ? (
                  <span className="rounded-full bg-amber-50 px-3 py-1 text-xs text-status-partial">{t('tv.waiting')}</span>
                ) : (
                  <button onClick={() => setOpen(open === x.tenant_id ? null : x.tenant_id)} className="rounded-lg border border-brand-200 px-3 py-2 text-sm text-brand-700 hover:bg-brand-100">
                    {t('tv.leaving')}
                  </button>
                )}
              </div>
              {open === x.tenant_id && (
                <div className="mt-3 space-y-2 border-t border-brand-100 pt-3">
                  <label className="block text-sm font-medium text-navy-900">{t('tv.endDate')} *</label>
                  <input type="date" className={input} value={date} onChange={(e) => setDate(e.target.value)} />
                  <textarea rows={2} className={input} placeholder={t('tv.notePlaceholder')} value={note} onChange={(e) => setNote(e.target.value)} />
                  <button
                    onClick={() => act(() => client.post('/tenant-vacate-requests', { tenant_id: x.tenant_id, end_date: date, note: note || undefined }), t('tv.sent'))}
                    className="w-full rounded-xl bg-brand-600 px-4 py-3 font-medium text-white hover:bg-brand-700 sm:w-auto"
                  >
                    {t('tv.send')}
                  </button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      <div className="space-y-3">
        {data.requests.map((r) => (
          <div key={r.id} className={card}>
            <div className="flex flex-wrap items-start justify-between gap-2">
              <div>
                <p className="font-medium text-navy-900">
                  {t('tr.flat')} {r.flat_no} · {r.tenant_name}
                </p>
                <p className="text-sm text-navy-700">
                  {t('tv.endDate')}: {r.end_date}
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
            {r.status === 'APPROVED' && <p className="mt-2 rounded-lg bg-green-50 p-2 text-sm text-status-paid">{t('tv.approvedNote')}</p>}
            {r.status === 'PENDING' && (
              <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-brand-100 pt-3">
                {data.can_decide ? (
                  <>
                    <button onClick={() => act(() => client.post(`/tenant-vacate-requests/${r.id}/approve`), t('tv.approved'))} className="rounded-lg bg-status-paid px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                      ✔ {t('tr.approve')}
                    </button>
                    {rejecting === r.id ? (
                      <>
                        <input className="min-w-40 flex-1 rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder={t('tr.reasonHint')} value={reason} onChange={(e) => setReason(e.target.value)} />
                        <button disabled={reason.trim().length < 3} onClick={() => act(() => client.post(`/tenant-vacate-requests/${r.id}/reject`, { reason: reason.trim() }))} className="rounded-lg bg-status-due px-3 py-2 text-sm text-white disabled:opacity-50">
                          {t('tr.rejectConfirm')}
                        </button>
                      </>
                    ) : (
                      <button onClick={() => setRejecting(r.id)} className="rounded-lg border border-status-due/40 px-4 py-2 text-sm text-status-due hover:bg-red-50">
                        {t('tr.reject')}
                      </button>
                    )}
                  </>
                ) : (
                  <button onClick={() => act(() => client.post(`/tenant-vacate-requests/${r.id}/cancel`))} className="rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700 hover:bg-brand-100">
                    {t('tr.cancel')}
                  </button>
                )}
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
