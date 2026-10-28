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

    /** @param  array<string, mixed>  $report  from ContributionService::statement() */
    public function contributionStatement(array $report): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($report['book']['name_en'], 0, 28));

        $sheet->setCellValue('A1', 'গ্রীন ভ্যালি — '.$report['book']['name_bn'].' (ফ্ল্যাটভিত্তিক)');
        $sheet->setCellValue('A2', $report['book']['has_target'] ? 'ডিফল্ট টার্গেট: '.number_format((float) $report['default_target'], 2) : 'টার্গেট এখনও ঠিক করা হয়নি');

        $headers = ['ক্রম', 'ফ্ল্যাট', 'মালিক'];
        foreach ($report['heads'] as $h) {
            $headers[] = $h['name_bn'];
        }
        array_push($headers, 'মোট জমা', 'টার্গেট', 'বকেয়া (+) / বেশি জমা (−)');

        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, 4], $h);
        }
        $last = count($headers);
        $this->headerStyle($spreadsheet, 'A4:'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($last).'4');

        $row = 5;
        foreach ($report['rows'] as $n => $r) {
            $col = 1;
            $sheet->setCellValue([$col++, $row], $n + 1);
            $sheet->setCellValue([$col++, $row], $r['flat_no']);
            $sheet->setCellValue([$col++, $row], $r['owner']);
            foreach ($report['heads'] as $h) {
                $sheet->setCellValue([$col++, $row], (float) $r['heads'][$h['code']]);
            }
            $sheet->setCellValue([$col++, $row], (float) $r['collected']);
            $sheet->setCellValue([$col++, $row], $r['target'] === null ? null : (float) $r['target']);
            $sheet->setCellValue([$col++, $row], $r['balance'] === null ? null : (float) $r['balance']);
            $row++;
        }

        $col = 3;
        $sheet->setCellValue([1, $row], 'মোট');
        foreach ($report['heads'] as $h) {
            $sheet->setCellValue([++$col, $row], (float) $report['totals'][$h['code']]);
        }
        $sheet->setCellValue([++$col, $row], (float) $report['totals']['collected']);
        $sheet->setCellValue([++$col, $row], (float) $report['totals']['target']);
        $sheet->setCellValue([++$col, $row], (float) $report['totals']['balance_due'] - (float) $report['totals']['excess']);
        $sheet->getStyle("A{$row}:".\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($last)."{$row}")->getFont()->setBold(true);
        $sheet->getStyle('D5:'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($last)."{$row}")->getNumberFormat()->setFormatCode('#,##0');

        for ($c = 1; $c <= $last; $c++) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /** @param  array<string, mixed>  $r  from FundStatementService::statement() */
    public function fundStatement(array $r): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($r['fund']['name_en'], 0, 28));

        $sheet->setCellValue('A1', 'গ্রীন ভ্যালি — '.$r['fund']['name_bn'].' — মাসিক জমা-খরচের বিবরণী');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->setCellValue('A2', date('F Y', strtotime($r['from'])));

        foreach (['তারিখ', 'জমা — বিবরণ', 'টাকা', 'তারিখ', 'খরচ — বিবরণ', 'টাকা'] as $i => $h) {
            $sheet->setCellValue([$i + 1, 4], $h);
        }
        $this->headerStyle($spreadsheet, 'A4:F4');

        $row = 5;
        $sheet->setCellValue("A{$row}", $r['from']);
        $sheet->setCellValue("B{$row}", 'শুরুর ব্যালেন্স — ক্যাশ ইন হ্যান্ড');
        $sheet->setCellValue("C{$row}", (float) $r['opening']['cash']);
        $row++;
        $sheet->setCellValue("A{$row}", $r['from']);
        $sheet->setCellValue("B{$row}", 'শুরুর ব্যালেন্স — ব্যাংক');
        $sheet->setCellValue("C{$row}", (float) $r['opening']['bank']);
        $row++;

        $n = max(count($r['receipts']), count($r['payments']));
        for ($i = 0; $i < $n; $i++, $row++) {
            if (isset($r['receipts'][$i])) {
                $sheet->setCellValue("A{$row}", $r['receipts'][$i]['date']);
                $sheet->setCellValue("B{$row}", $r['receipts'][$i]['particulars']);
                $sheet->setCellValue("C{$row}", (float) $r['receipts'][$i]['amount']);
            }
            if (isset($r['payments'][$i])) {
                $sheet->setCellValue("D{$row}", $r['payments'][$i]['date']);
                $sheet->setCellValue("E{$row}", $r['payments'][$i]['particulars']);
                $sheet->setCellValue("F{$row}", (float) $r['payments'][$i]['amount']);
            }
        }

        $sheet->setCellValue("B{$row}", 'মোট জমা');
        $sheet->setCellValue("C{$row}", (float) $r['totals']['receipts']);
        $sheet->setCellValue("E{$row}", 'মোট খরচ');
        $sheet->setCellValue("F{$row}", (float) $r['totals']['payments']);
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
        $row++;
        $sheet->setCellValue("D{$row}", $r['to']);
        $sheet->setCellValue("E{$row}", 'শেষ ব্যালেন্স — ব্যাংক');
        $sheet->setCellValue("F{$row}", (float) $r['closing']['bank']);
        $row++;
        $sheet->setCellValue("D{$row}", $r['to']);
        $sheet->setCellValue("E{$row}", 'শেষ ব্যালেন্স — ক্যাশ ইন হ্যান্ড');
        $sheet->setCellValue("F{$row}", (float) $r['closing']['cash']);
        $row++;
        $sheet->setCellValue("B{$row}", 'সর্বমোট');
        $sheet->setCellValue("C{$row}", (float) $r['left_total']);
        $sheet->setCellValue("E{$row}", 'সর্বমোট');
        $sheet->setCellValue("F{$row}", (float) $r['right_total']);
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
        $sheet->getStyle("C5:C{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("F5:F{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

        $row += 2;
        $sheet->setCellValue("A{$row}", 'খাতওয়ারি সারাংশ');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;
        foreach ([['জমা', $r['receipts_by_head'], 'B', 'C'], ['খরচ', $r['payments_by_head'], 'E', 'F']] as [$title, $heads, $lc, $vc]) {
            $rr = $row;
            $sheet->setCellValue("{$lc}{$rr}", $title);
            $sheet->getStyle("{$lc}{$rr}")->getFont()->setBold(true);
            foreach ($heads as $h) {
                $rr++;
                $sheet->setCellValue("{$lc}{$rr}", $h['name_bn']);
                $sheet->setCellValue("{$vc}{$rr}", (float) $h['amount']);
            }
            $sheet->getStyle("{$vc}".($row + 1).":{$vc}{$rr}")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        foreach (['A' => 12, 'B' => 48, 'C' => 14, 'D' => 12, 'E' => 48, 'F' => 14] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
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
