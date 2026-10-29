import client from './client';

export type TenantRequestStatus = 'PENDING' | 'APPROVED' | 'REJECTED' | 'CANCELLED';

export interface TenantRequest {
  id: number;
  flat_id: number;
  flat_no: string | null;
  name_bn: string;
  name_en: string | null;
  mobile: string;
  nid: string | null;
  start_date: string;
  monthly_rent: string | null;
  security_deposit: string | null;
  occupation: string | null;
  family_members_count: number | null;
  note: string | null;
  status: TenantRequestStatus;
  reject_reason: string | null;
  tenant_id: number | null;
  requested_by: string | null;
  requested_at: string | null;
  decided_by: string | null;
  decided_at: string | null;
}

export interface TenantRequestInput {
  flat_id: number;
  name_bn: string;
  name_en?: string;
  mobile: string;
  nid?: string;
  start_date: string;
  monthly_rent?: string;
  security_deposit?: string;
  occupation?: string;
  family_members_count?: number;
  note?: string;
}

export const listTenantRequests = () => client.get<{ can_decide: boolean; requests: TenantRequest[] }>('/tenant-requests').then((r) => r.data);
export const sendTenantRequest = (d: TenantRequestInput) => client.post<TenantRequest>('/tenant-requests', d).then((r) => r.data);
export const approveTenantRequest = (id: number) => client.post<TenantRequest>(`/tenant-requests/${id}/approve`).then((r) => r.data);
export const rejectTenantRequest = (id: number, reason: string) => client.post<TenantRequest>(`/tenant-requests/${id}/reject`, { reason }).then((r) => r.data);
export const cancelTenantRequest = (id: number) => client.post<TenantRequest>(`/tenant-requests/${id}/cancel`).then((r) => r.data);
