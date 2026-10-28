import client from './client';
import { toFormData } from './invoices';

export interface GasConsumer {
  id: number;
  flat_id: number | null;
  flat_no: string | null;
  name: string;
  meter_no: string | null;
  is_internal: boolean;
  active: boolean;
  last_reading: string | null;
}

export function listGasConsumers() {
  return client.get<GasConsumer[]>('/gas/consumers', { params: { all: 1 } }).then((r) => r.data);
}

export function recordGasReading(data: {
  gas_consumer_id: number;
  billing_period_id: number;
  current_reading: number | string;
  reading_date: string;
}) {
  return client.post('/gas/readings', data).then((r) => r.data);
}

export function applyGasToBills(billingPeriodId: number) {
  return client.post(`/gas/periods/${billingPeriodId}/apply`).then((r) => r.data);
}

export interface GasPurchase {
  id: number;
  date: string;
  supplier_name: string;
  quantity_kg: string;
  unit_price: string;
  transport_cost: string;
  total_cost: string;
  has_invoice: boolean;
  invoice_kb: number | null;
  expense_status: 'PENDING' | 'APPROVED';
}

export function listGasPurchases() {
  return client.get<GasPurchase[]>('/gas/purchases').then((r) => r.data);
}

export function recordGasPurchase(data: {
  date: string;
  supplier_name: string;
  quantity_kg: number | string;
  unit_price: number | string;
  transport_cost?: number | string;
  treasury_medium: 'CASH' | 'BANK';
  invoice_image?: File | null;
}) {
  // multipart, because the optional invoice photo is a file
  return client.post<GasPurchase>('/gas/purchases', toFormData(data)).then((r) => r.data);
}

export function createGasConsumer(data: { flat_id?: number | null; name: string; meter_no?: string; is_internal?: boolean }) {
  return client.post<GasConsumer>('/gas/consumers', data).then((r) => r.data);
}

export function updateGasConsumer(id: number, data: { name?: string; meter_no?: string | null; active?: boolean }) {
  return client.put<GasConsumer>(`/gas/consumers/${id}`, data).then((r) => r.data);
}
