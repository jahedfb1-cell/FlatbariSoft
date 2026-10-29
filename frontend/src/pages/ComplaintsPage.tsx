import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import FlatLabel from '../components/FlatLabel';
import Modal from '../components/Modal';
import * as governanceApi from '../api/governance';
import { listStaff, type Staff } from '../api/staff';
import { listFlats, type Flat } from '../api/flats';
import type { Complaint, ComplaintList, ComplaintPriority, ComplaintStatus } from '../api/governance';

const STATUSES: ComplaintStatus[] = ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'];
const PRIORITIES: ComplaintPriority[] = ['HIGH', 'MEDIUM', 'LOW'];

const statusStyle: Record<ComplaintStatus, string> = {
  OPEN: 'bg-red-50 text-status-due',
  IN_PROGRESS: 'bg-amber-50 text-status-partial',
  RESOLVED: 'bg-green-50 text-status-paid',
  CLOSED: 'bg-brand-100 text-navy-700',
};
const priorityStyle: Record<ComplaintPriority, string> = {
  HIGH: 'bg-red-100 text-status-due',
  MEDIUM: 'bg-amber-100 text-status-partial',
  LOW: 'bg-brand-100 text-navy-700',
};
const input = 'w-full rounded-lg border border-brand-200 px-3 py-2 text-sm';

const when = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '');

/**
 * The office's complaint desk: every complaint with its flat and who lives there, the urgent open ones on top,
 * hand one to a staff member, and finish it with a note on what was done. Finished ones stay on record and can be
 * re-opened.
 */
