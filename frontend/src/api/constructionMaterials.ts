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
  supplier_name?: string;
  transport_cost?: number | string;
  treasury_medium: 'CASH' | 'BANK';
  invoice_image?: File | null;
}) {
  // multipart, because the optional invoice photo is a file
  return client.post<ConstructionMaterialPurchase>('/construction-materials', toFormData(data)).then((r) => r.data);
}
