import client from './client';

export interface AppNotification {
  id: string;
  kind: string | null;
  title: string;
  body: string;
  link: string | null;
  read: boolean;
  created_at: string | null;
}

export interface NotificationList {
  unread: number;
  items: AppNotification[];
}

export const listNotifications = () => client.get<NotificationList>('/me/notifications').then((r) => r.data);
export const markNotificationRead = (id: string) => client.post<{ unread: number }>(`/me/notifications/${id}/read`).then((r) => r.data);
export const markAllNotificationsRead = () => client.post<{ unread: number }>('/me/notifications/read-all').then((r) => r.data);
