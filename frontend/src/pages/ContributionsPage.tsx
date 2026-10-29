import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import FlatLabel from '../components/FlatLabel';
import { useAuth } from '../context/AuthContext';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import Modal from '../components/Modal';
import { localToday } from '../components/HallCalendar';
import { apiError } from '../api/hall';
import { blobErrorMessage } from '../utils/download';
import * as api from '../api/contributions';
import type { Book, FlatLedger, ImportRow, Statement, StatementRow } from '../api/contributions';

const money = (v: string | number) => Number(v).toLocaleString('en-BD', { maximumFractionDigits: 0 });
const input = 'w-full rounded-lg border border-brand-200 px-3 py-2';
const btn = 'rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50';
const btnGhost = 'rounded-lg border border-brand-200 px-3 py-2 text-sm text-navy-700 hover:bg-brand-100 disabled:opacity-50';

/** Flat-wise construction-cost collection: the manager's sheet, but worked out by the system. */
export default function ContributionsPage() {
  const { user } = useAuth();
  const { t, lang } = useLanguage();
  const can = (p: string) => !!user && (user.roles.includes('super_admin') || user.permissions.includes(p));
  const canCollect = can('contribution.collect');
  const canManage = can('contribution.manage');

  const [books, setBooks] = useState<Book[]>([]);
  const [bookCode, setBookCode] = useState('CONSTRUCTION');
  const [data, setData] = useState<Statement | null>(null);
  const [onlyDue, setOnlyDue] = useState(false);
  // The open ledger lives in the address (?flat=ID), so the flat and owner names in every list can link straight to it.
  const [params, setParams] = useSearchParams();
  const openFlat = Number(params.get('flat')) || null;
  const setOpenFlat = (id: number | null) => setParams(id ? { flat: String(id) } : {}, { replace: false });
  const [showImport, setShowImport] = useState(false);
  const [editingDefault, setEditingDefault] = useState(false);
  const [defaultTarget, setDefaultTarget] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);

  useEffect(() => {
    api.listBooks().then(setBooks).catch((e) => setError(apiError(e, t('somethingWrong'))));
  }, [t]);

  // Switching tabs quickly must not let a slow reply for the old book overwrite the new book's table.
  const latest = useRef(0);
  const load = useCallback(() => {
    const id = ++latest.current;
    setData(null);
    api
      .getStatement(bookCode)
      .then((d) => {
        if (id !== latest.current) return;
        setData(d);
        setDefaultTarget(String(Number(d.default_target)));
      })
      .catch((e) => id === latest.current && setError(apiError(e, t('somethingWrong'))));
  }, [bookCode, t]);
  useEffect(load, [load]);

  const book = books.find((b) => b.code === bookCode);
  const bookName = (b: { name_bn: string; name_en: string }) => (lang === 'bn' ? b.name_bn : b.name_en);

  async function run(key: string, action: () => Promise<unknown>) {
    setError(null);
    setBusy(key);
    try {
      await action();
    } catch (e) {
      setError(await blobErrorMessage(e, t('somethingWrong')));
    } finally {
      setBusy(null);
    }
  }

  async function saveDefault(e: FormEvent) {
    e.preventDefault();
    await run('default', async () => {
      await api.setDefaultTarget(bookCode, defaultTarget);
      setEditingDefault(false);
      load();
    });
  }

  const rows = (data?.rows ?? []).filter((r) => !onlyDue || r.status === 'DUE');
  const totals = data?.totals;

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-6xl">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('contrib.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        <div className="mb-3 flex flex-wrap gap-2">
          {books.map((b) => (
            <button key={b.code} className={b.code === bookCode ? btn : btnGhost} onClick={() => setBookCode(b.code)}>
              {bookName(b)}
            </button>
          ))}
        </div>

        <div className="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-brand-200 bg-white p-3 shadow-sm">
          <span className="text-sm text-navy-700">{t('contrib.defaultTarget')}:</span>
          {editingDefault ? (
            <form onSubmit={saveDefault} className="flex gap-2">
              <input type="number" min={0} required className="w-36 rounded-lg border border-brand-200 px-2 py-1" value={defaultTarget} onChange={(e) => setDefaultTarget(e.target.value)} />
              <button className={btn}>{t('hall.save')}</button>
            </form>
          ) : (
            <>
              <b className="text-navy-900">{data ? (data.book.has_target ? `৳${money(data.default_target)}` : t('contrib.noTarget')) : '…'}</b>
              {canManage && (
                <button className="text-sm text-brand-700 hover:underline" onClick={() => setEditingDefault(true)}>
                  {t('contrib.editTarget')}
                </button>
              )}
            </>
          )}
          <div className="ml-auto flex flex-wrap gap-2">
            <label className="flex items-center gap-1 text-sm text-navy-700">
              <input type="checkbox" checked={onlyDue} onChange={(e) => setOnlyDue(e.target.checked)} /> {t('contrib.onlyDue')}
            </label>
            <button className={btnGhost} disabled={busy !== null} onClick={() => run('pdf', () => api.printStatement(bookCode))}>
              {t('contrib.pdf')}
            </button>
            <button className={btnGhost} disabled={busy !== null} onClick={() => run('xlsx', () => api.downloadStatementExcel(bookCode))}>
              {t('contrib.excel')}
            </button>
            <button className={btnGhost} disabled={busy !== null} onClick={() => run('all', () => api.printAllLedgers(bookCode))}>
              {t('contrib.allLedgers')}
            </button>
            {canManage && (
              <button className={btn} onClick={() => setShowImport(true)}>
                {t('contrib.import')}
              </button>
            )}
          </div>
        </div>

        {error && <p className="mb-3 text-sm text-status-due">{error}</p>}

        {data && totals && (
          <div className="overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
            <table className="w-full text-left text-sm">
              <thead className="bg-brand-100 text-navy-900">
                <tr>
                  <th className="px-3 py-2">{t('contrib.flat')}</th>
                  {data.heads.map((h) => (
                    <th key={h.code} className="px-3 py-2 text-right">
                      {lang === 'bn' ? h.name_bn : h.name_en}
                    </th>
                  ))}
                  <th className="px-3 py-2 text-right">{t('contrib.collected')}</th>
                  <th className="px-3 py-2 text-right">{t('contrib.target')}</th>
                  <th className="px-3 py-2 text-right">{t('contrib.balance')}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <Row key={r.flat_id} r={r} heads={data.heads.map((h) => h.code)} onOpen={() => setOpenFlat(r.flat_id)} />
                ))}
                <tr className="border-t-2 border-brand-300 bg-brand-50 font-semibold">
                  <td className="px-3 py-2">{t('contrib.total')}</td>
                  {data.heads.map((h) => (
                    <td key={h.code} className="px-3 py-2 text-right">
                      {money(totals[h.code])}
                    </td>
                  ))}
                  <td className="px-3 py-2 text-right">{money(totals.collected)}</td>
                  <td className="px-3 py-2 text-right">{money(totals.target)}</td>
                  <td className="px-3 py-2 text-right">
                    <span className="text-status-due">{money(totals.balance_due)}</span> / <span className="text-status-paid">({money(totals.excess)})</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        )}
      </div>

      {openFlat !== null && data && (
        <LedgerModal flatId={openFlat} books={books} startBook={bookCode} canCollect={canCollect} canManage={canManage} onClose={() => setOpenFlat(null)} onChanged={load} />
      )}
      {showImport && (
        <ImportModal
          book={bookCode}
          bookTitle={book ? bookName(book) : ''}
          heads={data?.heads ?? []}
          onClose={() => setShowImport(false)}
          onDone={() => {
            setShowImport(false);
            load();
          }}
        />
      )}
    </div>
  );
}

