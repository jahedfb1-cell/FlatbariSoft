import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import FlatLabel from '../components/FlatLabel';
import Sheet from '../components/Sheet';
import { apiError } from '../api/hall';
import { openPdfAfter } from '../utils/download';
import * as paymentsApi from '../api/payments';
import type { PaymentRow, PaymentPage, PaymentReview } from '../api/payments';

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
  const [reviewing, setReviewing] = useState<PaymentRow | null>(null);

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
                      <button onClick={() => setReviewing(p)} className="rounded-lg bg-brand-600 px-3 py-1 text-xs font-medium text-white hover:bg-brand-700">
                        {L('দেখুন ও অনুমোদন', 'Review & approve')}
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

      {reviewing && (
        <ReviewSheet
          payment={reviewing}
          onClose={() => setReviewing(null)}
          onApproved={() => {
            setReviewing(null);
            load();
          }}
        />
      )}
    </div>
  );
}

const MONTHS_BN = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
const MONTHS_EN = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

/** What an approver sees before approving: who paid, how much, how, against which bill - and which books it will land in. */
function ReviewSheet({ payment, onClose, onApproved }: { payment: PaymentRow; onClose: () => void; onApproved: () => void }) {
  const { lang, t } = useLanguage();
  const bn = lang === 'bn';
  const L = (b: string, e: string) => (bn ? b : e);
  const [r, setR] = useState<PaymentReview | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [rejecting, setRejecting] = useState(false);
  const [reason, setReason] = useState('');

  useEffect(() => {
    paymentsApi.reviewPayment(payment.id).then(setR).catch((e) => setError(apiError(e, t('somethingWrong'))));
  }, [payment.id, t]);

  async function approve() {
    setBusy(true);
    setError(null);
    try {
      // approving issues the receipt number: the receipt opens straight away, ready to print or save as PDF
      await openPdfAfter(() => paymentsApi.approvePayment(payment.id), (p) => (p.receipt_no ? { path: `/payments/${p.id}/receipt`, filename: `receipt-${p.receipt_no}.pdf` } : null));
      onApproved();
    } catch (e) {
      setError(apiError(e, t('somethingWrong')));
      setBusy(false);
    }
  }

  async function reject() {
    setBusy(true);
    setError(null);
    try {
      await paymentsApi.rejectPayment(payment.id, reason.trim());
      onApproved();
    } catch (e) {
      setError(apiError(e, t('somethingWrong')));
      setBusy(false);
    }
  }

  const row = (label: string, value: string, strong = false) => (
    <p className={`flex justify-between gap-3 ${strong ? 'font-semibold text-navy-900' : 'text-navy-700'}`}>
      <span>{label}</span>
      <span className="text-right">{value}</span>
    </p>
  );
  const methodText = r ? (METHOD_LABEL[r.method] ?? r.method) : '';
  const blocked = !!r?.posting.bank_missing;

  return (
    <Sheet
      title={L('পেমেন্ট অনুমোদন', 'Approve payment')}
      sub={r ? `${L('ফ্ল্যাট', 'Flat')} ${r.flat_no ?? ''} · ${[r.owner, r.tenant].filter(Boolean).join(' · ')}` : undefined}
      onClose={onClose}
      footer={
        <div className="space-y-2">
          {error && <p className="rounded-lg bg-status-due/10 px-3 py-2 text-xs text-status-due">{error}</p>}
          {rejecting && (
            <div className="space-y-2 rounded-xl border border-status-due/40 p-3">
              <p className="text-xs text-navy-700">{L('কেন প্রত্যাখ্যান? কারণটি ফ্ল্যাটের বাসিন্দারা নোটিফিকেশনে দেখবেন।', 'Why? The flat\'s people will see the reason in their alerts.')}</p>
              <input className="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder={L('কারণ (কমপক্ষে ৩ অক্ষর)', 'Reason (at least 3 letters)')} value={reason} onChange={(e) => setReason(e.target.value)} />
              <button type="button" disabled={busy || reason.trim().length < 3} onClick={reject} className="w-full rounded-xl bg-status-due py-2.5 text-sm font-semibold text-white disabled:opacity-40">
                {busy ? '…' : L('প্রত্যাখ্যান নিশ্চিত করুন', 'Confirm rejection')}
              </button>
            </div>
          )}
          {!rejecting && r && (
            <button type="button" onClick={() => setRejecting(true)} className="w-full text-center text-xs text-status-due hover:underline">
              ✕ {L('এই পেমেন্ট প্রত্যাখ্যান করুন', 'Reject this payment')}
            </button>
          )}
          <div className="flex gap-2">
            <button type="button" onClick={onClose} className="flex-1 rounded-xl border border-brand-200 py-3 text-sm text-navy-700 hover:bg-brand-50">
              {L('বাতিল', 'Cancel')}
            </button>
            <button type="button" onClick={approve} disabled={busy || !r || blocked} className="flex-[2] rounded-xl bg-brand-600 py-3 text-sm font-semibold text-white shadow hover:bg-brand-700 disabled:opacity-40">
              {busy ? '…' : `✓ ${L('অনুমোদন করুন', 'Approve')}${r ? ' · ' + money(r.amount) : ''}`}
            </button>
          </div>
        </div>
      }
    >
      {!r ? (
        <p className="py-8 text-center text-sm text-navy-700">{error ?? t('loading')}</p>
      ) : (
        <div className="space-y-4 text-sm">
          <div className="rounded-2xl bg-gradient-to-br from-brand-700 to-brand-600 p-4 text-white">
            <p className="text-xs text-white/80">{L('পেমেন্টের পরিমাণ', 'Payment amount')}</p>
            <p className="text-3xl font-bold">{money(r.amount)}</p>
            <p className="mt-1 text-xs text-white/85">{methodText} · {r.payment_date}</p>
          </div>

          <div className="space-y-1 rounded-xl border border-brand-200 p-3">
            {row(L('মাধ্যম', 'Method'), methodText)}
            {r.bank_name && row(L('ব্যাংক', 'Bank'), r.bank_name)}
            {r.reference_no && row(r.method === 'BANK' ? L('চেক / রেফারেন্স', 'Cheque / ref') : 'TxnID', r.reference_no)}
            {row(L('এন্ট্রি করেছেন', 'Entered by'), `${r.entered_by ?? '—'}${r.entered_at ? ' · ' + r.entered_at : ''}`)}
            {r.remarks && row(L('মন্তব্য', 'Remarks'), r.remarks)}
          </div>

          {r.bill ? (
            <div className="space-y-1 rounded-xl border border-brand-200 p-3">
              <p className="mb-1 font-medium text-navy-900">
                {L('যে বিলের বিপরীতে', 'Against the bill of')} {(bn ? MONTHS_BN : MONTHS_EN)[r.bill.month - 1]} {r.bill.year}
              </p>
              {Number(r.bill.previous_due) > 0 && row(L('আগের বকেয়া', 'Previous due'), money(r.bill.previous_due))}
              {row(L('সার্ভিস চার্জ', 'Service charge'), money(r.bill.service_charge))}
              {row(L('গ্যাস', 'Gas'), Number(r.bill.gas_amount) > 0 ? money(r.bill.gas_amount) : '—')}
              {Number(r.bill.other_charges) > 0 && row(L('অন্যান্য', 'Other'), money(r.bill.other_charges))}
              {Number(r.bill.late_fee) > 0 && row(L('বিলম্ব ফি', 'Late fee'), money(r.bill.late_fee))}
              {row(L('মোট', 'Total'), money(r.bill.total_payable), true)}
              <p className="mt-1 border-t border-brand-100 pt-1" />
              {row(L('এই পেমেন্টের আগে বাকি', 'Due before'), money(r.bill.due_before))}
              {row(L('এই পেমেন্টের পরে বাকি', 'Due after'), money(r.bill.due_after), true)}
              {r.split && Number(r.split.gas) > 0 && <p className="pt-1 text-xs text-navy-700">{L('ভাগ:', 'Split:')} {L('সার্ভিস', 'service')} {money(r.split.service)} · {L('গ্যাস', 'gas')} {money(r.split.gas)}</p>}
            </div>
          ) : (
            <p className="rounded-xl bg-amber-50 p-3 text-xs text-navy-900">{L('এই পেমেন্ট কোনো বিলের সঙ্গে যুক্ত নয়।', 'This payment is not linked to a bill.')}</p>
          )}

          <div className={`rounded-xl p-3 text-xs ${blocked ? 'bg-status-due/10 text-status-due' : 'bg-brand-50 text-navy-900'}`}>
            <p className="mb-0.5 font-medium">{L('অনুমোদনে যা হবে', 'On approval')}</p>
            {blocked ? (
              <p>{L('কোনো ব্যাংক হিসাব সেটআপ করা নেই — আগে "ব্যাংক হিসাব" পেজে একটি অ্যাকাউন্ট যোগ করুন।', 'No bank account is set up - add one on the Bank Accounts page first.')}</p>
            ) : (
              <>
                <p>• {r.posting.medium === 'CASH' ? L('ক্যাশ খাতায় জমা (নগদ)', 'Posted to the cash ledger') : `${L('ব্যাংক খাতায় জমা', 'Posted to the bank ledger')}: ${r.posting.account}`}</p>
                <p>• {L('বিলের বকেয়া কমবে ও রসিদ নম্বর তৈরি হবে', 'The bill balance drops and a receipt number is issued')}</p>
                <p>• {L('আয়ের হিসাবে স্বয়ংক্রিয় জার্নাল এন্ট্রি হবে (আর বদলানো যাবে না)', 'An automatic journal entry is made (it cannot be edited later)')}</p>
              </>
            )}
          </div>
        </div>
      )}
    </Sheet>
  );
}
