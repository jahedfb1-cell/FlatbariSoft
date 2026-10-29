import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import TenantKycForm from '../components/TenantKycForm';
import * as kycApi from '../api/kyc';
import type { TenantKyc, TenantKycInput } from '../api/kyc';

/** The office's view of a tenant's KYC form: read it, or fill it in for a tenant who cannot. */
export default function TenantKycPage() {
  const { t } = useLanguage();
  const { id } = useParams();
  const [kyc, setKyc] = useState<TenantKyc | null | undefined>(undefined);
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    kycApi.getTenantKyc(Number(id)).then(setKyc).catch(() => setKyc(null));
  }, [id]);

  async function save(d: TenantKycInput) {
    setBusy(true);
    setMsg(null);
    setError(null);
    try {
      setKyc(await kycApi.saveTenantKyc(Number(id), d));
      setMsg(t('kyc.saved'));
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (e: any) {
      const errs = e?.response?.data?.errors;
      setError(errs ? (Object.values(errs) as string[][]).flat().join(' ') : (e?.response?.data?.message ?? t('somethingWrong')));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-2xl">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div>
            {kyc && (
              <Link to={`/flats/${kyc.flat_id}`} className="text-sm text-navy-700 hover:underline">
                ← {t('kyc.flat')} {kyc.flat_no}
              </Link>
            )}
            <h1 className="text-xl font-semibold text-navy-900">{t('kyc.title')}</h1>
            {kyc && (
              <p className="text-sm text-navy-700">
                {kyc.person.name_bn} · {kyc.person.mobile}
              </p>
            )}
          </div>
          <LanguageToggle />
        </div>

        {kyc === undefined && <p className="text-navy-700">{t('loading')}</p>}
        {kyc === null && <p className="rounded-xl bg-white p-6 text-center text-sm text-navy-700 shadow-sm">{t('somethingWrong')}</p>}
        {kyc && (
          <>
            {kyc.complete ? (
              <div className="mb-4 rounded-xl bg-green-50 p-3 text-sm text-status-paid">✔ {t('kyc.complete')}</div>
            ) : (
              <div className="mb-4 rounded-xl bg-amber-50 p-3 text-sm text-status-partial">{t('kyc.incompleteOffice')}</div>
            )}
            {msg && <div className="mb-4 rounded-xl bg-green-50 p-3 text-sm text-status-paid">{msg}</div>}
            {error && <div className="mb-4 rounded-xl bg-red-50 p-3 text-sm text-status-due">{error}</div>}
            <TenantKycForm key={kyc.submitted_at ?? 'new'} kyc={kyc} onSave={save} busy={busy} />
          </>
        )}
      </div>
    </div>
  );
}
