import client from './client';
import { openPdf } from '../utils/download';

export type SalaryState = 'NOT_GENERATED' | 'UNPAID' | 'AWAITING_APPROVAL' | 'PAID';

export interface SalaryRow {
  staff_id: number;
  name: string;
  designation: string;
  mobile: string;
  id: number | null;
  state: SalaryState;
  basic: string;
  overtime: string;
  bonus: string;
  advance_deduction: string;
  net: string;
  advance_outstanding: string;
  suggested_deduction: string;
  paid_date: string | null;
  treasury_medium: 'CASH' | 'BANK' | null;
  voucher_no: string | null;
  late: boolean;
}
export interface SalarySheet {
  year: number;
  month: number;
  due_date: string;
  due_day: number;
  ready: boolean;
  rows: SalaryRow[];
  totals: { net: string; paid: string; unpaid: string };
  late_count: number;
}
export interface AdvanceRow {
  id: number;
  staff_id: number;
  staff_name: string | null;
  amount: string;
  monthly_deduction: string;
  given_date: string;
  note: string | null;
  status: 'PAID_OUT' | 'AWAITING_APPROVAL';
  voucher_no: string | null;
}
export interface AdvanceData {
  ready: boolean;
  advances: AdvanceRow[];
  owed: { staff_id: number; name: string; outstanding: string }[];
}

export const getSheet = (year: number, month: number) => client.get<SalarySheet>('/salary/sheet', { params: { year, month } }).then((r) => r.data);
export const generateSheet = (year: number, month: number) => client.post<SalarySheet & { made: number }>('/salary/generate', { year, month }).then((r) => r.data);
export const updateRow = (id: number, d: { basic?: string; overtime?: string; bonus?: string; advance_deduction?: string }) => client.put<SalarySheet>(`/salary/payments/${id}`, d).then((r) => r.data);
export const payRow = (id: number, treasury_medium: 'CASH' | 'BANK') => client.post<SalarySheet>(`/salary/payments/${id}/pay`, { treasury_medium }).then((r) => r.data);
export const payAll = (year: number, month: number, treasury_medium: 'CASH' | 'BANK') => client.post<SalarySheet & { paid: number }>('/salary/pay-all', { year, month, treasury_medium }).then((r) => r.data);
export const getAdvances = () => client.get<AdvanceData>('/salary/advances').then((r) => r.data);
export const giveAdvance = (d: { staff_id: number; amount: string; monthly_deduction?: string; given_date?: string; note?: string; treasury_medium: 'CASH' | 'BANK' }) =>
  client.post<AdvanceData>('/salary/advances', d).then((r) => r.data);
export const printPayslip = (id: number) => openPdf(`/salary/payments/${id}/payslip`, undefined, `payslip-${id}.pdf`);
export const printSheet = (year: number, month: number) => openPdf('/salary/sheet/pdf', { year: String(year), month: String(month) }, `salary-${year}-${month}.pdf`);
