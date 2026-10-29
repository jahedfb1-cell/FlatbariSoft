import { useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../../context/LanguageContext';
import LanguageToggle from '../../components/LanguageToggle';
import OwnerNav from '../../components/OwnerNav';
import { openPdf, blobErrorMessage } from '../../utils/download';
import * as myApi from '../../api/my';
import * as ops from '../../api/operations';
import type { Flat } from '../../api/flats';
import type { BillRow } from '../../api/billing';
import type { MyPayment } from '../../api/operations';

const input = 'w-full rounded-lg border border-brand-200 px-3 py-2 text-base';
const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD', { maximumFractionDigits: 2 })}`;
const today = () => new Date(Date.now() + 6 * 3600 * 1000).toISOString().slice(0, 10); // Dhaka date

/** A resident's payments: tell the office about a bKash / Nagad / bank payment, follow its approval, download the receipt. */
export default function OwnerPaymentsPage() {
  const { t } = useLanguage();
  const [flats, setFlats] = useState<Flat[]>([]);
  const [bills, setBills] = useState<BillRow[]>([]);
  const [pays, setPays] = useState<MyPayment[]>([]);
  const [f, setF] = useState({ flat_id: '', bill_id: '', amount: '', method: 'BKASH' as 'BKASH' | 'NAGAD' | 'BANK', reference_no: '', payment_date: today() });
  const [msg, setMsg] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  function load() {
    myApi.myFlats().then((fl) => {
      setFlats(fl);
      setF((cur) => (cur.flat_id || !fl[0] ? cur : { ...cur, flat_id: String(fl[0].id) }));
    });
    myApi.myBills().then(setBills);
    ops.myPayments().then(setPays);
  }
  useEffect(load, []);

  const open = bills.filter((b) => b.status !== 'PAID' && String(b.flat_id) === f.flat_id);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setMsg(null);
    setError(null);
    try {
      await ops.reportMyPayment({ flat_id: Number(f.flat_id), bill_id: f.bill_id ? Number(f.bill_id) : null, amount: f.amount, method: f.method, reference_no: f.reference_no, payment_date: f.payment_date });
      setF({ ...f, amount: '', reference_no: '' });
      setMsg(t('ownerPay.sent'));
      load();
    } catch (err: any) {
      const errs = err?.response?.data?.errors;
      setError(errs ? (Object.values(errs) as string[][]).flat().join(' ') : (err?.response?.data?.message ?? t('somethingWrong')));
    }
  }

  async function receipt(p: MyPayment) {
    try {
      await openPdf(`/me/payments/${p.id}/receipt`, undefined, `receipt-${p.receipt_no}.pdf`);
    } catch (e) {
      setError(await blobErrorMessage(e, t('somethingWrong')));
    }
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 pb-20 pt-8">
      <div className="mx-auto max-w-md">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <div>
            <Link to="/owner" className="text-sm text-navy-700 hover:underline">
              ← {t('owner.home')}
            </Link>
            <h1 className="text-xl font-semibold text-navy-900">{t('ownerPay.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        <form onSubmit={submit} className="space-y-3 rounded-2xl border border-brand-100 bg-white p-4 shadow-sm">
          <p className="text-sm text-navy-700">{t('ownerPay.hint')}</p>
          {flats.length > 1 && (
            <select className={input} value={f.flat_id} onChange={(e) => setF({ ...f, flat_id: e.target.value, bill_id: '' })}>
              {flats.map((fl) => (
                <option key={fl.id} value={fl.id}>
                  {fl.flat_no}
                </option>
              ))}
            </select>
          )}
          <select className={input} value={f.bill_id} onChange={(e) => setF({ ...f, bill_id: e.target.value })}>
            <option value="">{t('ownerPay.noBill')}</option>
            {open.map((b) => (
              <option key={b.id} value={b.id}>
                {b.due_date} · {t('ownerPay.due')} {money(b.due)}
              </option>
            ))}
          </select>
          <div className="grid grid-cols-3 gap-2">
            {(['BKASH', 'NAGAD', 'BANK'] as const).map((m) => (
              <button type="button" key={m} onClick={() => setF({ ...f, method: m })} className={`rounded-lg border px-2 py-2 text-sm font-medium ${f.method === m ? 'border-brand-600 bg-brand-600 text-white' : 'border-brand-200 bg-white text-navy-700'}`}>
                {m === 'BKASH' ? 'bKash' : m === 'NAGAD' ? 'Nagad' : t('ownerPay.bank')}
              </button>
            ))}
          </div>
          <input required type="number" step="0.01" min="1" inputMode="decimal" className={input} placeholder={t('ownerPay.amount')} value={f.amount} onChange={(e) => setF({ ...f, amount: e.target.value })} />
          <input required className={input} placeholder={t('ownerPay.trxId')} value={f.reference_no} onChange={(e) => setF({ ...f, reference_no: e.target.value })} />
          <input required type="date" max={today()} className={input} value={f.payment_date} onChange={(e) => setF({ ...f, payment_date: e.target.value })} />
          {msg && <p className="rounded-lg bg-green-50 p-2 text-sm text-status-paid">{msg}</p>}
          {error && <p className="rounded-lg bg-red-50 p-2 text-sm text-status-due">{error}</p>}
          <button className="w-full rounded-xl bg-brand-600 px-4 py-3 font-medium text-white hover:bg-brand-700">{t('ownerPay.send')}</button>
        </form>

        <h2 className="mb-2 mt-6 font-semibold text-navy-900">{t('ownerPay.history')}</h2>
        <div className="space-y-2">
          {pays.map((p) => (
            <div key={p.id} className="rounded-xl border border-brand-100 bg-white p-4 shadow-sm">
              <div className="flex items-center justify-between">
                <span className="text-lg font-semibold text-navy-900">{money(p.amount)}</span>
                <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${p.status === 'APPROVED' ? 'bg-green-50 text-status-paid' : 'bg-amber-50 text-status-partial'}`}>
                  {p.status === 'APPROVED' ? t('ownerPay.approved') : t('ownerPay.pending')}
                </span>
              </div>
              <p className="text-xs text-navy-700">
                {p.payment_date} · {p.method}
                {p.reference_no ? ` · ${p.reference_no}` : ''}
              </p>
              {p.status === 'APPROVED' && p.receipt_no && (
                <button onClick={() => receipt(p)} className="mt-2 rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-brand-700 hover:bg-brand-100">
                  🧾 {t('ownerPay.receipt')} {p.receipt_no}
                </button>
              )}
            </div>
          ))}
          {pays.length === 0 && <p className="text-sm text-navy-700">—</p>}
        </div>
      </div>
      <OwnerNav />
    </div>
  );
}
