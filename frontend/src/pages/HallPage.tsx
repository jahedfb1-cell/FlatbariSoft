import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import type { TranslationKey } from '../i18n/translations';
import LanguageToggle from '../components/LanguageToggle';
import Modal from '../components/Modal';
import HallCalendar, { localToday, monthKey } from '../components/HallCalendar';
import * as hallApi from '../api/hall';
import * as flatsApi from '../api/flats';
import type { Flat } from '../api/flats';
import type { Hall, HallAvailability, HallBooking, HallQuote, HallSlot, HallStatus } from '../api/hall';

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD')}`;
const input = 'w-full rounded-lg border border-brand-200 px-3 py-2';
const btn = 'rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700 disabled:opacity-50';
const btnGhost = 'rounded-lg border border-brand-200 px-4 py-2 text-navy-700 hover:bg-brand-100';

const STATUS_COLOR: Record<HallStatus, string> = {
  PENDING: 'bg-status-partial/15 text-status-partial',
  CONFIRMED: 'bg-brand-100 text-brand-800',
  COMPLETED: 'bg-status-paid/15 text-status-paid',
  CANCELLED: 'bg-navy-900/10 text-navy-700',
  REJECTED: 'bg-status-due/15 text-status-due',
};

export function StatusBadge({ status }: { status: HallStatus }) {
  const { t } = useLanguage();
  return <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLOR[status]}`}>{t(`hall.status.${status}` as TranslationKey)}</span>;
}

export default function HallPage() {
  const { user } = useAuth();
  const { t } = useLanguage();
  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));
  const canManage = can('hall.manage'); // settings and rates
  const canApprove = can('hall.approve'); // approve / reject / cancel / complete, price adjustments
  const canPay = can('hall.payments'); // take or refund money
  const canRequest = can('hall.request');

  const [tab, setTab] = useState<'calendar' | 'settings'>('calendar');
  const now = new Date();
  const [ym, setYm] = useState({ year: now.getFullYear(), month: now.getMonth() });
  const [hall, setHall] = useState<Hall | null>(null);
  const [availability, setAvailability] = useState<HallAvailability>({});
  const [bookings, setBookings] = useState<HallBooking[]>([]);
  const [selectedDate, setSelectedDate] = useState<string | null>(null);
  const [showNew, setShowNew] = useState(false);
  const [openId, setOpenId] = useState<number | null>(null);
  const [status, setStatus] = useState('');
  const [search, setSearch] = useState('');
  const [error, setError] = useState<string | null>(null);

  const loadAvailability = useCallback(() => {
    hallApi.getAvailability(monthKey(ym.year, ym.month)).then(setAvailability).catch(() => {});
  }, [ym]);

  const loadBookings = useCallback(() => {
    hallApi
      .listBookings({ status: status || undefined, q: search || undefined })
      .then(setBookings)
      .catch((e) => setError(hallApi.apiError(e, t('somethingWrong'))));
  }, [status, search, t]);

  useEffect(() => {
    hallApi.getHall().then(setHall).catch((e) => setError(hallApi.apiError(e, t('somethingWrong'))));
  }, [t]);
  useEffect(loadAvailability, [loadAvailability]);
  useEffect(loadBookings, [loadBookings]);

  function refresh() {
    loadAvailability();
    loadBookings();
  }

  // a rate of 0 is a real price (free); only an unset (null) rate blocks booking
  const ratesMissing = hall ? hall.rates.length === 0 || hall.rates.every((r) => r.outsider_rate === null) : false;

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-4xl">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{hall?.name ?? t('hall.title')}</h1>
            {hall && <p className="text-sm text-navy-700">{t('hall.capacity')}: {hall.capacity}</p>}
          </div>
          <LanguageToggle />
        </div>

        {error && <p className="mb-4 text-sm text-status-due">{error}</p>}
        {ratesMissing && (
          <p className="mb-4 rounded-lg border border-status-partial/40 bg-status-partial/10 px-3 py-2 text-sm text-navy-900">{t('hall.noRates')}</p>
        )}

        <div className="mb-4 flex gap-2">
          <button className={tab === 'calendar' ? btn : btnGhost} onClick={() => setTab('calendar')}>
            {t('hall.calendar')}
          </button>
          {canManage && (
            <button className={tab === 'settings' ? btn : btnGhost} onClick={() => setTab('settings')}>
              {t('hall.settings')}
            </button>
          )}
        </div>

        {tab === 'settings' && hall && canManage ? (
          <SettingsPanel hall={hall} onSaved={setHall} />
        ) : (
          <>
            <div className="grid gap-4 md:grid-cols-2">
              <HallCalendar
                year={ym.year}
                monthIndex={ym.month}
                availability={availability}
                selected={selectedDate}
                onSelect={setSelectedDate}
                onMonthChange={(year, month) => setYm({ year, month })}
              />
              <div className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
                <p className="text-sm text-navy-700">{selectedDate ? `${t('hall.selected')}: ${selectedDate}` : t('hall.pickDate')}</p>
                {selectedDate && (
                  <ul className="mt-2 space-y-1 text-sm text-navy-900">
                    {(availability[selectedDate] ?? []).map((b, i) => (
                      <li key={i} className="flex items-center justify-between">
                        <span>{t(`hall.slot.${b.slot}` as TranslationKey)}</span>
                        <StatusBadge status={b.status} />
                      </li>
                    ))}
                    {(availability[selectedDate] ?? []).length === 0 && <li className="text-status-paid">{t('hall.free')}</li>}
                  </ul>
                )}
                {canRequest && (
                  <button className={`${btn} mt-4 w-full`} disabled={!selectedDate || ratesMissing} onClick={() => setShowNew(true)}>
                    {t('hall.newBooking')}
                  </button>
                )}
              </div>
            </div>

            <div className="mt-6 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
              <div className="mb-3 flex flex-wrap items-center gap-2">
                <h2 className="mr-auto font-semibold text-navy-900">{t('hall.bookings')}</h2>
                <input className="rounded-lg border border-brand-200 px-3 py-1.5 text-sm" placeholder={t('hall.search')} value={search} onChange={(e) => setSearch(e.target.value)} />
                <select className="rounded-lg border border-brand-200 px-3 py-1.5 text-sm" value={status} onChange={(e) => setStatus(e.target.value)}>
                  <option value="">{t('hall.all')}</option>
                  {(['PENDING', 'CONFIRMED', 'COMPLETED', 'CANCELLED', 'REJECTED'] as HallStatus[]).map((s) => (
                    <option key={s} value={s}>
                      {t(`hall.status.${s}` as TranslationKey)}
                    </option>
                  ))}
                </select>
              </div>

              {bookings.length === 0 ? (
                <p className="text-sm text-navy-700">{t('hall.noBookings')}</p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-sm">
                    <thead className="text-navy-700">
                      <tr>
                        <th className="py-2 pr-3">{t('hall.bookingNo')}</th>
                        <th className="pr-3">{t('hall.date')}</th>
                        <th className="pr-3">{t('hall.customerName')}</th>
                        <th className="pr-3">{t('hall.total')}</th>
                        <th className="pr-3">{t('hall.due')}</th>
                        <th>{t('common.status')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {bookings.map((b) => (
                        <tr key={b.id} onClick={() => setOpenId(b.id)} className="cursor-pointer border-t border-brand-100 hover:bg-brand-50">
                          <td className="py-2 pr-3 font-medium">{b.booking_no}</td>
                          <td className="pr-3">
                            {b.booking_date} · {t(`hall.slot.${b.slot}` as TranslationKey)}
                          </td>
                          <td className="pr-3">
                            {b.customer_name}
                            {b.flat_no ? <span className="text-navy-700"> ({b.flat_no})</span> : null}
                          </td>
                          <td className="pr-3">{money(b.total_amount)}</td>
                          <td className="pr-3">{b.status === 'CONFIRMED' || b.status === 'COMPLETED' ? money(b.rent_due) : '—'}</td>
                          <td>
                            <StatusBadge status={b.status} />
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </>
        )}
      </div>

      {showNew && selectedDate && hall && (
        <NewBookingModal
          date={selectedDate}
          hall={hall}
          availability={availability}
          canManage={canApprove}
          onClose={() => setShowNew(false)}
          onCreated={() => {
            setShowNew(false);
            refresh();
          }}
        />
      )}
      {openId !== null && <BookingDetailModal id={openId} canApprove={canApprove} canPay={canPay} onClose={() => setOpenId(null)} onChanged={refresh} />}
    </div>
  );
}

function slotTaken(availability: HallAvailability, date: string, slot: HallSlot) {
  return (availability[date] ?? []).some((b) => b.slot === slot || b.slot === 'FULL_DAY' || slot === 'FULL_DAY');
}

function NewBookingModal({
  date,
  hall,
  availability,
  canManage,
  onClose,
  onCreated,
}: {
  date: string;
  hall: Hall;
  availability: HallAvailability;
  canManage: boolean;
  onClose: () => void;
  onCreated: () => void;
}) {
  const { t } = useLanguage();
  const firstFree = hallApi.HALL_SLOTS.find((s) => !slotTaken(availability, date, s)) ?? 'DAY';
  const [slot, setSlot] = useState<HallSlot>(firstFree);
  const [flats, setFlats] = useState<Flat[]>([]);
  const [flatId, setFlatId] = useState('');
  const [name, setName] = useState('');
  const [mobile, setMobile] = useState('');
  const [eventType, setEventType] = useState<string>('WEDDING');
  const [guests, setGuests] = useState('');
  const [notes, setNotes] = useState('');
  const [extra, setExtra] = useState('');
  const [discount, setDiscount] = useState('');
  const [quote, setQuote] = useState<HallQuote | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    flatsApi.listFlats().then((f: any) => setFlats(Array.isArray(f) ? f : f.data ?? [])).catch(() => {});
  }, []);

  useEffect(() => {
    setQuote(null);
    hallApi.getQuote(slot, !!flatId).then(setQuote).catch((e) => setError(hallApi.apiError(e, t('somethingWrong'))));
  }, [slot, flatId, t]);

  const total = quote ? Number(quote.rent) + Number(extra || 0) - Number(discount || 0) : 0;

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await hallApi.createBooking({
        flat_id: flatId ? Number(flatId) : null,
        customer_name: name,
        customer_mobile: mobile,
        event_type: eventType,
        guest_count: guests ? Number(guests) : 0,
        booking_date: date,
        slot,
        notes: notes || undefined,
        extra_charge: canManage && extra ? extra : undefined,
        discount: canManage && discount ? discount : undefined,
      });
      onCreated();
    } catch (err) {
      setError(hallApi.apiError(err, t('somethingWrong')));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal title={`${t('hall.newBooking')} — ${date}`} onClose={onClose}>
      <form onSubmit={submit} className="space-y-3">
        <div>
          <label className="mb-1 block text-sm text-navy-700">{t('hall.slot')}</label>
          <div className="grid grid-cols-3 gap-2">
            {hall.rates.map((r) => {
              const taken = slotTaken(availability, date, r.slot);
              return (
                <button
                  key={r.slot}
                  type="button"
                  disabled={taken}
                  onClick={() => setSlot(r.slot)}
                  className={`rounded-lg border px-2 py-2 text-sm ${slot === r.slot ? 'border-brand-600 bg-brand-100' : 'border-brand-200'} ${taken ? 'opacity-40' : ''}`}
                >
                  <span className="block font-medium">{t(`hall.slot.${r.slot}` as TranslationKey)}</span>
                  <span className="block text-xs text-navy-700">
                    {r.start_time}–{r.end_time}
                  </span>
                </button>
              );
            })}
          </div>
        </div>

        <select className={input} value={flatId} onChange={(e) => setFlatId(e.target.value)}>
          <option value="">{t('hall.outsider')}</option>
          {flats.map((f) => (
            <option key={f.id} value={f.id}>
              {t('hall.flat')}: {f.flat_no}
            </option>
          ))}
        </select>
        <input required className={input} placeholder={t('hall.customerName')} value={name} onChange={(e) => setName(e.target.value)} />
        <input required className={input} placeholder={t('hall.mobile')} value={mobile} onChange={(e) => setMobile(e.target.value)} />
        <div className="grid grid-cols-2 gap-2">
          <select className={input} value={eventType} onChange={(e) => setEventType(e.target.value)}>
            {hallApi.HALL_EVENT_TYPES.map((ev) => (
              <option key={ev} value={ev}>
                {t(`hall.event.${ev}` as TranslationKey)}
              </option>
            ))}
          </select>
          <input type="number" min={0} className={input} placeholder={t('hall.guests')} value={guests} onChange={(e) => setGuests(e.target.value)} />
        </div>
        {canManage && (
          <div className="grid grid-cols-2 gap-2">
            <input type="number" min={0} step="0.01" className={input} placeholder={t('hall.extra')} value={extra} onChange={(e) => setExtra(e.target.value)} />
            <input type="number" min={0} step="0.01" className={input} placeholder={t('hall.discount')} value={discount} onChange={(e) => setDiscount(e.target.value)} />
          </div>
        )}
        <textarea className={input} rows={2} placeholder={t('hall.notes')} value={notes} onChange={(e) => setNotes(e.target.value)} />

        {quote && (
          <div className="rounded-lg bg-brand-50 p-3 text-sm text-navy-900">
            <p className="flex justify-between">
              <span>{t('hall.total')}</span>
              <b>{money(total)}</b>
            </p>
            <p className="flex justify-between text-navy-700">
              <span>{t('hall.deposit')}</span>
              <span>{money(quote.security_deposit)}</span>
            </p>
          </div>
        )}
        {error && <p className="text-sm text-status-due">{error}</p>}
        <button type="submit" disabled={busy || !quote} className={`${btn} w-full`}>
          {t('hall.newBooking').replace('+ ', '')}
        </button>
      </form>
    </Modal>
  );
}

function BookingDetailModal({
  id,
  canApprove,
  canPay,
  onClose,
  onChanged,
}: {
  id: number;
  canApprove: boolean;
  canPay: boolean;
  onClose: () => void;
  onChanged: () => void;
}) {
  const { t } = useLanguage();
  const [b, setB] = useState<HallBooking | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [reasonFor, setReasonFor] = useState<'reject' | 'cancel' | null>(null);
  const [reason, setReason] = useState('');
  const [refund, setRefund] = useState('');
  const [payType, setPayType] = useState<hallApi.HallPayType>('RENT');
  const [amount, setAmount] = useState('');
  const [method, setMethod] = useState('CASH');
  const [reference, setReference] = useState('');
  const [paidOn, setPaidOn] = useState(localToday());

  useEffect(() => {
    hallApi.getBooking(id).then(setB).catch((e) => setError(hallApi.apiError(e, t('somethingWrong'))));
  }, [id, t]);

  async function run(action: () => Promise<HallBooking>) {
    setError(null);
    try {
      setB(await action());
      setReasonFor(null);
      setReason('');
      setRefund('');
      setAmount('');
      onChanged();
    } catch (err) {
      setError(hallApi.apiError(err, t('somethingWrong')));
    }
  }

  if (!b) return <Modal title="…" onClose={onClose}>{error ? <p className="text-status-due">{error}</p> : t('loading')}</Modal>;

  const today = localToday();
  const open = b.status === 'PENDING' || b.status === 'CONFIRMED';
  const payTypes: hallApi.HallPayType[] = b.status === 'COMPLETED' ? ['DEPOSIT_REFUND', 'RENT', 'DEPOSIT'] : ['RENT', 'DEPOSIT'];

  return (
    <Modal title={`${b.booking_no} — ${b.customer_name}`} onClose={onClose}>
      <div className="max-h-[70vh] space-y-4 overflow-y-auto text-sm text-navy-900">
        <div className="flex items-center justify-between">
          <span>
            {b.booking_date} · {t(`hall.slot.${b.slot}` as TranslationKey)} ({b.start_time}–{b.end_time})
          </span>
          <StatusBadge status={b.status} />
        </div>
        <p className="text-navy-700">
          {b.customer_mobile} · {t(`hall.event.${b.event_type}` as TranslationKey)} · {b.guest_count} {t('hall.guests')}
          {b.flat_no ? ` · ${b.flat_no}` : ''}
        </p>
        {b.status_reason && <p className="text-navy-700">{t('hall.reason')}: {b.status_reason}</p>}

        <div className="rounded-lg bg-brand-50 p-3">
          <Row label={t('hall.rent')} value={money(b.rent_amount)} />
          {Number(b.extra_charge) > 0 && <Row label={t('hall.extra')} value={money(b.extra_charge)} />}
          {Number(b.discount) > 0 && <Row label={t('hall.discount')} value={`− ${money(b.discount)}`} />}
          <Row label={t('hall.total')} value={money(b.total_amount)} bold />
          <Row label={t('hall.paid')} value={money(b.rent_paid)} />
          <Row label={t('hall.due')} value={money(b.rent_due)} bold />
          <hr className="my-2 border-brand-200" />
          <Row label={t('hall.deposit')} value={money(b.security_deposit)} />
          <Row label={t('hall.depositHeld')} value={money(b.deposit_held)} />
        </div>

        {b.payments && b.payments.length > 0 && (
          <div>
            <p className="mb-1 font-medium">{t('hall.payments')}</p>
            {b.payments.map((p) => (
              <p key={p.id} className="flex justify-between border-t border-brand-100 py-1">
                <span>
                  {p.paid_on} · {t(`hall.payType.${p.type}` as TranslationKey)} · {p.method}
                  {p.receipt_no ? ` · ${p.receipt_no}` : ''}
                </span>
                <span className={p.type.endsWith('REFUND') ? 'text-status-due' : 'text-status-paid'}>
                  {p.type.endsWith('REFUND') ? '−' : '+'} {money(p.amount)}
                </span>
              </p>
            ))}
          </div>
        )}

        {canPay && (b.status === 'CONFIRMED' || b.status === 'COMPLETED') && (
          <form
            className="space-y-2 rounded-lg border border-brand-200 p-3"
            onSubmit={(e) => {
              e.preventDefault();
              run(() => hallApi.recordPayment(b.id, { type: payType, amount, method, paid_on: paidOn, reference_no: reference || undefined }));
            }}
          >
            <p className="font-medium">{t('hall.recordPayment')}</p>
            <div className="grid grid-cols-2 gap-2">
              <select className={input} value={payType} onChange={(e) => setPayType(e.target.value as hallApi.HallPayType)}>
                {payTypes.map((p) => (
                  <option key={p} value={p}>
                    {t(`hall.payType.${p}` as TranslationKey)}
                  </option>
                ))}
              </select>
              <input required type="number" min="0.01" step="0.01" className={input} placeholder={t('common.amount')} value={amount} onChange={(e) => setAmount(e.target.value)} />
              <select className={input} value={method} onChange={(e) => setMethod(e.target.value)}>
                {['CASH', 'BANK', 'BKASH', 'NAGAD'].map((m) => (
                  <option key={m}>{m}</option>
                ))}
              </select>
              <input required type="date" className={input} value={paidOn} onChange={(e) => setPaidOn(e.target.value)} />
            </div>
            <input className={input} placeholder={t('hall.reference')} value={reference} onChange={(e) => setReference(e.target.value)} />
            <button type="submit" className={`${btn} w-full`}>
              {t('hall.confirmAction')}
            </button>
          </form>
        )}

        {canApprove && (
          <div className="flex flex-wrap gap-2">
            {b.status === 'PENDING' && (
              <>
                <button className={btn} onClick={() => run(() => hallApi.approveBooking(b.id))}>
                  {t('hall.approve')}
                </button>
                <button className={btnGhost} onClick={() => setReasonFor('reject')}>
                  {t('hall.reject')}
                </button>
              </>
            )}
            {b.status === 'CONFIRMED' && b.booking_date <= today && (
              <button className={btn} onClick={() => run(() => hallApi.completeBooking(b.id))}>
                {t('hall.complete')}
              </button>
            )}
            {open && (
              <button className={`${btnGhost} text-status-due`} onClick={() => setReasonFor('cancel')}>
                {t('hall.cancel')}
              </button>
            )}
          </div>
        )}

        {reasonFor && (
          <div className="space-y-2 rounded-lg border border-status-due/40 p-3">
            <textarea className={input} rows={2} placeholder={t('hall.reason')} value={reason} onChange={(e) => setReason(e.target.value)} />
            {reasonFor === 'cancel' && (
              <>
                <p className="text-navy-700">
                  {t('hall.policyRefund')}: <b>{money(b.policy_refund ?? 0)}</b> · {t('hall.deposit')}: <b>{money(b.deposit_held)}</b>
                </p>
                <input type="number" min={0} step="0.01" className={input} placeholder={t('hall.refund')} value={refund} onChange={(e) => setRefund(e.target.value)} />
                <p className="text-xs text-navy-700">{t('hall.refundHint')}</p>
              </>
            )}
            <button
              className={btn}
              disabled={!reason.trim()}
              onClick={() =>
                run(() =>
                  reasonFor === 'reject'
                    ? hallApi.rejectBooking(b.id, reason)
                    : hallApi.cancelBooking(b.id, { reason, refund_amount: refund || undefined }),
                )
              }
            >
              {t('hall.confirmAction')}
            </button>
          </div>
        )}

        {error && <p className="text-status-due">{error}</p>}
      </div>
    </Modal>
  );
}

function Row({ label, value, bold }: { label: string; value: string; bold?: boolean }) {
  return (
    <p className={`flex justify-between ${bold ? 'font-semibold' : ''}`}>
      <span>{label}</span>
      <span>{value}</span>
    </p>
  );
}

function SettingsPanel({ hall, onSaved }: { hall: Hall; onSaved: (h: Hall) => void }) {
  const { t } = useLanguage();
  const [f, setF] = useState(hall);
  const [msg, setMsg] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const set = <K extends keyof Hall>(k: K, v: Hall[K]) => setF((s) => ({ ...s, [k]: v }));
  const setRate = (slot: HallSlot, k: 'start_time' | 'end_time' | 'resident_rate' | 'outsider_rate', v: string) =>
    setF((s) => ({ ...s, rates: s.rates.map((r) => (r.slot === slot ? { ...r, [k]: v } : r)) }));

  async function save(e: FormEvent) {
    e.preventDefault();
    setMsg(null);
    setError(null);
    try {
      const saved = await hallApi.updateHall({
        name: f.name,
        capacity: Number(f.capacity),
        description: f.description,
        security_deposit: f.security_deposit,
        advance_percent: f.advance_percent,
        free_cancel_days: Number(f.free_cancel_days),
        late_cancel_refund_percent: f.late_cancel_refund_percent,
        is_active: f.is_active,
        rates: f.rates,
      });
      onSaved(saved);
      setF(saved);
      setMsg(t('hall.saved'));
    } catch (err) {
      setError(hallApi.apiError(err, t('somethingWrong')));
    }
  }

  const field = (label: string, el: React.ReactNode) => (
    <label className="block text-sm text-navy-700">
      {label}
      <div className="mt-1">{el}</div>
    </label>
  );

  return (
    <form onSubmit={save} className="space-y-4 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
      <div className="grid gap-3 sm:grid-cols-2">
        {field(t('hall.name'), <input required className={input} value={f.name} onChange={(e) => set('name', e.target.value)} />)}
        {field(t('hall.capacity'), <input required type="number" min={0} className={input} value={f.capacity} onChange={(e) => set('capacity', Number(e.target.value))} />)}
        {field(t('hall.deposit'), <input required type="number" min={0} step="0.01" className={input} value={f.security_deposit} onChange={(e) => set('security_deposit', e.target.value)} />)}
        {field(t('hall.advancePercent'), <input required type="number" min={0} max={100} step="0.01" className={input} value={f.advance_percent} onChange={(e) => set('advance_percent', e.target.value)} />)}
        {field(t('hall.freeCancelDays'), <input required type="number" min={0} className={input} value={f.free_cancel_days} onChange={(e) => set('free_cancel_days', Number(e.target.value))} />)}
        {field(t('hall.lateRefundPercent'), <input required type="number" min={0} max={100} step="0.01" className={input} value={f.late_cancel_refund_percent} onChange={(e) => set('late_cancel_refund_percent', e.target.value)} />)}
      </div>
      {field(t('hall.description'), <textarea className={input} rows={2} value={f.description ?? ''} onChange={(e) => set('description', e.target.value)} />)}
      <label className="flex items-center gap-2 text-sm text-navy-900">
        <input type="checkbox" checked={f.is_active} onChange={(e) => set('is_active', e.target.checked)} /> {t('hall.active')}
      </label>

      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm">
          <thead className="text-navy-700">
            <tr>
              <th className="py-2 pr-2">{t('hall.slot')}</th>
              <th className="pr-2">{t('hall.from')}</th>
              <th className="pr-2">{t('hall.to')}</th>
              <th className="pr-2">{t('hall.residentRate')}</th>
              <th>{t('hall.outsiderRate')}</th>
            </tr>
          </thead>
          <tbody>
            {f.rates.map((r) => (
              <tr key={r.slot} className="border-t border-brand-100">
                <td className="py-2 pr-2 font-medium">{t(`hall.slot.${r.slot}` as TranslationKey)}</td>
                <td className="pr-2">
                  <input required type="time" className={input} value={r.start_time} onChange={(e) => setRate(r.slot, 'start_time', e.target.value)} />
                </td>
                <td className="pr-2">
                  <input required type="time" className={input} value={r.end_time} onChange={(e) => setRate(r.slot, 'end_time', e.target.value)} />
                </td>
                <td className="pr-2">
                  <input required type="number" min={0} step="0.01" className={input} value={r.resident_rate ?? ''} placeholder="0 = ফ্রি" onChange={(e) => setRate(r.slot, 'resident_rate', e.target.value)} />
                </td>
                <td>
                  <input required type="number" min={0} step="0.01" className={input} value={r.outsider_rate ?? ''} onChange={(e) => setRate(r.slot, 'outsider_rate', e.target.value)} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {error && <p className="text-sm text-status-due">{error}</p>}
      {msg && <p className="text-sm text-status-paid">{msg}</p>}
      <button type="submit" className={btn}>
        {t('hall.save')}
      </button>
    </form>
  );
}
