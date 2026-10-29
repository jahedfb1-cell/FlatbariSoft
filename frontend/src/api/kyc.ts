import client from './client';

export type FamilyRelation = 'SPOUSE' | 'SON' | 'DAUGHTER' | 'FATHER' | 'MOTHER' | 'BROTHER' | 'SISTER' | 'RELATIVE' | 'OTHER';
export const RELATIONS: FamilyRelation[] = ['SPOUSE', 'SON', 'DAUGHTER', 'FATHER', 'MOTHER', 'BROTHER', 'SISTER', 'RELATIVE', 'OTHER'];

export interface FamilyMember {
  name: string;
  relation: FamilyRelation;
  age: number | null;
  nid: string | null;
  mobile: string | null;
  occupation: string | null;
}

export interface TenantKyc {
  tenant_id: number;
  flat_id: number;
  flat_no: string | null;
  status: 'ACTIVE' | 'VACATED';
  start_date: string | null;
  monthly_rent: string | null;
  complete: boolean;
  missing: string[];
  submitted_at: string | null;
  person: { name_bn: string; name_en: string | null; mobile: string; alt_mobile: string | null; nid: string | null; email: string | null; address: string | null };
  occupation: string | null;
  workplace: string | null;
  emergency_name: string | null;
  emergency_phone: string | null;
  previous_address: string | null;
  reference_name: string | null;
  reference_phone: string | null;
  other_info: string | null;
  family_members: FamilyMember[];
}

/** What the form sends: the same shape, without the read-only parts. */
export type TenantKycInput = Omit<TenantKyc, 'tenant_id' | 'flat_id' | 'flat_no' | 'status' | 'start_date' | 'monthly_rent' | 'complete' | 'missing' | 'submitted_at'>;

export const getMyKyc = () => client.get<TenantKyc | null>('/me/kyc').then((r) => r.data);
export const saveMyKyc = (d: TenantKycInput) => client.put<TenantKyc>('/me/kyc', d).then((r) => r.data);
export const getTenantKyc = (tenantId: number) => client.get<TenantKyc>(`/masters/tenants/${tenantId}/kyc`).then((r) => r.data);
export const saveTenantKyc = (tenantId: number, d: TenantKycInput) => client.put<TenantKyc>(`/masters/tenants/${tenantId}/kyc`, d).then((r) => r.data);
