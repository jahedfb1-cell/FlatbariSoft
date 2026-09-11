import client from './client';

export interface PaymentRow {
  id: number;
  flat_id: number;
  flat_no?: string;
  bill_id: number | null;
  amount: string;
  payment_date: string;
  method: 'CASH' | 'BANK' | 'BKASH' | 'NAGAD';
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
}) {
  return client.post<PaymentRow>('/payments', data).then((r) => r.data);
}

export function approvePayment(id: number) {
  return client.post<PaymentRow>(`/payments/${id}/approve`).then((r) => r.data);
}
