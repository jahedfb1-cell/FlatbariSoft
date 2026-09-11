import { useState, type FormEvent } from 'react';
import Modal from './Modal';
import * as flatsApi from '../api/flats';
import type { Flat } from '../api/flats';

const inputClass =
  'w-full rounded-lg border border-brand-200 px-3 py-2 text-navy-900 outline-none focus:border-brand-500';
const labelClass = 'mb-1 block text-sm font-medium text-navy-900';

export default function FlatFormModal({
  buildingId,
  flat,
  onClose,
  onSaved,
}: {
  buildingId: number;
  flat?: Flat;
  onClose: () => void;
  onSaved: () => void;
}) {
  const isEdit = !!flat;
  const [flatNo, setFlatNo] = useState(flat?.flat_no ?? '');
  const [floor, setFloor] = useState(flat?.floor ?? '');
  const [sizeSqft, setSizeSqft] = useState(flat?.size_sqft ?? '');
  const [serviceChargeRate, setServiceChargeRate] = useState(flat?.service_charge_rate ?? '3500');
  const [emptyFlatRate, setEmptyFlatRate] = useState(flat?.empty_flat_rate ?? '2500');
  const [gasMeterNo, setGasMeterNo] = useState(flat?.gas_meter_no ?? '');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const payload = {
        building_id: buildingId,
        flat_no: flatNo,
        floor,
        size_sqft: sizeSqft || undefined,
        service_charge_rate: serviceChargeRate || undefined,
        empty_flat_rate: emptyFlatRate || undefined,
        gas_meter_no: gasMeterNo || undefined,
      };
      if (isEdit) {
        await flatsApi.updateFlat(flat.id, payload);
      } else {
        await flatsApi.createFlat(payload);
      }
      onSaved();
    } catch (err: any) {
      const errors = err.response?.data?.errors;
      setError(errors ? Object.values(errors).flat().join(' ') : err.response?.data?.message || 'কিছু ভুল হয়েছে।');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Modal title={isEdit ? 'ফ্ল্যাট এডিট করুন' : 'নতুন ফ্ল্যাট যোগ করুন'} onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className={labelClass}>ফ্ল্যাট নম্বর</label>
            <input required value={flatNo} onChange={(e) => setFlatNo(e.target.value)} className={inputClass} />
          </div>
          <div>
            <label className={labelClass}>ফ্লোর</label>
            <input required value={floor} onChange={(e) => setFloor(e.target.value)} className={inputClass} />
          </div>
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className={labelClass}>সাইজ (Sqft)</label>
            <input
              type="number"
              value={sizeSqft}
              onChange={(e) => setSizeSqft(e.target.value)}
              className={inputClass}
            />
          </div>
          <div>
            <label className={labelClass}>গ্যাস মিটার নম্বর</label>
            <input value={gasMeterNo} onChange={(e) => setGasMeterNo(e.target.value)} className={inputClass} />
          </div>
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className={labelClass}>সার্ভিস চার্জ (৳)</label>
            <input
              type="number"
              value={serviceChargeRate}
              onChange={(e) => setServiceChargeRate(e.target.value)}
              className={inputClass}
            />
          </div>
          <div>
            <label className={labelClass}>খালি ফ্ল্যাট রেট (৳)</label>
            <input
              type="number"
              value={emptyFlatRate}
              onChange={(e) => setEmptyFlatRate(e.target.value)}
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
          {submitting ? 'সংরক্ষণ হচ্ছে...' : 'সংরক্ষণ করুন'}
        </button>
      </form>
    </Modal>
  );
}
