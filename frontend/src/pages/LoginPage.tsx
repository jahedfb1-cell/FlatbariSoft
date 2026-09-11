import { useState, type FormEvent } from 'react';
import * as authApi from '../api/auth';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';

type Step = 'mobile' | 'otp';

export default function LoginPage() {
  const { login } = useAuth();
  const { t } = useLanguage();
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
      setError(err.response?.data?.message || t('somethingWrong'));
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
      setError(err.response?.data?.message || t('somethingWrong'));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-brand-50 px-4">
      <div className="w-full max-w-sm rounded-xl border border-brand-200 bg-white p-8 shadow-sm">
        <div className="mb-2 flex items-start justify-between">
          <h1 className="text-xl font-semibold text-navy-900">{t('appName')}</h1>
          <LanguageToggle />
        </div>
        <p className="mt-1 text-sm text-navy-700">
          {step === 'mobile' ? t('login.mobileStepTitle') : t('login.otpStepTitle')}
        </p>

        {step === 'mobile' && (
          <form onSubmit={handleRequestOtp} className="mt-6 space-y-4">
            <div>
              <label className="mb-1 block text-sm font-medium text-navy-900">{t('login.mobileLabel')}</label>
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
              {submitting ? t('login.sending') : t('login.sendOtp')}
            </button>
          </form>
        )}

        {step === 'otp' && (
          <form onSubmit={handleVerifyOtp} className="mt-6 space-y-4">
            {devOtp && (
              <p className="rounded-lg bg-brand-100 px-3 py-2 text-sm text-navy-900">
                {t('login.devOtpHint')} <span className="font-semibold">{devOtp}</span>
              </p>
            )}
            <div>
              <label className="mb-1 block text-sm font-medium text-navy-900">{t('login.otpLabel')}</label>
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
              {submitting ? t('login.verifying') : t('login.verify')}
            </button>
            <button
              type="button"
              onClick={() => setStep('mobile')}
              className="w-full text-sm text-navy-700 hover:underline"
            >
              {t('login.changeMobile')}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
