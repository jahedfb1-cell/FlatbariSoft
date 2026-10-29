import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as ops from '../api/operations';
import type { AuditPage } from '../api/operations';

const input = 'rounded-lg border border-brand-200 px-3 py-2 text-sm';
const eventStyle: Record<string, string> = { created: 'bg-green-50 text-status-paid', updated: 'bg-amber-50 text-status-partial', deleted: 'bg-red-50 text-status-due', backup: 'bg-brand-100 text-navy-700' };

const show = (o: Record<string, unknown> | null) =>
  o
    ? Object.entries(o)
        .map(([k, v]) => `${k}: ${v === null ? '—' : typeof v === 'object' ? JSON.stringify(v) : String(v)}`)
        .join(' · ')
    : '';

/** Who changed what, and when: the trail behind every payment, expense, flat and setting. Read-only. */
export default function AuditLogsPage() {
  const { t } = useLanguage();
  const [f, setF] = useState({ model: '', event: '', from: '', to: '', page: 1 });
  const [data, setData] = useState<AuditPage | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let current = true;
    setError(null);
    ops.getAuditLogs(f).then((d) => current && setData(d)).catch(() => current && setError(t('somethingWrong')));
    return () => {
      current = false;
    };
  }, [f, t]);

  const upd = (k: string, v: string) => setF({ ...f, [k]: v, page: 1 });

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-4xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('auditlog.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        <div className="mb-4 flex flex-wrap gap-2">
          <select className={input} value={f.model} onChange={(e) => upd('model', e.target.value)}>
            <option value="">{t('audit.allRecords')}</option>
            {data?.models.map((m) => (
              <option key={m} value={m}>
                {m}
              </option>
            ))}
          </select>
          <select className={input} value={f.event} onChange={(e) => upd('event', e.target.value)}>
            <option value="">{t('audit.allEvents')}</option>
            <option value="created">{t('audit.created')}</option>
            <option value="updated">{t('audit.updated')}</option>
            <option value="deleted">{t('audit.deleted')}</option>
          </select>
          <input type="date" className={input} value={f.from} onChange={(e) => upd('from', e.target.value)} />
          <input type="date" className={input} value={f.to} onChange={(e) => upd('to', e.target.value)} />
        </div>

        {error && <div className="mb-3 rounded-lg bg-red-50 p-3 text-sm text-status-due">{error}</div>}

        <div className="space-y-2">
          {data?.data.map((r) => (
            <div key={r.id} className="rounded-xl border border-brand-100 bg-white p-4 shadow-sm">
              <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span className="font-medium text-navy-900">
                  {r.model} #{r.record_id}
                </span>
                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${eventStyle[r.event] ?? ''}`}>{r.event}</span>
              </div>
              <p className="mt-1 text-xs text-navy-700">
                {r.when} · {r.user ?? '—'} {r.ip ? `· ${r.ip}` : ''}
              </p>
              {r.event === 'updated' && (
                <div className="mt-2 space-y-1 text-xs">
                  <p className="rounded bg-red-50 p-1.5 text-navy-900">− {show(r.old)}</p>
                  <p className="rounded bg-green-50 p-1.5 text-navy-900">+ {show(r.new)}</p>
                </div>
              )}
              {r.event === 'created' && <p className="mt-2 rounded bg-green-50 p-1.5 text-xs text-navy-900">{show(r.new)}</p>}
              {r.event === 'deleted' && <p className="mt-2 rounded bg-red-50 p-1.5 text-xs text-navy-900">{show(r.old)}</p>}
            </div>
          ))}
          {data && data.data.length === 0 && <p className="rounded-xl bg-white p-6 text-center text-sm text-navy-700 shadow-sm">{t('audit.none')}</p>}
        </div>

        {data && data.last_page > 1 && (
          <div className="mt-4 flex items-center justify-center gap-3 text-sm">
            <button disabled={f.page <= 1} onClick={() => setF({ ...f, page: f.page - 1 })} className="rounded-lg border border-brand-200 bg-white px-3 py-1.5 disabled:opacity-40">
              ‹
            </button>
            <span className="text-navy-700">
              {data.page} / {data.last_page} · {data.total}
            </span>
            <button disabled={f.page >= data.last_page} onClick={() => setF({ ...f, page: f.page + 1 })} className="rounded-lg border border-brand-200 bg-white px-3 py-1.5 disabled:opacity-40">
              ›
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
