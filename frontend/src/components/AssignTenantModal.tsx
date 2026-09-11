import { useState, type FormEvent } from 'react';
import Modal from './Modal';
import * as flatsApi from '../api/flats';
import { useLanguage } from '../context/LanguageContext';

const inputClass =
  'w-full rounded-lg border border-brand-200 px-3 py-2 text-navy-900 outline-none focus:border-brand-500';
const labelClass = 'mb-1 block text-sm font-medium text-navy-900';

export default function AssignTenantModal({
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
  const [occupation, setOccupation] = useState('');
  const [familyMembers, setFamilyMembers] = useState('');
  const [startDate, setStartDate] = useState(new Date().toISOString().slice(0, 10));
  const [monthlyRent, setMonthlyRent] = useState('');
  const [securityDeposit, setSecurityDeposit] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      await flatsApi.assignTenant(flatId, {
        person: { name_bn: nameBn, mobile },
        start_date: startDate,
        occupation: occupation || undefined,
        family_members_count: familyMembers ? Number(familyMembers) : undefined,
        monthly_rent: monthlyRent || undefined,
        security_deposit: securityDeposit || undefined,
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
    <Modal title={t('assignTenant.title')} onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className={labelClass}>{t('assignTenant.nameLabel')}</label>
            <input required value={nameBn} onChange={(e) => setNameBn(e.target.value)} className={inputClass} />
          </div>
          <div>
            <label className={labelClass}>{t('assignTenant.mobileLabel')}</label>
            <input
              required
              placeholder="01912345678"
              value={mobile}
              onChange={(e) => setMobile(e.target.value)}
              className={inputClass}
            />
          </div>
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className={labelClass}>{t('assignTenant.occupation')}</label>
            <input value={occupation} onChange={(e) => setOccupation(e.target.value)} className={inputClass} />
          </div>
          <div>
            <label className={labelClass}>{t('assignTenant.familyMembers')}</label>
            <input
              type="number"
              value={familyMembers}
              onChange={(e) => setFamilyMembers(e.target.value)}
              className={inputClass}
            />
          </div>
        </div>
        <div>
          <label className={labelClass}>{t('assignTenant.startDate')}</label>
          <input
            type="date"
            required
            value={startDate}
            onChange={(e) => setStartDate(e.target.value)}
            className={inputClass}
          />
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className={labelClass}>{t('assignTenant.monthlyRent')}</label>
            <input
              type="number"
              value={monthlyRent}
              onChange={(e) => setMonthlyRent(e.target.value)}
              className={inputClass}
            />
          </div>
          <div>
            <label className={labelClass}>{t('assignTenant.securityDeposit')}</label>
            <input
              type="number"
              value={securityDeposit}
              onChange={(e) => setSecurityDeposit(e.target.value)}
              className={inputClass}
            />
          </div>
        </div>
        {error && <p className="text-sm text-status-due">{error}</p>}
        <button
          type="submit"
          disabled={submitting}
          className="w-full rounded-lg bg-brand-600 px-4 py-2 font-medium text-white transition hover:bg-brand-700 disabled:opacity-50"
        >
          {submitting ? t('flatForm.saving') : t('assignTenant.title')}
        </button>
      </form>
    </Modal>
  );
}
