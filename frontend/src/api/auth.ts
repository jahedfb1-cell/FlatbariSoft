import client from './client';

export interface CurrentUser {
  id: number;
  mobile: string;
  email: string | null;
  role: string;
  status: string;
  last_login: string | null;
  roles: string[];
  permissions: string[];
}

export interface RequestOtpResponse {
  message: string;
  dev_otp: string | null;
}

export interface VerifyOtpResponse {
  token: string;
  user: CurrentUser;
}

export function requestOtp(mobile: string) {
  return client.post<RequestOtpResponse>('/auth/otp/request', { mobile }).then((r) => r.data);
}

export function verifyOtp(mobile: string, code: string) {
  return client.post<VerifyOtpResponse>('/auth/otp/verify', { mobile, code }).then((r) => r.data);
}

export function loginWithPassword(email: string, password: string) {
  return client.post<VerifyOtpResponse>('/auth/login', { email, password }).then((r) => r.data);
}

export function me() {
  return client.get<CurrentUser>('/auth/me').then((r) => r.data);
}

export function logout() {
  return client.post('/auth/logout');
}
