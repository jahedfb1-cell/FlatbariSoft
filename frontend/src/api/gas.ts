import client from './client';
import { toFormData } from './invoices';
import { downloadFile } from '../utils/download';

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
  editable?: boolean;
  supplier_id?: number | null;
  cylinders?: number | null;
  cylinder_kg?: string | null;
  price_per_cylinder?: string | null;
  treasury_medium?: 'CASH' | 'BANK';
}

export function listGasPurchases() {
  return client.get<GasPurchase[]>('/gas/purchases').then((r) => r.data);
}

export function recordGasPurchase(data: {
  date: string;
  supplier_id?: number | string;
  supplier_name?: string;
  cylinders?: number | string;
  cylinder_kg?: number | string;
  price_per_cylinder?: number | string;
  quantity_kg?: number | string;
  unit_price?: number | string;
  transport_cost?: number | string;
  treasury_medium: 'CASH' | 'BANK';
  invoice_image?: File | null;
}) {
  // multipart, because the optional invoice photo is a file
  return client.post<GasPurchase>('/gas/purchases', toFormData(data)).then((r) => r.data);
}

/** Correct a purchase whose expense is still pending. Multipart, so it goes as POST with the method spoofed. */
export function updateGasPurchase(id: number, data: Parameters<typeof recordGasPurchase>[0]) {
  const form = toFormData(data);
  form.append('_method', 'PUT');
  return client.post<GasPurchase>(`/gas/purchases/${id}`, form).then((r) => r.data);
}

export function cancelGasPurchase(id: number) {
  return client.delete(`/gas/purchases/${id}`).then((r) => r.data);
}

export function createGasConsumer(data: { flat_id?: number | null; name: string; meter_no?: string; is_internal?: boolean }) {
  return client.post<GasConsumer>('/gas/consumers', data).then((r) => r.data);
}

export function updateGasConsumer(id: number, data: { name?: string; meter_no?: string | null; active?: boolean }) {
  return client.put<GasConsumer>(`/gas/consumers/${id}`, data).then((r) => r.data);
}

export interface GasSummary {
  year: number;
  month: number;
  total_purchases: number;
  total_kg: string;
  total_cost: string;
  month_purchases: number;
  month_kg: string;
  month_cost: string;
  month_bought_m3: string;
  flats_used_m3: string;
  office_used_m3: string;
  total_used_m3: string;
  flats_read: number;
  flats_total: number;
  loss_m3: string;
  loss_percent: number | null;
  /** the per-unit rate flats are billed this month, and where it comes from: this month's purchase, an earlier month's, or the fixed setting */
  bill_rate?: string | null;
  bill_rate_source?: 'PURCHASE' | 'EARLIER_PURCHASE' | 'FIXED' | 'NONE';
}

export function getGasSummary(year: number, month: number) {
  return client.get<GasSummary>('/gas/summary', { params: { year, month } }).then((r) => r.data);
}

// ---- the month's readings for every flat on one sheet

export interface ReadingSheetRow {
  consumer_id: number;
  flat_no: string | null;
  name: string;
  is_office: boolean;
  previous_reading: string | null;
  previous_known: boolean;
  current_reading: string | null;
  units_used: string | null;
}

export interface ReadingSheet {
  year: number;
  month: number;
  rows: ReadingSheetRow[];
  missing_flats: number;
}

export function getReadingSheet(year: number, month: number) {
  return client.get<ReadingSheet>('/gas/readings/sheet', { params: { year, month } }).then((r) => r.data);
}

export interface SheetEntry {
  consumer_id: number;
  previous_reading?: string;
  current_reading?: string;
}

export function saveReadingSheet(year: number, month: number, rows: SheetEntry[]) {
  return client.post<{ saved: number; skipped: number; period_id: number; message: string }>('/gas/readings/sheet', { year, month, rows }).then((r) => r.data);
}

export interface SheetCheck {
  rows: ReadingSheetRow[];
  problems: string[];
  ready: number;
  skipped: number;
}

export function checkReadingFile(year: number, month: number, file: File) {
  const form = new FormData();
  form.append('year', String(year));
  form.append('month', String(month));
  form.append('file', file);
  return client.post<SheetCheck>('/gas/readings/import', form).then((r) => r.data);
}

export const downloadReadingTemplate = (year: number, month: number) =>
  downloadFile(`/gas/readings/template?year=${year}&month=${month}`, `gas-readings-${year}-${String(month).padStart(2, '0')}.xlsx`);
