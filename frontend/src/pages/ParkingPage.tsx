import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import type { TranslationKey } from '../i18n/translations';
import LanguageToggle from '../components/LanguageToggle';
import Modal from '../components/Modal';
import { localToday } from '../components/HallCalendar';
import { apiError } from '../api/hall';
import * as flatsApi from '../api/flats';
import type { Flat } from '../api/flats';
import * as parkingApi from '../api/parking';
import type { ParkingCharge, ParkingSlot, ParkingSummary } from '../api/parking';

const money = (v: string | number) => `৳${Number(v).toLocaleString('en-BD')}`;
const input = 'w-full rounded-lg border border-brand-200 px-3 py-2';
const btn = 'rounded-lg bg-brand-600 px-4 py-2 font-medium text-white hover:bg-brand-700 disabled:opacity-50';
const btnGhost = 'rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-navy-700 hover:bg-brand-100';

export const CHARGE_COLOR: Record<ParkingCharge['status'], string> = {
  PAID: 'bg-status-paid/15 text-status-paid',
  PARTIAL: 'bg-status-partial/15 text-status-partial',
  UNPAID: 'bg-status-due/15 text-status-due',
};

export default function ParkingPage() {
  const { user } = useAuth();
  const { t } = useLanguage();
  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));
  const canManage = can('parking.manage');
  const canCollect = can('parking.payments');

  const [tab, setTab] = useState<'slots' | 'charges'>('slots');

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-4xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('parking.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        <div className="mb-4 flex gap-2">
          <button className={tab === 'slots' ? btn : btnGhost} onClick={() => setTab('slots')}>
            {t('parking.tab.slots')}
          </button>
          <button className={tab === 'charges' ? btn : btnGhost} onClick={() => setTab('charges')}>
            {t('parking.tab.charges')}
          </button>
        </div>

        {tab === 'slots' ? <SlotsTab canManage={canManage} /> : <ChargesTab canManage={canManage} canCollect={canCollect} />}
      </div>
    </div>
  );
}

