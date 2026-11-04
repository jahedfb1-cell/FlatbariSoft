import client from './client';

export type WaterStatus = 'NOT_POSTED' | 'PENDING' | 'OVERDUE' | 'PAID';

export interface WaterBill {
  id: number;
  year: number;
  month: number;
  account_no: string;
  bill_no: string | null;
  previous_reading: string | null;
  current_reading: string | null;
  consumption: string | null;
  water_charge: string;
  sewerage_charge: string;
  vat: string;
  other_charge: string;
  late_fee: string;
  total: string;
  due_date: string | null;
  notes: string | null;
  expense_id: number;
  expense_status: 'PENDING' | 'APPROVED' | null;
  voucher_no: string | null;
  treasury_medium: 'CASH' | 'BANK' | null;
  status: 'PENDING' | 'OVERDUE' | 'PAID';
}
export interface WaterMonth {
  year: number;
  month: number;
  bills: WaterBill[];
  status: WaterStatus;
}
export interface WaterYear {
  fiscal_year: number;
  label: string;
  months: WaterMonth[];
  total: string;
  paid: string;
  ready: boolean;
  last: { account_no: string; current_reading: string | null } | null;
}

export const getWaterYear = (fiscalYear: number) => client.get<WaterYear>('/water-bills', { params: { fiscal_year: fiscalYear } }).then((r) => r.data);

export interface WaterInput {
  bill_year: number;
  bill_month: number;
  account_no?: string;
  bill_no?: string;
  previous_reading?: string;
  current_reading?: string;
  water_charge: string;
  sewerage_charge?: string;
  vat?: string;
  other_charge?: string;
  late_fee?: string;
  due_date?: string;
  notes?: string;
  treasury_medium: 'CASH' | 'BANK';
  attachments?: File[];
}

function form(d: WaterInput) {
  const f = new FormData();
  Object.entries(d).forEach(([k, v]) => {
    if (k === 'attachments' || v === undefined || v === null || v === '') return;
    f.append(k, String(v));
  });
  (d.attachments ?? []).forEach((file) => f.append('attachments[]', file));
  return f;
}

export const postWaterBill = (d: WaterInput) => client.post<WaterBill>('/water-bills', form(d)).then((r) => r.data);
export const correctWaterBill = (id: number, d: WaterInput) => client.put<WaterBill>(`/water-bills/${id}`, d).then((r) => r.data);
