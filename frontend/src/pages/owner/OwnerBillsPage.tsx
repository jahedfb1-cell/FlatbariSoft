import { useEffect, useState } from 'react';
import { useLanguage } from '../../context/LanguageContext';
import LanguageToggle from '../../components/LanguageToggle';
import OwnerNav from '../../components/OwnerNav';
import * as myApi from '../../api/my';
import type { BillRow } from '../../api/billing';

export default function OwnerBillsPage() {
  const { t } = useLanguage();
  const [bills, setBills] = useState<BillRow[]>([]);

  useEffect(() => {
    myApi.myBills().then(setBills);
  }, []);

  return (
    <div className="min-h-screen bg-brand-50 px-4 pb-20 pt-8">
      <div className="mx-auto max-w-md">
        <div className="mb-4 flex items-center justify-between">
          <h1 className="text-xl font-semibold text-navy-900">{t('owner.bills')}</h1>
          <LanguageToggle />
        </div>
        <div className="space-y-3">
          {bills.map((b) => (
            <div key={b.id} className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
              <div className="flex items-center justify-between">
                <p className="font-medium text-navy-900">{b.flat_no}</p>
                <span
                  className={`rounded-full px-2 py-1 text-xs font-medium ${
                    b.status === 'PAID' ? 'bg-status-paid/15 text-status-paid' : b.status === 'PARTIAL' ? 'bg-status-partial/15 text-status-partial' : 'bg-status-due/15 text-status-due'
                  }`}
                >
                  {b.status}
                </span>
              </div>
              <p className="mt-1 text-sm text-navy-700">Due: {b.due_date}</p>
              <p className="mt-1 text-lg font-semibold text-navy-900">৳{Number(b.total_payable).toLocaleString('en-BD')}</p>
              <p className="text-sm text-navy-700">Paid: ৳{Number(b.paid_amount).toLocaleString('en-BD')}</p>
            </div>
          ))}
          {bills.length === 0 && <p className="text-sm text-navy-700">—</p>}
        </div>
      </div>
      <OwnerNav />
    </div>
  );
}
