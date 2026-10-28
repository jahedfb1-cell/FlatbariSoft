import client from './client';
import { openPdf } from '../utils/download';

export interface Resolution {
  id: number;
  resolution_no: string;
  description: string | null;
  decision: string | null;
  status: string;
  has_attachment: boolean;
  attachment_name: string | null;
  attachment_mime: string | null;
}

export interface Meeting {
  id: number;
  title: string;
  meeting_date: string;
  venue: string | null;
  agenda: string | null;
  status: string;
  resolutions: Resolution[];
}

export type ComplaintStatus = 'OPEN' | 'IN_PROGRESS' | 'RESOLVED' | 'CLOSED';
export type ComplaintPriority = 'LOW' | 'MEDIUM' | 'HIGH';

export interface Complaint {
  id: number;
  flat_id: number;
  flat_no: string | null;
  owner: string | null;
  tenant: string | null;
  category: string;
  description: string;
  priority: ComplaintPriority;
  status: ComplaintStatus;
  assigned_to: number | null;
  assignee: string | null;
  resolution_note: string | null;
  resolved_at: string | null;
  resolved_by: string | null;
  created_at: string;
  rating: number | null;
}

export interface ComplaintList {
  counts: Partial<Record<ComplaintStatus, number>>;
  complaints: Complaint[];
}

export function listMeetings() {
  return client.get<Meeting[]>('/meetings').then((r) => r.data);
}

export function createMeeting(data: { title: string; meeting_date: string; venue?: string; agenda?: string }) {
  return client.post<Meeting>('/meetings', data).then((r) => r.data);
}

/** A resolution is typed text, a PDF / photo, or both. */
export function addResolution(meetingId: number, data: { description?: string; attachment?: File | null }) {
  const form = new FormData();
  if (data.description) form.append('description', data.description);
  if (data.attachment) form.append('attachment', data.attachment);
  return client.post<Resolution>(`/meetings/${meetingId}/resolutions`, form).then((r) => r.data);
}

/** Attach (or replace) the file of a resolution already recorded. */
export function attachToResolution(resolutionId: number, file: File) {
  const form = new FormData();
  form.append('attachment', file);
  return client.post<Resolution>(`/meetings/resolutions/${resolutionId}/attachment`, form).then((r) => r.data);
}

export const openResolutionFile = (r: Resolution) => openPdf(`/meetings/resolutions/${r.id}/attachment`, undefined, r.attachment_name ?? `${r.resolution_no}`);

export function listComplaints(filters: { status?: string; priority?: string; q?: string } = {}) {
  const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v));
  return client.get<ComplaintList>('/complaints', { params }).then((r) => r.data);
}

export function createComplaint(data: { flat_id: number; category: string; description: string; priority?: ComplaintPriority }) {
  return client.post<Complaint>('/complaints', data).then((r) => r.data);
}

export function updateComplaintStatus(
  id: number,
  data: { status: ComplaintStatus; assigned_to?: number | null; priority?: ComplaintPriority; resolution_note?: string | null },
) {
  return client.put<Complaint>(`/complaints/${id}/status`, data).then((r) => r.data);
}
