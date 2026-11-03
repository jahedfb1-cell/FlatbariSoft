import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import FlatLabel from '../components/FlatLabel';
import { apiError } from '../api/hall';
import * as paymentsApi from '../api/payments';
import type { PaymentRow, PaymentPage } from '../api/payments';

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { maximumFractionDigits: 2 })}`;
const input = 'rounded-lg border border-brand-200 px-3 py-2 text-sm';

const METHOD_LABEL: Record<string, string> = { CASH: 'Cash', BANK: 'Bank / Cheque', BKASH: 'bKash', NAGAD: 'Nagad', ROCKET: 'Rocket', UPAY: 'Upay' };

/**
 * The payment register: every payment ever entered, searchable, with the ones waiting for an approver at the top of the
 * filter. Payments are taken from the Billing page (against a flat's bill); this page is where they are checked,
 * approved and their receipts reprinted.
 */
export default function PaymentsPage() {
  const { user } = useAuth();
  const { t, lang } = useLanguage();
  const bn = lang === 'bn';
  const L = (b: string, e: string) => (bn ? b : e);
  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));

  const [status, setStatus] = useState('');
  const [method, setMethod] = useState('');
  const [q, setQ] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [page, setPage] = useState(1);
  const [data, setData] = useState<PaymentPage | null>(null);
  const [pendingCount, setPendingCount] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  const load = useCallback(async () => {
    try {
      setData(await paymentsApi.searchPayments({ status, method, q, from, to, page }));
      setPendingCount((await paymentsApi.searchPayments({ status: 'PENDING', per_page: 1 })).meta.total);
    } catch (err) {
      setError(apiError(err, t('somethingWrong')));
    }
  }, [status, method, q, from, to, page, t]);
  useEffect(() => {
    const id = setTimeout(load, q ? 300 : 0); // let the search box settle
    return () => clearTimeout(id);
  }, [load, q]);

  const filter = (fn: () => void) => {
    fn();
    setPage(1);
  };

  async function approve(p: PaymentRow) {
    setBusyId(p.id);
    setError(null);
    try {
      await paymentsApi.approvePayment(p.id);
      await load();
    } catch (err) {
      setError(apiError(err, t('somethingWrong')));
    } finally {
      setBusyId(null);
    }
  }

  const rows = data?.data ?? [];
  const total = rows.reduce((c, p) => c + Number(p.amount), 0);

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-6xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{L('পেমেন্ট রেজিস্টার ও অনুমোদন', 'Payment register & approvals')}</h1>
            <p className="text-sm text-navy-700">{L('নতুন পেমেন্ট নেওয়া হয় বিল পেজ থেকে; এখানে সব পেমেন্ট খুঁজে দেখা, অনুমোদন ও রসিদ প্রিন্ট হয়।', 'New payments are taken on the Billing page; here you search, approve and reprint receipts.')}</p>
          </div>
          <div className="flex items-center gap-2">
            <LanguageToggle />
            {can('payment.create') && (
              <Link to="/billing" className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                + {L('পেমেন্ট নিন (বিল পেজ)', 'Take a payment (Billing)')}
              </Link>
            )}
          </div>
        </div>

        {pendingCount > 0 && status !== 'PENDING' && (
          <button onClick={() => filter(() => setStatus('PENDING'))} className="mb-4 w-full rounded-xl border border-status-partial/40 bg-amber-50 p-3 text-left text-sm text-navy-900 hover:bg-amber-100">
            ⏳ <b>{pendingCount}</b> {L('টি পেমেন্ট অনুমোদনের অপেক্ষায় — দেখুন', 'payment(s) waiting for approval - view')}
          </button>
        )}

        <div className="mb-4 grid grid-cols-2 gap-3 rounded-xl border border-brand-200 bg-white p-4 shadow-sm sm:grid-cols-6">
          <input className={`${input} col-span-2`} placeholder={L('ফ্ল্যাট / রসিদ / রেফারেন্স খুঁজুন', 'Search flat / receipt / reference')} value={q} onChange={(e) => filter(() => setQ(e.target.value))} />
          <select className={input} value={status} onChange={(e) => filter(() => setStatus(e.target.value))}>
            <option value="">{L('সব অবস্থা', 'All statuses')}</option>
            <option value="PENDING">{t('common.pending')}</option>
            <option value="APPROVED">{t('common.approved')}</option>
          </select>
          <select className={input} value={method} onChange={(e) => filter(() => setMethod(e.target.value))}>
            <option value="">{L('সব মাধ্যম', 'All methods')}</option>
            {Object.entries(METHOD_LABEL).map(([v, label]) => (
              <option key={v} value={v}>{label}</option>
            ))}
          </select>
          <input className={input} type="date" value={from} onChange={(e) => filter(() => setFrom(e.target.value))} aria-label="from" title={L('থেকে', 'From')} />
          <input className={input} type="date" value={to} onChange={(e) => filter(() => setTo(e.target.value))} aria-label="to" title={L('পর্যন্ত', 'To')} />
        </div>
        {error && <p className="mb-4 text-sm text-status-due">{error}</p>}

        <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
          <table className="w-full text-left text-sm">
            <thead className="bg-brand-100 text-navy-900">
              <tr>
                <th className="px-3 py-3">{L('তারিখ', 'Date')}</th>
                <th className="px-3 py-3">{L('ফ্ল্যাট', 'Flat')}</th>
                <th className="px-3 py-3 text-right">{t('common.amount')}</th>
                <th className="px-3 py-3">{L('মাধ্যম ও রেফারেন্স', 'Method & reference')}</th>
                <th className="px-3 py-3">{L('মন্তব্য', 'Remarks')}</th>
                <th className="px-3 py-3">{t('payments.receiptNo')}</th>
                <th className="px-3 py-3">{t('common.status')}</th>
                <th className="px-3 py-3" />
              </tr>
            </thead>
            <tbody>
              {rows.length === 0 && (
                <tr>
                  <td colSpan={8} className="px-3 py-6 text-center text-navy-700">{data ? L('কোনো পেমেন্ট পাওয়া যায়নি।', 'No payments found.') : t('loading')}</td>
                </tr>
              )}
              {rows.map((p) => (
                <tr key={p.id} className="border-t border-brand-100 align-top">
                  <td className="whitespace-nowrap px-3 py-2">{p.payment_date}</td>
                  <td className="px-3 py-2">
                    <FlatLabel flatId={p.flat_id} flatNo={p.flat_no} owner={p.owner} tenant={p.tenant} />
                  </td>
                  <td className="px-3 py-2 text-right font-medium">{money(p.amount)}</td>
                  <td className="px-3 py-2">
                    {METHOD_LABEL[p.method] ?? p.method}
                    {p.bank_name && <span className="block text-xs text-navy-700">{p.bank_name}</span>}
                    {p.reference_no && <span className="block text-xs text-navy-700">{p.reference_no}</span>}
                  </td>
                  <td className="max-w-[14rem] px-3 py-2 text-xs text-navy-700">{p.remarks ?? '—'}</td>
                  <td className="whitespace-nowrap px-3 py-2">{p.receipt_no ?? '—'}</td>
                  <td className="px-3 py-2">
                    <span className={`rounded-full px-2 py-0.5 text-xs ${p.status === 'PENDING' ? 'bg-status-partial/15 text-status-partial' : 'bg-status-paid/15 text-status-paid'}`}>
                      {p.status === 'PENDING' ? t('common.pending') : t('common.approved')}
                    </span>
                  </td>
                  <td className="whitespace-nowrap px-3 py-2 text-right">
                    {p.status === 'PENDING' && can('payment.approve') && (
                      <button disabled={busyId === p.id} onClick={() => approve(p)} className="font-medium text-brand-700 hover:underline disabled:opacity-50">
                        {t('common.approve')}
                      </button>
                    )}
                    {p.status === 'APPROVED' && (
                      <button onClick={() => paymentsApi.printReceipt(p.id, p.receipt_no ?? String(p.id))} className="font-medium text-brand-700 hover:underline">
                        🖨 {t('billing.receipt')}
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
            {rows.length > 0 && (
              <tfoot>
                <tr className="border-t border-brand-200 bg-brand-50 font-medium">
                  <td className="px-3 py-2" colSpan={2}>{L('এই পাতার মোট', 'Total on this page')}</td>
                  <td className="px-3 py-2 text-right">{money(total)}</td>
                  <td colSpan={5} />
                </tr>
              </tfoot>
            )}
          </table>
        </div>

        {data && data.meta.last_page > 1 && (
          <div className="mt-3 flex items-center justify-center gap-3 text-sm text-navy-700">
            <button className="rounded-lg border border-brand-200 px-3 py-1 disabled:opacity-40" disabled={page <= 1} onClick={() => setPage(page - 1)}>‹</button>
            <span>
              {page} / {data.meta.last_page} · {data.meta.total} {L('টি পেমেন্ট', 'payments')}
            </span>
            <button className="rounded-lg border border-brand-200 px-3 py-1 disabled:opacity-40" disabled={page >= data.meta.last_page} onClick={() => setPage(page + 1)}>›</button>
          </div>
        )}
      </div>
    </div>
  );
}
