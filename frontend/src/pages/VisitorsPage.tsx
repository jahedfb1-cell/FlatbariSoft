import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import { listFlats, type Flat } from '../api/flats';
import * as ops from '../api/operations';
import type { Visitor, VisitorPurpose } from '../api/operations';

const input = 'w-full rounded-lg border border-brand-200 px-3 py-2 text-sm';
const PURPOSES: VisitorPurpose[] = ['GUEST', 'DELIVERY', 'SERVICE', 'OTHER'];
const icon: Record<VisitorPurpose, string> = { GUEST: '🙋', DELIVERY: '📦', SERVICE: '🛠️', OTHER: '•' };

/** The gate book. Built for the gatekeeper's phone: one short form to write someone in, one tap to write them out. */
export default function VisitorsPage() {
  const { t } = useLanguage();
  const { user } = useAuth();
  const canWrite = !!user && (user.roles.includes('super_admin') || user.permissions.includes('visitor.manage'));
  const [flats, setFlats] = useState<Flat[]>([]);
  const [data, setData] = useState<{ inside_now: number; visitors: Visitor[] } | null>(null);
  const [onlyInside, setOnlyInside] = useState(true);
  const [q, setQ] = useState('');
  const [form, setForm] = useState({ flat_id: '', visitor_name: '', mobile: '', purpose: 'GUEST' as VisitorPurpose, vehicle_no: '', note: '' });
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    ops.listVisitors({ inside: onlyInside, q }).then(setData).catch(() => setError(t('somethingWrong')));
  }, [onlyInside, q, t]);

  useEffect(() => {
    const timer = setTimeout(load, q ? 300 : 0);
    return () => clearTimeout(timer);
  }, [load, q]);
  useEffect(() => {
    listFlats().then(setFlats).catch(() => undefined);
  }, []);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await ops.addVisitor({ ...form, flat_id: Number(form.flat_id) });
      setForm({ ...form, visitor_name: '', mobile: '', vehicle_no: '', note: '' });
      load();
    } catch (err: any) {
      const errs = err?.response?.data?.errors;
      setError(errs ? (Object.values(errs) as string[][]).flat().join(' ') : t('somethingWrong'));
    }
  }

  async function out(v: Visitor) {
    await ops.checkoutVisitor(v.id);
    load();
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-3xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('visitors.title')}</h1>
            {data && (
              <p className="text-sm text-navy-700">
                {t('visitors.insideNow')}: <b>{data.inside_now}</b>
              </p>
            )}
          </div>
          <LanguageToggle />
        </div>

        {canWrite && (
          <form onSubmit={submit} className="mb-5 grid gap-3 rounded-2xl border border-brand-100 bg-white p-4 shadow-sm sm:grid-cols-2">
            <select required className={input} value={form.flat_id} onChange={(e) => setForm({ ...form, flat_id: e.target.value })}>
              <option value="">{t('visitors.pickFlat')}</option>
              {flats.map((f) => (
                <option key={f.id} value={f.id}>
                  {f.flat_no}
                </option>
              ))}
            </select>
            <select className={input} value={form.purpose} onChange={(e) => setForm({ ...form, purpose: e.target.value as VisitorPurpose })}>
              {PURPOSES.map((p) => (
                <option key={p} value={p}>
                  {icon[p]} {t(`visitors.p.${p}` as any)}
                </option>
              ))}
            </select>
            <input required className={input} placeholder={t('visitors.name')} value={form.visitor_name} onChange={(e) => setForm({ ...form, visitor_name: e.target.value })} />
            <input className={input} placeholder={t('visitors.mobile')} inputMode="tel" value={form.mobile} onChange={(e) => setForm({ ...form, mobile: e.target.value })} />
            <input className={input} placeholder={t('visitors.vehicle')} value={form.vehicle_no} onChange={(e) => setForm({ ...form, vehicle_no: e.target.value })} />
            <input className={input} placeholder={t('visitors.note')} value={form.note} onChange={(e) => setForm({ ...form, note: e.target.value })} />
            {error && <p className="text-sm text-status-due sm:col-span-2">{error}</p>}
            <button className="rounded-xl bg-brand-600 px-4 py-3 font-medium text-white hover:bg-brand-700 sm:col-span-2">{t('visitors.writeIn')}</button>
          </form>
        )}

        <div className="mb-3 flex flex-wrap items-center gap-3">
          <input className={`${input} max-w-xs`} placeholder={t('visitors.search')} value={q} onChange={(e) => setQ(e.target.value)} />
          <label className="flex items-center gap-2 text-sm text-navy-700">
            <input type="checkbox" checked={onlyInside} onChange={(e) => setOnlyInside(e.target.checked)} />
            {t('visitors.onlyInside')}
          </label>
        </div>

        <div className="space-y-2">
          {data?.visitors.map((v) => (
            <div key={v.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-100 bg-white p-4 shadow-sm">
              <div>
                <p className="font-medium text-navy-900">
                  {icon[v.purpose]} {v.visitor_name} <span className="text-sm text-brand-700">→ {v.flat_no}</span>
                </p>
                <p className="text-xs text-navy-700">
                  {v.in_at}
                  {v.out_at ? ` → ${v.out_at}` : ''}
                  {v.mobile ? ` · ${v.mobile}` : ''}
                  {v.vehicle_no ? ` · ${v.vehicle_no}` : ''}
                  {v.note ? ` · ${v.note}` : ''}
                </p>
              </div>
              {v.inside ? (
                canWrite ? (
                  <button onClick={() => out(v)} className="rounded-lg bg-status-partial px-3 py-2 text-sm font-medium text-white hover:opacity-90">
                    {t('visitors.writeOut')}
                  </button>
                ) : (
                  <span className="rounded-full bg-amber-50 px-3 py-1 text-xs text-status-partial">{t('visitors.inside')}</span>
                )
              ) : (
                <span className="rounded-full bg-brand-100 px-3 py-1 text-xs text-navy-700">{t('visitors.left')}</span>
              )}
            </div>
          ))}
          {data && data.visitors.length === 0 && <p className="rounded-xl bg-white p-6 text-center text-sm text-navy-700 shadow-sm">{t('visitors.none')}</p>}
        </div>
      </div>
    </div>
  );
}
