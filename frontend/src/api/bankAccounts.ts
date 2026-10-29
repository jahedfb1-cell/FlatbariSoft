import client from './client';

export interface BankHolder {
  name: string | null;
  phone: string | null;
}

export interface BankAccount {
  id: number;
  bank_name: string;
  branch: string | null;
  account_no: string;
  account_title: string;
  mandate_rule: string | null;
  holders: BankHolder[]; // always three entries; empty ones have null name and phone
  sms_phone: string | null;
  opening_balance: string;
  current_balance: string;
}

/** The flat shape the server takes: holder_1_name, holder_1_phone ... holder_3_phone. */
export interface BankAccountInput {
  bank_name?: string;
  branch?: string | null;
  account_no?: string;
  account_title?: string;
  mandate_rule?: string | null;
  sms_phone?: string | null;
  opening_balance?: number | string;
  holder_1_name?: string | null;
  holder_1_phone?: string | null;
  holder_2_name?: string | null;
  holder_2_phone?: string | null;
  holder_3_name?: string | null;
  holder_3_phone?: string | null;
}

export function listBankAccounts() {
  return client.get<BankAccount[]>('/bank-accounts').then((r) => r.data);
}

export function createBankAccount(data: BankAccountInput) {
  return client.post<BankAccount>('/bank-accounts', data).then((r) => r.data);
}

export function updateBankAccount(id: number, data: BankAccountInput) {
  return client.put<BankAccount>(`/bank-accounts/${id}`, data).then((r) => r.data);
}
