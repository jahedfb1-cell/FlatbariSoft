import client from './client';

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
}) {
  return client.post<ConstructionMaterialPurchase>('/construction-materials', data).then((r) => r.data);
}
