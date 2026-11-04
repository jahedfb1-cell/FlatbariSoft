import client from './client';

export type SplitMethod = 'EQUAL' | 'SERVICE_RATE' | 'SIZE' | 'MANUAL';
export type WorkStatus = 'PLANNED' | 'ONGOING' | 'DONE' | 'CANCELLED';

export interface InstallmentInput {
  due_date: string;
  percent: string | number;
}
export interface Installment {
  no: number;
  due_date: string;
  amount: string;
  paid: string;
  due: string;
  status: 'PAID' | 'PARTIAL' | 'OVERDUE' | 'UPCOMING';
}
export interface ScheduleRow {
  no: number;
  due_date: string;
  percent: string;
  expected: string;
  collected: string;
  due: string;
  overdue_amount: string;
  overdue_flats: number;
  is_overdue: boolean;
}
export interface ShareRow {
  flat_id: number;
  flat_no: string;
  owner: string | null;
  tenant: string | null;
  share: string;
  paid: string;
  due: string | null;
  status: 'DUE' | 'EXCESS' | 'CLEARED' | 'NO_TARGET';
  installments: Installment[];
  overdue: string;
}
export interface WorkSummary {
  id: number;
  title: string;
  description: string | null;
  status: WorkStatus;
  start_date: string | null;
  end_date: string | null;
  split_method: SplitMethod;
  resolution_id: number | null;
  book_code: string;
  estimated: string;
  planned: string;
  gap: string;
  collected: string;
  still_to_collect: string;
  spent: string;
  pending_spend: string;
  in_hand: string;
  budget_left: string;
  percent_spent: number | null;
  over_budget: boolean;
  spent_more_than_collected: boolean;
  flats: number;
  overdue_total: string;
  schedule: ScheduleRow[];
  rows: ShareRow[];
}
export interface WorkDetail extends WorkSummary {
  expenses: { id: number; date: string; payee: string; amount: string; status: 'PENDING' | 'APPROVED'; voucher_no: string | null }[];
}
export interface PreviewRow {
  flat_id: number;
  flat_no: string;
  owner: string | null;
  tenant: string | null;
  weight: string;
  amount: string;
}
export interface ShareInput {
  flat_id: number;
  amount: string | number;
  note?: string | null;
}
export interface WorkInput {
  title: string;
  description?: string;
  estimated_cost: string | number;
  start_date?: string;
  end_date?: string;
  status?: 'PLANNED' | 'ONGOING';
  split_method: SplitMethod;
  resolution_id?: number | null;
  shares: ShareInput[];
  installments?: InstallmentInput[];
}

export const listWorks = () => client.get<Omit<WorkSummary, 'rows'>[]>('/work-budgets').then((r) => r.data);
export const getWork = (id: number) => client.get<WorkDetail>(`/work-budgets/${id}`).then((r) => r.data);
export const previewShares = (d: { split_method: SplitMethod; estimated_cost: string | number; flat_ids?: number[] }) =>
  client.post<{ rows: PreviewRow[] }>('/work-budgets/preview', d).then((r) => r.data.rows);
export const createWork = (d: WorkInput) => client.post<WorkDetail>('/work-budgets', d).then((r) => r.data);
export const updateWork = (id: number, d: Partial<{ title: string; description: string | null; estimated_cost: string | number; start_date: string | null; end_date: string | null; status: WorkStatus; resolution_id: number | null }>) =>
  client.put<WorkDetail>(`/work-budgets/${id}`, d).then((r) => r.data);
export const saveShares = (id: number, shares: ShareInput[]) => client.put<WorkDetail>(`/work-budgets/${id}/shares`, { shares }).then((r) => r.data);
export const saveSchedule = (id: number, installments: InstallmentInput[]) => client.put<WorkDetail>(`/work-budgets/${id}/schedule`, { installments }).then((r) => r.data);

/** A special collection (gas line, generator, east wall ...) one flat still has to pay. */
export interface FlatCollection {
  work_id: number;
  title: string;
  status: WorkStatus;
  share: string;
  paid: string;
  due: string;
  head_id: number;
}
export const collectionsForFlat = (flatId: number) => client.get<FlatCollection[]>(`/work-budgets/for-flat/${flatId}`).then((r) => r.data);
export const collectForWork = (workId: number, d: { flat_id: number; amount: string; received_on: string; method: string; reference_no?: string; notes?: string }) =>
  client.post<{ receipt_no: string; amount: string }>(`/work-budgets/${workId}/collect`, d).then((r) => r.data);
