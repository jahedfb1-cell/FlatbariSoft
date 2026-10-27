import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { useLanguage } from '../../context/LanguageContext';
import type { TranslationKey } from '../../i18n/translations';
import LanguageToggle from '../../components/LanguageToggle';
import OwnerNav from '../../components/OwnerNav';
import HallCalendar, { monthKey } from '../../components/HallCalendar';
import { StatusBadge } from '../HallPage';
import * as hallApi from '../../api/hall';
import * as myApi from '../../api/my';
import type { Flat } from '../../api/flats';
import type { Hall, HallAvailability, HallBooking, HallQuote, HallSlot } from '../../api/hall';

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD')}`;
const input = 'w-full rounded-lg border border-brand-200 px-3 py-2';

/** Resident view of the community hall: see what's free, ask for a date, follow the request. */
export default function OwnerHallPage() {
  const { t } = useLanguage();
  const now = new Date();
  const [ym, setYm] = useState({ year: now.getFullYear(), month: now.getMonth() });
  const [hall, setHall] = useState<Hall | null>(null);
  const [availability, setAvailability] = useState<HallAvailability>({});
  const [mine, setMine] = useState<HallBooking[]>([]);
  const [flats, setFlats] = useState<Flat[]>([]);
  const [date, setDate] = useState<string | null>(null);

  const [slot, setSlot] = useState<HallSlot>('DAY');
  const [flatId, setFlatId] = useState('');
  const [name, setName] = useState('');
  const [mobile, setMobile] = useState('');
  const [eventType, setEventType] = useState<string>('BIRTHDAY');
  const [guests, setGuests] = useState('');
  const [notes, setNotes] = useState('');
  const [quote, setQuote] = useState<HallQuote | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const loadMine = useCallback(() => {
    hallApi.myBookings().then(setMine).catch(() => {});
  }, []);
  const loadAvailability = useCallback(() => {
    hallApi.myAvailability(monthKey(ym.year, ym.month)).then(setAvailability).catch(() => {});
  }, [ym]);

  useEffect(() => {
    hallApi.myHall().then(setHall).catch((e) => setError(hallApi.apiError(e, t('somethingWrong'))));
    myApi.myFlats().then((f) => {
      setFlats(f);
      if (f[0]) setFlatId(String(f[0].id));
    });
    loadMine();
  }, [loadMine, t]);
  useEffect(loadAvailability, [loadAvailability]);

  useEffect(() => {
    setQuote(null);
    hallApi.myQuote(slot).then(setQuote).catch(() => {});
  }, [slot]);

  const taken = (s: HallSlot) => !!date && (availability[date] ?? []).some((b) => b.slot === s || b.slot === 'FULL_DAY' || s === 'FULL_DAY');

  async function submit(e: FormEvent) {
    e.preventDefault();
    if (!date) return;
    setError(null);
    setBusy(true);
    try {
      await hallApi.requestMyBooking({
        flat_id: Number(flatId),
        customer_name: name,
        customer_mobile: mobile,
        event_type: eventType,
        guest_count: guests ? Number(guests) : 0,
        booking_date: date,
        slot,
        notes: notes || undefined,
      });
      setDate(null);
      setNotes('');
      loadMine();
      loadAvailability();
    } catch (err) {
      setError(hallApi.apiError(err, t('somethingWrong')));
    } finally {
      setBusy(false);
    }
  }

  async function cancel(id: number) {
    await hallApi.cancelMyBooking(id).catch((err) => setError(hallApi.apiError(err, t('somethingWrong'))));
    loadMine();
    loadAvailability();
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 pb-24 pt-8">
      <div className="mx-auto max-w-md space-y-4">
        <div className="flex items-center justify-between">
          <h1 className="text-xl font-semibold text-navy-900">{hall?.name ?? t('hall.title')}</h1>
          <LanguageToggle />
        </div>

        <HallCalendar
          year={ym.year}
          monthIndex={ym.month}
          availability={availability}
          selected={date}
          onSelect={setDate}
          onMonthChange={(year, month) => setYm({ year, month })}
        />

        {date ? (
          <form onSubmit={submit} className="space-y-3 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
            <p className="font-medium text-navy-900">
              {t('hall.requestBooking')} — {date}
            </p>
            <div className="grid grid-cols-3 gap-2">
              {(hall?.rates ?? []).map((r) => (
                <button
                  key={r.slot}
                  type="button"
                  disabled={taken(r.slot)}
                  onClick={() => setSlot(r.slot)}
                  className={`rounded-lg border px-2 py-2 text-sm ${slot === r.slot ? 'border-brand-600 bg-brand-100' : 'border-brand-200'} ${taken(r.slot) ? 'opacity-40' : ''}`}
                >
                  <span className="block font-medium">{t(`hall.slot.${r.slot}` as TranslationKey)}</span>
                  <span className="block text-xs text-navy-700">
                    {r.start_time}–{r.end_time}
                  </span>
                </button>
              ))}
            </div>
            <select className={input} value={flatId} onChange={(e) => setFlatId(e.target.value)}>
              {flats.map((f) => (
                <option key={f.id} value={f.id}>
                  {f.flat_no}
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
            <textarea className={input} rows={2} placeholder={t('hall.notes')} value={notes} onChange={(e) => setNotes(e.target.value)} />
            {quote && (
              <p className="rounded-lg bg-brand-50 p-3 text-sm text-navy-900">
                {t('hall.total')}: <b>{money(quote.rent)}</b> · {t('hall.deposit')}: <b>{money(quote.security_deposit)}</b>
                <span className="block text-xs text-navy-700">{t('hall.priceNote')}</span>
              </p>
            )}
            {error && <p className="text-sm text-status-due">{error}</p>}
            <button type="submit" disabled={busy || !quote || taken(slot)} className="w-full rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700 disabled:opacity-50">
              {t('hall.requestBooking')}
            </button>
          </form>
        ) : (
          <p className="text-center text-sm text-navy-700">{t('hall.pickDate')}</p>
        )}

        <div>
          <h2 className="mb-2 font-semibold text-navy-900">{t('hall.myBookings')}</h2>
          {mine.length === 0 && <p className="text-sm text-navy-700">{t('hall.noBookings')}</p>}
          <div className="space-y-2">
            {mine.map((b) => (
              <div key={b.id} className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
                <div className="flex items-center justify-between">
                  <p className="font-medium text-navy-900">
                    {b.booking_date} · {t(`hall.slot.${b.slot}` as TranslationKey)}
                  </p>
                  <StatusBadge status={b.status} />
                </div>
                <p className="text-sm text-navy-700">
                  {b.booking_no} · {t(`hall.event.${b.event_type}` as TranslationKey)}
                </p>
                <p className="mt-1 text-sm text-navy-900">
                  {t('hall.total')}: {money(b.total_amount)}
                  {(b.status === 'CONFIRMED' || b.status === 'COMPLETED') && ` · ${t('hall.due')}: ${money(b.rent_due)}`}
                </p>
                {b.status === 'PENDING' && (
                  <>
                    <p className="mt-1 text-xs text-navy-700">{t('hall.pendingNote')}</p>
                    <button onClick={() => cancel(b.id)} className="mt-2 text-sm text-status-due hover:underline">
                      {t('hall.cancel')}
                    </button>
                  </>
                )}
                {b.status_reason && b.status !== 'PENDING' && <p className="mt-1 text-xs text-navy-700">{b.status_reason}</p>}
              </div>
            ))}
          </div>
        </div>
      </div>
      <OwnerNav />
    </div>
  );
}
