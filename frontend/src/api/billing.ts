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

export function generateBilling(year: number, month: number, allowWithoutGas = false) {
  return client.post('/billing/generate', { year, month, ...(allowWithoutGas ? { allow_without_gas: true } : {}) }).then((r) => r.data);
}

export function getBillingRegister(year: number, month: number) {
  return client.get<BillRow[]>(`/billing/${year}/${month}`).then((r) => r.data);
}

export function findOrCreateBillingPeriod(year: number, month: number) {
  return client.post<{ id: number; status: string }>(`/billing/periods/${year}/${month}`).then((r) => r.data);
}

export interface BillingMonth {
  year: number;
  month: number;
  bill_count: number;
  billed: string;
  paid: string;
  due: string;
}
export interface BillingMonths {
  fiscal_year: number;
  label: string;
  months: BillingMonth[];
}
/** The twelve months of a July-June year with what each was billed and collected; empty months included. */
export function getBillingMonths(fiscalYear: number) {
  return client.get<BillingMonths>('/billing/months', { params: { fiscal_year: fiscalYear } }).then((r) => r.data);
}

export interface BillCorrection {
  service_charge: string;
  gas_amount: string;
  other_charges: string;
  late_fee: string;
  reason: string;
}
/** Correct one bill's amounts (the server recomputes the total and keeps the reason in the audit log). */
export function correctBill(id: number, d: BillCorrection) {
  return client.put<BillRow>(`/billing/bills/${id}`, d).then((r) => r.data);
}
