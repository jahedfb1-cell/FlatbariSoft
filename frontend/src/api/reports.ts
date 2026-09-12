import client from './client';
import { downloadFile } from '../utils/download';

export interface TrialBalanceLine {
  account_code: string;
  account_name: string;
  account_type: string;
  debit: number;
  credit: number;
}

export interface TrialBalance {
  lines: TrialBalanceLine[];
  total_debit: number;
  total_credit: number;
  balanced: boolean;
}

export interface IncomeStatement {
  fiscal_year: string;
  total_income: number;
  total_expense: number;
  net_surplus: number;
}

export interface BalanceSheet {
  assets: number;
  liabilities: number;
  funds: number;
  current_net_income: number;
  balanced: boolean;
}

export interface DashboardKpis {
  collection: number;
  outstanding_due: number;
  expense: number;
  sinking_fund_balance: number;
  gas_consumer_count: number;
}

export function getTrialBalance() {
  return client.get<TrialBalance>('/reports/trial-balance').then((r) => r.data);
}

export function getIncomeStatement(fiscalYear?: string) {
  return client.get<IncomeStatement>('/reports/income-statement', { params: fiscalYear ? { fiscal_year: fiscalYear } : {} }).then((r) => r.data);
}

export function getBalanceSheet() {
  return client.get<BalanceSheet>('/reports/balance-sheet').then((r) => r.data);
}

export function getDashboard(year: number, month: number) {
  return client.get<DashboardKpis>('/reports/dashboard', { params: { year, month } }).then((r) => r.data);
}

export type ReportKind = 'trial-balance' | 'balance-sheet' | 'income-statement';

export function downloadReportPdf(kind: ReportKind) {
  return downloadFile(`/reports/${kind}/pdf`, `${kind}.pdf`);
}

export function downloadReportExcel(kind: ReportKind) {
  return downloadFile(`/reports/${kind}/excel`, `${kind}.xlsx`);
}
