import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import * as authApi from '../api/auth';
import { apiError } from '../api/hall';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';

const input = 'w-full rounded-lg border border-brand-200 px-3 py-2 text-navy-900 outline-none focus:border-brand-500';
const btn = 'w-full rounded-lg bg-brand-600 px-4 py-2 font-medium text-white transition hover:bg-brand-700 disabled:opacity-50';

/** Forgot password: an e-mail address gets a link, a mobile number gets a code by SMS to type in here. */
export default function ForgotPasswordPage() {
  const { lang } = useLanguage();
  const bn = lang === 'bn';
  const L = (b: string, e: string) => (bn ? b : e);

  const [identifier, setIdentifier] = useState('');
  const [sent, setSent] = useState<{ channel: 'email' | 'sms'; message: string } | null>(null);
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [done, setDone] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function send(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      setSent(await authApi.forgotPassword(identifier.trim()));
    } catch (err) {
      setError(apiError(err, L('কিছু একটা সমস্যা হয়েছে।', 'Something went wrong.')));
    } finally {
      setBusy(false);
    }
  }

  async function reset(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      const r = await authApi.resetPasswordByCode({ mobile: identifier.trim(), code: code.trim(), password, password_confirmation: confirm });
      setDone(r.message);
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
          <h1 className="text-xl font-semibold text-navy-900">{L('পাসওয়ার্ড ভুলে গেছেন?', 'Forgot password?')}</h1>
          <LanguageToggle />
        </div>

        {done ? (
          <div className="mt-6 space-y-4 text-sm">
            <p className="rounded-lg bg-status-paid/10 p-3 text-status-paid">{done}</p>
            <Link to="/" className={`${btn} block text-center`}>{L('লগইন করুন', 'Sign in')}</Link>
          </div>
        ) : !sent ? (
          <form onSubmit={send} className="mt-4 space-y-4">
            <p className="text-sm text-navy-700">{L('আপনার ইমেইল দিন — পাসওয়ার্ড বদলানোর লিংক যাবে। অথবা মোবাইল নম্বর দিন — SMS-এ কোড যাবে।', 'Enter your e-mail for a reset link, or your mobile number for a code by SMS.')}</p>
            <input required className={input} inputMode="email" placeholder="you@gmail.com / 01712345678" value={identifier} onChange={(e) => setIdentifier(e.target.value)} />
            {error && <p className="text-sm text-status-due">{error}</p>}
            <button className={btn} disabled={busy}>{busy ? '…' : L('পাঠান', 'Send')}</button>
            <Link to="/" className="block text-center text-sm text-navy-700 hover:underline">← {L('লগইনে ফিরুন', 'Back to sign in')}</Link>
          </form>
        ) : sent.channel === 'email' ? (
          <div className="mt-6 space-y-4 text-sm">
            <p className="rounded-lg bg-brand-50 p-3 text-navy-900">✉️ {sent.message}</p>
            <p className="text-xs text-navy-700">{L('লিংক ৬০ মিনিট পর্যন্ত কাজ করবে। মেইল না এলে স্প্যাম ফোল্ডার দেখুন, অথবা অফিসে বলুন — অ্যাডমিন নতুন পাসওয়ার্ড দিয়ে দিতে পারবেন।', 'The link works for 60 minutes. If nothing arrives check spam, or ask the office - an admin can set a new password for you.')}</p>
            <Link to="/" className="block text-center text-sm text-navy-700 hover:underline">← {L('লগইনে ফিরুন', 'Back to sign in')}</Link>
          </div>
        ) : (
          <form onSubmit={reset} className="mt-4 space-y-3">
            <p className="rounded-lg bg-brand-50 p-3 text-sm text-navy-900">📱 {sent.message}</p>
            <input required inputMode="numeric" className={input} placeholder={L('SMS-এর কোড', 'Code from the SMS')} value={code} onChange={(e) => setCode(e.target.value)} />
            <input required type="password" minLength={8} autoComplete="new-password" className={input} placeholder={L('নতুন পাসওয়ার্ড (কমপক্ষে ৮ অক্ষর)', 'New password (8+ characters)')} value={password} onChange={(e) => setPassword(e.target.value)} />
            <input required type="password" minLength={8} autoComplete="new-password" className={input} placeholder={L('আবার লিখুন', 'Repeat it')} value={confirm} onChange={(e) => setConfirm(e.target.value)} />
            {error && <p className="text-sm text-status-due">{error}</p>}
            <button className={btn} disabled={busy}>{busy ? '…' : L('পাসওয়ার্ড বদলান', 'Change password')}</button>
            <button type="button" className="w-full text-sm text-navy-700 hover:underline" onClick={() => { setSent(null); setError(null); }}>{L('অন্য নম্বর দিন / আবার পাঠান', 'Use another number / resend')}</button>
          </form>
        )}
      </div>
    </div>
  );
}
