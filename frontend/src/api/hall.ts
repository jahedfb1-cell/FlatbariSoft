import client from './client';

export type HallSlot = 'DAY' | 'NIGHT' | 'FULL_DAY';
export type HallStatus = 'PENDING' | 'CONFIRMED' | 'COMPLETED' | 'CANCELLED' | 'REJECTED';
export type HallPayType = 'RENT' | 'DEPOSIT' | 'DEPOSIT_REFUND';

export const HALL_SLOTS: HallSlot[] = ['DAY', 'NIGHT', 'FULL_DAY'];
export const HALL_EVENT_TYPES = ['WEDDING', 'BIRTHDAY', 'RELIGIOUS', 'MEETING', 'COMMUNITY', 'OTHER'] as const;

export interface HallRate {
  slot: HallSlot;
  start_time: string;
  end_time: string;
  // null = not decided yet (bookings are refused); 0 = free
  resident_rate: string | null;
  outsider_rate: string | null;
}

export interface Hall {
  id: number;
  name: string;
  capacity: number;
  description: string | null;
  security_deposit: string;
  advance_percent: string;
  free_cancel_days: number;
  late_cancel_refund_percent: string;
  is_active: boolean;
  rates: HallRate[];
}

export interface HallPayment {
  id: number;
  type: 'RENT' | 'DEPOSIT' | 'RENT_REFUND' | 'DEPOSIT_REFUND';
  amount: string;
  method: string;
  paid_on: string;
  reference_no: string | null;
  receipt_no: string | null;
}

export interface HallBooking {
  id: number;
  booking_no: string;
  flat_id: number | null;
  flat_no?: string | null;
  customer_name: string;
  customer_mobile: string;
  event_type: string;
  guest_count: number;
  booking_date: string;
  slot: HallSlot;
  start_time: string;
  end_time: string;
  rent_amount: string;
  extra_charge: string;
  discount: string;
  total_amount: string;
  security_deposit: string;
  rent_paid: string;
  rent_due: string;
  deposit_held: string;
  deposit_due: string;
  policy_refund: string | null;
  status: HallStatus;
  status_reason: string | null;
  notes: string | null;
  payments?: HallPayment[];
}

export interface HallQuote {
  rent: string;
  security_deposit: string;
  start_time: string;
  end_time: string;
  is_resident: boolean;
}

/** date -> the slots already taken that day */
export type HallAvailability = Record<string, { slot: HallSlot; status: HallStatus }[]>;

export interface NewBookingInput {
  flat_id?: number | null;
  customer_name: string;
  customer_mobile: string;
  event_type: string;
  guest_count?: number;
  booking_date: string;
  slot: HallSlot;
  notes?: string;
  extra_charge?: string;
  discount?: string;
}

export interface HallSettingsInput {
  name: string;
  capacity: number;
  description: string | null;
  security_deposit: string;
  advance_percent: string;
  free_cancel_days: number;
  late_cancel_refund_percent: string;
  is_active: boolean;
  rates: HallRate[];
}

// ---- office side
export const getHall = () => client.get<Hall>('/hall').then((r) => r.data);
export const updateHall = (data: HallSettingsInput) => client.put<Hall>('/hall', data).then((r) => r.data);
export const getAvailability = (month: string) => client.get<HallAvailability>('/hall/availability', { params: { month } }).then((r) => r.data);
export const getQuote = (slot: HallSlot, resident: boolean) =>
  client.get<HallQuote>('/hall/quote', { params: { slot, resident: resident ? 1 : 0 } }).then((r) => r.data);

export interface BookingFilters {
  status?: string;
  from?: string;
  to?: string;
  q?: string;
}
export const listBookings = (params: BookingFilters = {}) =>
  client.get<{ data: HallBooking[] }>('/hall/bookings', { params: { ...params, per_page: 100 } }).then((r) => r.data.data);
export const getBooking = (id: number) => client.get<HallBooking>(`/hall/bookings/${id}`).then((r) => r.data);
export const createBooking = (data: NewBookingInput) => client.post<HallBooking>('/hall/bookings', data).then((r) => r.data);
export const approveBooking = (id: number) => client.post<HallBooking>(`/hall/bookings/${id}/approve`).then((r) => r.data);
export const rejectBooking = (id: number, reason: string) => client.post<HallBooking>(`/hall/bookings/${id}/reject`, { reason }).then((r) => r.data);
export const completeBooking = (id: number) => client.post<HallBooking>(`/hall/bookings/${id}/complete`).then((r) => r.data);
export const cancelBooking = (id: number, data: { reason: string; refund_amount?: string; method?: string }) =>
  client.post<HallBooking>(`/hall/bookings/${id}/cancel`, data).then((r) => r.data);
export const recordPayment = (
  id: number,
  data: { type: HallPayType; amount: string; method: string; paid_on: string; reference_no?: string },
) => client.post<HallBooking>(`/hall/bookings/${id}/payments`, data).then((r) => r.data);

// ---- resident portal
export const myHall = () => client.get<Hall>('/me/hall').then((r) => r.data);
export const myAvailability = (month: string) => client.get<HallAvailability>('/me/hall/availability', { params: { month } }).then((r) => r.data);
export const myQuote = (slot: HallSlot) => client.get<HallQuote>('/me/hall/quote', { params: { slot } }).then((r) => r.data);
export const myBookings = () => client.get<HallBooking[]>('/me/hall/bookings').then((r) => r.data);
export const requestMyBooking = (data: NewBookingInput) => client.post<HallBooking>('/me/hall/bookings', data).then((r) => r.data);
export const cancelMyBooking = (id: number) => client.post<HallBooking>(`/me/hall/bookings/${id}/cancel`).then((r) => r.data);

/** First server-side validation message, else the generic one. */
export function apiError(err: any, fallback: string): string {
  const errors = err?.response?.data?.errors;
  if (errors) return Object.values(errors).flat().join(' ');
  return err?.response?.data?.message ?? fallback;
}
