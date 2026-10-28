import client from './client';
import { downloadFile, openPdf } from '../utils/download';

export type SupplierKind = 'gas' | 'materials';

export interface SupplierSummary {
  key: string;
  name: string;
  count: number;
  quantity_kg: string;
  billed: string;
  paid: string;
  owed: string;
  avg_cost_per_kg: string;
  last_purchase: string;
}

export interface SupplierList {
  kind: SupplierKind;
  suppliers: SupplierSummary[];
  totals: { count: string; quantity_kg: string; billed: string; paid: string; owed: string };
}

export interface SupplierRow {
  date: string;
  type: 'PURCHASE' | 'PAYMENT';
  ref: string;
  purchase_id: number;
  particulars: string;
  voucher_no: string | null;
  quantity_kg: string | null;
  credit: string;
  debit: string;
  balance: string;
}

export interface SupplierLedger {
  kind: SupplierKind;
  supplier: string;
  from: string | null;
  to: string | null;
  opening: string;
  rows: SupplierRow[];
  totals: { purchased: string; paid: string; quantity_kg: string; closing: string };
}

const base = (kind: SupplierKind) => (kind === 'gas' ? '/gas/suppliers' : '/construction-materials/suppliers');
const range = (from: string, to: string) => ({ ...(from ? { from } : {}), ...(to ? { to } : {}) });

export const listSuppliers = (kind: SupplierKind, from: string, to: string) =>
  client.get<SupplierList>(base(kind), { params: range(from, to) }).then((r) => r.data);

export const getSupplierLedger = (kind: SupplierKind, supplier: string, from: string, to: string) =>
  client.get<SupplierLedger>(`${base(kind)}/ledger`, { params: { supplier, ...range(from, to) } }).then((r) => r.data);

export const printSupplierLedger = (kind: SupplierKind, supplier: string, from: string, to: string) =>
  openPdf(`${base(kind)}/ledger/pdf`, { supplier, ...range(from, to) }, 'supplier-ledger.pdf');

export const downloadSupplierLedgerExcel = (kind: SupplierKind, supplier: string, from: string, to: string) =>
  downloadFile(`${base(kind)}/ledger/excel?${new URLSearchParams({ supplier, ...range(from, to) }).toString()}`, 'supplier-ledger.xlsx');
