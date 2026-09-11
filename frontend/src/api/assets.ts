import client from './client';

export interface AssetRow {
  id: number;
  name: string;
  category: 'LIFT' | 'SOLAR' | 'SUBSTATION' | 'PUMP' | 'GENERATOR' | 'OTHER';
  purchase_date: string | null;
  purchase_cost: string | null;
  next_service_date: string | null;
  service_overdue: boolean;
  status: string;
}

export function listAssets() {
  return client.get<AssetRow[]>('/assets').then((r) => r.data);
}

export function createAsset(data: Partial<AssetRow> & { name: string; category: string }) {
  return client.post<AssetRow>('/assets', data).then((r) => r.data);
}

export function addServiceLog(assetId: number, data: { service_date: string; description?: string; cost?: number | string; technician?: string }) {
  return client.post(`/assets/${assetId}/service-logs`, data).then((r) => r.data);
}
