import client from './client';

export interface ManagedUser {
  id: number;
  name: string | null;
  flats: { flat_no: string; role: 'OWNER' | 'TENANT' }[];
  mobile: string;
  email: string | null;
  status: 'ACTIVE' | 'INACTIVE';
  role: string;
  roles: string[];
  last_login: string | null;
}

export interface RoleInfo {
  name: string;
  label_bn: string;
  label_en: string;
  permissions: string[];
  users_count: number;
  locked: boolean;
  system: boolean;
}

export interface PermissionGroup {
  module: string;
  label_bn: string;
  label_en: string;
  permissions: { name: string; label_bn: string; label_en: string }[];
}

export interface NewUserInput {
  name_bn: string;
  mobile: string;
  email?: string;
  password: string;
  roles: string[];
}

export const listUsers = (params: { q?: string; role?: string; status?: string } = {}) =>
  client.get<{ data: ManagedUser[] }>('/users', { params: { ...params, per_page: 100 } }).then((r) => r.data.data);
export const createUser = (data: NewUserInput) => client.post<ManagedUser>('/users', data).then((r) => r.data);
export const updateUser = (id: number, data: { email?: string | null; status?: 'ACTIVE' | 'INACTIVE'; roles?: string[] }) =>
  client.put<ManagedUser>(`/users/${id}`, data).then((r) => r.data);
export const resetPassword = (id: number, password: string) => client.post(`/users/${id}/reset-password`, { password }).then((r) => r.data);

export const listRoles = () => client.get<{ roles: RoleInfo[]; catalog: PermissionGroup[] }>('/roles').then((r) => r.data);
export const saveRolePermissions = (name: string, permissions: string[]) =>
  client.put<{ roles: RoleInfo[] }>(`/roles/${name}/permissions`, { permissions }).then((r) => r.data.roles);
export const createRole = (name: string, permissions: string[]) => client.post<{ roles: RoleInfo[] }>('/roles', { name, permissions }).then((r) => r.data.roles);
export const deleteRole = (name: string) => client.delete<{ roles: RoleInfo[] }>(`/roles/${name}`).then((r) => r.data.roles);

export function roleLabel(r: Pick<RoleInfo, 'name' | 'label_bn' | 'label_en'>, lang: 'bn' | 'en') {
  return lang === 'bn' ? r.label_bn : r.label_en;
}
