import client from './client';
import { downloadFile, openPdf } from '../utils/download';

export interface BookHead {
  id: number;
  code: string;
  name_bn: string;
  name_en: string;
}

export interface Book {
  code: string;
  name_bn: string;
  name_en: string;
  has_target: boolean;
  heads: BookHead[];
}

export interface BookRef {
  id: number;
  code: string;
  name_bn: string;
  name_en: string;
  has_target: boolean;
}

export interface StatementRow {
  flat_id: number;
  flat_no: string;
  owner: string | null;
  tenant: string | null;
  heads: Record<string, string>;
  collected: string;
  target: string | null; // null = no target decided for this book
  has_own_target: boolean;
  balance: string | null; // > 0 owed, < 0 paid more than the target
  status: 'DUE' | 'EXCESS' | 'CLEARED' | 'NO_TARGET';
}

export interface Statement {
  book: BookRef;
  default_target: string;
  heads: (BookHead & { counts_toward_target: boolean })[];
  rows: StatementRow[];
  totals: Record<string, string>;
}

export interface LedgerEntry {
  id: number;
  receipt_no: string | null;
  date: string;
  head_code: string;
  head_name_bn: string;
  head_name_en: string;
  counts_toward_target: boolean;
  method: string;
  reference_no: string | null;
  notes: string | null;
  amount: string;
  balance_after: string | null;
  is_reversal: boolean;
  is_reversed: boolean;
}

export interface FlatLedger {
  book: BookRef;
  flat_id: number;
  flat_no: string;
  owner: string | null;
  tenant: string | null;
  target: string | null;
  collected: string;
  balance: string | null;
  entries: LedgerEntry[];
}

export interface ImportRow {
  flat_no: string;
  amounts: Record<string, number>;
}

export interface ImportResult {
  rows: number;
  entries: number;
  total: string;
  problems: string[];
}

export const listBooks = () => client.get<Book[]>('/contributions/books').then((r) => r.data);
export const getStatement = (book: string) => client.get<Statement>(`/contributions/books/${book}/statement`).then((r) => r.data);
export const getFlatLedgers = (flatId: number) => client.get<FlatLedger[]>(`/contributions/flats/${flatId}/ledgers`).then((r) => r.data);
export const collect = (
  flatId: number,
  d: { contribution_head_id: number; amount: string; received_on: string; method: string; reference_no?: string; notes?: string },
) => client.post(`/contributions/flats/${flatId}/collections`, d).then((r) => r.data);
export const reverse = (id: number, reason: string) => client.post(`/contributions/collections/${id}/reverse`, { reason }).then((r) => r.data);
export const setFlatTarget = (book: string, flatId: number, target_amount: string | null, note?: string) =>
  client.put(`/contributions/books/${book}/flats/${flatId}/target`, { target_amount, note }).then((r) => r.data);
export const setDefaultTarget = (book: string, target_amount: string) =>
  client.put(`/contributions/books/${book}/default-target`, { target_amount }).then((r) => r.data);
export const importOpening = (book: string, rows: ImportRow[], as_of: string, dry_run: boolean) =>
  client.post<ImportResult>(`/contributions/books/${book}/import`, { rows, as_of, dry_run }).then((r) => r.data);

export const printStatement = (book: string) => downloadFile(`/contributions/books/${book}/statement/pdf`, `contributions-${book}.pdf`);
export const downloadStatementExcel = (book: string) => downloadFile(`/contributions/books/${book}/statement/excel`, `contributions-${book}.xlsx`);
export const printAllLedgers = (book: string) => openPdf(`/contributions/books/${book}/ledgers/pdf`, undefined, `ledgers-${book}.pdf`);
export const printFlatLedgers = (flatId: number, flatNo: string) => openPdf(`/contributions/flats/${flatId}/ledgers/pdf`, undefined, `ledger-${flatNo}.pdf`);

export const myLedgers = () => client.get<FlatLedger[]>('/me/contributions').then((r) => r.data);
export const printMyLedger = () => openPdf('/me/contributions/pdf', undefined, 'my-ledger.pdf');
