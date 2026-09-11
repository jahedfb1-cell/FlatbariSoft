import { useState, type FormEvent } from 'react';
import Modal from './Modal';
import * as staffApi from '../api/staff';
import type { Staff } from '../api/staff';
import { useLanguage } from '../context/LanguageContext';

const inputClass =
  'w-full rounded-lg border border-brand-200 px-3 py-2 text-navy-900 outline-none focus:border-brand-500';
const labelClass = 'mb-1 block text-sm font-medium text-navy-900';

export default function StaffFormModal({
  staff,
  onClose,
  onSaved,
}: {
  staff?: Staff;
  onClose: () => void;
  onSaved: () => void;
}) {
  const { t } = useLanguage();
  const isEdit = !!staff;
  const [name, setName] = useState(staff?.name ?? '');
  const [designation, setDesignation] = useState(staff?.designation ?? '');
  const [mobile, setMobile] = useState(staff?.mobile ?? '');
  const [joinDate, setJoinDate] = useState(staff?.join_date ?? new Date().toISOString().slice(0, 10));
  const [monthlySalary, setMonthlySalary] = useState(staff?.monthly_salary ?? '');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const payload = { name, designation, mobile, join_date: joinDate, monthly_salary: monthlySalary };
      if (isEdit) {
        await staffApi.updateStaff(staff.id, payload);
      } else {
        await staffApi.createStaff(payload);
      }
      onSaved();
    } catch (err: any) {
      const errors = err.response?.data?.errors;
      setError(errors ? Object.values(errors).flat().join(' ') : err.response?.data?.message || t('somethingWrong'));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Modal title={isEdit ? t('staff.editTitle') : t('staff.addTitle')} onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className={labelClass}>{t('staff.name')}</label>
          <input required value={name} onChange={(e) => setName(e.target.value)} className={inputClass} />
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className={labelClass}>{t('staff.designation')}</label>
            <input required value={designation} onChange={(e) => setDesignation(e.target.value)} className={inputClass} />
          </div>
          <div>
            <label className={labelClass}>{t('staff.mobile')}</label>
            <input required value={mobile} onChange={(e) => setMobile(e.target.value)} className={inputClass} />
          </div>
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className={labelClass}>{t('staff.joinDate')}</label>
            <input
              type="date"
              required
              value={joinDate}
              onChange={(e) => setJoinDate(e.target.value)}
              className={inputClass}
            />
          </div>
          <div>
            <label className={labelClass}>{t('staff.salary')}</label>
            <input
              type="number"
              required
              value={monthlySalary}
              onChange={(e) => setMonthlySalary(e.target.value)}
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
          {submitting ? t('flatForm.saving') : t('flatForm.save')}
        </button>
      </form>
    </Modal>
  );
}
