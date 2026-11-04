import client from './client';
import { openPdf } from '../utils/download';

export interface PaymentRow {
  id: number;
  flat_id: number;
  flat_no?: string;
  owner?: string | null;
  tenant?: string | null;
  bill_id: number | null;
  amount: string;
  payment_date: string;
  method: 'CASH' | 'BANK' | 'BKASH' | 'NAGAD' | 'ROCKET' | 'UPAY';
  reference_no?: string | null;
  bank_name?: string | null;
  remarks?: string | null;
  receipt_no: string | null;
  status: 'PENDING' | 'APPROVED';
}

export function listPayments(status?: string) {
  return client
    .get<{ data: PaymentRow[] }>('/payments', { params: status ? { status } : {} })
    .then((r) => (r.data as any).data ?? r.data);
}

export function createPayment(data: {
  flat_id: number;
  bill_id?: number;
  amount: number | string;
  payment_date: string;
  method: string;
  reference_no?: string;
  bank_name?: string;
  remarks?: string;
}) {
  return client.post<PaymentRow>('/payments', data).then((r) => r.data);
}

export function approvePayment(id: number) {
  return client.post<PaymentRow>(`/payments/${id}/approve`).then((r) => r.data);
}

/** The printable money receipt of an approved payment. */
export function printReceipt(id: number, receiptNo: string) {
  return openPdf(`/payments/${id}/receipt`, undefined, `receipt-${receiptNo}.pdf`);
}

export interface PaymentPage {
  data: PaymentRow[];
  meta: { current_page: number; last_page: number; total: number };
}
/** The payment register: every payment, filtered by status / method / dates / free text, a page at a time. */
export function searchPayments(params: { status?: string; method?: string; q?: string; from?: string; to?: string; page?: number; per_page?: number }) {
  const clean = Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v !== undefined));
  return client.get<PaymentPage>('/payments', { params: clean }).then((r) => r.data);
}

export interface PaymentReview {
  id: number;
  status: 'PENDING' | 'APPROVED';
  receipt_no: string | null;
  flat_no: string | null;
  owner: string | null;
  tenant: string | null;
  amount: string;
  payment_date: string;
  method: string;
  reference_no: string | null;
  bank_name: string | null;
  remarks: string | null;
  entered_by: string | null;
  entered_at: string | null;
  bill: null | {
    year: number;
    month: number;
    due_date: string | null;
    previous_due: string;
    service_charge: string;
    gas_amount: string;
    other_charges: string;
    late_fee: string;
    total_payable: string;
    due_before: string;
    due_after: string;
  };
  split: null | { service: string; gas: string };
  posting: { medium: 'CASH' | 'BANK'; account: string | null; bank_missing: boolean };
}
export const reviewPayment = (id: number) => client.get<PaymentReview>(`/payments/${id}/review`).then((r) => r.data);
