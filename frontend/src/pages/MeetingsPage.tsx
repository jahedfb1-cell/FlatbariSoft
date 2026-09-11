import { useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as governanceApi from '../api/governance';
import type { Meeting } from '../api/governance';

export default function MeetingsPage() {
  const { t } = useLanguage();
  const [meetings, setMeetings] = useState<Meeting[]>([]);
  const [title, setTitle] = useState('');
  const [date, setDate] = useState('');
  const [resolutionText, setResolutionText] = useState<Record<number, string>>({});

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

  async function handleAddResolution(meetingId: number) {
    const description = resolutionText[meetingId];
    if (!description) return;
    await governanceApi.addResolution(meetingId, { description });
    setResolutionText((r) => ({ ...r, [meetingId]: '' }));
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

        <div className="space-y-4">
          {meetings.map((m) => (
            <div key={m.id} className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
              <p className="font-medium text-navy-900">
                {m.title} <span className="text-sm text-navy-700">({m.meeting_date})</span>
              </p>
              {m.resolutions.map((r) => (
                <p key={r.id} className="mt-2 border-t border-brand-100 pt-2 text-sm text-navy-900">
                  <span className="font-medium">{r.resolution_no}:</span> {r.description}
                </p>
              ))}
              <div className="mt-3 flex gap-2">
                <input
                  placeholder="New resolution..."
                  value={resolutionText[m.id] ?? ''}
                  onChange={(e) => setResolutionText((t) => ({ ...t, [m.id]: e.target.value }))}
                  className="flex-1 rounded-lg border border-brand-200 px-3 py-1.5 text-sm"
                />
                <button onClick={() => handleAddResolution(m.id)} className="text-sm text-brand-700 hover:underline">
                  {t('meetings.addResolution')}
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
