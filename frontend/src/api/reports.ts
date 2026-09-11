import client from './client';

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

export function getIncomeStatement(fiscalYear: string) {
  return client.get('/reports/income-statement', { params: { fiscal_year: fiscalYear } }).then((r) => r.data);
}

export function getBalanceSheet() {
  return client.get('/reports/balance-sheet').then((r) => r.data);
}

export function getDashboard(year: number, month: number) {
  return client.get<DashboardKpis>('/reports/dashboard', { params: { year, month } }).then((r) => r.data);
}