function SlotsTab({ canManage }: { canManage: boolean }) {
  const { t } = useLanguage();
  const [slots, setSlots] = useState<ParkingSlot[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState<ParkingSlot | 'new' | null>(null);
  const [allocating, setAllocating] = useState<ParkingSlot | null>(null);
  const [releasing, setReleasing] = useState<ParkingSlot | null>(null);

  const load = useCallback(() => {
    parkingApi
      .listSlots()
      .then(setSlots)
      .catch((e) => setError(apiError(e, t('somethingWrong'))));
  }, [t]);
  useEffect(load, [load]);

  return (
    <div className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
      {canManage && (
        <div className="mb-3 text-right">
          <button className={btn} onClick={() => setEditing('new')}>
            {t('parking.newSlot')}
          </button>
        </div>
      )}
      {error && <p className="mb-2 text-sm text-status-due">{error}</p>}
      {slots.length === 0 ? (
        <p className="text-sm text-navy-700">{t('parking.noSlots')}</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="text-navy-700">
              <tr>
                <th className="py-2 pr-3">{t('parking.code')}</th>
                <th className="pr-3">{t('parking.type')}</th>
                <th className="pr-3">{t('parking.fee')}</th>
                <th className="pr-3">{t('parking.holder')}</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {slots.map((s) => (
                <tr key={s.id} className={`border-t border-brand-100 ${s.is_active ? '' : 'opacity-50'}`}>
                  <td className="py-2 pr-3 font-medium">
                    {s.code}
                    {s.zone && <span className="block text-xs font-normal text-navy-700">{s.zone}</span>}
                  </td>
                  <td className="pr-3">{t(`parking.type.${s.vehicle_type}` as TranslationKey)}</td>
                  <td className="pr-3">{money(s.monthly_fee)}</td>
                  <td className="pr-3">
                    {s.holder ? (
                      <>
                        {s.holder.flat_no}
                        {s.holder.vehicle_no && <span className="text-navy-700"> · {s.holder.vehicle_no}</span>}
                        {Number(s.holder.monthly_fee) !== Number(s.monthly_fee) && <span className="block text-xs text-navy-700">{money(s.holder.monthly_fee)}</span>}
                      </>
                    ) : (
                      <span className="text-status-paid">{t('parking.free')}</span>
                    )}
                  </td>
                  <td className="whitespace-nowrap text-right">
                    {canManage && (
                      <>
                        {s.holder ? (
                          <button className="mr-2 text-status-due hover:underline" onClick={() => setReleasing(s)}>
                            {t('parking.release')}
                          </button>
                        ) : (
                          s.is_active && (
                            <button className="mr-2 text-brand-700 hover:underline" onClick={() => setAllocating(s)}>
                              {t('parking.allocate')}
                            </button>
                          )
                        )}
                        <button className="text-brand-700 hover:underline" onClick={() => setEditing(s)}>
                          {t('parking.edit')}
                        </button>
                      </>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {editing && (
        <SlotModal
          slot={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onDone={() => {
            setEditing(null);
            load();
          }}
        />
      )}
      {allocating && (
        <AllocateModal
          slot={allocating}
          onClose={() => setAllocating(null)}
          onDone={() => {
            setAllocating(null);
            load();
          }}
        />
      )}
      {releasing?.holder && (
        <ReleaseModal
          slot={releasing}
          onClose={() => setReleasing(null)}
          onDone={() => {
            setReleasing(null);
            load();
          }}
        />
      )}
    </div>
  );
}

function SlotModal({ slot, onClose, onDone }: { slot: ParkingSlot | null; onClose: () => void; onDone: () => void }) {
  const { t } = useLanguage();
  const [code, setCode] = useState(slot?.code ?? '');
  const [zone, setZone] = useState(slot?.zone ?? '');
  const [type, setType] = useState<parkingApi.VehicleType>(slot?.vehicle_type ?? 'CAR');
  const [fee, setFee] = useState(slot ? String(Number(slot.monthly_fee)) : '');
  const [active, setActive] = useState(slot?.is_active ?? true);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    const data = { code, zone: zone || null, vehicle_type: type, monthly_fee: fee, is_active: active };
    try {
      if (slot) await parkingApi.updateSlot(slot.id, data);
      else await parkingApi.createSlot(data);
      onDone();
    } catch (err) {
      setError(apiError(err, t('somethingWrong')));
    }
  }

  return (
    <Modal title={slot ? `${t('parking.edit')} — ${slot.code}` : t('parking.newSlot')} onClose={onClose}>
      <form onSubmit={submit} className="space-y-3">
        <input required className={input} placeholder={t('parking.code')} value={code} onChange={(e) => setCode(e.target.value)} />
        <input className={input} placeholder={t('parking.zone')} value={zone} onChange={(e) => setZone(e.target.value)} />
        <select className={input} value={type} onChange={(e) => setType(e.target.value as parkingApi.VehicleType)}>
          {parkingApi.VEHICLE_TYPES.map((v) => (
            <option key={v} value={v}>
              {t(`parking.type.${v}` as TranslationKey)}
            </option>
          ))}
        </select>
        <div>
          <input required type="number" min={0} step="0.01" className={input} placeholder={t('parking.fee')} value={fee} onChange={(e) => setFee(e.target.value)} />
          <p className="mt-1 text-xs text-navy-700">{t('parking.feeHint')}</p>
        </div>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={active} onChange={(e) => setActive(e.target.checked)} /> {t('parking.active')}
        </label>
        {error && <p className="text-sm text-status-due">{error}</p>}
        <button type="submit" className={`${btn} w-full`}>
          {t('parking.save')}
        </button>
      </form>
    </Modal>
  );
}

function AllocateModal({ slot, onClose, onDone }: { slot: ParkingSlot; onClose: () => void; onDone: () => void }) {
  const { t } = useLanguage();
  const [flats, setFlats] = useState<Flat[]>([]);
  const [flatId, setFlatId] = useState('');
  const [vehicleNo, setVehicleNo] = useState('');
  const [from, setFrom] = useState(localToday());
  const [fee, setFee] = useState(String(Number(slot.monthly_fee)));
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    flatsApi.listFlats().then((f: any) => setFlats(Array.isArray(f) ? f : f.data ?? [])).catch(() => {});
  }, []);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await parkingApi.allocateSlot(slot.id, { flat_id: Number(flatId), vehicle_no: vehicleNo || undefined, from_date: from, monthly_fee: fee });
      onDone();
    } catch (err) {
      setError(apiError(err, t('somethingWrong')));
    }
  }

  return (
    <Modal title={`${t('parking.allocate')} — ${slot.code}`} onClose={onClose}>
      <form onSubmit={submit} className="space-y-3">
        <select required className={input} value={flatId} onChange={(e) => setFlatId(e.target.value)}>
          <option value="">{t('parking.flat')}</option>
          {flats.map((f) => (
            <option key={f.id} value={f.id}>
              {f.flat_no}
            </option>
          ))}
        </select>
        <input className={input} placeholder={t('parking.vehicleNo')} value={vehicleNo} onChange={(e) => setVehicleNo(e.target.value)} />
        <div className="grid grid-cols-2 gap-2">
          <input required type="date" className={input} value={from} onChange={(e) => setFrom(e.target.value)} />
          <input required type="number" min={0} step="0.01" className={input} placeholder={t('parking.fee')} value={fee} onChange={(e) => setFee(e.target.value)} />
        </div>
        {error && <p className="text-sm text-status-due">{error}</p>}
        <button type="submit" className={`${btn} w-full`}>
          {t('parking.allocate')}
        </button>
      </form>
    </Modal>
  );
}

function ReleaseModal({ slot, onClose, onDone }: { slot: ParkingSlot; onClose: () => void; onDone: () => void }) {
  const { t } = useLanguage();
  const [to, setTo] = useState(localToday());
  const [error, setError] = useState<string | null>(null);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await parkingApi.releaseAllocation(slot.holder!.allocation_id, to);
      onDone();
    } catch (err) {
      setError(apiError(err, t('somethingWrong')));
    }
  }

  return (
    <Modal title={`${t('parking.release')} — ${slot.code} (${slot.holder?.flat_no})`} onClose={onClose}>
      <form onSubmit={submit} className="space-y-3">
        <label className="block text-sm text-navy-700">
          {t('parking.to')}
          <input required type="date" className={`${input} mt-1`} value={to} onChange={(e) => setTo(e.target.value)} />
        </label>
        {error && <p className="text-sm text-status-due">{error}</p>}
        <button type="submit" className={`${btn} w-full`}>
          {t('parking.release')}
        </button>
      </form>
    </Modal>
  );
}

function ChargesTab({ canManage, canCollect }: { canManage: boolean; canCollect: boolean }) {
  const { t } = useLanguage();
  const now = new Date();
  const [year, setYear] = useState(now.getFullYear());
  const [month, setMonth] = useState(now.getMonth() + 1);
  const [charges, setCharges] = useState<ParkingCharge[]>([]);
  const [summary, setSummary] = useState<ParkingSummary | null>(null);
  const [msg, setMsg] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [paying, setPaying] = useState<ParkingCharge | null>(null);

  const load = useCallback(() => {
    parkingApi.listCharges(year, month).then(setCharges).catch((e) => setError(apiError(e, t('somethingWrong'))));
    parkingApi.getSummary(year, month).then(setSummary).catch(() => {});
  }, [year, month, t]);
  useEffect(load, [load]);

  async function generate() {
    setError(null);
    setMsg(null);
    try {
      const r = await parkingApi.generateCharges(year, month);
      setMsg(`${t('parking.generated')}: ${r.created} · ${t('parking.alreadyHad')}: ${r.skipped}`);
      load();
    } catch (e) {
      setError(apiError(e, t('somethingWrong')));
    }
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        <select className="rounded-lg border border-brand-200 px-3 py-1.5" value={month} onChange={(e) => setMonth(Number(e.target.value))}>
          {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
            <option key={m} value={m}>
              {new Date(2000, m - 1, 1).toLocaleDateString('en-GB', { month: 'long' })}
            </option>
          ))}
        </select>
        <input type="number" className="w-24 rounded-lg border border-brand-200 px-3 py-1.5" value={year} onChange={(e) => setYear(Number(e.target.value))} />
        {canManage && (
          <button className={`${btn} ml-auto`} onClick={generate}>
            {t('parking.generate')}
          </button>
        )}
      </div>
      {msg && <p className="text-sm text-status-paid">{msg}</p>}
      {error && <p className="text-sm text-status-due">{error}</p>}

      {summary && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <Card label={t('parking.charged')} value={money(summary.charged)} />
          <Card label={t('parking.collected')} value={money(summary.collected)} tone="paid" />
          <Card label={t('parking.due')} value={money(summary.due)} tone="due" />
          <Card label={t('parking.slotsTaken')} value={`${summary.slots_taken}/${summary.slots_total}`} />
        </div>
      )}

      <div className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
        {charges.length === 0 ? (
          <p className="text-sm text-navy-700">{t('parking.noCharges')}</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="text-navy-700">
                <tr>
                  <th className="py-2 pr-3">{t('parking.code')}</th>
                  <th className="pr-3">{t('parking.flat')}</th>
                  <th className="pr-3">{t('parking.fee')}</th>
                  <th className="pr-3">{t('parking.paid')}</th>
                  <th className="pr-3">{t('parking.due')}</th>
                  <th className="pr-3">{t('common.status')}</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {charges.map((c) => (
                  <tr key={c.id} className="border-t border-brand-100">
                    <td className="py-2 pr-3 font-medium">{c.slot_code}</td>
                    <td className="pr-3">
                      {c.flat_no}
                      {c.vehicle_no && <span className="block text-xs text-navy-700">{c.vehicle_no}</span>}
                    </td>
                    <td className="pr-3">{money(c.amount)}</td>
                    <td className="pr-3">{money(c.paid)}</td>
                    <td className="pr-3">{money(c.due)}</td>
                    <td className="pr-3">
                      <span className={`rounded-full px-2 py-0.5 text-xs ${CHARGE_COLOR[c.status]}`}>{t(`parking.status.${c.status}` as TranslationKey)}</span>
                    </td>
                    <td className="text-right">
                      {canCollect && c.status !== 'PAID' && (
                        <button className="text-brand-700 hover:underline" onClick={() => setPaying(c)}>
                          {t('parking.collect')}
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {paying && (
        <PayModal
          charge={paying}
          onClose={() => setPaying(null)}
          onDone={() => {
            setPaying(null);
            load();
          }}
        />
      )}
    </div>
  );
}

function Card({ label, value, tone }: { label: string; value: string; tone?: 'paid' | 'due' }) {
  const color = tone === 'due' ? 'text-status-due' : tone === 'paid' ? 'text-status-paid' : 'text-navy-900';
  return (
    <div className="rounded-xl border border-brand-200 bg-white p-3 shadow-sm">
      <p className="text-xs text-navy-700">{label}</p>
      <p className={`text-lg font-semibold ${color}`}>{value}</p>
    </div>
  );
}

function PayModal({ charge, onClose, onDone }: { charge: ParkingCharge; onClose: () => void; onDone: () => void }) {
  const { t } = useLanguage();
  const [amount, setAmount] = useState(String(Number(charge.due)));
  const [method, setMethod] = useState('CASH');
  const [paidOn, setPaidOn] = useState(localToday());
  const [reference, setReference] = useState('');
  const [error, setError] = useState<string | null>(null);

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await parkingApi.payCharge(charge.id, { amount, method, paid_on: paidOn, reference_no: reference || undefined });
      onDone();
    } catch (err) {
      setError(apiError(err, t('somethingWrong')));
    }
  }

  return (
    <Modal title={`${t('parking.collect')} — ${charge.slot_code} (${charge.flat_no})`} onClose={onClose}>
      <form onSubmit={submit} className="space-y-3">
        <p className="text-sm text-navy-700">
          {t('parking.due')}: <b>{money(charge.due)}</b>
        </p>
        <div className="grid grid-cols-2 gap-2">
          <input required type="number" min="0.01" step="0.01" className={input} value={amount} onChange={(e) => setAmount(e.target.value)} />
          <select className={input} value={method} onChange={(e) => setMethod(e.target.value)}>
            {['CASH', 'BANK', 'BKASH', 'NAGAD'].map((m) => (
              <option key={m}>{m}</option>
            ))}
          </select>
          <input required type="date" className={input} value={paidOn} onChange={(e) => setPaidOn(e.target.value)} />
          <input className={input} placeholder={t('hall.reference')} value={reference} onChange={(e) => setReference(e.target.value)} />
        </div>
        {error && <p className="text-sm text-status-due">{error}</p>}
        <button type="submit" className={`${btn} w-full`}>
          {t('parking.collect')}
        </button>
      </form>
    </Modal>
  );
}
