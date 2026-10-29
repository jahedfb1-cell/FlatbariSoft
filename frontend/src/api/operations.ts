import client from './client';
import { downloadFile } from '../utils/download';

// ---- settings
export interface Settings {
  bill_due_day: string;
  late_fee_mode: 'NONE' | 'PERCENT' | 'FIXED';
  late_fee_value: string;
  late_fee_grace_days: string;
  auto_generate_bills: string;
  sms_enabled: string;
  reminders_enabled: string;
  reminder_days_before_due: string;
  reminder_repeat_days: string;
}
export interface SettingsResponse {
  settings: Settings;
  sms_gateway: 'CONFIGURED' | 'NOT_CONFIGURED';
}
export interface SmsLogRow {
  id: number;
  mobile: string;
  message: string;
  event_key: string | null;
  status: 'QUEUED_PENDING_GATEWAY' | 'SENT' | 'FAILED';
  created_at: string;
}
export const getSettings = () => client.get<SettingsResponse>('/settings').then((r) => r.data);
export const saveSettings = (s: Record<string, string | number | boolean>) => client.put<{ settings: Settings }>('/settings', s).then((r) => r.data.settings);
export const getSmsLogs = () => client.get<SmsLogRow[]>('/settings/sms-logs').then((r) => r.data);
export const downloadBackup = () => downloadFile('/backup/download', `green-valley-backup-${new Date().toISOString().slice(0, 10)}.sql`);

// ---- audit log
export interface AuditRow {
  id: number;
  when: string;
  user: string | null;
  event: string;
  model: string;
  record_id: number;
  old: Record<string, unknown> | null;
  new: Record<string, unknown> | null;
  ip: string | null;
}
export interface AuditPage {
  data: AuditRow[];
  models: string[];
  page: number;
  last_page: number;
  total: number;
}
export const getAuditLogs = (params: Record<string, string | number>) =>
  client.get<AuditPage>('/audit-logs', { params: Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '')) }).then((r) => r.data);

// ---- visitors
export type VisitorPurpose = 'GUEST' | 'DELIVERY' | 'SERVICE' | 'OTHER';
export interface Visitor {
  id: number;
  flat_id: number;
  flat_no: string | null;
  visitor_name: string;
  mobile: string | null;
  purpose: VisitorPurpose;
  vehicle_no: string | null;
  note: string | null;
  in_at: string;
  out_at: string | null;
  inside: boolean;
}
export const listVisitors = (params: { inside?: boolean; q?: string; date?: string } = {}) =>
  client
    .get<{ inside_now: number; visitors: Visitor[] }>('/visitors', { params: { ...(params.inside ? { inside: 1 } : {}), ...(params.q ? { q: params.q } : {}), ...(params.date ? { date: params.date } : {}) } })
    .then((r) => r.data);
export const addVisitor = (d: { flat_id: number; visitor_name: string; mobile?: string; purpose: VisitorPurpose; vehicle_no?: string; note?: string }) =>
  client.post<Visitor>('/visitors', d).then((r) => r.data);
export const checkoutVisitor = (id: number) => client.post<Visitor>(`/visitors/${id}/checkout`).then((r) => r.data);
export const myVisitors = () => client.get<Visitor[]>('/me/visitors').then((r) => r.data);

// ---- budget
export interface BudgetRow {
  expense_head_id: number;
  code: string;
  name_bn: string;
  name_en: string;
  budget: string;
  spent: string;
  remaining: string;
  percent: number | null;
  over: boolean;
}
export interface BudgetReport {
  fiscal_year: number;
  label: string;
  from: string;
  to: string;
  rows: BudgetRow[];
  totals: { budget: string; spent: string; remaining: string; percent: number | null };
}
export const getBudget = (fiscalYear: number) => client.get<BudgetReport>('/budget', { params: { fiscal_year: fiscalYear } }).then((r) => r.data);
export const saveBudget = (fiscalYear: number, items: { expense_head_id: number; amount: string }[]) =>
  client.put<BudgetReport>('/budget', { fiscal_year: fiscalYear, items }).then((r) => r.data);

// ---- resident payments
export interface MyPayment {
  id: number;
  flat_id: number;
  amount: string;
  payment_date: string;
  method: string;
  reference_no: string | null;
  receipt_no: string | null;
  status: 'PENDING' | 'APPROVED';
}
export const myPayments = () => client.get<MyPayment[]>('/me/payments').then((r) => r.data);
export const reportMyPayment = (d: { flat_id: number; bill_id?: number | null; amount: string; method: 'BKASH' | 'NAGAD' | 'BANK'; reference_no: string; payment_date: string }) =>
  client.post<MyPayment>('/me/payments', d).then((r) => r.data);
