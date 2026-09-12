import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useLanguage } from '../context/LanguageContext';
import LanguageToggle from '../components/LanguageToggle';
import * as reportsApi from '../api/reports';
import type { TrialBalance, BalanceSheet, IncomeStatement, ReportKind } from '../api/reports';

export default function ReportsPage() {
  const { t } = useLanguage();
  const [trialBalance, setTrialBalance] = useState<TrialBalance | null>(null);
  const [balanceSheet, setBalanceSheet] = useState<BalanceSheet | null>(null);
  const [incomeStatement, setIncomeStatement] = useState<IncomeStatement | null>(null);
  const [downloading, setDownloading] = useState<string | null>(null);

  useEffect(() => {
    reportsApi.getTrialBalance().then(setTrialBalance);
    reportsApi.getBalanceSheet().then(setBalanceSheet);
    reportsApi.getIncomeStatement().then(setIncomeStatement);
  }, []);

  async function handleDownload(kind: ReportKind, format: 'pdf' | 'excel') {
    const key = `${kind}-${format}`;
    setDownloading(key);
    try {
      if (format === 'pdf') await reportsApi.downloadReportPdf(kind);
      else await reportsApi.downloadReportExcel(kind);
    } finally {
      setDownloading(null);
    }
  }

  function ExportButtons({ kind }: { kind: ReportKind }) {
    return (
      <div className="flex items-center gap-2 print:hidden">
        <button onClick={() => window.print()} className="rounded-lg border border-brand-200 px-2 py-1 text-xs text-navy-900 hover:bg-brand-100">
          {t('reports.print')}
        </button>
        <button
          onClick={() => handleDownload(kind, 'pdf')}
          disabled={downloading === `${kind}-pdf`}
          className="rounded-lg border border-brand-200 px-2 py-1 text-xs text-navy-900 hover:bg-brand-100 disabled:opacity-50"
        >
          {downloading === `${kind}-pdf` ? t('reports.downloading') : t('reports.pdf')}
        </button>
        <button
          onClick={() => handleDownload(kind, 'excel')}
          disabled={downloading === `${kind}-excel`}
          className="rounded-lg border border-brand-200 px-2 py-1 text-xs text-navy-900 hover:bg-brand-100 disabled:opacity-50"
        >
          {downloading === `${kind}-excel` ? t('reports.downloading') : t('reports.excel')}
        </button>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-brand-50 px-4 py-8 print:bg-white print:px-0 print:py-0">
      {/* Tailwind's print: variant handles hiding nav/buttons; @page and
          break-inside aren't Tailwind utilities, so they're set here. */}
      <style>{`
        @media print {
          @page { size: A4; margin: 15mm; }
          .print-section { break-inside: avoid; margin-bottom: 24px; }
        }
      `}</style>
      <div className="mx-auto max-w-3xl">
        <div className="mb-6 flex items-center justify-between print:hidden">
          <div>
            <Link to="/" className="text-sm text-navy-700 hover:underline">
              ← {t('nav.dashboard')}
            </Link>
            <h1 className="mt-1 text-xl font-semibold text-navy-900">{t('reports.title')}</h1>
          </div>
          <LanguageToggle />
        </div>

        <h1 className="mb-4 hidden text-xl font-semibold text-navy-900 print:block">{t('reports.title')} — Green Valley</h1>

        {balanceSheet && (
          <div className="print-section mb-6 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
            <div className="mb-2 flex items-center justify-between">
              <h2 className="font-semibold text-navy-900">{t('reports.balanceSheet')}</h2>
              <div className="flex items-center gap-3">
                <span className={balanceSheet.balanced ? 'text-status-paid' : 'text-status-due'}>
                  {balanceSheet.balanced ? t('reports.balanced') : t('reports.notBalanced')}
                </span>
                <ExportButtons kind="balance-sheet" />
              </div>
            </div>
            <div className="grid grid-cols-3 gap-3 text-sm text-navy-900">
              <p>
                {t('reports.assets')}: ৳{balanceSheet.assets.toLocaleString('en-BD')}
              </p>
              <p>
                {t('reports.liabilities')}: ৳{balanceSheet.liabilities.toLocaleString('en-BD')}
              </p>
              <p>
                {t('reports.funds')}: ৳{balanceSheet.funds.toLocaleString('en-BD')}
              </p>
            </div>
          </div>
        )}

        {incomeStatement && (
          <div className="print-section mb-6 rounded-xl border border-brand-200 bg-white p-4 shadow-sm">
            <div className="mb-2 flex items-center justify-between">
              <h2 className="font-semibold text-navy-900">
                {t('reports.incomeStatement')} ({incomeStatement.fiscal_year})
              </h2>
              <ExportButtons kind="income-statement" />
            </div>
            <div className="grid grid-cols-3 gap-3 text-sm text-navy-900">
              <p>
                {t('reports.totalIncome')}: ৳{incomeStatement.total_income.toLocaleString('en-BD')}
              </p>
              <p>
                {t('reports.totalExpense')}: ৳{incomeStatement.total_expense.toLocaleString('en-BD')}
              </p>
              <p>
                {t('reports.netSurplus')}: ৳{incomeStatement.net_surplus.toLocaleString('en-BD')}
              </p>
            </div>
          </div>
        )}

        {trialBalance && (
          <div className="print-section overflow-x-auto rounded-xl border border-brand-200 bg-white shadow-sm">
            <div className="flex items-center justify-between border-b border-brand-100 p-4">
              <h2 className="font-semibold text-navy-900">{t('reports.trialBalance')}</h2>
              <div className="flex items-center gap-3">
                <span className={trialBalance.balanced ? 'text-status-paid' : 'text-status-due'}>
                  {trialBalance.balanced ? t('reports.balanced') : t('reports.notBalanced')}
                </span>
                <ExportButtons kind="trial-balance" />
              </div>
            </div>
            <table className="w-full text-left text-sm">
              <thead className="bg-brand-100 text-navy-900">
                <tr>
                  <th className="px-4 py-2">Account</th>
                  <th className="px-4 py-2">Debit</th>
                  <th className="px-4 py-2">Credit</th>
                </tr>
              </thead>
              <tbody>
                {trialBalance.lines.map((line) => (
                  <tr key={line.account_code} className="border-t border-brand-100">
                    <td className="px-4 py-2 text-navy-900">
                      {line.account_code} — {line.account_name}
                    </td>
                    <td className="px-4 py-2 text-navy-900">{line.debit ? `৳${line.debit.toLocaleString('en-BD')}` : ''}</td>
                    <td className="px-4 py-2 text-navy-900">{line.credit ? `৳${line.credit.toLocaleString('en-BD')}` : ''}</td>
                  </tr>
                ))}
                <tr className="border-t border-brand-200 font-semibold">
                  <td className="px-4 py-2 text-navy-900">Total</td>
                  <td className="px-4 py-2 text-navy-900">৳{trialBalance.total_debit.toLocaleString('en-BD')}</td>
                  <td className="px-4 py-2 text-navy-900">৳{trialBalance.total_credit.toLocaleString('en-BD')}</td>
                </tr>
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
