import client from './client';

export interface OwnerDocument {
  id: number;
  person_id: number;
  flat_id: number;
  document_type: 'NID' | 'NAMJARI' | 'LAND_TAX_RECEIPT' | 'OTHER';
  side: 'FRONT' | 'BACK' | null;
  document_year: number | null;
  issued_date: string | null;
  expiry_date: string | null;
  file_url: string;
  created_at: string;
}

export interface TenantDocument {
  id: number;
  tenant_id: number;
  document_type: 'NID' | 'PHOTO' | 'POLICE_VERIFICATION';
  side: 'FRONT' | 'BACK' | null;
  file_url: string;
  uploaded_at: string;
}

export function listOwnerDocuments(personId: number) {
  return client.get<OwnerDocument[]>(`/masters/persons/${personId}/documents`).then((r) => r.data);
}

export function uploadOwnerDocument(
  personId: number,
  data: {
    flat_id: number;
    document_type: string;
    side?: string;
    document_year?: string;
    issued_date?: string;
    expiry_date?: string;
    file: File;
  },
) {
  const form = new FormData();
  Object.entries(data).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      form.append(key, value as any);
    }
  });
  return client
    .post<OwnerDocument>(`/masters/persons/${personId}/documents`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    .then((r) => r.data);
}

export function deleteOwnerDocument(id: number) {
  return client.delete(`/masters/owner-documents/${id}`);
}

export function listTenantDocuments(tenantId: number) {
  return client.get<TenantDocument[]>(`/masters/tenants/${tenantId}/documents`).then((r) => r.data);
}

export function uploadTenantDocument(tenantId: number, data: { document_type: string; side?: string; file: File }) {
  const form = new FormData();
  form.append('document_type', data.document_type);
  if (data.side) form.append('side', data.side);
  form.append('file', data.file);
  return client
    .post<TenantDocument>(`/masters/tenants/${tenantId}/documents`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    .then((r) => r.data);
}

export function deleteTenantDocument(id: number) {
  return client.delete(`/masters/tenant-documents/${id}`);
}
