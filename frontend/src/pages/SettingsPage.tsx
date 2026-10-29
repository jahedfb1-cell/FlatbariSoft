import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import { blobErrorMessage } from '../utils/download';
import * as ops from '../api/operations';
import type { Settings, SmsLogRow } from '../api/operations';

const input = 'w-full rounded-lg border border-brand-200 px-3 py-2 text-sm';
const card = 'rounded-2xl border border-brand-100 bg-white p-5 shadow-sm';

/** The office's own switches: due day, late fee, reminders, SMS - plus the SMS log and a data backup. */
export default function SettingsPage() {
  const { t } = useLanguage();
  const { user } = useAuth();
  const [s, setS] = useState<Settings | null>(null);
  const [gateway, setGateway] = useState<'CONFIGURED' | 'NOT_CONFIGURED'>('NOT_CONFIGURED');
  const [logs, setLogs] = useState<SmsLogRow[]>([]);
  const [msg, setMsg] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const canBackup = !!user && (user.roles.includes('super_admin') || user.permissions.includes('backup.manage'));

  useEffect(() => {
    ops.getSettings().then((r) => {
      setS(r.settings);
      setGateway(r.sms_gateway);
    });
    ops.getSmsLogs().then(setLogs).catch(() => undefined);
  }, []);

  if (!s) return <div className="p-8 text-navy-700">{t('loading')}</div>;
  const set = (k: keyof Settings, v: string) => setS({ ...s, [k]: v });
  const flag = (k: keyof Settings) => s[k] === '1';
  const toggle = (k: keyof Settings) => set(k, flag(k) ? '0' : '1');

  async function save() {
    setMsg(null);
    setError(null);
    try {
      const next = await ops.saveSettings({
        ...s!,
        auto_generate_bills: flag('auto_generate_bills'),
        sms_enabled: flag('sms_enabled'),
        reminders_enabled: flag('reminders_enabled'),
      });
      setS(next);
      setMsg(t('settings.saved'));
    } catch (e: any) {
      const errs = e?.response?.data?.errors;
      setError(errs ? (Object.values(errs) as string[][]).flat().join(' ') : (e?.response?.data?.message ?? t('somethingWrong')));
    }
  }

  async function backup() {
    setError(null);
    try {
      await ops.downloadBackup();
    } catch (e) {
      setError(await blobErrorMessage(e, t('somethingWrong')));
    }
  }

  const Switch = ({ k, label, hint }: { k: keyof Settings; label: string; hint?: string }) => (
    <label className="flex cursor-pointer items-start gap-3 py-2">
      <input type="checkbox" className="mt-1 h-5 w-5" checked={flag(k)} onChange={() => toggle(k)} />
      <span>
        <span className="block text-sm font-medium text-navy-900">{label}</span>
        {hint && <span className="block text-xs text-navy-700">{hint}</span>}
      </span>
    </label>
  );

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-3xl space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('settings.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        {msg && <div className="rounded-lg bg-green-50 p-3 text-sm text-status-paid">{msg}</div>}
        {error && <div className="rounded-lg bg-red-50 p-3 text-sm text-status-due">{error}</div>}

        <div className={card}>
          <h2 className="mb-3 font-semibold text-navy-900">{t('settings.billing')}</h2>
          <label className="block text-sm text-navy-700">
            {t('settings.dueDay')}
            <input type="number" min={1} max={28} className={`${input} mt-1 max-w-32`} value={s.bill_due_day} onChange={(e) => set('bill_due_day', e.target.value)} />
          </label>
          <Switch k="auto_generate_bills" label={t('settings.autoBills')} hint={t('settings.autoBillsHint')} />
        </div>

        <div className={card}>
          <h2 className="mb-3 font-semibold text-navy-900">{t('settings.lateFee')}</h2>
          <div className="grid gap-3 sm:grid-cols-3">
            <label className="text-sm text-navy-700">
              {t('settings.lateMode')}
              <select className={`${input} mt-1`} value={s.late_fee_mode} onChange={(e) => set('late_fee_mode', e.target.value)}>
                <option value="NONE">{t('settings.lateNone')}</option>
                <option value="PERCENT">{t('settings.latePercent')}</option>
                <option value="FIXED">{t('settings.lateFixed')}</option>
              </select>
            </label>
            <label className="text-sm text-navy-700">
              {s.late_fee_mode === 'PERCENT' ? t('settings.lateValuePct') : t('settings.lateValueTk')}
              <input type="number" min={0} step="0.01" className={`${input} mt-1`} value={s.late_fee_value} disabled={s.late_fee_mode === 'NONE'} onChange={(e) => set('late_fee_value', e.target.value)} />
            </label>
            <label className="text-sm text-navy-700">
              {t('settings.graceDays')}
              <input type="number" min={0} max={60} className={`${input} mt-1`} value={s.late_fee_grace_days} onChange={(e) => set('late_fee_grace_days', e.target.value)} />
            </label>
          </div>
          <p className="mt-2 text-xs text-navy-700">{t('settings.lateHint')}</p>
        </div>

        <div className={card}>
          <h2 className="mb-1 font-semibold text-navy-900">{t('settings.sms')}</h2>
          <p className={`mb-2 text-xs ${gateway === 'CONFIGURED' ? 'text-status-paid' : 'text-status-partial'}`}>
            {gateway === 'CONFIGURED' ? t('settings.gatewayOk') : t('settings.gatewayMissing')}
          </p>
          <Switch k="sms_enabled" label={t('settings.smsOn')} hint={t('settings.smsOnHint')} />
          <Switch k="reminders_enabled" label={t('settings.remindersOn')} hint={t('settings.remindersHint')} />
          <div className="mt-2 grid gap-3 sm:grid-cols-2">
            <label className="text-sm text-navy-700">
              {t('settings.daysBefore')}
              <input type="number" min={0} max={20} className={`${input} mt-1`} value={s.reminder_days_before_due} onChange={(e) => set('reminder_days_before_due', e.target.value)} />
            </label>
            <label className="text-sm text-navy-700">
              {t('settings.repeatDays')}
              <input type="number" min={1} max={60} className={`${input} mt-1`} value={s.reminder_repeat_days} onChange={(e) => set('reminder_repeat_days', e.target.value)} />
            </label>
          </div>
        </div>

        <button onClick={save} className="w-full rounded-xl bg-brand-600 px-4 py-3 font-medium text-white hover:bg-brand-700 sm:w-auto">
          {t('settings.save')}
        </button>

        {canBackup && (
          <div className={card}>
            <h2 className="mb-1 font-semibold text-navy-900">{t('settings.backup')}</h2>
            <p className="mb-3 text-xs text-navy-700">{t('settings.backupHint')}</p>
            <button onClick={backup} className="rounded-lg border border-brand-200 px-4 py-2 text-sm text-navy-700 hover:bg-brand-100">
              ⬇ {t('settings.backupBtn')}
            </button>
          </div>
        )}

        <div className={card}>
          <h2 className="mb-3 font-semibold text-navy-900">{t('settings.smsLog')}</h2>
          {logs.length === 0 && <p className="text-sm text-navy-700">{t('settings.noSms')}</p>}
          <div className="divide-y divide-brand-100 text-sm">
            {logs.map((l) => (
              <div key={l.id} className="py-2">
                <div className="flex flex-wrap justify-between gap-2">
                  <span className="font-medium text-navy-900">{l.mobile}</span>
                  <span className={l.status === 'SENT' ? 'text-status-paid' : l.status === 'FAILED' ? 'text-status-due' : 'text-status-partial'}>{l.status}</span>
                </div>
                <p className="text-xs text-navy-700">{l.message}</p>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
