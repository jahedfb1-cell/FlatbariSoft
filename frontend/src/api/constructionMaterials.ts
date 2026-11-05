import client from './client';
import { toFormData } from './invoices';

export interface ConstructionMaterialPurchase {
  id: number;
  date: string;
  item_name: string;
  quantity: string;
  unit: string;
  unit_rate: string;
  supplier_name: string | null;
  transport_cost: string;
  total_cost: string;
  has_invoice: boolean;
  invoice_kb: number | null;
  expense_status: 'PENDING' | 'APPROVED';
  editable?: boolean;
  supplier_id?: number | null;
  treasury_medium?: 'CASH' | 'BANK';
}

export function listMaterialPurchases() {
  return client.get<ConstructionMaterialPurchase[]>('/construction-materials').then((r) => r.data);
}

export function recordMaterialPurchase(data: {
  date: string;
  item_name: string;
  quantity: number | string;
  unit: string;
  unit_rate: number | string;
  supplier_id?: number | string;
  supplier_name?: string;
  transport_cost?: number | string;
  treasury_medium: 'CASH' | 'BANK';
  invoice_image?: File | null;
}) {
  // multipart, because the optional invoice photo is a file
  return client.post<ConstructionMaterialPurchase>('/construction-materials', toFormData(data)).then((r) => r.data);
}

/** Correct a purchase whose expense is still pending. Multipart, so it goes as POST with the method spoofed. */
export function updateMaterialPurchase(id: number, data: Parameters<typeof recordMaterialPurchase>[0]) {
  const form = toFormData(data);
  form.append('_method', 'PUT');
  return client.post<ConstructionMaterialPurchase>(`/construction-materials/${id}`, form).then((r) => r.data);
}

export const cancelMaterialPurchase = (id: number) => client.delete(`/construction-materials/${id}`).then((r) => r.data);
