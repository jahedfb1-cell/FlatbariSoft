import client from './client';

export interface Resolution {
  id: number;
  resolution_no: string;
  description: string;
  decision: string | null;
  status: string;
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

export interface Complaint {
  id: number;
  flat_id: number;
  category: string;
  description: string;
  priority: 'LOW' | 'MEDIUM' | 'HIGH';
  status: 'OPEN' | 'IN_PROGRESS' | 'RESOLVED' | 'CLOSED';
  resolved_at: string | null;
}

export function listMeetings() {
  return client.get<Meeting[]>('/meetings').then((r) => r.data);
}

export function createMeeting(data: { title: string; meeting_date: string; venue?: string; agenda?: string }) {
  return client.post<Meeting>('/meetings', data).then((r) => r.data);
}

export function addResolution(meetingId: number, data: { description: string; decision?: string }) {
  return client.post<Resolution>(`/meetings/${meetingId}/resolutions`, data).then((r) => r.data);
}

export function listComplaints(status?: string) {
  return client.get<Complaint[]>('/complaints', { params: status ? { status } : {} }).then((r) => r.data);
}

export function updateComplaintStatus(id: number, status: string, assignedTo?: number) {
  return client.put(`/complaints/${id}/status`, { status, assigned_to: assignedTo }).then((r) => r.data);
}
