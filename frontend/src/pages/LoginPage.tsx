import { useState, type FormEvent } from 'react';
import * as authApi from '../api/auth';
import { useAuth } from '../context/AuthContext';

type Step = 'mobile' | 'otp';

export default function LoginPage() {
  const { login } = useAuth();
  const [step, setStep] = useState<Step>('mobile');
  const [mobile, setMobile] = useState('');
  const [code, setCode] = useState('');
  const [devOtp, setDevOtp] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleRequestOtp(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const res = await authApi.requestOtp(mobile);
      setDevOtp(res.dev_otp);
      setStep('otp');
    } catch (err: any) {
      setError(err.response?.data?.message || 'কিছু একটা ভুল হয়েছে।');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleVerifyOtp(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const res = await authApi.verifyOtp(mobile, code);
      login(res.token, res.user);
    } catch (err: any) {
      setError(err.response?.data?.message || 'কিছু একটা ভুল হয়েছে।');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-brand-50 px-4">
      <div className="w-full max-w-sm rounded-xl border border-brand-200 bg-white p-8 shadow-sm">
        <h1 className="text-xl font-semibold text-navy-900">Green Valley BMS</h1>
        <p className="mt-1 text-sm text-navy-700">
          {step === 'mobile' ? 'মোবাইল নম্বর দিয়ে লগইন করুন' : 'OTP কোড দিন'}
        </p>

        {step === 'mobile' && (
          <form onSubmit={handleRequestOtp} className="mt-6 space-y-4">
            <div>
              <label className="mb-1 block text-sm font-medium text-navy-900">মোবাইল নম্বর</label>
              <input
                type="tel"
                required
                placeholder="01712345678"
                value={mobile}
                onChange={(e) => setMobile(e.target.value)}
                className="w-full rounded-lg border border-brand-200 px-3 py-2 text-navy-900 outline-none focus:border-brand-500"
              />
            </div>
            {error && <p className="text-sm text-status-due">{error}</p>}
            <button
              type="submit"
              disabled={submitting}
              className="w-full rounded-lg bg-brand-600 px-4 py-2 font-medium text-white transition hover:bg-brand-700 disabled:opacity-50"
            >
              {submitting ? 'পাঠানো হচ্ছে...' : 'OTP পাঠান'}
            </button>
          </form>
        )}

        {step === 'otp' && (
          <form onSubmit={handleVerifyOtp} className="mt-6 space-y-4">
            {devOtp && (
              <p className="rounded-lg bg-brand-100 px-3 py-2 text-sm text-navy-900">
                Dev মোডে SMS গেটওয়ে নেই — OTP: <span className="font-semibold">{devOtp}</span>
              </p>
            )}
            <div>
              <label className="mb-1 block text-sm font-medium text-navy-900">OTP কোড</label>
              <input
                type="text"
                inputMode="numeric"
                required
                value={code}
                onChange={(e) => setCode(e.target.value)}
                className="w-full rounded-lg border border-brand-200 px-3 py-2 text-navy-900 outline-none focus:border-brand-500"
              />
            </div>
            {error && <p className="text-sm text-status-due">{error}</p>}
            <button
              type="submit"
              disabled={submitting}
              className="w-full rounded-lg bg-brand-600 px-4 py-2 font-medium text-white transition hover:bg-brand-700 disabled:opacity-50"
            >
              {submitting ? 'যাচাই হচ্ছে...' : 'যাচাই করুন'}
            </button>
            <button
              type="button"
              onClick={() => setStep('mobile')}
              className="w-full text-sm text-navy-700 hover:underline"
            >
              মোবাইল নম্বর পরিবর্তন করুন
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
