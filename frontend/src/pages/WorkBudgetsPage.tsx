import { useCallback, useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import { apiError } from '../api/hall';
import { listFlats } from '../api/flats';
import type { Flat } from '../api/flats';
import { listMeetings } from '../api/governance';
import * as api from '../api/workBudgets';
import type { PreviewRow, ShareRow, SplitMethod, WorkDetail, WorkStatus, WorkSummary } from '../api/workBudgets';

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { maximumFractionDigits: 2 })}`;
const input = 'w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500';
const btn = 'rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50';
const btnGhost = 'rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700 hover:bg-brand-100 disabled:opacity-50';

const STATUS: Record<WorkStatus, { bn: string; en: string; tone: string }> = {
  PLANNED: { bn: 'পরিকল্পনা', en: 'Planned', tone: 'bg-brand-100 text-navy-700' },
  ONGOING: { bn: 'চলমান', en: 'Ongoing', tone: 'bg-status-partial/15 text-status-partial' },
  DONE: { bn: 'সমাপ্ত', en: 'Done', tone: 'bg-status-paid/15 text-status-paid' },
  CANCELLED: { bn: 'বাতিল', en: 'Cancelled', tone: 'bg-status-due/15 text-status-due' },
};

const METHODS: { value: SplitMethod; bn: string; en: string; hintBn: string; hintEn: string }[] = [
  { value: 'EQUAL', bn: 'সবাই সমান', en: 'Equal for all', hintBn: 'বাছাই করা ফ্ল্যাটে সমান ভাগ', hintEn: 'The same share for each chosen flat' },
  { value: 'SERVICE_RATE', bn: 'সার্ভিস চার্জ অনুপাতে', en: 'By service-charge rate', hintBn: 'যার সার্ভিস চার্জ বেশি, তার ভাগও বেশি', hintEn: 'A higher service charge means a bigger share' },
  { value: 'SIZE', bn: 'ফ্ল্যাটের আকার (বর্গফুট) অনুপাতে', en: 'By flat size (sqft)', hintBn: 'বড় ফ্ল্যাট বেশি দেবে', hintEn: 'Bigger flats pay more' },
  { value: 'MANUAL', bn: 'নিজে হাতে বসাব', en: 'I will type each share', hintBn: 'প্রতিটি ফ্ল্যাটের টাকা নিজে লিখবেন', hintEn: 'You enter every flat’s amount yourself' },
];

function Bar({ value, tone = 'bg-brand-600' }: { value: number; tone?: string }) {
  return (
    <div className="h-2 overflow-hidden rounded-full bg-brand-100">
      <div className={`h-full rounded-full ${tone}`} style={{ width: `${Math.max(0, Math.min(100, value))}%` }} />
    </div>
  );
}
const pct = (part: string | number, whole: string | number) => (Number(whole) > 0 ? (Number(part) / Number(whole)) * 100 : 0);

/** One-off works (roof repair, painting ...) with their own estimate. Not every flat pays, nor equally: shares are chosen and then adjustable. */
export default function WorkBudgetsPage() {
  const { user } = useAuth();
  const { t, lang } = useLanguage();
  const bn = lang === 'bn';
  const L = (b: string, e: string) => (bn ? b : e);
  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));

  const [works, setWorks] = useState<Omit<WorkSummary, 'rows'>[] | null>(null);
  const [openId, setOpenId] = useState<number | null>(null);
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    api.listWorks().then(setWorks).catch((e) => setError(apiError(e, t('somethingWrong'))));
  }, [t]);
  useEffect(load, [load]);

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-6xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{L('কাজের বাজেট ও আদায়', 'Work budgets & collections')}</h1>
            <p className="text-sm text-navy-700">{L('নতুন কোনো কাজের জন্য আলাদা বাজেট; কোন ফ্ল্যাট কত দেবে তা নিজে ঠিক করা যায়।', 'A separate budget for a new piece of work; you decide how much each flat pays.')}</p>
          </div>
          <div className="flex items-center gap-2">
            <LanguageToggle />
            {can('budget.manage') && (
              <button className={btn} onClick={() => setCreating(true)}>
                + {L('নতুন কাজের বাজেট', 'New work budget')}
              </button>
            )}
          </div>
        </div>
        {error && <p className="mb-4 text-sm text-status-due">{error}</p>}

        {works && works.length === 0 && (
          <div className="rounded-xl border border-dashed border-brand-300 bg-white p-8 text-center text-sm text-navy-700">
            {L('এখনো কোনো কাজের বাজেট নেই। ওপরের বোতামে চেপে প্রথমটি তৈরি করুন।', 'No work budgets yet. Create the first one with the button above.')}
          </div>
        )}

        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
          {works?.map((w) => (
            <button key={w.id} onClick={() => setOpenId(w.id)} className="rounded-xl border border-brand-200 bg-white p-4 text-left shadow-sm transition hover:shadow-md">
              <div className="mb-2 flex items-start justify-between gap-2">
                <h3 className="font-semibold text-navy-900">{w.title}</h3>
                <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs ${STATUS[w.status].tone}`}>{bn ? STATUS[w.status].bn : STATUS[w.status].en}</span>
              </div>
              <p className="text-xs text-navy-700">
                {L('প্রাক্কলিত', 'Estimate')} <b className="text-navy-900">{money(w.estimated)}</b> · {w.flats} {L('টি ফ্ল্যাট', 'flats')}
              </p>
              <div className="mt-3 space-y-2 text-xs">
                <div>
                  <div className="mb-1 flex justify-between text-navy-700">
                    <span>{L('আদায়', 'Collected')}</span>
                    <span>{money(w.collected)} / {money(w.planned)}</span>
                  </div>
                  <Bar value={pct(w.collected, w.planned)} tone="bg-status-paid" />
                </div>
                <div>
                  <div className="mb-1 flex justify-between text-navy-700">
                    <span>{L('খরচ', 'Spent')}</span>
                    <span className={w.over_budget ? 'font-medium text-status-due' : ''}>{money(w.spent)} / {money(w.estimated)}</span>
                  </div>
                  <Bar value={pct(w.spent, w.estimated)} tone={w.over_budget ? 'bg-status-due' : 'bg-brand-600'} />
                </div>
              </div>
              {(w.spent_more_than_collected || Number(w.gap) !== 0) && (
                <p className="mt-3 rounded-lg bg-amber-50 px-2 py-1 text-xs text-status-partial">
                  {w.spent_more_than_collected ? L('⚠ আদায়ের চেয়ে খরচ বেশি', '⚠ Spent more than collected') : Number(w.gap) > 0 ? `${L('ভাগে ঘাটতি', 'Shares fall short by')} ${money(w.gap)}` : `${L('ভাগ বেশি ধরা হয়েছে', 'Shares exceed estimate by')} ${money(Math.abs(Number(w.gap)))}`}
                </p>
              )}
            </button>
          ))}
        </div>
      </div>

      {creating && (
        <NewWorkWizard
          onClose={() => setCreating(false)}
          onCreated={(w) => {
            setCreating(false);
            load();
            setOpenId(w.id);
          }}
        />
      )}
      {openId !== null && (
        <WorkDetailView
          id={openId}
          canManage={can('budget.manage')}
          onClose={() => {
            setOpenId(null);
            load();
          }}
        />
      )}
    </div>
  );
}