export default function ComplaintsPage() {
  const { t } = useLanguage();
  const [data, setData] = useState<ComplaintList | null>(null);
  const [status, setStatus] = useState('');
  const [priority, setPriority] = useState('');
  const [q, setQ] = useState('');
  const [staff, setStaff] = useState<Staff[]>([]);
  const [flats, setFlats] = useState<Flat[]>([]);
  const [finishing, setFinishing] = useState<Complaint | null>(null);
  const [adding, setAdding] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    governanceApi.listComplaints({ status, priority, q }).then(setData).catch(() => setError(t('somethingWrong')));
  }, [status, priority, q, t]);

  useEffect(() => {
    const timer = setTimeout(load, q ? 300 : 0); // wait for the typing to settle
    return () => clearTimeout(timer);
  }, [load, q]);

  useEffect(() => {
    listStaff().then((s: Staff[]) => setStaff(s.filter((x) => x.status === 'ACTIVE'))).catch(() => undefined);
    listFlats().then(setFlats).catch(() => undefined);
  }, []);

  async function change(c: Complaint, patch: Partial<{ status: ComplaintStatus; assigned_to: number | null; priority: ComplaintPriority; resolution_note: string | null }>) {
    setError(null);
    try {
      await governanceApi.updateComplaintStatus(c.id, {
        status: patch.status ?? c.status,
        assigned_to: patch.assigned_to !== undefined ? patch.assigned_to : c.assigned_to,
        priority: patch.priority ?? c.priority,
        resolution_note: patch.resolution_note !== undefined ? patch.resolution_note : c.resolution_note,
      });
      setFinishing(null);
      load();
    } catch (e: any) {
      setError(e?.response?.data?.message ?? t('somethingWrong'));
    }
  }

  const counts = data?.counts ?? {};
  const chip = (value: string, label: string, n?: number) => (
    <button
      key={value}
      onClick={() => setStatus(value)}
      className={`rounded-full px-3 py-1.5 text-sm ${status === value ? 'bg-brand-600 text-white' : 'border border-brand-200 bg-white text-navy-700 hover:bg-brand-100'}`}
    >
      {label}
      {n !== undefined ? ` · ${n}` : ''}
    </button>
  );

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-4xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('complaints.title')}</h1>
          </div>
          <div className="flex items-center gap-2">
            <LanguageToggle />
            <button onClick={() => setAdding(true)} className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm text-white hover:bg-brand-700">
              + {t('complaints.add')}
            </button>
          </div>
        </div>

        <div className="mb-3 flex flex-wrap gap-2">
          {chip('', t('complaints.all'))}
          {STATUSES.map((s) => chip(s, t(`complaints.status.${s}` as any), counts[s] ?? 0))}
        </div>
        <div className="mb-4 flex flex-wrap gap-2">
          <input className={`${input} max-w-xs`} placeholder={t('complaints.search')} value={q} onChange={(e) => setQ(e.target.value)} />
          <select className={`${input} w-auto`} value={priority} onChange={(e) => setPriority(e.target.value)}>
            <option value="">{t('complaints.anyPriority')}</option>
            {PRIORITIES.map((p) => (
              <option key={p} value={p}>
                {t(`complaints.pri.${p}` as any)}
              </option>
            ))}
          </select>
        </div>

        {error && <div className="mb-3 rounded-lg bg-red-50 p-3 text-sm text-status-due">{error}</div>}

        <div className="space-y-3">
          {data?.complaints.map((c) => (
            <div key={c.id} className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <FlatLabel flatId={c.flat_id} flatNo={c.flat_no} owner={c.owner} tenant={c.tenant} />
                <div className="flex flex-wrap items-center gap-2 text-xs">
                  <span className={`rounded-full px-2 py-0.5 font-medium ${priorityStyle[c.priority]}`}>{t(`complaints.pri.${c.priority}` as any)}</span>
                  <span className={`rounded-full px-2 py-0.5 font-medium ${statusStyle[c.status]}`}>{t(`complaints.status.${c.status}` as any)}</span>
                  <span className="text-navy-700">{when(c.created_at)}</span>
                </div>
              </div>

              <p className="mt-3 font-medium text-navy-900">{c.category}</p>
              <p className="text-sm text-navy-700">{c.description}</p>

              {c.resolution_note && (
                <p className="mt-2 rounded-lg bg-green-50 p-2 text-sm text-navy-900">
                  ✔ {c.resolution_note}
                  {c.resolved_at && (
                    <span className="ml-2 text-xs text-navy-700">
                      {when(c.resolved_at)}
                      {c.resolved_by ? ` · ${c.resolved_by}` : ''}
                    </span>
                  )}
                </p>
              )}

              <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-brand-100 pt-3">
                <select
                  className={`${input} w-auto`}
                  value={c.assigned_to ?? ''}
                  onChange={(e) => change(c, { assigned_to: e.target.value ? Number(e.target.value) : null, status: c.status === 'OPEN' && e.target.value ? 'IN_PROGRESS' : c.status })}
                >
                  <option value="">{t('complaints.unassigned')}</option>
                  {staff.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name} · {s.designation}
                    </option>
                  ))}
                </select>
                <select className={`${input} w-auto`} value={c.priority} onChange={(e) => change(c, { priority: e.target.value as ComplaintPriority })}>
                  {PRIORITIES.map((p) => (
                    <option key={p} value={p}>
                      {t(`complaints.pri.${p}` as any)}
                    </option>
                  ))}
                </select>
                <div className="ml-auto flex gap-2">
                  {c.status === 'OPEN' && (
                    <button onClick={() => change(c, { status: 'IN_PROGRESS' })} className="rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-navy-700 hover:bg-brand-100">
                      {t('complaints.start')}
                    </button>
                  )}
                  {(c.status === 'OPEN' || c.status === 'IN_PROGRESS') && (
                    <button onClick={() => setFinishing(c)} className="rounded-lg bg-status-paid px-3 py-1.5 text-sm font-medium text-white hover:opacity-90">
                      ✔ {t('complaints.finish')}
                    </button>
                  )}
                  {c.status === 'RESOLVED' && (
                    <button onClick={() => change(c, { status: 'CLOSED' })} className="rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-navy-700 hover:bg-brand-100">
                      {t('complaints.close')}
                    </button>
                  )}
                  {(c.status === 'RESOLVED' || c.status === 'CLOSED') && (
                    <button onClick={() => change(c, { status: 'OPEN' })} className="rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-navy-700 hover:bg-brand-100">
                      {t('complaints.reopen')}
                    </button>
                  )}
                </div>
              </div>
            </div>
          ))}
          {data && data.complaints.length === 0 && <p className="rounded-xl bg-white p-6 text-center text-sm text-navy-700 shadow-sm">{t('complaints.none')}</p>}
        </div>
      </div>

      {finishing && <FinishModal complaint={finishing} onClose={() => setFinishing(null)} onSave={(note) => change(finishing, { status: 'RESOLVED', resolution_note: note })} />}
      {adding && (
        <AddModal
          flats={flats}
          onClose={() => setAdding(false)}
          onSaved={() => {
            setAdding(false);
            load();
          }}
        />
      )}
    </div>
  );
}

