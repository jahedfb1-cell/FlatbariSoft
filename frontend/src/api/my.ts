import client from './client';
import { openPdf } from '../utils/download';
import type { Flat } from './flats';
import type { BillRow } from './billing';

export function myFlats() {
  return client.get<Flat[]>('/me/flats').then((r) => r.data);
}

export function myBills() {
  return client.get<BillRow[]>('/me/bills').then((r) => r.data);
}

export function myDocuments() {
  return client.get('/me/documents').then((r) => r.data);
}

export function myComplaints() {
  return client.get('/me/complaints').then((r) => r.data);
}

export function submitMyComplaint(data: { flat_id: number; category: string; description: string }) {
  return client.post('/me/complaints', data).then((r) => r.data);
}

// ---- the flat owner's / tenant's home

export interface PortalBill {
  bill_id: number;
  flat_no: string;
  owner: string | null;
  tenant: string | null;
  year: number;
  month: number;
  month_label: string;
  service_charge: string;
  gas_amount: string;
  gas_previous: string | null;
  gas_current: string | null;
  gas_units: string | null;
  gas_rate: string | null;
  other_charges: string;
  late_fee: string;
  previous_due: string;
  total_payable: string;
  paid_amount: string;
  due: string;
  due_date: string | null;
  due_date_iso: string | null;
  status: 'UNPAID' | 'PARTIAL' | 'PAID';
}

export interface PortalFlat {
  id: number;
  flat_no: string;
  floor: string;
  size_sqft: string | null;
  gas_meter_no: string | null;
  occupancy_status: string;
  role: 'OWNER' | 'TENANT';
  tenant: { name: string | null; mobile: string | null; since: string | null; kyc_complete: boolean } | null;
  latest_bill: { id: number; month_label: string; total_payable: string; paid_amount: string; due: string; due_date: string | null; status: 'UNPAID' | 'PARTIAL' | 'PAID'; overdue: boolean } | null;
  parking: { slot: string | null; vehicle_no: string | null }[];
}

export interface PortalSummary {
  flats: PortalFlat[];
  totals: { bill_due: string; overdue: boolean; pending_payments: string; construction_due: string | null };
  counts: { unread_notifications: number; new_notices: number; open_complaints: number };
}

export const mySummary = () => client.get<PortalSummary>('/me/summary').then((r) => r.data);
export const myBillDetails = () => client.get<PortalBill[]>('/me/bills/details').then((r) => r.data);
export const openMyBillPdf = (billId: number, flatNo: string, label: string) => openPdf(`/me/bills/${billId}/pdf`, undefined, `${flatNo}-Bill-${label.replace(/\s+/g, '-')}.pdf`);

export interface PortalDecision {
  id: number;
  resolution_no: string | null;
  meeting_title: string;
  meeting_date: string | null;
  description: string;
  decision: string | null;
  status: 'PENDING' | 'ONGOING' | 'DONE' | 'CANCELLED';
}
export const myDecisions = () => client.get<PortalDecision[]>('/me/decisions').then((r) => r.data);

export interface Contact {
  id: number;
  name: string;
  role: string | null;
  phone: string;
}
export const myContacts = () => client.get<Contact[]>('/me/contacts').then((r) => r.data);
