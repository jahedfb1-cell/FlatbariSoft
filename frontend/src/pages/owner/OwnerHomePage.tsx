import { useEffect, useState, type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { useLanguage } from '../../context/LanguageContext';
import OwnerNav from '../../components/OwnerNav';
import * as myApi from '../../api/my';
import * as kycApi from '../../api/kyc';
import type { TenantKyc } from '../../api/kyc';
import type { PortalSummary } from '../../api/my';

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { maximumFractionDigits: 2 })}`;

/**
 * A flat owner's / tenant's home: what is due this month, what is overdue, what is still owed on the construction books,
 * what is new - and everything else one tap away. Each person sees only their own flats.
 */
export default function OwnerHomePage() {
  const { user } = useAuth();
  const { t, lang } = useLanguage();
  const L = (bn: string, en: string) => (lang === 'bn' ? bn : en);
  const [data, setData] = useState<PortalSummary | null>(null);
  const [failed, setFailed] = useState(false);
  const [kyc, setKyc] = useState<TenantKyc | null>(null);

  useEffect(() => {
    myApi.mySummary().then(setData).catch(() => setFailed(true));
    kycApi.getMyKyc().then(setKyc).catch(() => undefined);
  }, []);

  const isOwner = !!data?.flats.some((f) => f.role === 'OWNER');
  const construction = data?.totals.construction_due;

  const tile = (to: string, icon: string, label: string, hint?: ReactNode) => (
    <Link key={to} to={to} className="flex min-h-20 flex-col justify-center gap-0.5 rounded-2xl border border-brand-200 bg-white p-3 text-navy-900 shadow-sm transition hover:bg-brand-50">
      <span className="text-2xl leading-none">{icon}</span>
      <span className="text-sm font-medium">{label}</span>
      {hint && <span className="text-xs text-navy-700">{hint}</span>}
    </Link>
  );

  const statusText = (s: string) => (s === 'PAID' ? L('পরিশোধিত', 'Paid') : s === 'PARTIAL' ? L('আংশিক', 'Partly paid') : L('বকেয়া', 'Unpaid'));

  return (
    <div className="px-4 pb-24 pt-4">
      <div className="mx-auto max-w-md space-y-4">
        <p className="text-sm text-navy-700">
          {L('স্বাগতম', 'Welcome')} · {user?.mobile}
        </p>

        {failed && <div className="rounded-xl bg-white p-4 text-sm text-status-due shadow">{t('somethingWrong')}</div>}
        {!data && !failed && <div className="h-40 animate-pulse rounded-2xl bg-white/70 shadow" />}

        {kyc && !kyc.complete && (
          <Link to="/owner/kyc" className="block rounded-xl border border-status-partial/40 bg-amber-50 p-4 text-center font-medium text-status-partial shadow-sm">
            📋 {t('kyc.fillNow')}
          </Link>
        )}

        {data && data.flats.length === 0 && <p className="rounded-xl bg-white p-4 text-sm text-navy-700 shadow-sm">{L('আপনার নামে এখনও কোনো ফ্ল্যাট যুক্ত নেই। অফিসে জানান।', 'No flat is linked to you yet. Please tell the office.')}</p>}

        {/* this month's bill, flat by flat */}
        {data?.flats.map((f) => {
          const b = f.latest_bill;
          return (
            <div key={f.id} className={`overflow-hidden rounded-2xl border bg-white shadow-sm ${b?.overdue ? 'border-status-due/50' : 'border-brand-200'}`}>
              <div className="flex items-center justify-between gap-2 bg-gradient-to-br from-brand-700 to-brand-600 px-4 py-3 text-white">
                <div>
                  <p className="text-lg font-semibold leading-tight">
                    {L('ফ্ল্যাট', 'Flat')} {f.flat_no}
                  </p>
                  <p className="text-xs text-white/80">{f.role === 'OWNER' ? L('মালিক', 'Owner') : L('ভাড়াটিয়া', 'Tenant')}</p>
                </div>
                {b && <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${b.status === 'PAID' ? 'bg-white text-status-paid' : 'bg-white/20 text-white'}`}>{statusText(b.status)}</span>}
              </div>
              {b ? (
                <div className="space-y-3 p-4">
                  <p className="text-xs text-navy-700">
                    {L('সর্বশেষ বিল', 'Latest bill')} · {b.month_label}
                  </p>
                  <div className="grid grid-cols-3 gap-2 text-center">
                    <div className="rounded-xl bg-brand-50 p-2">
                      <p className="text-[11px] text-navy-700">{L('মোট', 'Total')}</p>
                      <p className="text-sm font-semibold text-navy-900">{money(b.total_payable)}</p>
                    </div>
                    <div className="rounded-xl bg-brand-50 p-2">
                      <p className="text-[11px] text-navy-700">{L('দিয়েছেন', 'Paid')}</p>
                      <p className="text-sm font-semibold text-status-paid">{money(b.paid_amount)}</p>
                    </div>
                    <div className={`rounded-xl p-2 ${Number(b.due) > 0 ? 'bg-status-due/10' : 'bg-brand-50'}`}>
                      <p className="text-[11px] text-navy-700">{L('বাকি', 'Due')}</p>
                      <p className={`text-sm font-semibold ${Number(b.due) > 0 ? 'text-status-due' : 'text-navy-900'}`}>{money(b.due)}</p>
                    </div>
                  </div>
                  {b.overdue && (
                    <p className="rounded-lg bg-status-due/10 px-3 py-2 text-xs font-medium text-status-due">
                      ⚠ {L('শেষ তারিখ পেরিয়ে গেছে', 'The due date has passed')} ({b.due_date})
                    </p>
                  )}
                  {!b.overdue && Number(b.due) > 0 && b.due_date && (
                    <p className="text-xs text-navy-700">
                      {L('শেষ তারিখ', 'Pay by')}: {b.due_date}
                    </p>
                  )}
                  <div className="flex gap-2">
                    {Number(b.due) > 0 && (
                      <Link to="/owner/payments" className="flex-[2] rounded-xl bg-brand-600 py-3 text-center text-sm font-semibold text-white shadow hover:bg-brand-700">
                        💳 {L('টাকা জানান', 'Report a payment')}
                      </Link>
                    )}
                    <Link to="/owner/bills" className="flex-1 rounded-xl border border-brand-200 py-3 text-center text-sm font-medium text-navy-700 hover:bg-brand-50">
                      {L('বিল দেখুন', 'View bill')}
                    </Link>
                  </div>
                </div>
              ) : (
                <p className="p-4 text-sm text-navy-700">{L('এখনও কোনো বিল তৈরি হয়নি।', 'No bill has been made yet.')}</p>
              )}
            </div>
          );
        })}

        {/* money elsewhere */}
        {data && (Number(data.totals.pending_payments) > 0 || (construction !== null && construction !== undefined && Number(construction) > 0)) && (
          <div className="space-y-2 rounded-2xl border border-brand-200 bg-white p-4 text-sm shadow-sm">
            {Number(data.totals.pending_payments) > 0 && (
              <p className="flex justify-between text-navy-700">
                <span>⏳ {L('অনুমোদনের অপেক্ষায় পেমেন্ট', 'Payments waiting for approval')}</span>
                <b className="text-navy-900">{money(data.totals.pending_payments)}</b>
              </p>
            )}
            {construction !== null && construction !== undefined && Number(construction) > 0 && (
              <Link to="/owner/contributions" className="flex justify-between text-navy-700">
                <span>🏗 {L('নির্মাণ ব্যয়ের বাকি', 'Construction cost still due')}</span>
                <b className="text-status-due">{money(construction)}</b>
              </Link>
            )}
          </div>
        )}

        {/* what is new */}
        {data && (
          <div className="grid grid-cols-3 gap-2 text-center text-xs">
            <Link to="/owner/notifications" className="rounded-xl border border-brand-200 bg-white p-3 shadow-sm">
              <p className="text-xl font-bold text-navy-900">{data.counts.unread_notifications}</p>
              <p className="text-navy-700">🔔 {L('নতুন বার্তা', 'New alerts')}</p>
            </Link>
            <Link to="/owner/notices" className="rounded-xl border border-brand-200 bg-white p-3 shadow-sm">
              <p className="text-xl font-bold text-navy-900">{data.counts.new_notices}</p>
              <p className="text-navy-700">📢 {L('নোটিশ (৩০ দিন)', 'Notices (30 days)')}</p>
            </Link>
            <Link to="/owner/complaints" className="rounded-xl border border-brand-200 bg-white p-3 shadow-sm">
              <p className="text-xl font-bold text-navy-900">{data.counts.open_complaints}</p>
              <p className="text-navy-700">🛠 {L('খোলা অভিযোগ', 'Open complaints')}</p>
            </Link>
          </div>
        )}

        {/* everything else */}
        <div className="grid grid-cols-2 gap-3">
          {tile('/owner/payments', '💳', t('owner.payments'), L('রসিদ ও ইতিহাস', 'Receipts & history'))}
          {isOwner && tile('/owner/contributions', '🏗', t('owner.contrib'))}
          {tile('/owner/flat', '🏠', L('আমার ফ্ল্যাট', 'My flat'), L('তথ্য, ভাড়াটিয়া, পার্কিং', 'Details, tenant, parking'))}
          {tile('/owner/hall', '🏛️', t('owner.hall'))}
          {tile('/owner/visitors', '🚪', t('owner.visitors'))}
          {tile('/owner/parking', '🚗', t('owner.parking'))}
          {tile('/owner/info', 'ℹ️', L('ভবনের তথ্য', 'Building info'), L('জরুরি নম্বর, সভার সিদ্ধান্ত', 'Phone numbers, decisions'))}
          {isOwner && tile('/owner/tenant-request', '🔑', t('tr.title'))}
          {kyc && kyc.complete && tile('/owner/kyc', '📋', t('kyc.title'))}
        </div>
      </div>
      <OwnerNav />
    </div>
  );
}