function FinishModal({ complaint, onClose, onSave }: { complaint: Complaint; onClose: () => void; onSave: (note: string | null) => void }) {
  const { t } = useLanguage();
  const [note, setNote] = useState(complaint.resolution_note ?? '');
  return (
    <Modal title={t('complaints.finishTitle')} onClose={onClose}>
      <p className="mb-2 text-sm text-navy-700">
        {complaint.flat_no} · {complaint.category}
      </p>
      <textarea className={input} rows={4} placeholder={t('complaints.noteHint')} value={note} onChange={(e) => setNote(e.target.value)} />
      <div className="mt-4 flex justify-end gap-2">
        <button onClick={onClose} className="rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700">
          {t('complaints.cancel')}
        </button>
        <button onClick={() => onSave(note.trim() || null)} className="rounded-lg bg-status-paid px-4 py-2 text-sm font-medium text-white hover:opacity-90">
          ✔ {t('complaints.finish')}
        </button>
      </div>
    </Modal>
  );
}

function AddModal({ flats, onClose, onSaved }: { flats: Flat[]; onClose: () => void; onSaved: () => void }) {
  const { t } = useLanguage();
  const [flatId, setFlatId] = useState('');
  const [category, setCategory] = useState('');
  const [description, setDescription] = useState('');
  const [priority, setPriority] = useState<ComplaintPriority>('MEDIUM');
  const [error, setError] = useState<string | null>(null);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await governanceApi.createComplaint({ flat_id: Number(flatId), category, description, priority });
      onSaved();
    } catch (err: any) {
      const errors = err?.response?.data?.errors;
      setError(errors ? (Object.values(errors) as string[][]).flat().join(' ') : (err?.response?.data?.message ?? t('somethingWrong')));
    }
  }

  return (
    <Modal title={t('complaints.add')} onClose={onClose}>
      <form onSubmit={submit} className="space-y-3">
        <select required className={input} value={flatId} onChange={(e) => setFlatId(e.target.value)}>
          <option value="">{t('complaints.pickFlat')}</option>
          {flats.map((f) => (
            <option key={f.id} value={f.id}>
              {f.flat_no}
            </option>
          ))}
        </select>
        <input required className={input} placeholder={t('common.category')} value={category} onChange={(e) => setCategory(e.target.value)} />
        <textarea required className={input} rows={3} placeholder={t('common.description')} value={description} onChange={(e) => setDescription(e.target.value)} />
        <select className={input} value={priority} onChange={(e) => setPriority(e.target.value as ComplaintPriority)}>
          {PRIORITIES.map((p) => (
            <option key={p} value={p}>
              {t(`complaints.pri.${p}` as any)}
            </option>
          ))}
        </select>
        {error && <p className="text-sm text-status-due">{error}</p>}
        <div className="flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700">
            {t('complaints.cancel')}
          </button>
          <button className="rounded-lg bg-brand-600 px-4 py-2 text-sm text-white hover:bg-brand-700">{t('common.submit')}</button>
        </div>
      </form>
    </Modal>
  );
}
