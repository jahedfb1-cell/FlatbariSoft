import { useState, type FormEvent } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import * as authApi from '../api/auth';
import { apiError } from '../api/hall';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';

const input = 'w-full rounded-lg border border-brand-200 px-3 py-2 text-navy-900 outline-none focus:border-brand-500';
const btn = 'w-full rounded-lg bg-brand-600 px-4 py-2 font-medium text-white transition hover:bg-brand-700 disabled:opacity-50';

/** The page the e-mailed link opens: choose a new password. */
export default function ResetPasswordPage() {
  const { lang } = useLanguage();
  const bn = lang === 'bn';
  const L = (b: string, e: string) => (bn ? b : e);
  const [params] = useSearchParams();
  const token = params.get('token') ?? '';
  const email = params.get('email') ?? '';

  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [done, setDone] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      setDone((await authApi.resetPasswordByLink({ email, token, password, password_confirmation: confirm })).message);
    } catch (err) {
      setError(apiError(err, L('কিছু একটা সমস্যা হয়েছে।', 'Something went wrong.')));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-brand-50 px-4">
      <div className="w-full max-w-sm rounded-xl border border-brand-200 bg-white p-8 shadow-sm">
        <div className="mb-2 flex items-start justify-between">
          <h1 className="text-xl font-semibold text-navy-900">{L('নতুন পাসওয়ার্ড', 'New password')}</h1>
          <LanguageToggle />
        </div>
        {done ? (
          <div className="mt-6 space-y-4 text-sm">
            <p className="rounded-lg bg-status-paid/10 p-3 text-status-paid">{done}</p>
            <Link to="/" className={`${btn} block text-center`}>{L('লগইন করুন', 'Sign in')}</Link>
          </div>
        ) : !token || !email ? (
          <div className="mt-6 space-y-4 text-sm">
            <p className="rounded-lg bg-status-due/10 p-3 text-status-due">{L('এই লিংকটি সঠিক নয়। নতুন করে "পাসওয়ার্ড ভুলে গেছেন" থেকে লিংক নিন।', 'This link is not valid. Ask for a new one from "Forgot password".')}</p>
            <Link to="/forgot-password" className={`${btn} block text-center`}>{L('নতুন লিংক নিন', 'Get a new link')}</Link>
          </div>
        ) : (
          <form onSubmit={submit} className="mt-4 space-y-3">
            <p className="text-sm text-navy-700">{email}</p>
            <input required type="password" minLength={8} autoComplete="new-password" className={input} placeholder={L('নতুন পাসওয়ার্ড (কমপক্ষে ৮ অক্ষর)', 'New password (8+ characters)')} value={password} onChange={(e) => setPassword(e.target.value)} />
            <input required type="password" minLength={8} autoComplete="new-password" className={input} placeholder={L('আবার লিখুন', 'Repeat it')} value={confirm} onChange={(e) => setConfirm(e.target.value)} />
            {error && <p className="text-sm text-status-due">{error}</p>}
            <button className={btn} disabled={busy}>{busy ? '…' : L('পাসওয়ার্ড বদলান', 'Change password')}</button>
          </form>
        )}
      </div>
    </div>
  );
}
