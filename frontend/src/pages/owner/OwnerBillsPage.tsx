import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../../context/LanguageContext';
import OwnerNav from '../../components/OwnerNav';
import { blobErrorMessage } from '../../utils/download';
import * as myApi from '../../api/my';
import type { PortalBill } from '../../api/my';

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { maximumFractionDigits: 2 })}`;
const num = (v: string) => Number(v).toLocaleString('en-BD', { maximumFractionDigits: 2 });

/** The flat's bills, newest first, each with its full breakdown (previous due, service charge, gas meter, charges) and a PDF copy. */
export default function OwnerBillsPage() {
  const { t, lang } = useLanguage();
  const L = (bn: string, en: string) => (lang === 'bn' ? bn : en);
  const [bills, setBills] = useState<PortalBill[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    myApi.myBillDetails().then(setBills).catch(() => {
      setError(t('somethingWrong')); // a failed load must not read as "no bill yet"
      setBills([]);
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const chip = (s: string) =>
    s === 'PAID' ? 'bg-status-paid/15 text-status-paid' : s === 'PARTIAL' ? 'bg-status-partial/15 text-status-partial' : 'bg-status-due/15 text-status-due';
  const chipText = (s: string) => (s === 'PAID' ? L('পরিশোধিত', 'Paid') : s === 'PARTIAL' ? L('আংশিক', 'Partly paid') : L('বকেয়া', 'Unpaid'));

  const line = (label: string, value: string, strong = false) => (
    <p className={`flex justify-between gap-3 py-1 ${strong ? 'border-t border-brand-100 pt-2 font-semibold text-navy-900' : 'text-navy-700'}`}>
      <span>{label}</span>
      <span className="whitespace-nowrap">{value}</span>
    </p>
  );

  return (
    <div className="px-4 pb-24 pt-4">
      <div className="mx-auto max-w-md">
        <div className="mb-3">
          <Link to="/owner" className="text-sm text-navy-700 hover:underline">
            ← {t('owner.home')}
          </Link>
          <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('owner.bills')}</h1>
        </div>
        {error && <p className="mb-3 text-sm text-status-due">{error}</p>}
        {!bills && <div className="h-40 animate-pulse rounded-2xl bg-white/70 shadow" />}
        <div className="space-y-4">
          {bills?.map((b) => (
            <div key={b.bill_id} className="overflow-hidden rounded-2xl border border-brand-200 bg-white shadow-sm">
              <div className="flex items-center justify-between gap-2 border-b border-brand-100 px-4 py-3">
                <div>
                  <p className="font-semibold text-navy-900">
                    {b.month_label} · {L('ফ্ল্যাট', 'Flat')} {b.flat_no}
                  </p>
                  {b.due_date && (
                    <p className="text-xs text-navy-700">
                      {L('শেষ তারিখ', 'Pay by')}: {b.due_date}
                    </p>
                  )}
                </div>
                <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${chip(b.status)}`}>{chipText(b.status)}</span>
              </div>

              <div className="px-4 py-3 text-sm">
                {Number(b.previous_due) > 0 && line(L('আগের বকেয়া', 'Previous due'), money(b.previous_due))}
                {line(L('সার্ভিস চার্জ', 'Service charge'), money(b.service_charge))}
                {line(L('গ্যাস বিল', 'Gas bill'), money(b.gas_amount))}
                {b.gas_units !== null && b.gas_previous !== null && b.gas_current !== null && (
                  <p className="-mt-0.5 mb-1 rounded-lg bg-brand-50 px-3 py-1.5 text-xs text-navy-700">
                    🔥 {L('মিটার', 'Meter')} {num(b.gas_previous)} → {num(b.gas_current)} = <b className="text-navy-900">{num(b.gas_units)} {L('ইউনিট', 'units')}</b>
                    {b.gas_rate ? ` × ৳${num(b.gas_rate)}` : ''}
                  </p>
                )}
                {Number(b.other_charges) > 0 && line(L('অন্যান্য', 'Other charges'), money(b.other_charges))}
                {Number(b.late_fee) > 0 && line(L('বিলম্ব ফি', 'Late fee'), money(b.late_fee))}
                {line(L('মোট প্রদেয়', 'Total payable'), money(b.total_payable), true)}
                {Number(b.paid_amount) > 0 && line(L('দিয়েছেন', 'Paid so far'), `− ${money(b.paid_amount)}`)}
                <p className={`mt-1 flex justify-between rounded-xl px-3 py-2 text-base font-bold ${Number(b.due) > 0 ? 'bg-status-due/10 text-status-due' : 'bg-status-paid/10 text-status-paid'}`}>
                  <span>{Number(b.due) > 0 ? L('বাকি', 'Balance due') : L('পরিশোধিত', 'Paid in full')}</span>
                  <span>{Number(b.due) > 0 ? money(b.due) : '✓'}</span>
                </p>
              </div>

              <div className="flex gap-2 border-t border-brand-100 px-4 py-3">
                <button
                  onClick={() => myApi.openMyBillPdf(b.bill_id, b.flat_no, b.month_label).catch(async (e) => setError(await blobErrorMessage(e, t('somethingWrong'))))}
                  className="flex-1 rounded-xl border border-brand-200 py-2.5 text-sm font-medium text-brand-700 hover:bg-brand-50"
                >
                  🖨 {L('বিলের PDF', 'Bill PDF')}
                </button>
                {Number(b.due) > 0 && (
                  <Link to="/owner/payments" className="flex-1 rounded-xl bg-brand-600 py-2.5 text-center text-sm font-semibold text-white hover:bg-brand-700">
                    💳 {L('টাকা জানান', 'Report payment')}
                  </Link>
                )}
              </div>
            </div>
          ))}
          {bills && bills.length === 0 && !error && <p className="rounded-xl bg-white p-4 text-sm text-navy-700 shadow-sm">{L('এখনও কোনো বিল তৈরি হয়নি।', 'No bill has been made yet.')}</p>}
        </div>
      </div>
      <OwnerNav />
    </div>
  );
}
