import { useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as bankApi from '../api/bankAccounts';
import type { BankAccount } from '../api/bankAccounts';

export default function BankAccountsPage() {
  const { t } = useLanguage();
  const [accounts, setAccounts] = useState<BankAccount[]>([]);
  const [bankName, setBankName] = useState('');
  const [accountNo, setAccountNo] = useState('');
  const [accountTitle, setAccountTitle] = useState('');
  const [opening, setOpening] = useState('0');

  function load() {
    bankApi.listBankAccounts().then(setAccounts);
  }

  useEffect(load, []);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    await bankApi.createBankAccount({ bank_name: bankName, account_no: accountNo, account_title: accountTitle, opening_balance: opening });
    setBankName('');
    setAccountNo('');
    setAccountTitle('');
    setOpening('0');
    load();
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8">
      <div className="mx-auto max-w-2xl">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('bank.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        {accounts.length === 0 && <p className="mb-4 text-sm text-status-due">{t('bank.none')}</p>}

        <form onSubmit={handleSubmit} className="mb-6 grid grid-cols-2 gap-3 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
          <input required placeholder={t('bank.name')} value={bankName} onChange={(e) => setBankName(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input required placeholder={t('bank.accountNo')} value={accountNo} onChange={(e) => setAccountNo(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input required placeholder={t('bank.accountTitle')} value={accountTitle} onChange={(e) => setAccountTitle(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <input type="number" placeholder={t('bank.opening')} value={opening} onChange={(e) => setOpening(e.target.value)} className="rounded-lg border border-brand-200 px-3 py-2" />
          <button type="submit" className="col-span-2 rounded-lg bg-brand-600 px-3 py-2 text-white hover:bg-brand-700">
            {t('common.add')}
          </button>
        </form>

        <div className="space-y-2">
          {accounts.map((a) => (
            <div key={a.id} className="rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
              <p className="font-medium text-navy-900">
                {a.bank_name} — {a.account_title}
              </p>
              <p className="text-sm text-navy-700">{a.account_no}</p>
              <p className="mt-1 text-sm text-navy-900">
                {t('bank.current')}: ৳{Number(a.current_balance).toLocaleString('en-BD')}
              </p>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
