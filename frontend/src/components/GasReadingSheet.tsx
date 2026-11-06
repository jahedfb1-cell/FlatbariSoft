import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import { useLanguage } from '../context/LanguageContext';
import * as gasApi from '../api/gas';
import type { ReadingSheetRow, SheetCheck } from '../api/gas';

const MONTHS_BN = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
const MONTHS_EN = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const clean = (v: string | null) => (v === null ? '' : String(Number(v)));
const apiError = (err: any): string => {
  const errors = err?.response?.data?.errors;
  if (errors) return (Object.values(errors) as string[][]).flat().join('\n');
  return err?.response?.data?.message || 'Error';
};

/**
 * The month's gas meter readings for every flat and the project office, on one sheet.
 * Last month's reading is already there; the office types only this month's, and the units used appear as they type.
 * The same sheet can be filled from Excel: download the template (last month's readings in it), fill it, upload it.
 */
export default function GasReadingSheet({
  year,
  month,
  onSaved,
  saveLabel,
  footer,
}: {
  year: number;
  month: number;
  onSaved?: (info: { saved: number; missing: number }) => void;
  saveLabel?: string;
  footer?: ReactNode;
}) {
  const { lang } = useLanguage();
  const L = (bn: string, en: string) => (lang === 'bn' ? bn : en);
  const [rows, setRows] = useState<ReadingSheetRow[]>([]);
  const [current, setCurrent] = useState<Record<number, string>>({});
  const [previous, setPrevious] = useState<Record<number, string>>({});
  const [problems, setProblems] = useState<string[]>([]);
  const [note, setNote] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const file = useRef<HTMLInputElement>(null);

  const load = useCallback(async () => {
    const sheet = await gasApi.getReadingSheet(year, month);
    setRows(sheet.rows);
    setCurrent(Object.fromEntries(sheet.rows.map((r) => [r.consumer_id, clean(r.current_reading)])));
    setPrevious(Object.fromEntries(sheet.rows.map((r) => [r.consumer_id, r.previous_known ? '' : clean(r.previous_reading)])));
    setProblems([]);
    setNote(null);
  }, [year, month]);

  useEffect(() => {
    load().catch((e) => setProblems([apiError(e)]));
  }, [load]);

  const prevOf = (r: ReadingSheetRow) => (r.previous_known ? r.previous_reading : previous[r.consumer_id] || null);
  const unitsOf = (r: ReadingSheetRow) => {
    const c = current[r.consumer_id];
    const p = prevOf(r);
    if (c === '' || c === undefined || p === null || p === '') return null;
    return Number(c) - Number(p);
  };
  const typed = rows.filter((r) => (current[r.consumer_id] ?? '') !== '');
  const flatsMissing = rows.filter((r) => !r.is_office && (current[r.consumer_id] ?? '') === '').length;

  async function pickFile(f: File | undefined) {
    if (!f) return;
    setBusy(true);
    setNote(null);
    try {
      const check: SheetCheck = await gasApi.checkReadingFile(year, month, f);
      setProblems(check.problems);
      // what the file holds goes into the sheet for the office to look over; nothing is saved yet
      setCurrent((c) => ({ ...c, ...Object.fromEntries(check.rows.map((r) => [r.consumer_id, clean(r.current_reading)])) }));
      setPrevious((p) => ({ ...p, ...Object.fromEntries(check.rows.filter((r) => !r.previous_known).map((r) => [r.consumer_id, clean(r.previous_reading)])) }));
      setNote(
        L(
          `ফাইল থেকে ${check.ready}টির রিডিং নেওয়া হয়েছে${check.problems.length ? `, ${check.problems.length}টিতে সমস্যা` : ''}। দেখে নিয়ে "সংরক্ষণ" চাপুন।`,
          `${check.ready} readings taken from the file${check.problems.length ? `, ${check.problems.length} with problems` : ''}. Look them over, then press Save.`,
        ),
      );
    } catch (e: any) {
      setProblems([apiError(e)]);
    } finally {
      setBusy(false);
      if (file.current) file.current.value = '';
    }
  }

  async function save() {
    setBusy(true);
    setNote(null);
    setProblems([]);
    try {
      const entries = typed.map((r) => ({
        consumer_id: r.consumer_id,
        current_reading: current[r.consumer_id],
        ...(r.previous_known ? {} : { previous_reading: previous[r.consumer_id] || undefined }),
      }));
      const res = await gasApi.saveReadingSheet(year, month, entries);
      setNote(L(`${res.saved}টি রিডিং সংরক্ষিত হয়েছে।`, `${res.saved} readings saved.`));
      await load();
      onSaved?.({ saved: res.saved, missing: flatsMissing });
    } catch (e: any) {
      setProblems(apiError(e).split('\n'));
    } finally {
      setBusy(false);
    }
  }

  const input = 'w-28 rounded-lg border border-brand-200 px-2 py-1 text-right text-sm';

  return (
    <div className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
      <div className="mb-3 flex flex-wrap items-center gap-2">
        <p className="mr-auto text-sm font-semibold text-navy-900">
          {L('মিটার রিডিং', 'Meter readings')} — {(lang === 'bn' ? MONTHS_BN : MONTHS_EN)[month - 1]} {year}
        </p>
        <button type="button" onClick={() => gasApi.downloadReadingTemplate(year, month).catch((e: any) => setProblems([apiError(e)]))} className="rounded-lg border border-brand-200 px-3 py-1.5 text-xs text-brand-700 hover:bg-brand-100">
          ⬇ {L('Excel টেমপ্লেট', 'Excel template')}
        </button>
        <button type="button" disabled={busy} onClick={() => file.current?.click()} className="rounded-lg border border-brand-200 px-3 py-1.5 text-xs text-brand-700 hover:bg-brand-100 disabled:opacity-50">
          ⬆ {L('Excel থেকে নিন', 'Load from Excel')}
        </button>
        <input ref={file} type="file" accept=".xlsx,.xls,.csv" className="hidden" onChange={(e) => pickFile(e.target.files?.[0])} />
      </div>

      <p className="mb-3 text-xs text-navy-700">
        {L('আগের মাসের রিডিং আগে থেকেই বসানো আছে — শুধু এই মাসের মিটারের রিডিং লিখুন। ব্যবহার = এই রিডিং − আগের রিডিং।', "Last month's reading is already filled in — type only this month's meter reading. Units used = this reading − last reading.")}
      </p>

      <div className="max-h-[55vh] overflow-auto rounded-lg border border-brand-100">
        <table className="w-full text-left text-sm">
          <thead className="sticky top-0 bg-brand-100 text-navy-900">
            <tr>
              <th className="px-3 py-2">{L('ফ্ল্যাট', 'Flat')}</th>
              <th className="px-3 py-2 text-right">{L('আগের রিডিং', 'Previous')}</th>
              <th className="px-3 py-2 text-right">{L('বর্তমান রিডিং', 'Current')}</th>
              <th className="px-3 py-2 text-right">{L('ব্যবহার (ইউনিট)', 'Units used')}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => {
              const u = unitsOf(r);
              return (
                <tr key={r.consumer_id} className="border-t border-brand-100">
                  <td className="px-3 py-1.5 text-navy-900">
                    <b>{r.is_office ? L('প্রজেক্ট অফিস', 'Project office') : r.flat_no}</b>
                  </td>
                  <td className="px-3 py-1.5 text-right text-navy-900">
                    {r.previous_known ? (
                      clean(r.previous_reading)
                    ) : (
                      <input
                        type="number"
                        min={0}
                        step="any"
                        value={previous[r.consumer_id] ?? ''}
                        onChange={(e) => setPrevious((p) => ({ ...p, [r.consumer_id]: e.target.value }))}
                        placeholder={L('শুরুর রিডিং', 'Start')}
                        className={input}
                      />
                    )}
                  </td>
                  <td className="px-3 py-1.5 text-right">
                    <input
                      type="number"
                      min={0}
                      step="any"
                      value={current[r.consumer_id] ?? ''}
                      onChange={(e) => setCurrent((c) => ({ ...c, [r.consumer_id]: e.target.value }))}
                      className={input}
                    />
                  </td>
                  <td className={`px-3 py-1.5 text-right font-medium ${u !== null && u < 0 ? 'text-status-due' : 'text-navy-900'}`}>
                    {u === null ? '—' : u < 0 ? L('কম!', 'Lower!') : u.toLocaleString('en-BD', { maximumFractionDigits: 2 })}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {problems.length > 0 && (
        <ul className="mt-3 list-disc space-y-0.5 pl-5 text-sm text-status-due">
          {problems.map((p, i) => (
            <li key={i}>{p}</li>
          ))}
        </ul>
      )}
      {note && <p className="mt-3 text-sm text-navy-900">{note}</p>}

      <div className="mt-3 flex flex-wrap items-center gap-3">
        <span className="text-xs text-navy-700">
          {L(`লেখা হয়েছে ${typed.length}টি · ফ্ল্যাটের রিডিং বাকি ${flatsMissing}টি`, `${typed.length} typed · ${flatsMissing} flats still without a reading`)}
        </span>
        <button type="button" disabled={busy || typed.length === 0} onClick={save} className="ml-auto rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
          {saveLabel ?? L('সংরক্ষণ', 'Save readings')}
        </button>
      </div>
      {footer}
    </div>
  );
}
