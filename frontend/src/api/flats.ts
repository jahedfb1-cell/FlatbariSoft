import client from './client';

export interface PersonData {
  id?: number;
  name_bn: string;
  name_en?: string;
  mobile: string;
  nid?: string;
  email?: string;
  address?: string;
  preferred_channel?: string;
}

export interface FlatParty {
  id: number;
  role: 'OWNER' | 'TENANT';
  from_date: string;
  to_date: string | null;
  is_billing_contact: boolean;
  person: PersonData;
}

export interface TenantData {
  id: number;
  flat_id: number;
  occupation: string | null;
  family_members_count: number | null;
  start_date: string;
  end_date: string | null;
  monthly_rent: string | null;
  security_deposit: string | null;
  status: 'ACTIVE' | 'VACATED';
  person: PersonData;
}

export interface Flat {
  id: number;
  building_id: number;
  flat_no: string;
  floor: string;
  size_sqft: string | null;
  occupancy_status: 'OWNER_OCCUPIED' | 'TENANTED' | 'EMPTY';
  service_charge_rate: string;
  empty_flat_rate: string;
  gas_meter_no: string | null;
  status: 'ACTIVE' | 'INACTIVE';
  current_owners?: FlatParty[];
  current_tenant?: TenantData[];
}

export interface FlatFormInput {
  building_id: number;
  flat_no: string;
  floor: string;
  size_sqft?: number | string;
  service_charge_rate?: number | string;
  empty_flat_rate?: number | string;
  gas_meter_no?: string;
  occupancy_status?: string;
  status?: string;
}

export interface Building {
  id: number;
  name: string;
  address: string;
  total_floors: number;
  total_flats: number;
}

export function listBuildings() {
  return client.get<Building[]>('/masters/buildings').then((r) => r.data);
}

export function listFlats(params: { search?: string } = {}) {
  return client.get<{ data: Flat[] }>('/masters/flats', { params }).then((r) => r.data.data ?? (r.data as any));
}

export function getFlat(id: number) {
  return client.get<Flat>(`/masters/flats/${id}`).then((r) => r.data);
}

export function createFlat(data: FlatFormInput) {
  return client.post<Flat>('/masters/flats', data).then((r) => r.data);
}

export function updateFlat(id: number, data: Partial<FlatFormInput>) {
  return client.put<Flat>(`/masters/flats/${id}`, data).then((r) => r.data);
}

export function deleteFlat(id: number) {
  return client.delete(`/masters/flats/${id}`);
}

export function assignOwner(
  flatId: number,
  data: { person: PersonData; from_date: string; is_billing_contact?: boolean },
) {
  return client.post<FlatParty>(`/masters/flats/${flatId}/owners`, data).then((r) => r.data);
}

export function endOwnership(flatId: number, flatPartyId: number, toDate?: string) {
  return client
    .delete<FlatParty>(`/masters/flats/${flatId}/owners/${flatPartyId}`, { data: { to_date: toDate } })
    .then((r) => r.data);
}

export function assignTenant(
  flatId: number,
  data: {
    person: PersonData;
    start_date: string;
    occupation?: string;
    family_members_count?: number;
    monthly_rent?: number | string;
    security_deposit?: number | string;
  },
) {
  return client.post<TenantData>(`/masters/flats/${flatId}/tenants`, data).then((r) => r.data);
}

export function vacateTenant(tenantId: number, endDate?: string) {
  return client.post<TenantData>(`/masters/tenants/${tenantId}/vacate`, { end_date: endDate }).then((r) => r.data);
}