function Overlay({ title, sub, onClose, children }: { title: string; sub?: string; onClose: () => void; children: ReactNode }) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);
  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-navy-900/50 px-3 py-6">
      <div className="mx-auto w-full max-w-4xl rounded-2xl bg-brand-50 p-4 shadow-2xl sm:p-6">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div>
            <h2 className="text-lg font-semibold text-navy-900">{title}</h2>
            {sub && <p className="text-sm text-navy-700">{sub}</p>}
          </div>
          <button onClick={onClose} aria-label="close" className="rounded-lg px-2 py-1 text-navy-700 hover:bg-brand-100">
            ✕
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}

/** Step 1: the work. Step 2: who takes part and how the cost is first split. Step 3: adjust every share, then save. */
function NewWorkWizard({ onClose, onCreated }: { onClose: () => void; onCreated: (w: WorkDetail) => void }) {
  const { lang } = useLanguage();
  const bn = lang === 'bn';
  const L = (b: string, e: string) => (bn ? b : e);

  const [step, setStep] = useState(1);
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [estimate, setEstimate] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [resolutionId, setResolutionId] = useState('');
  const [resolutions, setResolutions] = useState<{ id: number; label: string }[]>([]);
  const [flats, setFlats] = useState<Flat[]>([]);
  const [chosen, setChosen] = useState<Set<number>>(new Set());
  const [method, setMethod] = useState<SplitMethod>('EQUAL');
  const [rows, setRows] = useState<(PreviewRow & { note: string })[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    listFlats({}).then((f: Flat[]) => {
      const sorted = [...f].filter((x) => x.status === 'ACTIVE').sort((a, b) => a.flat_no.localeCompare(b.flat_no, undefined, { numeric: true }));
      setFlats(sorted);
      setChosen(new Set(sorted.map((x) => x.id)));
    });
    listMeetings()
      .then((ms) => setResolutions(ms.flatMap((m) => m.resolutions.map((r) => ({ id: r.id, label: `${r.resolution_no} — ${m.title}` })))))
      .catch(() => undefined);
  }, []);

  const floors = useMemo(() => Array.from(new Set(flats.map((f) => f.floor))).sort((a, b) => String(a).localeCompare(String(b), undefined, { numeric: true })), [flats]);
  const toggle = (id: number) => setChosen((s) => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const setFloor = (floor: string, on: boolean) => setChosen((s) => { const n = new Set(s); flats.filter((f) => f.floor === floor).forEach((f) => (on ? n.add(f.id) : n.delete(f.id))); return n; });

  const total = rows.reduce((c, r) => c + (Number(r.amount) || 0), 0);
  const gap = Number(estimate || 0) - total;

  async function toStep3() {
    setBusy(true);
    setError(null);
    try {
      const p = await api.previewShares({ split_method: method, estimated_cost: estimate, flat_ids: Array.from(chosen) });
      setRows(p.map((r) => ({ ...r, note: '' })));
      setStep(3);
    } catch (err) {
      setError(apiError(err, L('ভাগ হিসাব করা যায়নি।', 'Could not work out the shares.')));
    } finally {
      setBusy(false);
    }
  }

  async function save(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const w = await api.createWork({
        title: title.trim(),
        description: description.trim() || undefined,
        estimated_cost: estimate,
        start_date: startDate || undefined,
        end_date: endDate || undefined,
        split_method: method,
        resolution_id: resolutionId ? Number(resolutionId) : null,
        shares: rows.map((r) => ({ flat_id: r.flat_id, amount: r.amount || 0, note: r.note || null })),
      });
      onCreated(w);
    } catch (err) {
      setError(apiError(err, L('সংরক্ষণ করা যায়নি।', 'Could not save.')));
    } finally {
      setBusy(false);
    }
  }

  const stepDot = (n: number, label: string) => (
    <div className={`flex items-center gap-2 text-xs ${step === n ? 'font-semibold text-brand-700' : 'text-navy-700'}`}>
      <span className={`flex h-6 w-6 items-center justify-center rounded-full ${step >= n ? 'bg-brand-600 text-white' : 'bg-brand-100'}`}>{n}</span>
      {label}
    </div>
  );

  return (
    <Overlay title={L('নতুন কাজের বাজেট', 'New work budget')} onClose={onClose}>
      <div className="mb-4 flex flex-wrap gap-4">
        {stepDot(1, L('কাজের তথ্য', 'The work'))}
        {stepDot(2, L('কারা দেবে ও ভাগের পদ্ধতি', 'Who pays & how to split'))}
        {stepDot(3, L('প্রতিটি ভাগ ঠিক করুন', 'Adjust each share'))}
      </div>

      {step === 1 && (
        <div className="space-y-3 rounded-xl border border-brand-200 bg-white p-4">
          <label className="block text-sm text-navy-700">
            {L('কাজের নাম', 'Name of the work')} *
            <input className={`${input} mt-1`} value={title} onChange={(e) => setTitle(e.target.value)} placeholder={L('যেমন: ছাদ মেরামত', 'e.g. Roof repair')} />
          </label>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <label className="block text-sm text-navy-700">
              {L('প্রাক্কলিত মোট খরচ (৳)', 'Estimated total cost (৳)')} *
              <input className={`${input} mt-1`} type="number" min={1} value={estimate} onChange={(e) => setEstimate(e.target.value)} />
            </label>
            <label className="block text-sm text-navy-700">
              {L('শুরুর তারিখ', 'Start date')}
              <input className={`${input} mt-1`} type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
            </label>
            <label className="block text-sm text-navy-700">
              {L('শেষের তারিখ', 'End date')}
              <input className={`${input} mt-1`} type="date" min={startDate} value={endDate} onChange={(e) => setEndDate(e.target.value)} />
            </label>
          </div>
          <label className="block text-sm text-navy-700">
            {L('মিটিং রেজল্যুশন (সিদ্ধান্ত)', 'Meeting resolution')}
            <select className={`${input} mt-1`} value={resolutionId} onChange={(e) => setResolutionId(e.target.value)}>
              <option value="">{L('— কোনোটি নয় —', '— none —')}</option>
              {resolutions.map((r) => (
                <option key={r.id} value={r.id}>{r.label}</option>
              ))}
            </select>
          </label>
          <label className="block text-sm text-navy-700">
            {L('বিবরণ', 'Description')}
            <textarea rows={2} className={`${input} mt-1`} value={description} onChange={(e) => setDescription(e.target.value)} />
          </label>
          <div className="flex justify-end">
            <button className={btn} disabled={!title.trim() || !(Number(estimate) > 0)} onClick={() => setStep(2)}>
              {L('পরের ধাপ', 'Next')} →
            </button>
          </div>
        </div>
      )}

      {step === 2 && (
        <div className="space-y-4">
          <div className="rounded-xl border border-brand-200 bg-white p-4">
            <p className="mb-2 text-sm font-medium text-navy-900">{L('ভাগের পদ্ধতি বাছুন', 'Choose how to split')}</p>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
              {METHODS.map((m) => (
                <button key={m.value} type="button" onClick={() => setMethod(m.value)} className={`rounded-xl border-2 p-3 text-left text-sm transition ${method === m.value ? 'border-brand-600 bg-brand-50' : 'border-brand-200 hover:bg-brand-50'}`}>
                  <span className="block font-medium text-navy-900">{bn ? m.bn : m.en}</span>
                  <span className="block text-xs text-navy-700">{bn ? m.hintBn : m.hintEn}</span>
                </button>
              ))}
            </div>
          </div>

          <div className="rounded-xl border border-brand-200 bg-white p-4">
            <div className="mb-2 flex flex-wrap items-center gap-2">
              <p className="text-sm font-medium text-navy-900">
                {L('কোন কোন ফ্ল্যাট দেবে?', 'Which flats pay?')} <span className="font-normal text-navy-700">({chosen.size}/{flats.length})</span>
              </p>
              <button type="button" className={btnGhost} onClick={() => setChosen(new Set(flats.map((f) => f.id)))}>{L('সব', 'All')}</button>
              <button type="button" className={btnGhost} onClick={() => setChosen(new Set())}>{L('কেউ না', 'None')}</button>
            </div>
            <div className="mb-2 flex flex-wrap gap-1.5">
              {floors.map((fl) => {
                const ids = flats.filter((f) => f.floor === fl).map((f) => f.id);
                const all = ids.every((i) => chosen.has(i));
                return (
                  <button key={fl} type="button" onClick={() => setFloor(fl, !all)} className={`rounded-full border px-2.5 py-1 text-xs ${all ? 'border-brand-600 bg-brand-600 text-white' : 'border-brand-200 text-navy-700 hover:bg-brand-100'}`}>
                    {L('তলা', 'Floor')} {fl}
                  </button>
                );
              })}
            </div>
            <div className="grid grid-cols-3 gap-1.5 sm:grid-cols-6 lg:grid-cols-8">
              {flats.map((f) => (
                <label key={f.id} className={`flex cursor-pointer items-center justify-center rounded-lg border px-2 py-1.5 text-sm ${chosen.has(f.id) ? 'border-brand-600 bg-brand-50 font-medium text-brand-700' : 'border-brand-200 text-navy-700'}`}>
                  <input type="checkbox" className="sr-only" checked={chosen.has(f.id)} onChange={() => toggle(f.id)} />
                  {f.flat_no}
                </label>
              ))}
            </div>
          </div>
          {error && <p className="rounded-lg bg-status-due/10 p-2 text-sm text-status-due">{error}</p>}
          <div className="flex justify-between">
            <button className={btnGhost} onClick={() => setStep(1)}>← {L('পিছনে', 'Back')}</button>
            <button className={btn} disabled={busy || chosen.size === 0} onClick={toStep3}>
              {busy ? '…' : L('ভাগ দেখুন', 'Work out shares')} →
            </button>
          </div>
        </div>
      )}

      {step === 3 && (
        <form onSubmit={save} className="space-y-3">
          <div className="grid grid-cols-3 gap-3 text-center">
            <div className="rounded-xl border border-brand-200 bg-white p-3">
              <p className="text-xs text-navy-700">{L('প্রাক্কলিত খরচ', 'Estimate')}</p>
              <p className="font-semibold text-navy-900">{money(estimate)}</p>
            </div>
            <div className="rounded-xl border border-brand-200 bg-white p-3">
              <p className="text-xs text-navy-700">{L('ভাগের মোট', 'Total of shares')}</p>
              <p className="font-semibold text-navy-900">{money(total)}</p>
            </div>
            <div className={`rounded-xl border p-3 ${Math.abs(gap) < 0.005 ? 'border-status-paid/40 bg-status-paid/10' : 'border-status-partial/40 bg-amber-50'}`}>
              <p className="text-xs text-navy-700">{gap > 0.005 ? L('এখনো ঘাটতি', 'Still short') : gap < -0.005 ? L('বেশি ধরা হয়েছে', 'Over-asked') : L('মিলেছে', 'Matches')}</p>
              <p className="font-semibold text-navy-900">{Math.abs(gap) < 0.005 ? '✓' : money(Math.abs(gap))}</p>
            </div>
          </div>
          <p className="text-xs text-navy-700">{L('যে কোনো ফ্ল্যাটের টাকা বদলান, ০ দিলে মওকুফ (কারণ লিখুন), বাদ দিতে সারির শেষের ✕ চাপুন। সবাই পুরো টাকা না-ও দিতে পারে — তাই ভাগের মোট প্রাক্কলিত খরচের সমান না হলেও সংরক্ষণ করা যায়।', 'Change any flat’s amount, enter 0 to waive (add a reason), or press ✕ to drop a flat. Shares need not add up to the estimate.')}</p>
          <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white">
            <table className="w-full text-left text-sm">
              <thead className="bg-brand-100 text-navy-900">
                <tr>
                  <th className="px-3 py-2">{L('ফ্ল্যাট', 'Flat')}</th>
                  <th className="px-3 py-2">{L('মালিক', 'Owner')}</th>
                  <th className="px-3 py-2 text-right">{L('ভাগ (৳)', 'Share (৳)')}</th>
                  <th className="px-3 py-2">{L('নোট / কারণ', 'Note / reason')}</th>
                  <th className="px-3 py-2" />
                </tr>
              </thead>
              <tbody>
                {rows.map((r, i) => (
                  <tr key={r.flat_id} className="border-t border-brand-100">
                    <td className="px-3 py-1.5 font-medium">{r.flat_no}</td>
                    <td className="px-3 py-1.5 text-navy-700">{r.owner ?? '—'}</td>
                    <td className="px-3 py-1.5">
                      <input type="number" min={0} step="0.01" className={`${input} w-32 text-right`} value={r.amount} onChange={(e) => setRows(rows.map((x, j) => (j === i ? { ...x, amount: e.target.value } : x)))} />
                    </td>
                    <td className="px-3 py-1.5">
                      <input className={input} maxLength={255} value={r.note} placeholder={Number(r.amount) === 0 ? L('মওকুফের কারণ', 'Reason for waiver') : ''} onChange={(e) => setRows(rows.map((x, j) => (j === i ? { ...x, note: e.target.value } : x)))} />
                    </td>
                    <td className="px-3 py-1.5 text-right">
                      <button type="button" className="text-status-due hover:underline" onClick={() => setRows(rows.filter((_, j) => j !== i))} aria-label="remove">✕</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {error && <p className="rounded-lg bg-status-due/10 p-2 text-sm text-status-due">{error}</p>}
          <div className="flex justify-between">
            <button type="button" className={btnGhost} onClick={() => setStep(2)}>← {L('পিছনে', 'Back')}</button>
            <button className={btn} disabled={busy || rows.length === 0}>{busy ? '…' : L('কাজের বাজেট তৈরি করুন', 'Create work budget')}</button>
          </div>
        </form>
      )}
    </Overlay>
  );
}

/** One work: money collected against money spent, every flat's share (editable) and the expenses linked to it. */
function WorkDetailView({ id, canManage, onClose }: { id: number; canManage: boolean; onClose: () => void }) {
  const { lang } = useLanguage();
  const bn = lang === 'bn';
  const L = (b: string, e: string) => (bn ? b : e);
  const [w, setW] = useState<WorkDetail | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState<Record<number, { amount: string; note: string }>>({});
  const [estimate, setEstimate] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    api.getWork(id).then(setW).catch((e) => setError(apiError(e, L('লোড করা যায়নি।', 'Could not load.'))));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);
  useEffect(load, [load]);

  const startEdit = () => {
    if (!w) return;
    setDraft(Object.fromEntries(w.rows.map((r) => [r.flat_id, { amount: String(Number(r.share)), note: '' }])));
    setEditing(true);
  };
  async function saveShares() {
    if (!w) return;
    setBusy(true);
    setError(null);
    try {
      setW(await api.saveShares(w.id, w.rows.map((r) => ({ flat_id: r.flat_id, amount: draft[r.flat_id]?.amount || 0, note: draft[r.flat_id]?.note || null }))));
      setEditing(false);
    } catch (err) {
      setError(apiError(err, L('সংরক্ষণ করা যায়নি।', 'Could not save.')));
    } finally {
      setBusy(false);
    }
  }
  async function patch(d: Parameters<typeof api.updateWork>[1]) {
    if (!w) return;
    setError(null);
    try {
      setW(await api.updateWork(w.id, d));
      setEstimate(null);
    } catch (err) {
      setError(apiError(err, L('সংরক্ষণ করা যায়নি।', 'Could not save.')));
    }
  }

  const card = (label: string, value: string, tone = 'text-navy-900', sub?: string) => (
    <div className="rounded-xl border border-brand-200 bg-white p-3">
      <p className="text-xs text-navy-700">{label}</p>
      <p className={`text-lg font-semibold ${tone}`}>{value}</p>
      {sub && <p className="text-[11px] text-navy-700">{sub}</p>}
    </div>
  );

  if (!w) return <Overlay title="…" onClose={onClose}><p className="py-8 text-center text-sm text-navy-700">{error ?? '…'}</p></Overlay>;
  const rowStatus = (r: ShareRow) => (r.status === 'CLEARED' ? ['✓', 'text-status-paid'] : r.status === 'EXCESS' ? [L('বেশি', 'Excess'), 'text-status-partial'] : Number(r.paid) > 0 ? [L('আংশিক', 'Partial'), 'text-status-partial'] : [L('বাকি', 'Due'), 'text-status-due']);

  return (
    <Overlay title={w.title} sub={`${bn ? STATUS[w.status].bn : STATUS[w.status].en}${w.start_date ? ' · ' + w.start_date : ''}${w.end_date ? ' → ' + w.end_date : ''}`} onClose={onClose}>
      {w.description && <p className="mb-3 text-sm text-navy-700">{w.description}</p>}
      {error && <p className="mb-3 rounded-lg bg-status-due/10 p-2 text-sm text-status-due">{error}</p>}

      <div className="mb-3 grid grid-cols-2 gap-3 md:grid-cols-4">
        {card(L('প্রাক্কলিত খরচ', 'Estimate'), money(w.estimated))}
        {card(L('ফ্ল্যাটদের কাছে চাওয়া (ভাগের মোট)', 'Asked of flats'), money(w.planned), Number(w.gap) === 0 ? 'text-navy-900' : 'text-status-partial', Number(w.gap) > 0 ? `${L('ঘাটতি', 'short')} ${money(w.gap)}` : Number(w.gap) < 0 ? `${L('বেশি', 'over')} ${money(Math.abs(Number(w.gap)))}` : undefined)}
        {card(L('আদায় হয়েছে', 'Collected'), money(w.collected), 'text-status-paid', `${L('বাকি', 'still due')} ${money(w.still_to_collect)}`)}
        {card(L('খরচ হয়েছে', 'Spent'), money(w.spent), w.over_budget ? 'text-status-due' : 'text-navy-900', w.pending_spend !== '0.0000' ? `+ ${money(w.pending_spend)} ${L('অনুমোদনের অপেক্ষায়', 'awaiting approval')}` : undefined)}
      </div>
      <div className="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-brand-200 bg-white p-4 sm:grid-cols-2">
        <div>
          <p className="mb-1 flex justify-between text-xs text-navy-700"><span>{L('আদায় / ভাগের মোট', 'Collected / asked')}</span><span>{Math.round(pct(w.collected, w.planned))}%</span></p>
          <Bar value={pct(w.collected, w.planned)} tone="bg-status-paid" />
        </div>
        <div>
          <p className="mb-1 flex justify-between text-xs text-navy-700"><span>{L('খরচ / প্রাক্কলিত', 'Spent / estimate')}</span><span>{w.percent_spent ?? 0}%</span></p>
          <Bar value={pct(w.spent, w.estimated)} tone={w.over_budget ? 'bg-status-due' : 'bg-brand-600'} />
        </div>
        <p className="text-sm text-navy-900 sm:col-span-2">
          {L('এই কাজের জন্য হাতে আছে', 'In hand for this work')}: <b className={Number(w.in_hand) < 0 ? 'text-status-due' : ''}>{money(w.in_hand)}</b> · {L('বাজেটের বাকি', 'budget left')}: <b>{money(w.budget_left)}</b>
        </p>
        {w.spent_more_than_collected && <p className="rounded-lg bg-amber-50 p-2 text-xs text-status-partial sm:col-span-2">⚠ {L('এ পর্যন্ত আদায়ের চেয়ে বেশি খরচ হয়েছে — বাকি ফ্ল্যাটদের কাছ থেকে আদায় বাড়ান।', 'More has been spent than collected - collect the rest from the flats.')}</p>}
      </div>

      <div className="mb-4 flex flex-wrap gap-2">
        <Link to={`/contributions?book=${w.book_code}`} className={btn}>💰 {L('আদায় নিন / খাতা', 'Collect / ledger')}</Link>
        <Link to={`/expenses?work=${w.id}`} className={btnGhost}>💸 {L('খরচ যোগ করুন', 'Add an expense')}</Link>
        {canManage && !editing && <button className={btnGhost} onClick={startEdit}>✏️ {L('ফ্ল্যাটের ভাগ বদলান', 'Change shares')}</button>}
        {canManage && estimate === null && <button className={btnGhost} onClick={() => setEstimate(String(Number(w.estimated)))}>{L('প্রাক্কলিত খরচ বদলান', 'Change estimate')}</button>}
        {canManage && (
          <select className={`${input} w-auto`} value={w.status} onChange={(e) => patch({ status: e.target.value as WorkStatus })} aria-label="status">
            {(Object.keys(STATUS) as WorkStatus[]).map((s) => (
              <option key={s} value={s}>{bn ? STATUS[s].bn : STATUS[s].en}</option>
            ))}
          </select>
        )}
      </div>
      {estimate !== null && (
        <div className="mb-4 flex items-end gap-2 rounded-xl border border-brand-200 bg-white p-3">
          <label className="text-sm text-navy-700">
            {L('নতুন প্রাক্কলিত খরচ (৳)', 'New estimate (৳)')}
            <input className={`${input} mt-1 w-40`} type="number" min={1} value={estimate} onChange={(e) => setEstimate(e.target.value)} />
          </label>
          <button className={btn} onClick={() => patch({ estimated_cost: estimate })}>{L('সংরক্ষণ', 'Save')}</button>
          <button className={btnGhost} onClick={() => setEstimate(null)}>{L('বাতিল', 'Cancel')}</button>
        </div>
      )}

      <div className="mb-4 overflow-x-auto rounded-xl border border-brand-200 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="bg-brand-100 text-navy-900">
            <tr>
              <th className="px-3 py-2">{L('ফ্ল্যাট', 'Flat')}</th>
              <th className="px-3 py-2">{L('মালিক', 'Owner')}</th>
              <th className="px-3 py-2 text-right">{L('ভাগ', 'Share')}</th>
              <th className="px-3 py-2 text-right">{L('দিয়েছে', 'Paid')}</th>
              <th className="px-3 py-2 text-right">{L('বাকি', 'Due')}</th>
              <th className="px-3 py-2">{L('অবস্থা', 'Status')}</th>
            </tr>
          </thead>
          <tbody>
            {w.rows.map((r) => {
              const [text, tone] = rowStatus(r);
              return (
                <tr key={r.flat_id} className="border-t border-brand-100">
                  <td className="px-3 py-1.5 font-medium">
                    <Link to={`/contributions?book=${w.book_code}&flat=${r.flat_id}`} className="hover:underline">{r.flat_no}</Link>
                  </td>
                  <td className="px-3 py-1.5 text-navy-700">{r.owner ?? '—'}</td>
                  <td className="px-3 py-1.5 text-right">
                    {editing ? (
                      <input type="number" min={0} step="0.01" className={`${input} w-28 text-right`} value={draft[r.flat_id]?.amount ?? ''} onChange={(e) => setDraft({ ...draft, [r.flat_id]: { ...(draft[r.flat_id] ?? { note: '' }), amount: e.target.value } })} />
                    ) : (
                      money(r.share)
                    )}
                  </td>
                  <td className="px-3 py-1.5 text-right">{money(r.paid)}</td>
                  <td className="px-3 py-1.5 text-right font-medium">{r.due === null ? '—' : money(Math.max(0, Number(r.due)))}</td>
                  <td className={`px-3 py-1.5 text-xs font-medium ${tone}`}>{text}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      {editing && (
        <div className="mb-4 flex justify-end gap-2">
          <button className={btnGhost} onClick={() => setEditing(false)}>{L('বাতিল', 'Cancel')}</button>
          <button className={btn} disabled={busy} onClick={saveShares}>{busy ? '…' : L('ভাগ সংরক্ষণ', 'Save shares')}</button>
        </div>
      )}

      <h3 className="mb-2 text-sm font-semibold text-navy-900">{L('এই কাজের খরচ', 'Expenses on this work')}</h3>
      <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white">
        <table className="w-full text-left text-sm">
          <tbody>
            {w.expenses.length === 0 && (
              <tr><td className="px-3 py-4 text-center text-navy-700">{L('এখনো কোনো খরচ নেই।', 'No expenses yet.')}</td></tr>
            )}
            {w.expenses.map((e) => (
              <tr key={e.id} className="border-t border-brand-100 first:border-t-0">
                <td className="px-3 py-1.5">{e.date}</td>
                <td className="px-3 py-1.5">{e.payee}</td>
                <td className="px-3 py-1.5 text-right font-medium">{money(e.amount)}</td>
                <td className="px-3 py-1.5 text-xs">
                  <span className={e.status === 'APPROVED' ? 'text-status-paid' : 'text-status-partial'}>{e.status === 'APPROVED' ? (e.voucher_no ?? L('অনুমোদিত', 'Approved')) : L('অপেক্ষমাণ', 'Pending')}</span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Overlay>
  );
}
