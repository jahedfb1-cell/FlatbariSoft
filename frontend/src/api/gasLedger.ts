import client from './client';
import { downloadFile, openPdf } from '../utils/download';

export interface LedgerRow {
  id: number;
  ref: string;
  date: string;
  supplier_name: string;
  quantity_kg: string;
  unit_price: string;
  transport_cost: string;
  total_cost: string;
  voucher_no: string | null;
  status: 'APPROVED' | 'PENDING';
  paid_by: string | null;
  has_invoice: boolean;
  running_kg: string;
  running_cost: string;
}

export interface GasLedger {
  from: string | null;
  to: string | null;
  rows: LedgerRow[];
  totals: {
    count: number;
    quantity_kg: string;
    gas_cost: string;
    transport_cost: string;
    total_cost: string;
    avg_cost_per_kg: string;
    approved_cost: string;
    pending_cost: string;
    with_invoice: number;
    without_invoice: number;
  };
  by_month: { month: string; count: number; quantity_kg: string; total_cost: string }[];
  overall: { count: number; quantity_kg: string; total_cost: string };
}

const params = (from: string, to: string) => ({ ...(from ? { from } : {}), ...(to ? { to } : {}) });

export const getGasLedger = (from: string, to: string) =>
  client.get<GasLedger>('/gas/purchases/ledger', { params: params(from, to) }).then((r) => r.data);

/** Black-and-white A4 PDF of the ledger; with vouchers every bill photo follows, one per page. */
export const printGasLedger = (from: string, to: string, vouchers: boolean) =>
  openPdf('/gas/purchases/ledger/pdf', { ...params(from, to), ...(vouchers ? { vouchers: '1' } : {}) }, 'gas-purchase-ledger.pdf');

export const downloadGasLedgerExcel = (from: string, to: string) => {
  const q = new URLSearchParams(params(from, to)).toString();
  return downloadFile(`/gas/purchases/ledger/excel${q ? `?${q}` : ''}`, 'gas-purchase-ledger.xlsx');
};