function Row({ r, heads, onOpen }: { r: StatementRow; heads: string[]; onOpen: () => void }) {
  const bal = r.balance === null ? null : Number(r.balance);
  return (
    <tr className="cursor-pointer border-t border-brand-100 hover:bg-brand-50" onClick={onOpen}>
      <td className="px-3 py-2">
        <FlatLabel flatId={r.flat_id} flatNo={r.flat_no} owner={r.owner} tenant={r.tenant} />
      </td>
      {heads.map((c) => (
        <td key={c} className="px-3 py-2 text-right text-navy-700">
          {Number(r.heads[c]) ? money(r.heads[c]) : '–'}
        </td>
      ))}
      <td className="px-3 py-2 text-right">{money(r.collected)}</td>
      <td className="px-3 py-2 text-right">
        {r.target === null ? '–' : money(r.target)}
        {r.has_own_target && <span className="text-xs text-brand-700"> *</span>}
      </td>
      <td className={`px-3 py-2 text-right font-medium ${bal === null ? 'text-navy-700' : bal > 0 ? 'text-status-due' : bal < 0 ? 'text-status-paid' : 'text-navy-700'}`}>
        {bal === null || bal === 0 ? '–' : bal > 0 ? money(bal) : `(${money(-bal)})`}
      </td>
    </tr>
  );
}

