import client from './client';

export interface Installment {
  id: number;
  installment_no: number;
  due_date: string;
  amount: string;
  paid_amount: string;
  status: 'UNPAID' | 'PARTIAL' | 'PAID';
}

export interface ConstructionDue {
  id: number;
  flat_id: number;
  category: string;
  source_type: 'INITIAL_CONTRACT' | 'LEVY_RESOLUTION';
  resolution_id: number | null;
  total_amount: string;
  paid_amount: string;
  balance: string;
  installments: Installment[];
}

export function listDues() {
  return client.get<ConstructionDue[]>('/construction-dues').then((r) => r.data);
}

export function createDue(data: {
  flat_id: number;
  category: string;
  source_type: string;
  resolution_id?: number;
  total_amount: number | string;
  installments: { due_date: string; amount: number | string }[];
}) {
  return client.post<ConstructionDue>('/construction-dues', data).then((r) => r.data);
}

export function payInstallment(installmentId: number, amount: number | string) {
  return client.post<Installment>(`/construction-dues/installments/${installmentId}/pay`, { amount }).then((r) => r.data);
}

export function agingReport() {
  return client.get<{ '0-30': number; '31-90': number; '90+': number }>('/construction-dues/aging-report').then((r) => r.data);
}
