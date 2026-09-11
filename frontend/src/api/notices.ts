import client from './client';

export interface Notice {
  id: number;
  title: string;
  body: string;
  audience: 'ALL' | 'OWNERS' | 'TENANTS';
  published_at: string;
}

export function listNotices() {
  return client.get<Notice[]>('/notices').then((r) => r.data);
}

export function createNotice(data: { title: string; body: string; audience?: string }) {
  return client.post<Notice>('/notices', data).then((r) => r.data);
}
