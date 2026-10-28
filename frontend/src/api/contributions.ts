import client from './client';
import { downloadFile, openPdf } from '../utils/download';

export interface ContribHead {
  id: number;
  code: string;
  name_bn: string;
  name_en: string;
  counts_toward_target: boolean;
}

export interface StatementRow {
  flat_id: number;
  flat_no: string;
  owner: string | null;
  heads: Record<string, string>;
  collected: string;
  target: string;
  has_own_target: boolean;
  balance: string; // > 0 owed, < 0 paid more than the target
  status: 'DUE' | 'EXCESS' | 'CLEARED';
}

export interface Statement {
  default_target: string;
  heads: ContribHead[];
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
  balance_after: string;
  is_reversal: boolean;
  is_reversed: boolean;
}

export interface FlatLedger {
  flat_id: number;
  flat_no: string;
  owner: string | null;
  target: string;
  collected: string;
  balance: string;
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

export const getStatement = () => client.get<Statement>('/contributions/statement').then((r) => r.data);
export const getLedger = (flatId: number) => client.get<FlatLedger>(`/contributions/flats/${flatId}/ledger`).then((r) => r.data);
export const collect = (
  flatId: number,
  d: { contribution_head_id: number; amount: string; received_on: string; method: string; reference_no?: string; notes?: string },
) => client.post(`/contributions/flats/${flatId}/collections`, d).then((r) => r.data);
export const reverse = (id: number, reason: string) => client.post(`/contributions/collections/${id}/reverse`, { reason }).then((r) => r.data);
export const setFlatTarget = (flatId: number, target_amount: string | null, note?: string) =>
  client.put(`/contributions/flats/${flatId}/target`, { target_amount, note }).then((r) => r.data);
export const setDefaultTarget = (target_amount: string) => client.put('/contributions/settings/default-target', { target_amount }).then((r) => r.data);
export const importOpening = (rows: ImportRow[], as_of: string, dry_run: boolean) =>
  client.post<ImportResult>('/contributions/import', { rows, as_of, dry_run }).then((r) => r.data);

export const printStatement = () => downloadFile('/contributions/statement/pdf', 'construction-contributions.pdf');
export const downloadStatementExcel = () => downloadFile('/contributions/statement/excel', 'construction-contributions.xlsx');
export const printAllLedgers = () => openPdf('/contributions/ledgers/pdf', undefined, 'flat-ledgers.pdf');
export const printLedger = (flatId: number, flatNo: string) => openPdf(`/contributions/flats/${flatId}/ledger/pdf`, undefined, `ledger-${flatNo}.pdf`);

export const myLedgers = () => client.get<FlatLedger[]>('/me/contributions').then((r) => r.data);
export const printMyLedger = () => openPdf('/me/contributions/pdf', undefined, 'my-ledger.pdf');
