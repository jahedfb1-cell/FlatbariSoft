import client from './client';

export interface Staff {
  id: number;
  name: string;
  designation: string;
  mobile: string;
  nid: string | null;
  join_date: string;
  monthly_salary: string;
  status: 'ACTIVE' | 'INACTIVE';
}

export interface StaffFormInput {
  name: string;
  designation: string;
  mobile: string;
  nid?: string;
  join_date: string;
  monthly_salary: number | string;
}

export function listStaff() {
  return client.get<{ data: Staff[] }>('/masters/staff').then((r) => (r.data as any).data ?? r.data);
}

export function createStaff(data: StaffFormInput) {
  return client.post<Staff>('/masters/staff', data).then((r) => r.data);
}

export function updateStaff(id: number, data: Partial<StaffFormInput>) {
  return client.put<Staff>(`/masters/staff/${id}`, data).then((r) => r.data);
}

export function deleteStaff(id: number) {
  return client.delete(`/masters/staff/${id}`);
}
