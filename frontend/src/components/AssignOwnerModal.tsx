import { useState, type FormEvent } from 'react';
import Modal from './Modal';
import * as flatsApi from '../api/flats';
import { useLanguage } from '../context/LanguageContext';

const inputClass =
  'w-full rounded-lg border border-brand-200 px-3 py-2 text-navy-900 outline-none focus:border-brand-500';
const labelClass = 'mb-1 block text-sm font-medium text-navy-900';

export default function AssignOwnerModal({
  flatId,
  onClose,
  onSaved,
}: {
  flatId: number;
  onClose: () => void;
  onSaved: () => void;
}) {
  const { t } = useLanguage();
  const [nameBn, setNameBn] = useState('');
  const [mobile, setMobile] = useState('');
  const [fromDate, setFromDate] = useState(new Date().toISOString().slice(0, 10));
  const [isBillingContact, setIsBillingContact] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      await flatsApi.assignOwner(flatId, {
        person: { name_bn: nameBn, mobile },
        from_date: fromDate,
        is_billing_contact: isBillingContact,
      });
      onSaved();
    } catch (err: any) {
      const errors = err.response?.data?.errors;
      setError(errors ? Object.values(errors).flat().join(' ') : err.response?.data?.message || t('somethingWrong'));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Modal title={t('assignOwner.title')} onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className={labelClass}>{t('assignOwner.nameLabel')}</label>
          <input required value={nameBn} onChange={(e) => setNameBn(e.target.value)} className={inputClass} />
        </div>
        <div>
          <label className={labelClass}>{t('assignOwner.mobileLabel')}</label>
          <input
            required
            placeholder="01712345678"
            value={mobile}
            onChange={(e) => setMobile(e.target.value)}
            className={inputClass}
          />
          <p className="mt-1 text-xs text-navy-700">{t('assignOwner.mobileHint')}</p>
        </div>
        <div>
          <label className={labelClass}>{t('assignOwner.fromDate')}</label>
          <input
            type="date"
            required
            value={fromDate}
            onChange={(e) => setFromDate(e.target.value)}
            className={inputClass}
          />
        </div>
        <label className="flex items-center gap-2 text-sm text-navy-900">
          <input
            type="checkbox"
            checked={isBillingContact}
            onChange={(e) => setIsBillingContact(e.target.checked)}
          />
          {t('assignOwner.billingContact')}
        </label>
        {error && <p className="text-sm text-status-due">{error}</p>}
        <button
          type="submit"
          disabled={submitting}
          className="w-full rounded-lg bg-brand-600 px-4 py-2 font-medium text-white transition hover:bg-brand-700 disabled:opacity-50"
        >
          {submitting ? t('flatForm.saving') : t('assignOwner.title')}
        </button>
      </form>
    </Modal>
  );
}
