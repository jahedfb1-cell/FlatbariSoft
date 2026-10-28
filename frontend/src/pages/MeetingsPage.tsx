import { useEffect, useRef, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import { blobErrorMessage } from '../utils/download';
import * as governanceApi from '../api/governance';
import type { Meeting, Resolution } from '../api/governance';

const ACCEPT = 'application/pdf,image/jpeg,image/png,image/webp';
const input = 'w-full rounded-lg border border-brand-200 px-3 py-2 text-sm';

const apiError = (e: any, fallback: string) =>
  e?.response?.data?.errors ? (Object.values(e.response.data.errors) as string[][]).flat().join(' ') : (e?.response?.data?.message ?? fallback);

/**
 * Meetings and the resolutions taken in them. A resolution is typed text, a PDF or a photo of the signed
 * minutes - or text together with a file. A file can also be added (or replaced) later on a resolution
 * that was recorded without one.
 */
export default function MeetingsPage() {
  const { t } = useLanguage();
  const [meetings, setMeetings] = useState<Meeting[]>([]);
  const [title, setTitle] = useState('');
  const [date, setDate] = useState('');
  const [error, setError] = useState<string | null>(null);

  function load() {
    governanceApi.listMeetings().then(setMeetings);
  }

  useEffect(load, []);

  async function handleCreate(e: FormEvent) {
    e.preventDefault();
    await governanceApi.createMeeting({ title, meeting_date: date });
    setTitle('');
    setDate('');
    load();
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-3xl">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('meetings.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        <form onSubmit={handleCreate} className="mb-6 flex flex-wrap gap-3 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
          <input required placeholder="Title" value={title} onChange={(e) => setTitle(e.target.value)} className="flex-1 rounded-lg border border-brand-200 px-3 py-2" />
          <input required type="date" value={date} onChange={(e) => setDate(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <button type="submit" className="rounded-lg bg-brand-600 px-3 py-2 text-white hover:bg-brand-700">
            {t('meetings.addMeeting')}
          </button>
        </form>

        {error && <div className="mb-3 rounded-lg bg-red-50 p-3 text-sm text-status-due">{error}</div>}

        <div className="space-y-4">
          {meetings.map((m) => (
            <MeetingCard key={m.id} meeting={m} onChanged={load} onError={setError} />
          ))}
        </div>
      </div>
    </div>
  );
}

function MeetingCard({ meeting: m, onChanged, onError }: { meeting: Meeting; onChanged: () => void; onError: (msg: string | null) => void }) {
  const { t } = useLanguage();
  const [text, setText] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [busy, setBusy] = useState(false);
  const fileInput = useRef<HTMLInputElement>(null);

  async function add() {
    if (!text.trim() && !file) {
      onError(t('meetings.needTextOrFile'));
      return;
    }
    onError(null);
    setBusy(true);
    try {
      await governanceApi.addResolution(m.id, { description: text.trim() || undefined, attachment: file });
      setText('');
      setFile(null);
      if (fileInput.current) fileInput.current.value = '';
      onChanged();
    } catch (e) {
      onError(apiError(e, t('somethingWrong')));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
      <p className="font-medium text-navy-900">
        {m.title} <span className="text-sm text-navy-700">({m.meeting_date})</span>
      </p>

      {m.resolutions.map((r) => (
        <ResolutionRow key={r.id} resolution={r} onChanged={onChanged} onError={onError} />
      ))}

      <div className="mt-4 rounded-lg bg-brand-50 p-3">
        <textarea rows={2} className={input} placeholder={t('meetings.resolutionHint')} value={text} onChange={(e) => setText(e.target.value)} />
        <div className="mt-2 flex flex-wrap items-center gap-2">
          <label className="cursor-pointer rounded-lg border border-brand-200 bg-white px-3 py-1.5 text-sm text-navy-700 hover:bg-brand-100">
            📎 {file ? file.name : t('meetings.attachFile')}
            <input ref={fileInput} type="file" accept={ACCEPT} className="hidden" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
          </label>
          {file && (
            <button
              type="button"
              onClick={() => {
                setFile(null);
                if (fileInput.current) fileInput.current.value = '';
              }}
              className="text-xs text-navy-700 hover:underline"
            >
              ✕
            </button>
          )}
          <span className="text-xs text-navy-700">{t('meetings.fileRules')}</span>
          <button onClick={add} disabled={busy} className="ml-auto rounded-lg bg-brand-600 px-3 py-1.5 text-sm text-white hover:bg-brand-700 disabled:opacity-50">
            {t('meetings.addResolution')}
          </button>
        </div>
      </div>
    </div>
  );
}

function ResolutionRow({ resolution: r, onChanged, onError }: { resolution: Resolution; onChanged: () => void; onError: (msg: string | null) => void }) {
  const { t } = useLanguage();
  const [busy, setBusy] = useState(false);

  async function open() {
    onError(null);
    try {
      await governanceApi.openResolutionFile(r);
    } catch (e) {
      onError(await blobErrorMessage(e, t('somethingWrong')));
    }
  }

  async function replace(file: File | undefined) {
    if (!file) return;
    onError(null);
    setBusy(true);
    try {
      await governanceApi.attachToResolution(r.id, file);
      onChanged();
    } catch (e) {
      onError(apiError(e, t('somethingWrong')));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mt-2 border-t border-brand-100 pt-2 text-sm text-navy-900">
      <p>
        <span className="font-medium">{r.resolution_no}</span>
        {r.description ? `: ${r.description}` : ''}
      </p>
      <div className="mt-1 flex flex-wrap items-center gap-3 text-xs">
        {r.has_attachment && (
          <button onClick={open} className="rounded-full bg-brand-100 px-3 py-1 text-brand-800 hover:bg-brand-200">
            {r.attachment_mime === 'application/pdf' ? '📄' : '🖼️'} {r.attachment_name ?? t('meetings.viewFile')}
          </button>
        )}
        <label className={`cursor-pointer text-brand-700 hover:underline ${busy ? 'opacity-50' : ''}`}>
          {r.has_attachment ? t('meetings.replaceFile') : `📎 ${t('meetings.attachFile')}`}
          <input type="file" accept={ACCEPT} className="hidden" disabled={busy} onChange={(e) => replace(e.target.files?.[0])} />
        </label>
      </div>
    </div>
  );
}
