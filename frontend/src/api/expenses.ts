import client from './client';

export interface ExpenseRow {
  id: number;
  date: string;
  expense_head_id: number;
  payee: string;
  amount: string;
  description: string | null;
  treasury_medium: 'BANK' | 'CASH';
  fund_type: 'OPERATING' | 'GAS' | 'CAPITAL' | 'SINKING';
  resolution_id: number | null;
  voucher_no: string | null;
  status: 'PENDING' | 'APPROVED';
  attachments_count: number;
  approvals_count: number;
}

export interface ExpenseHead {
  id: number;
  code: string;
  name_bn: string;
  name_en: string | null;
}

export interface ExpenseFile {
  id: number;
  name: string;
  mime: string;
  size: number;
}

export interface ExpenseDetail {
  id: number;
  date: string;
  head_bn: string | null;
  head_en: string | null;
  payee: string;
  amount: string;
  description: string | null;
  treasury_medium: 'BANK' | 'CASH';
  fund_type: 'OPERATING' | 'GAS' | 'CAPITAL' | 'SINKING';
  resolution_id: number | null;
  voucher_no: string | null;
  status: 'PENDING' | 'APPROVED';
  created_by: string | null;
  created_at: string | null;
  approvals_needed: number;
  approvals: { by: string | null; at: string | null; note: string | null }[];
  attachments: ExpenseFile[];
  source: { type: 'gas' | 'materials'; purchase_id: number; has_invoice: boolean; item?: string } | null;
}

export function listExpenses(status?: string) {
  return client
    .get<{ data: ExpenseRow[] }>('/expenses', { params: status ? { status } : {} })
    .then((r) => (r.data as any).data ?? r.data);
}

/** The voucher / bill papers (photos or PDFs) go along as multipart attachments. */
export function createExpense(data: {
  date: string;
  expense_head_id: number;
  payee: string;
  amount: number | string;
  description?: string;
  treasury_medium: string;
  fund_type?: string;
  resolution_id?: number;
  attachments?: File[];
}) {
  const form = new FormData();
  Object.entries(data).forEach(([k, v]) => {
    if (k === 'attachments' || v === undefined || v === null || v === '') return;
    form.append(k, String(v));
  });
  (data.attachments ?? []).forEach((f) => form.append('attachments[]', f));
  return client.post<ExpenseRow>('/expenses', form).then((r) => r.data);
}

export const getExpense = (id: number) => client.get<ExpenseDetail>(`/expenses/${id}`).then((r) => r.data);

/** An approval always carries the approver's written note. */
export function approveExpense(id: number, note: string) {
  return client.post<ExpenseRow>(`/expenses/${id}/approve`, { note }).then((r) => r.data);
}

export function addExpenseFiles(id: number, files: File[]) {
  const form = new FormData();
  files.forEach((f) => form.append('attachments[]', f));
  return client.post<ExpenseDetail>(`/expenses/${id}/attachments`, form).then((r) => r.data);
}

export const removeExpenseFile = (fileId: number) => client.delete(`/expenses/attachments/${fileId}`).then((r) => r.data);

/** The file itself as a blob (the endpoint needs the bearer token, so a plain link will not do). */
export const fetchExpenseFile = (fileId: number) => client.get<Blob>(`/expenses/attachments/${fileId}`, { responseType: 'blob' }).then((r) => r.data);

export function closeMonth(year: number, month: number) {
  return client.post('/closing', { year, month }).then((r) => r.data);
}

export function listExpenseHeads() {
  return client.get<ExpenseHead[]>('/expense-heads').then((r) => r.data);
}
