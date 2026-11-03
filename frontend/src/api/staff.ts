import client from './client';
import { openPdf } from '../utils/download';

export interface Staff {
  id: number;
  name: string;
  designation: string;
  mobile: string;
  nid: string | null;
  join_date: string;
  monthly_salary: string;
  status: 'ACTIVE' | 'INACTIVE';
  kyc_updated_at?: string | null;
  documents_count?: number;
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

export type StaffDocType = 'CV' | 'NID' | 'PHOTO' | 'CERTIFICATE' | 'OTHER';
export interface StaffFamilyMember {
  name: string;
  relation: string;
  age?: number | string | null;
  mobile?: string | null;
  occupation?: string | null;
}
export interface StaffDocument {
  id: number;
  type: StaffDocType;
  name: string;
  mime: string;
  size: number;
}
export interface StaffKyc {
  staff_id: number;
  name: string;
  designation: string;
  mobile: string;
  nid: string | null;
  join_date: string | null;
  status: string;
  complete: boolean;
  missing: string[];
  ready: boolean;
  kyc_updated_at: string | null;
  documents: StaffDocument[];
  father_name: string | null;
  mother_name: string | null;
  spouse_name: string | null;
  date_of_birth: string | null;
  gender: string | null;
  marital_status: string | null;
  blood_group: string | null;
  religion: string | null;
  alt_mobile: string | null;
  email: string | null;
  present_address: string | null;
  permanent_address: string | null;
  emergency_name: string | null;
  emergency_phone: string | null;
  emergency_relation: string | null;
  education: string | null;
  experience: string | null;
  reference_name: string | null;
  reference_phone: string | null;
  other_info: string | null;
  family_members: StaffFamilyMember[];
}

export const getStaffKyc = (id: number) => client.get<StaffKyc>(`/masters/staff/${id}/kyc`).then((r) => r.data);
export const saveStaffKyc = (id: number, d: Record<string, unknown>) => client.put<StaffKyc>(`/masters/staff/${id}/kyc`, d).then((r) => r.data);
export function uploadStaffDocs(id: number, type: StaffDocType, files: File[]) {
  const form = new FormData();
  form.append('type', type);
  files.forEach((f) => form.append('files[]', f));
  return client.post<StaffKyc>(`/masters/staff/${id}/documents`, form).then((r) => r.data);
}
export const removeStaffDoc = (docId: number) => client.delete<StaffKyc>(`/masters/staff/documents/${docId}`).then((r) => r.data);
export const openStaffDoc = (d: StaffDocument) => openPdf(`/masters/staff/documents/${d.id}`, undefined, d.name);
