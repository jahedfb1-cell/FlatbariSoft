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

    /** @param  array<string, mixed>  $ledger  from GasLedgerService::ledger() */
    public function gasLedger(array $ledger): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Gas Purchase Ledger');

        $sheet->setCellValue('A1', 'গ্রীন ভ্যালি — গ্যাস ক্রয় লেজার');
        $sheet->mergeCells('A1:K1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', 'সময়: '.($ledger['from'] ?: 'শুরু').' থেকে '.($ledger['to'] ?: 'আজ'));

        $headers = ['রেফ', 'তারিখ', 'সরবরাহকারী', 'কেজি', 'রেট', 'পরিবহন', 'মোট', 'ভাউচার নং', 'বিল ছবি', 'চলমান কেজি', 'চলমান মোট'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i).'4', $h);
        }
        $this->headerStyle($spreadsheet, 'A4:K4');

        $row = 5;
        foreach ($ledger['rows'] as $r) {
            $sheet->setCellValue("A{$row}", $r['ref']);
            $sheet->setCellValue("B{$row}", $r['date']);
            $sheet->setCellValue("C{$row}", $r['supplier_name']);
            $sheet->setCellValue("D{$row}", (float) $r['quantity_kg']);
            $sheet->setCellValue("E{$row}", (float) $r['unit_price']);
            $sheet->setCellValue("F{$row}", (float) $r['transport_cost']);
            $sheet->setCellValue("G{$row}", (float) $r['total_cost']);
            $sheet->setCellValue("H{$row}", $r['voucher_no'] ?: ($r['status'] === 'PENDING' ? 'অপেক্ষমাণ' : '-'));
            $sheet->setCellValue("I{$row}", $r['has_invoice'] ? 'আছে' : 'নেই');
            $sheet->setCellValue("J{$row}", (float) $r['running_kg']);
            $sheet->setCellValue("K{$row}", (float) $r['running_cost']);
            $row++;
        }

        $t = $ledger['totals'];
        $sheet->setCellValue("A{$row}", 'মোট');
        $sheet->setCellValue("D{$row}", (float) $t['quantity_kg']);
        $sheet->setCellValue("F{$row}", (float) $t['transport_cost']);
        $sheet->setCellValue("G{$row}", (float) $t['total_cost']);
        $sheet->getStyle("A{$row}:K{$row}")->getFont()->setBold(true);
        $sheet->getStyle("D5:G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("J5:K{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

        $row += 2;
        $sheet->setCellValue("A{$row}", 'গড় খরচ প্রতি কেজি (পরিবহনসহ)');
        $sheet->setCellValue("G{$row}", (float) $t['avg_cost_per_kg']);
        $sheet->setCellValue('A'.($row + 1), 'এ পর্যন্ত মোট কেনা (সব সময়)');
        $sheet->setCellValue('D'.($row + 1), (float) $ledger['overall']['quantity_kg']);
        $sheet->setCellValue('G'.($row + 1), (float) $ledger['overall']['total_cost']);

        foreach (range('A', 'K') as $col) {
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
