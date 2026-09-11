import client from './client';

export interface ExpenseRow {
  id: number;
  date: string;
  expense_head_id: number;
  payee: string;
  amount: string;
  treasury_medium: 'BANK' | 'CASH';
  fund_type: 'OPERATING' | 'GAS' | 'CAPITAL' | 'SINKING';
  resolution_id: number | null;
  voucher_no: string | null;
  status: 'PENDING' | 'APPROVED';
  approvals_count: number;
}

export interface ExpenseHead {
  id: number;
  code: string;
  name_bn: string;
  name_en: string | null;
}

export function listExpenses(status?: string) {
  return client
    .get<{ data: ExpenseRow[] }>('/expenses', { params: status ? { status } : {} })
    .then((r) => (r.data as any).data ?? r.data);
}

export function createExpense(data: {
  date: string;
  expense_head_id: number;
  payee: string;
  amount: number | string;
  treasury_medium: string;
  fund_type?: string;
  resolution_id?: number;
}) {
  return client.post<ExpenseRow>('/expenses', data).then((r) => r.data);
}

export function approveExpense(id: number) {
  return client.post<ExpenseRow>(`/expenses/${id}/approve`).then((r) => r.data);
}

export function closeMonth(year: number, month: number) {
  return client.post('/closing', { year, month }).then((r) => r.data);
}

export function listExpenseHeads() {
  return client.get<ExpenseHead[]>('/expense-heads').then((r) => r.data);
}