/** One flat owner's ledgers - one per book - with the receipt form, reversals and the flat's own targets. */
function LedgerModal({
  flatId,
  books,
  startBook,
  canCollect,
  canManage,
  onClose,
  onChanged,
}: {
  flatId: number;
  books: Book[];
  startBook: string;
  canCollect: boolean;
  canManage: boolean;
  onClose: () => void;
  onChanged: () => void;
}) {
  const { t, lang } = useLanguage();
  const [ledgers, setLedgers] = useState<FlatLedger[] | null>(null);
  const [bookCode, setBookCode] = useState(startBook);
  const [error, setError] = useState<string | null>(null);
  const [headId, setHeadId] = useState('');
  const [amount, setAmount] = useState('');
  const [method, setMethod] = useState('CASH');
  const [date, setDate] = useState(localToday());
  const [reference, setReference] = useState('');
  const [reversing, setReversing] = useState<number | null>(null);
  const [reason, setReason] = useState('');
  const [target, setTarget] = useState('');

  const load = useCallback(() => {
    api
      .getFlatLedgers(flatId)
      .then(setLedgers)
      .catch((e) => setError(apiError(e, t('somethingWrong'))));
  }, [flatId, t]);
  useEffect(load, [load]);

  const ledger = ledgers?.find((l) => l.book.code === bookCode);
  const book = books.find((b) => b.code === bookCode);

  // keep the form in step with the book on screen
  useEffect(() => {
    setHeadId(String(book?.heads[0]?.id ?? ''));
    setTarget(ledger?.target ? String(Number(ledger.target)) : '');
    setReversing(null);
    setError(null);
  }, [bookCode, book, ledger?.target]);

  async function act(action: () => Promise<unknown>) {
    setError(null);
    try {
      await action();
      load();
      onChanged();
    } catch (e) {
      setError(apiError(e, t('somethingWrong')));
    }
  }

  if (!ledgers || !ledger || !book) return <Modal title="…" onClose={onClose}>{error ? <p className="text-status-due">{error}</p> : t('loading')}</Modal>;

  const bal = ledger.balance === null ? null : Number(ledger.balance);
  const nameOf = (x: { name_bn: string; name_en: string }) => (lang === 'bn' ? x.name_bn : x.name_en);
  const headName = (e: { head_name_bn: string; head_name_en: string }) => (lang === 'bn' ? e.head_name_bn : e.head_name_en);

  return (
    <Modal title={`${t('contrib.ledger')} — ${ledger.flat_no}${ledger.owner ? ` (${ledger.owner})` : ''}`} onClose={onClose}>
      <div className="max-h-[70vh] space-y-4 overflow-y-auto text-sm text-navy-900">
        <div className="flex flex-wrap gap-2">
          {ledgers.map((l) => (
            <button key={l.book.code} className={l.book.code === bookCode ? btn : btnGhost} onClick={() => setBookCode(l.book.code)}>
              {nameOf(l.book)}
            </button>
          ))}
        </div>

        <div className="rounded-lg bg-brand-50 p-3">
          <p className="flex justify-between">
            <span>{t('contrib.target')}</span>
            <b>{ledger.target === null ? t('contrib.noTarget') : `৳${money(ledger.target)}`}</b>
          </p>
          <p className="flex justify-between">
            <span>{t('contrib.collected')}</span>
            <b>৳{money(ledger.collected)}</b>
          </p>
          {bal !== null && (
            <p className="flex justify-between">
              <span>{bal >= 0 ? t('contrib.stillDue') : t('contrib.paidExcess')}</span>
              <b className={bal > 0 ? 'text-status-due' : 'text-status-paid'}>৳{money(Math.abs(bal))}</b>
            </p>
          )}
        </div>

        <button className={btnGhost} onClick={() => api.printFlatLedgers(flatId, ledger.flat_no).catch(() => setError(t('somethingWrong')))}>
          🖨 {t('contrib.ledgerPdf')}
        </button>

        {ledger.entries.length === 0 ? (
          <p className="text-navy-700">{t('contrib.noEntries')}</p>
        ) : (
          <table className="w-full text-left">
            <thead className="text-navy-700">
              <tr>
                <th className="py-1 pr-2">{t('contrib.date')}</th>
                <th className="pr-2">{t('contrib.head')}</th>
                <th className="pr-2 text-right">{t('contrib.amount')}</th>
                <th className="pr-2 text-right">{t('contrib.balanceAfter')}</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {ledger.entries.map((e) => (
                <tr key={e.id} className={`border-t border-brand-100 ${e.is_reversal || e.is_reversed ? 'text-navy-700 line-through decoration-1' : ''}`}>
                  <td className="py-1 pr-2 align-top">
                    {e.date}
                    <span className="block text-xs">{e.receipt_no}</span>
                  </td>
                  <td className="pr-2 align-top">
                    {headName(e)}
                    <span className="block text-xs">
                      {e.method === 'OPENING' ? t('contrib.opening') : e.method}
                      {e.is_reversal ? ` · ${t('contrib.reversalOf')}` : ''}
                      {!e.counts_toward_target ? ` ${t('contrib.notCounted')}` : ''}
                    </span>
                  </td>
                  <td className="pr-2 text-right align-top">{money(e.amount)}</td>
                  <td className="pr-2 text-right align-top">{e.balance_after === null ? '–' : money(e.balance_after)}</td>
                  <td className="text-right align-top no-underline">
                    {canManage && !e.is_reversal && !e.is_reversed && (
                      <button className="text-status-due hover:underline" onClick={() => setReversing(e.id)}>
                        {t('contrib.reverse')}
                      </button>
                    )}
                    {e.is_reversed && <span className="text-xs">{t('contrib.reversed')}</span>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        {reversing !== null && (
          <div className="space-y-2 rounded-lg border border-status-due/40 p-3">
            <input className={input} placeholder={t('contrib.reverseReason')} value={reason} onChange={(e) => setReason(e.target.value)} />
            <button
              className={btn}
              disabled={!reason.trim()}
              onClick={() =>
                act(async () => {
                  await api.reverse(reversing, reason);
                  setReversing(null);
                  setReason('');
                })
              }
            >
              {t('hall.confirmAction')}
            </button>
          </div>
        )}

        {canCollect && (
          <form
            className="space-y-2 rounded-lg border border-brand-200 p-3"
            onSubmit={(e) => {
              e.preventDefault();
              act(async () => {
                await api.collect(flatId, { contribution_head_id: Number(headId), amount, received_on: date, method, reference_no: reference || undefined });
                setAmount('');
                setReference('');
              });
            }}
          >
            <p className="font-medium">
              {t('contrib.collect')} — {nameOf(book)}
            </p>
            <div className="grid grid-cols-2 gap-2">
              <select className={input} value={headId} onChange={(e) => setHeadId(e.target.value)}>
                {book.heads.map((h) => (
                  <option key={h.id} value={h.id}>
                    {nameOf(h)}
                  </option>
                ))}
              </select>
              <input required type="number" min="0.01" step="0.01" className={input} placeholder={t('contrib.amount')} value={amount} onChange={(e) => setAmount(e.target.value)} />
              <select className={input} value={method} onChange={(e) => setMethod(e.target.value)}>
                {['CASH', 'BANK', 'BKASH', 'NAGAD'].map((m) => (
                  <option key={m}>{m}</option>
                ))}
              </select>
              <input required type="date" className={input} value={date} onChange={(e) => setDate(e.target.value)} />
            </div>
            <input className={input} placeholder={t('contrib.refNo')} value={reference} onChange={(e) => setReference(e.target.value)} />
            <button type="submit" className={`${btn} w-full`}>
              {t('contrib.collect')}
            </button>
          </form>
        )}

        {canManage && (
          <div className="flex flex-wrap items-center gap-2 rounded-lg border border-brand-200 p-3">
            <span className="text-navy-700">{t('contrib.ownTarget')}:</span>
            <input type="number" min={0} className="w-32 rounded-lg border border-brand-200 px-2 py-1" value={target} onChange={(e) => setTarget(e.target.value)} />
            <button className={btnGhost} disabled={target === ''} onClick={() => act(() => api.setFlatTarget(bookCode, flatId, target))}>
              {t('hall.save')}
            </button>
            <button className="text-sm text-brand-700 hover:underline" onClick={() => act(() => api.setFlatTarget(bookCode, flatId, null))}>
              {t('contrib.useDefault')}
            </button>
          </div>
        )}

        {error && <p className="text-status-due">{error}</p>}
      </div>
    </Modal>
  );
}

/** Rows pasted from Excel: flat no, then one amount per head in the order of the heads. */
function parsePaste(text: string, codes: string[]): ImportRow[] {
  const num = (s: string) => Number(s.replace(/[,\s৳]/g, '')) || 0;

  return text
    .split(/\r?\n/)
    .map((line) => line.split('\t'))
    .filter((cells) => cells[0]?.trim() && /\d/.test(cells[0])) // skips header and blank lines
    .map((cells) => ({
      flat_no: cells[0].trim(),
      amounts: Object.fromEntries(codes.map((c, i) => [c, num(cells[i + 1] ?? '')])),
    }));
}

function ImportModal({
  book,
  bookTitle,
  heads,
  onClose,
  onDone,
}: {
  book: string;
  bookTitle: string;
  heads: Statement['heads'];
  onClose: () => void;
  onDone: () => void;
}) {
  const { t, lang } = useLanguage();
  const [text, setText] = useState('');
  const [asOf, setAsOf] = useState(localToday());
  const [preview, setPreview] = useState<api.ImportResult | null>(null);
  const [error, setError] = useState<string | null>(null);
  const codes = heads.map((h) => h.code);

  async function submit(dry: boolean) {
    setError(null);
    try {
      const res = await api.importOpening(book, parsePaste(text, codes), asOf, dry);
      if (dry) setPreview(res);
      else onDone();
    } catch (e: any) {
      const list = e?.response?.data?.errors?.rows;
      setError(Array.isArray(list) ? list.join(' · ') : apiError(e, t('somethingWrong')));
      setPreview(null);
    }
  }

  return (
    <Modal title={`${t('contrib.import')} — ${bookTitle}`} onClose={onClose}>
      <div className="space-y-3 text-sm">
        <p className="text-navy-700">
          {t('contrib.importHelp')}
          <b className="mt-1 block text-navy-900">
            {t('contrib.flat')}, {heads.map((h) => (lang === 'bn' ? h.name_bn : h.name_en)).join(', ')}
          </b>
        </p>
        <p className="rounded-lg bg-brand-50 p-2 text-navy-900">{t('contrib.importWarn')}</p>
        <textarea
          rows={8}
          className={`${input} font-mono text-xs`}
          placeholder={`10A\t${heads.map(() => '0').join('\t')}`}
          value={text}
          onChange={(e) => {
            setText(e.target.value);
            setPreview(null);
          }}
        />
        <label className="block text-navy-700">
          {t('contrib.importAsOf')}
          <input type="date" className={`${input} mt-1`} value={asOf} onChange={(e) => setAsOf(e.target.value)} />
        </label>
        {preview && (
          <p className="rounded-lg bg-status-paid/10 p-2 text-navy-900">
            {preview.rows} {t('contrib.importPreview')} · {preview.entries} {t('contrib.importEntries')} · {t('contrib.importTotal')} ৳{money(preview.total)}
          </p>
        )}
        {error && <p className="text-status-due">{error}</p>}
        <div className="flex gap-2">
          <button className={btnGhost} disabled={!text.trim()} onClick={() => submit(true)}>
            {t('contrib.importCheck')}
          </button>
          <button className={btn} disabled={!preview} onClick={() => submit(false)}>
            {t('contrib.importConfirm')}
          </button>
        </div>
      </div>
    </Modal>
  );
}
