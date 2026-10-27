import client from './client';

export type VehicleType = 'CAR' | 'MOTORBIKE' | 'BICYCLE';
export const VEHICLE_TYPES: VehicleType[] = ['CAR', 'MOTORBIKE', 'BICYCLE'];

export interface SlotHolder {
  allocation_id: number;
  flat_id: number;
  flat_no: string | null;
  person_name: string | null;
  vehicle_no: string | null;
  from_date: string;
  monthly_fee: string;
}

export interface ParkingSlot {
  id: number;
  code: string;
  zone: string | null;
  vehicle_type: VehicleType;
  monthly_fee: string;
  is_active: boolean;
  notes: string | null;
  holder: SlotHolder | null;
}

export interface ParkingPaymentRow {
  id: number;
  amount: string;
  method: string;
  paid_on: string;
  reference_no: string | null;
  receipt_no: string | null;
}

export interface ParkingCharge {
  id: number;
  year: number;
  month: number;
  slot_code: string | null;
  vehicle_type: VehicleType | null;
  flat_id: number | null;
  flat_no: string | null;
  vehicle_no: string | null;
  amount: string;
  paid: string;
  due: string;
  due_date: string;
  status: 'PAID' | 'PARTIAL' | 'UNPAID';
  payments?: ParkingPaymentRow[];
}

export interface ParkingSummary {
  year: number;
  month: number;
  charged: string;
  collected: string;
  due: string;
  slots_total: number;
  slots_taken: number;
}

export interface SlotInput {
  code: string;
  zone?: string | null;
  vehicle_type: VehicleType;
  monthly_fee: string;
  is_active?: boolean;
  notes?: string | null;
}

export const listSlots = () => client.get<ParkingSlot[]>('/parking/slots').then((r) => r.data);
export const createSlot = (d: SlotInput) => client.post<ParkingSlot>('/parking/slots', d).then((r) => r.data);
export const updateSlot = (id: number, d: SlotInput) => client.put<ParkingSlot>(`/parking/slots/${id}`, d).then((r) => r.data);
export const allocateSlot = (id: number, d: { flat_id: number; vehicle_no?: string; from_date: string; monthly_fee?: string }) =>
  client.post<ParkingSlot>(`/parking/slots/${id}/allocate`, d).then((r) => r.data);
export const releaseAllocation = (allocationId: number, to_date: string) =>
  client.post<ParkingSlot>(`/parking/allocations/${allocationId}/release`, { to_date }).then((r) => r.data);

export const listCharges = (year: number, month: number) => client.get<ParkingCharge[]>('/parking/charges', { params: { year, month } }).then((r) => r.data);
export const generateCharges = (year: number, month: number) =>
  client.post<{ created: number; skipped: number }>('/parking/charges/generate', { year, month }).then((r) => r.data);
export const payCharge = (id: number, d: { amount: string; method: string; paid_on: string; reference_no?: string }) =>
  client.post<ParkingCharge>(`/parking/charges/${id}/payments`, d).then((r) => r.data);
export const getSummary = (year: number, month: number) => client.get<ParkingSummary>('/parking/summary', { params: { year, month } }).then((r) => r.data);

export interface MyParking {
  slots: { allocation_id: number; slot_code: string; zone: string | null; vehicle_type: VehicleType; vehicle_no: string | null; flat_no: string | null; monthly_fee: string }[];
  charges: ParkingCharge[];
}
export const myParking = () => client.get<MyParking>('/me/parking').then((r) => r.data);
