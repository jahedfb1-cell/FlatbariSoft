import client from './client';

/** The building's phone numbers, as the office keeps them (residents read them through /me/contacts). */
export interface BuildingContact {
  id: number;
  name: string;
  role: string | null;
  phone: string;
  sort_order: number;
  is_active: boolean;
}

export type ContactInput = { name: string; role?: string; phone: string; is_active?: boolean };

export const listBuildingContacts = () => client.get<BuildingContact[]>('/building-contacts').then((r) => r.data);
export const createBuildingContact = (d: ContactInput) => client.post<BuildingContact>('/building-contacts', d).then((r) => r.data);
export const updateBuildingContact = (id: number, d: ContactInput) => client.put<BuildingContact>(`/building-contacts/${id}`, d).then((r) => r.data);
export const deleteBuildingContact = (id: number) => client.delete(`/building-contacts/${id}`).then((r) => r.data);
