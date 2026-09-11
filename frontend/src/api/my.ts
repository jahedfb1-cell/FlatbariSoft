import client from './client';
import type { Flat } from './flats';
import type { BillRow } from './billing';

export function myFlats() {
  return client.get<Flat[]>('/me/flats').then((r) => r.data);
}

export function myBills() {
  return client.get<BillRow[]>('/me/bills').then((r) => r.data);
}

export function myDocuments() {
  return client.get('/me/documents').then((r) => r.data);
}

export function myComplaints() {
  return client.get('/me/complaints').then((r) => r.data);
}

export function submitMyComplaint(data: { flat_id: number; category: string; description: string }) {
  return client.post('/me/complaints', data).then((r) => r.data);
}
