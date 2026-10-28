import client from './client';

export interface BillRow {
  id: number;
  flat_id: number;
  flat_no?: string;
  service_charge: string;
  gas_amount: string;
  other_charges: string;
  late_fee: string;
  previous_due: string;
  total_payable: string;
  paid_amount: string;
  due: number;
  pending_amount: string;
  owner?: string | null;
  tenant?: string | null;
  due_date: string;
  status: 'UNPAID' | 'PARTIAL' | 'PAID';
}

export function generateBilling(year: number, month: number) {
  return client.post('/billing/generate', { year, month }).then((r) => r.data);
}

export function getBillingRegister(year: number, month: number) {
  return client.get<BillRow[]>(`/billing/${year}/${month}`).then((r) => r.data);
}

export function findOrCreateBillingPeriod(year: number, month: number) {
  return client.post<{ id: number; status: string }>(`/billing/periods/${year}/${month}`).then((r) => r.data);
}
