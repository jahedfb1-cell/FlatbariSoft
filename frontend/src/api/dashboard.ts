import client from './client';
import type { GasSummary } from './gas';

export interface DashboardOverview {
  year: number;
  month: number;
  today: string;
  billing?: {
    has_bills: boolean;
    billed: string;
    collected: string;
    due: string;
    percent: number | null;
    paid_flats: number;
    partial_flats: number;
    unpaid_flats: number;
    outstanding_all: string;
    trend: { year: number; month: number; billed: string; paid: string }[];
  };
  funds?: {
    total_closing: string;
    funds: { code: string; name_bn: string; name_en: string; closing: string; cash: string; bank: string; receipts: string; payments: string }[];
  };
  approvals?: { payments: number | null; expenses: number | null };
  recent_payments?: { id: number; flat_id: number; flat_no: string | null; amount: string; date: string; status: 'PENDING' | 'APPROVED' }[];
  gas?: GasSummary;
  parking?: { charged: string; collected: string; due: string; slots_total: number; slots_taken: number };
  hall?: {
    pending_requests: number;
    upcoming: { id: number; date: string; slot: string; event: string | null; customer: string; status: string }[];
  };
  complaints?: { open: number; urgent: number };
  next_meeting?: { id: number; title: string; date: string; venue: string | null } | null;
  contributions?: { code: string; name_bn: string; name_en: string; collected: string; target: string; has_target: boolean; percent: number | null }[];
  supplier_owed?: { materials: string; gas: string };
  flats?: { total: number };
}

export const getOverview = (year: number, month: number) =>
  client.get<DashboardOverview>('/dashboard/overview', { params: { year, month } }).then((r) => r.data);
