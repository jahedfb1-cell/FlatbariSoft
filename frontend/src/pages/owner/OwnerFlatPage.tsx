import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../../context/LanguageContext';
import OwnerNav from '../../components/OwnerNav';
import * as myApi from '../../api/my';
import type { PortalSummary } from '../../api/my';

/** The flat itself: its details, who lives in it (the owner sees the tenant and whether the tenant's KYC is done), and its parking. */
export default function OwnerFlatPage() {
  const { t, lang } = useLanguage();
  const L = (bn: string, en: string) => (lang === 'bn' ? bn : en);
  const [data, setData] = useState<PortalSummary | null>(null);

  useEffect(() => {
    myApi.mySummary().then(setData).catch(() => setData({ flats: [], totals: { bill_due: '0', overdue: false, pending_payments: '0', construction_due: null }, counts: { unread_notifications: 0, new_notices: 0, open_complaints: 0 } }));
  }, []);

  const occupancy = (o: string) => (o === 'OWNER_OCCUPIED' ? L('মালিক নিজে থাকেন', 'Owner lives here') : o === 'TENANTED' ? L('ভাড়া দেওয়া', 'Rented out') : L('খালি', 'Empty'));
  const row = (label: string, value: string | null | undefined) => (
    <p className="flex justify-between gap-3 py-1.5 text-sm">
      <span className="text-navy-700">{label}</span>
      <span className="text-right font-medium text-navy-900">{value || '—'}</span>
    </p>
  );

  return (
    <div className="px-4 pb-24 pt-4">
      <div className="mx-auto max-w-md space-y-4">
        <div>
          <Link to="/owner" className="text-sm text-navy-700 hover:underline">
            ← {t('owner.home')}
          </Link>
          <h1 className="mt-1 text-xl font-semibold text-navy-900">{L('আমার ফ্ল্যাট', 'My flat')}</h1>
        </div>
        {!data && <div className="h-40 animate-pulse rounded-2xl bg-white/70 shadow" />}
        {data?.flats.map((f) => (
          <div key={f.id} className="overflow-hidden rounded-2xl border border-brand-200 bg-white shadow-sm">
            <div className="bg-gradient-to-br from-brand-700 to-brand-600 px-4 py-3 text-white">
              <p className="text-lg font-semibold">
                {L('ফ্ল্যাট', 'Flat')} {f.flat_no}
              </p>
              <p className="text-xs text-white/80">{f.role === 'OWNER' ? L('আপনি মালিক', 'You are the owner') : L('আপনি ভাড়াটিয়া', 'You are the tenant')}</p>
            </div>
            <div className="divide-y divide-brand-100 px-4 py-2">
              {row(L('তলা', 'Floor'), f.floor)}
              {row(L('আকার', 'Size'), f.size_sqft ? `${Number(f.size_sqft).toLocaleString('en-BD')} sqft` : null)}
              {row(L('অবস্থা', 'Status'), occupancy(f.occupancy_status))}
              {row(L('গ্যাস মিটার নম্বর', 'Gas meter no.'), f.gas_meter_no)}
              {row(L('পার্কিং', 'Parking'), f.parking.length ? f.parking.map((p) => `${p.slot}${p.vehicle_no ? ` (${p.vehicle_no})` : ''}`).join(', ') : null)}
            </div>

            {f.role === 'OWNER' && (
              <div className="border-t border-brand-100 px-4 py-3">
                <p className="mb-1 text-xs font-semibold text-navy-700">{L('আমার ভাড়াটিয়া', 'My tenant')}</p>
                {f.tenant ? (
                  <>
                    {row(L('নাম', 'Name'), f.tenant.name)}
                    {row(L('মোবাইল', 'Mobile'), f.tenant.mobile)}
                    {row(L('থাকছেন', 'Since'), f.tenant.since)}
                    <p className={`mt-1 rounded-lg px-3 py-2 text-xs font-medium ${f.tenant.kyc_complete ? 'bg-status-paid/10 text-status-paid' : 'bg-amber-50 text-status-partial'}`}>
                      {f.tenant.kyc_complete ? `✓ ${L('ভাড়াটিয়ার KYC পূরণ হয়েছে', "The tenant's KYC is complete")}` : `⚠ ${L('ভাড়াটিয়ার KYC এখনও অসম্পূর্ণ', "The tenant's KYC is not complete yet")}`}
                    </p>
                  </>
                ) : (
                  <p className="text-sm text-navy-700">{L('এই ফ্ল্যাটে এখন কোনো ভাড়াটিয়া নেই।', 'No tenant lives in this flat now.')}</p>
                )}
                <Link to="/owner/tenant-request" className="mt-3 block rounded-xl border border-brand-200 py-2.5 text-center text-sm font-medium text-brand-700 hover:bg-brand-50">
                  🔑 {t('tr.title')}
                </Link>
              </div>
            )}
          </div>
        ))}
        {data && data.flats.length === 0 && <p className="rounded-xl bg-white p-4 text-sm text-navy-700 shadow-sm">{L('আপনার নামে এখনও কোনো ফ্ল্যাট যুক্ত নেই।', 'No flat is linked to you yet.')}</p>}
      </div>
      <OwnerNav />
    </div>
  );
}
