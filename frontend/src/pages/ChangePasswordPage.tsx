import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as authApi from '../api/auth';

export default function ChangePasswordPage() {
  const { t } = useLanguage();
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setSuccess(false);

    if (newPassword.length < 8) {
      setError(t('password.minLength'));
      return;
    }
    if (newPassword !== confirmPassword) {
      setError(t('password.mismatch'));
      return;
    }

    setSubmitting(true);
    try {
      await authApi.changePassword(currentPassword, newPassword, confirmPassword);
      setSuccess(true);
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
    } catch (err: any) {
      setError(err.response?.data?.message || t('somethingWrong'));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-md">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('password.change')}</h1>
          </div>
          <LanguageToggle />
        </div>

        <form onSubmit={handleSubmit} className="space-y-4 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
          <div>
            <label className="mb-1 block text-sm font-medium text-navy-900">{t('password.current')}</label>
            <input
              type="password"
              required
              value={currentPassword}
              onChange={(e) => setCurrentPassword(e.target.value)}
              className="w-full rounded-lg border border-brand-200 px-3 py-2 text-navy-900 outline-none focus:border-brand-500"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-navy-900">{t('password.new')}</label>
            <input
              type="password"
              required
              minLength={8}
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
              className="w-full rounded-lg border border-brand-200 px-3 py-2 text-navy-900 outline-none focus:border-brand-500"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-navy-900">{t('password.confirm')}</label>
            <input
              type="password"
              required
              minLength={8}
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              className="w-full rounded-lg border border-brand-200 px-3 py-2 text-navy-900 outline-none focus:border-brand-500"
            />
          </div>

          {error && <p className="text-sm text-status-due">{error}</p>}
          {success && <p className="text-sm text-status-paid">{t('password.success')}</p>}

          <button
            type="submit"
            disabled={submitting}
            className="w-full rounded-lg bg-brand-600 px-4 py-2 font-medium text-white transition hover:bg-brand-700 disabled:opacity-50"
          >
            {submitting ? t('password.submitting') : t('password.submit')}
          </button>
        </form>
      </div>
    </div>
  );
}
