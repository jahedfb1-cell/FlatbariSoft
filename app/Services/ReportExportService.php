<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Excel exports use Bengali labels (name_bn) — unlike the PDF exports,
 * a spreadsheet's cells are just stored text; Excel/LibreOffice/Google
 * Sheets do their own text shaping when they open the file, so Bengali
 * renders correctly with no font-embedding workaround needed (see
 * PdfFontService for why the PDF path can't do the same).
 */
class ReportExportService
{
    private function headerStyle(Spreadsheet $spreadsheet, string $range): void
    {
        $spreadsheet->getActiveSheet()->getStyle($range)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF2F6']],
        ]);
    }

    public function trialBalance(array $report): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Trial Balance');

        $sheet->setCellValue('A1', 'গ্রীন ভ্যালি — ট্রায়াল ব্যালেন্স');
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', 'Account');
        $sheet->setCellValue('B2', 'Debit');
        $sheet->setCellValue('C2', 'Credit');
        $this->headerStyle($spreadsheet, 'A2:C2');

        $row = 3;
        foreach ($report['lines'] as $line) {
            $sheet->setCellValue("A{$row}", $line['account_code'].' — '.$line['account_name']);
            $sheet->setCellValue("B{$row}", $line['debit'] ?: null);
            $sheet->setCellValue("C{$row}", $line['credit'] ?: null);
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'Total');
        $sheet->setCellValue("B{$row}", $report['total_debit']);
        $sheet->setCellValue("C{$row}", $report['total_credit']);
        $sheet->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);

        $sheet->getStyle("B3:C{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (['A', 'B', 'C'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    public function balanceSheet(array $report): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Balance Sheet');

        $sheet->setCellValue('A1', 'গ্রীন ভ্যালি — ব্যালেন্স শিট');
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $rows = [
            ['সম্পদ (Assets)', $report['assets']],
            ['দায় (Liabilities)', $report['liabilities']],
            ['তহবিল (Funds)', $report['funds']],
            ['চলতি নিট আয় (Current Net Income)', $report['current_net_income']],
        ];

        $row = 3;
        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'অবস্থা (Status)');
        $sheet->setCellValue("B{$row}", $report['balanced'] ? 'Balanced' : 'NOT Balanced');
        $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);

        $sheet->getStyle('B3:B'.($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('A3:A'.$row)->getAlignment()->setWrapText(true);
        foreach (['A', 'B'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    public function incomeStatement(array $report): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Income Statement');

        $sheet->setCellValue('A1', 'গ্রীন ভ্যালি — আয়-ব্যয় বিবরণী ('.$report['fiscal_year'].')');
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $rows = [
            ['মোট আয় (Total Income)', $report['total_income']],
            ['মোট ব্যয় (Total Expense)', $report['total_expense']],
            ['নিট উদ্বৃত্ত/(ঘাটতি) (Net Surplus/(Deficit))', $report['net_surplus']],
        ];

        $row = 3;
        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }
        $sheet->getStyle('A'.($row - 1).':B'.($row - 1))->getFont()->setBold(true);

        $sheet->getStyle('B3:B'.($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('A3:A'.($row - 1))->getAlignment()->setWrapText(true);
        foreach (['A', 'B'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
