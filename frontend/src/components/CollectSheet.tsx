import { useMemo, useState } from 'react';
import Sheet from './Sheet';
import { useLanguage } from '../context/LanguageContext';
import { localToday } from './HallCalendar';
import { apiError } from '../api/hall';
import { openPdf, openPdfAfter } from '../utils/download';
import * as api from '../api/contributions';
import type { Book, FlatLedger } from '../api/contributions';

const money = (v: number | string) => Number(v).toLocaleString('en-BD', { maximumFractionDigits: 2 });

const METHODS: { value: string; icon: string; bn: string; en: string }[] = [
  { value: 'CASH', icon: '💵', bn: 'নগদ', en: 'Cash' },
  { value: 'BANK', icon: '🏦', bn: 'ব্যাংক', en: 'Bank' },
  { value: 'BKASH', icon: '📱', bn: 'bKash', en: 'bKash' },
  { value: 'NAGAD', icon: '📲', bn: 'Nagad', en: 'Nagad' },
];

interface Line {
  headId: string;
  amount: string;
}

/**
 * Taking a payment from a flat owner: one sheet, made for a thumb. Every head is picked from a big drop-down, the amount
 * sits small beside it, and one payment can cover several heads - the receipt is one PDF for all of them.
 * On a phone it slides up from the bottom; on a computer it is a centred card.
 */
