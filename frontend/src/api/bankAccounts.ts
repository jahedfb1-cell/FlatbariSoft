import client from './client';

export interface BankAccount {
  id: number;
  bank_name: string;
  branch: string | null;
  account_no: string;
  account_title: string;
  opening_balance: string;
  current_balance: string;
}

export function listBankAccounts() {
  return client.get<BankAccount[]>('/bank-accounts').then((r) => r.data);
}

export function createBankAccount(data: { bank_name: string; branch?: string; account_no: string; account_title: string; opening_balance?: number | string }) {
  return client.post<BankAccount>('/bank-accounts', data).then((r) => r.data);
}
