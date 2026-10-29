import client from './client';
import { downloadFile, openPdf } from '../utils/download';

export type FundCode = 'OPERATING' | 'GAS' | 'CAPITAL' | 'SINKING';
export const FUNDS: FundCode[] = ['OPERATING', 'GAS', 'CAPITAL', 'SINKING'];

export interface Line {
  date: string;
  particulars: string;
  head_code: string;
  head_name_bn: string;
  head_name_en: string;
  medium: 'CASH' | 'BANK';
  amount: string;
}

export interface HeadSum {
  code: string;
  name_bn: string;
  name_en: string;
  amount: string;
}

export interface LoanPosition {
  lender: FundCode;
  borrower: FundCode;
  owed: string;
  lender_name_bn: string;
  lender_name_en: string;
  borrower_name_bn: string;
  borrower_name_en: string;
}

export interface FundStatement {
  fund: { code: FundCode; name_bn: string; name_en: string };
  year: number;
  month: number;
  from: string;
  to: string;
  opening: { cash: string; bank: string; total: string };
  receipts: Line[];
  payments: Line[];
  receipts_by_head: HeadSum[];
  payments_by_head: HeadSum[];
  totals: { receipts: string; payments: string };
  closing: { cash: string; bank: string; total: string };
  left_total: string;
  right_total: string;
  bank_note: { opening: string; cash_deposited: string; other_receipts: string; profit: string; withdrawn: string; payments: string; closing: string };
  loans: LoanPosition[];
}

export interface Summary {
  year: number;
  month: number;
  funds: { fund: FundStatement['fund']; opening: string; receipts: string; payments: string; closing: string; closing_cash: string; closing_bank: string }[];
  total: { opening: string; receipts: string; payments: string; closing: string };
  loans: LoanPosition[];
}

export interface Entry {
  id: number;
  receipt_no: string | null;
  kind: 'OPENING' | 'DEPOSIT_TO_BANK' | 'WITHDRAW_FROM_BANK' | 'BANK_PROFIT' | 'FUND_LOAN' | 'LOAN_REPAYMENT';
  fund: FundCode;
  to_fund: FundCode | null;
  medium: 'CASH' | 'BANK' | null;
  amount: string;
  date: string;
  notes: string | null;
  is_reversal: boolean;
  is_reversed: boolean;
}

export const getStatement = (fund: FundCode, year: number, month: number) =>
  client.get<FundStatement>('/treasury/statement', { params: { fund, year, month } }).then((r) => r.data);
export const getSummary = (year: number, month: number) => client.get<Summary>('/treasury/summary', { params: { year, month } }).then((r) => r.data);
export const printStatement = (fund: FundCode, year: number, month: number) =>
  openPdf('/treasury/statement/pdf', { fund, year: String(year), month: String(month) }, `statement-${fund}-${year}-${month}.pdf`);
/** Several funds in ONE document: an all-funds summary page, then each fund's full statement. */
export const printCombinedStatement = (funds: FundCode[], year: number, month: number) =>
  openPdf('/treasury/statement/combined/pdf', { year: String(year), month: String(month), ...Object.fromEntries(funds.map((f, i) => [`funds[${i}]`, f])) }, `statement-combined-${year}-${month}.pdf`);
export const downloadStatementExcel = (fund: FundCode, year: number, month: number) =>
  downloadFile(`/treasury/statement/excel?fund=${fund}&year=${year}&month=${month}`, `statement-${fund}-${year}-${month}.xlsx`);

export const listEntries = () => client.get<Entry[]>('/treasury/entries').then((r) => r.data);
export const openingBalance = (d: { fund: FundCode; cash: string; bank: string; date: string; notes?: string }) => client.post('/treasury/opening-balance', d).then((r) => r.data);
export const bankDeposit = (d: { fund: FundCode; amount: string; date: string; notes?: string }) => client.post('/treasury/bank-deposit', d).then((r) => r.data);
export const bankWithdrawal = (d: { fund: FundCode; amount: string; date: string; notes?: string }) => client.post('/treasury/bank-withdrawal', d).then((r) => r.data);
export const bankProfit = (d: { fund: FundCode; amount: string; date: string; notes?: string }) => client.post('/treasury/bank-profit', d).then((r) => r.data);
export const lend = (d: { from_fund: FundCode; to_fund: FundCode; medium: 'CASH' | 'BANK'; amount: string; date: string; notes?: string }) =>
  client.post('/treasury/loans', d).then((r) => r.data);
export const repay = (d: { from_fund: FundCode; to_fund: FundCode; medium: 'CASH' | 'BANK'; amount: string; date: string; notes?: string }) =>
  client.post('/treasury/loan-repayments', d).then((r) => r.data);
export const reverseEntry = (id: number, reason: string) => client.post(`/treasury/entries/${id}/reverse`, { reason }).then((r) => r.data);