export default function CollectSheet({
  flatId,
  flatNo,
  owner,
  book,
  ledger,
  lastEntryId,
  onClose,
  onDone,
}: {
  flatId: number;
  flatNo: string;
  owner: string | null;
  book: Book;
  ledger: FlatLedger;
  /** the highest entry id before this payment, so the new rows can be told apart */
  lastEntryId: number;
  onClose: () => void;
  onDone: () => void;
}) {
  const { lang } = useLanguage();
  const L = (bn: string, en: string) => (lang === 'bn' ? bn : en);
  const nameOf = (x: { name_bn: string; name_en: string }) => (lang === 'bn' ? x.name_bn : x.name_en);

  const [lines, setLines] = useState<Line[]>([{ headId: String(book.heads[0]?.id ?? ''), amount: '' }]);
  const [method, setMethod] = useState('CASH');
  const [date, setDate] = useState(localToday());
  const [reference, setReference] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState<{ ids: number[]; no: string; total: number } | null>(null);

  const total = useMemo(() => lines.reduce((c, l) => c + (Number(l.amount) || 0), 0), [lines]);
  const due = ledger.balance === null ? null : Number(ledger.balance);
  const setLine = (i: number, patch: Partial<Line>) => setLines((rows) => rows.map((r, k) => (k === i ? { ...r, ...patch } : r)));
  const needsRef = method !== 'CASH';

  function addRow() {
    const used = new Set(lines.map((l) => l.headId));
    const next = book.heads.find((h) => !used.has(String(h.id)));
    if (next) setLines((rows) => [...rows, { headId: String(next.id), amount: '' }]);
  }

  async function submit() {
    const payload = lines.filter((l) => l.headId && Number(l.amount) > 0).map((l) => ({ contribution_head_id: Number(l.headId), amount: l.amount }));
    if (payload.length === 0) {
      setError(L('অন্তত একটি খাত বেছে টাকার পরিমাণ লিখুন।', 'Pick a head and enter an amount.'));
      return;
    }
    setError(null);
    setBusy(true);
    try {
      let fresh: { id: number; receipt_no: string | null }[] = [];
      // one receipt for the whole payment opens at once, ready to print or save as PDF
      await openPdfAfter(
        () => api.collectLines(flatId, { lines: payload, received_on: date, method, reference_no: reference.trim() || undefined }),
        (all: FlatLedger[]) => {
          fresh = all
            .flatMap((l) => l.entries)
            .filter((x) => x.id > lastEntryId && !x.is_reversal && x.method !== 'OPENING')
            .sort((a, b) => a.id - b.id);
          return fresh.length ? { path: `/contributions/receipt?${fresh.map((x) => `ids[]=${x.id}`).join('&')}`, filename: `receipt-${fresh[0].receipt_no}.pdf` } : null;
        },
      );
      setDone({ ids: fresh.map((x) => x.id), no: fresh.map((x) => x.receipt_no).filter(Boolean).join(', '), total: payload.reduce((c, l) => c + Number(l.amount), 0) });
      onDone();
    } catch (e) {
      setError(apiError(e, L('পেমেন্ট নেওয়া যায়নি।', 'Could not take the payment.')));
    } finally {
      setBusy(false);
    }
  }

  const title = L('জমা নিন', 'Take payment');
  const sub = `${L('ফ্ল্যাট', 'Flat')} ${flatNo}${owner ? ` · ${owner}` : ''} · ${nameOf(book)}`;

  // ---- after the money is taken
  if (done) {
    return (
      <Sheet title={title} sub={sub} onClose={onClose}>
        <div className="space-y-3 py-4 text-center">
          <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-status-paid/15 text-3xl text-status-paid">✓</div>
          <p className="text-3xl font-bold text-navy-900">৳{money(done.total)}</p>
          <p className="text-sm font-medium text-status-paid">{L('জমা নেওয়া হয়েছে', 'Payment taken')}</p>
          {done.no && (
            <p className="text-sm text-navy-700">
              {L('রসিদ', 'Receipt')}: <span className="font-semibold text-navy-900">{done.no}</span>
            </p>
          )}
          <div className="flex flex-col gap-2 pt-2 sm:flex-row sm:justify-center">
            {done.ids.length > 0 && (
              <button
                className="rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white hover:bg-brand-700"
                onClick={() => openPdf(`/contributions/receipt?${done.ids.map((i) => `ids[]=${i}`).join('&')}`, undefined, `receipt-${done.no.split(',')[0]}.pdf`).catch(() => setError(L('PDF খোলা যায়নি।', 'Could not open the PDF.')))}
              >
                🖨 {L('রসিদ আবার প্রিন্ট', 'Print the receipt again')}
              </button>
            )}
            <button className="rounded-xl border border-brand-200 px-5 py-3 text-sm font-medium text-navy-700 hover:bg-brand-100" onClick={onClose}>
              {L('বন্ধ করুন', 'Close')}
            </button>
          </div>
          {error && <p className="text-sm text-status-due">{error}</p>}
        </div>
      </Sheet>
    );
  }

  return (
    <Sheet
      title={title}
      sub={sub}
      onClose={onClose}
      footer={
        <div className="space-y-2">
          {error && <p className="rounded-lg bg-status-due/10 px-3 py-2 text-xs text-status-due">{error}</p>}
          <button
            type="button"
            onClick={submit}
            disabled={busy || total <= 0}
            className="w-full rounded-xl bg-brand-600 py-3.5 text-base font-semibold text-white shadow transition hover:bg-brand-700 disabled:opacity-40"
          >
            {busy ? '…' : `${title} · ৳${money(total)}`}
          </button>
          <p className="text-center text-[11px] text-navy-700">{L('রসিদ PDF নিজে থেকেই খুলবে', 'The receipt PDF opens by itself')}</p>
        </div>
      }
    >
      <div className="space-y-5">
        {/* where this flat stands in this book */}
        {due !== null && (
          <div className="flex items-center justify-between gap-3 rounded-2xl bg-gradient-to-br from-brand-700 to-brand-600 p-4 text-white">
            <div>
              <p className="text-xs text-white/80">{due > 0 ? L('এখনও বাকি', 'Still due') : due < 0 ? L('বেশি দেওয়া আছে', 'Paid in excess') : L('পুরো পরিশোধিত', 'Fully paid')}</p>
              <p className="text-2xl font-bold leading-tight">৳{money(Math.abs(due))}</p>
            </div>
            {due > 0 && (
              <button
                type="button"
                className="shrink-0 rounded-full bg-white/20 px-3 py-1.5 text-xs font-medium hover:bg-white/30"
                onClick={() => setLine(0, { amount: String(due) })}
              >
                {L('পুরো বাকি দিন', 'Pay the full due')}
              </button>
            )}
          </div>
        )}

        {/* the heads: a big drop-down, a small amount */}
        <div className="space-y-3">
          <p className="text-xs font-medium text-navy-700">{L('কোন খাতে কত টাকা?', 'Which head, how much?')}</p>
          {lines.map((line, i) => {
            const taken = new Set(lines.filter((_, k) => k !== i).map((l) => l.headId)); // a head picked in another row is not offered again
            return (
              <div key={i} className="rounded-2xl border border-brand-200 bg-brand-50/50 p-3">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                  <select
                    aria-label={L('খাত', 'Head')}
                    className="h-12 min-w-0 flex-1 rounded-xl border-2 border-brand-200 bg-white px-3 text-base font-medium text-navy-900 outline-none focus:border-brand-500"
                    value={line.headId}
                    onChange={(e) => setLine(i, { headId: e.target.value })}
                  >
                    {book.heads
                      .filter((h) => !taken.has(String(h.id)))
                      .map((h) => (
                        <option key={h.id} value={h.id}>
                          {nameOf(h)}
                        </option>
                      ))}
                  </select>
                  <div className="flex items-center gap-2">
                    <div className="relative">
                      <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-navy-700">৳</span>
                      <input
                        inputMode="decimal"
                        type="number"
                        min="0"
                        step="0.01"
                        aria-label={L('টাকার পরিমাণ', 'Amount')}
                        placeholder="0"
                        className="h-11 w-32 rounded-xl border-2 border-brand-200 bg-white pl-7 pr-2 text-right text-base font-semibold text-navy-900 outline-none focus:border-brand-500"
                        value={line.amount}
                        onChange={(e) => setLine(i, { amount: e.target.value })}
                      />
                    </div>
                    {lines.length > 1 && (
                      <button
                        type="button"
                        aria-label={L('বাদ দিন', 'Remove')}
                        className="flex h-10 w-10 items-center justify-center rounded-full text-status-due hover:bg-status-due/10"
                        onClick={() => setLines((rows) => rows.filter((_, k) => k !== i))}
                      >
                        ✕
                      </button>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
          {lines.length < book.heads.length && (
            <button type="button" onClick={addRow} className="w-full rounded-xl border-2 border-dashed border-brand-300 py-2.5 text-sm font-medium text-brand-700 hover:bg-brand-50">
              + {L('আরেকটি খাত', 'Another head')}
            </button>
          )}
        </div>

        {/* the total */}
        <div className="flex items-center justify-between rounded-2xl bg-brand-50 px-4 py-3">
          <span className="text-sm text-navy-700">{L('মোট', 'Total')}</span>
          <span className="text-2xl font-bold text-navy-900">৳{money(total)}</span>
        </div>

        {/* how it was paid */}
        <div className="space-y-2">
          <p className="text-xs font-medium text-navy-700">{L('কীভাবে দিয়েছেন?', 'How was it paid?')}</p>
          <div className="grid grid-cols-4 gap-1.5 rounded-xl bg-brand-100 p-1">
            {METHODS.map((m) => (
              <button
                key={m.value}
                type="button"
                onClick={() => setMethod(m.value)}
                className={`flex flex-col items-center gap-0.5 rounded-lg px-1 py-2 text-xs font-medium transition ${method === m.value ? 'bg-white text-brand-700 shadow' : 'text-navy-700 hover:text-navy-900'}`}
              >
                <span className="text-lg leading-none">{m.icon}</span>
                {lang === 'bn' ? m.bn : m.en}
              </button>
            ))}
          </div>
          {needsRef && (
            <input
              className="h-11 w-full rounded-xl border-2 border-brand-200 bg-white px-3 text-sm outline-none focus:border-brand-500"
              placeholder={L('চেক / ট্রানজেকশন নম্বর', 'Cheque / transaction no.')}
              value={reference}
              onChange={(e) => setReference(e.target.value)}
            />
          )}
          <label className="flex items-center justify-between gap-3 rounded-xl border-2 border-brand-200 bg-white px-3 py-2 text-sm text-navy-700">
            {L('তারিখ', 'Date')}
            <input type="date" required value={date} max={localToday()} onChange={(e) => setDate(e.target.value)} className="bg-transparent text-right text-base font-medium text-navy-900 outline-none" />
          </label>
        </div>
      </div>
    </Sheet>
  );
}
