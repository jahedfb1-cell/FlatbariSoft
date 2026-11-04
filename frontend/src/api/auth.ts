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

/** Password login by e-mail address or mobile number. */
export function loginWithPassword(identifier: string, password: string) {
  return client.post<VerifyOtpResponse>('/auth/login', { identifier, password }).then((r) => r.data);
}

/** "Forgot password": an e-mail gets a link, a mobile number gets an SMS code. The answer never says whether the account exists. */
export function forgotPassword(identifier: string) {
  return client.post<{ channel: 'email' | 'sms'; message: string }>('/auth/forgot-password', { identifier }).then((r) => r.data);
}

export function resetPasswordByLink(d: { email: string; token: string; password: string; password_confirmation: string }) {
  return client.post<{ message: string }>('/auth/reset-password', d).then((r) => r.data);
}

export function resetPasswordByCode(d: { mobile: string; code: string; password: string; password_confirmation: string }) {
  return client.post<{ message: string }>('/auth/reset-password', d).then((r) => r.data);
}

export function me() {
  return client.get<CurrentUser>('/auth/me').then((r) => r.data);
}

export function logout() {
  return client.post('/auth/logout');
}

export function changePassword(currentPassword: string, newPassword: string, newPasswordConfirmation: string) {
  return client
    .post<{ message: string }>('/auth/change-password', {
      current_password: currentPassword,
      new_password: newPassword,
      new_password_confirmation: newPasswordConfirmation,
    })
    .then((r) => r.data);
}
