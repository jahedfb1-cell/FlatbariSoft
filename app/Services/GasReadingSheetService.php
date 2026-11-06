<?php

namespace App\Services;

use App\Models\BillingPeriod;
use App\Models\GasConsumer;
use App\Models\GasMeterReading;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * The month's meter readings for every flat (and the project office) on one sheet.
 *
 * A reading is what the meter shows. Last month's reading is already in the system, so the office only types this
 * month's; units used = this reading - last reading. The very first month has no earlier reading, so there the
 * meter's starting reading is typed too (or the units used, which gives it back).
 *
 * The same sheet can come from Excel: a template is downloaded with last month's readings filled in, the current
 * column is filled, and the file is uploaded. Everything is checked first; if one row is wrong nothing is saved.
 */
class GasReadingSheetService
{
    public function __construct(private readonly GasBillingService $gas) {}

    /** The consumers in sheet order: the project office first, then the flats by flat number. */
    private function consumers()
    {
        $this->gas->syncFlatConsumers();

        return GasConsumer::with('flat')->where('active', true)->get()
            ->sortBy(fn (GasConsumer $c) => [$c->flat_id ? 1 : 0, (int) ($c->flat?->flat_no ?? 0), (string) ($c->flat?->flat_no ?? '')])
            ->values();
    }

    private function period(int $year, int $month, bool $create = false): ?BillingPeriod
    {
        return $create
            ? BillingPeriod::firstOrCreate(['year' => $year, 'month' => $month], ['status' => 'DRAFT'])
            : BillingPeriod::where('year', $year)->where('month', $month)->first();
    }

    /**
     * One row per consumer: where the meter stood last month, and what has been entered for this month (if anything).
     *
     * @return array<int, array<string, mixed>>
     */
    public function sheet(int $year, int $month): array
    {
        $period = $this->period($year, $month);
        $probe = $period ?? new BillingPeriod(['year' => $year, 'month' => $month]);

        return $this->consumers()->map(function (GasConsumer $c) use ($period, $probe) {
            $previous = $this->gas->previousReadingFor($c, $probe);
            $this_ = $period ? GasMeterReading::where('gas_consumer_id', $c->id)->where('billing_period_id', $period->id)->first() : null;

            return [
                'consumer_id' => $c->id,
                'flat_no' => $c->flat?->flat_no,
                'name' => $c->name,
                'is_office' => ! $c->flat_id,
                'previous_reading' => $previous ? $this->num($previous->current_reading) : null,
                'previous_known' => (bool) $previous,
                'current_reading' => $this_ ? $this->num($this_->current_reading) : null,
                'units_used' => $this_ ? $this->num($this_->units_used) : null,
            ];
        })->all();
    }

    /**
     * Works out every row (previous, current, units) and finds what is wrong, saving nothing.
     * A row with nothing typed is skipped. $rows: [['flat_no' | 'consumer_id', 'previous_reading'?, 'current_reading'?, 'units'?], ...]
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, problems: array<int, string>, ready: int, skipped: int}
     */
    public function check(int $year, int $month, array $rows): array
    {
        $period = $this->period($year, $month);
        $probe = $period ?? new BillingPeriod(['year' => $year, 'month' => $month]);
        $consumers = $this->consumers();
        $byFlat = $consumers->filter(fn ($c) => $c->flat_id)->keyBy(fn ($c) => strtoupper(trim((string) $c->flat->flat_no)));
        $byId = $consumers->keyBy('id');
        $office = $consumers->first(fn ($c) => ! $c->flat_id);

        $out = [];
        $problems = [];
        $seen = [];
        $skipped = 0;

        foreach (array_values($rows) as $i => $row) {
            $line = $i + 1;
            $no = strtoupper(trim((string) ($row['flat_no'] ?? '')));
            $consumer = isset($row['consumer_id']) ? $byId->get((int) $row['consumer_id']) : null;
            $consumer ??= $byFlat->get($no);
            if (! $consumer && in_array($no, ['OFFICE', 'PROJECT OFFICE', 'প্রজেক্ট অফিস'], true)) {
                $consumer = $office;
            }
            if (! $consumer) {
                if ($no !== '') {
                    $problems[] = "লাইন {$line}: ফ্ল্যাট '{$no}' পাওয়া যায়নি।";
                }

                continue;
            }
            if (isset($seen[$consumer->id])) {
                $problems[] = "লাইন {$line}: ".$this->label($consumer).' একাধিকবার আছে।';

                continue;
            }
            $seen[$consumer->id] = true;

            $given = fn (string $k) => isset($row[$k]) && trim((string) $row[$k]) !== '' ? trim((string) $row[$k]) : null;
            $current = $given('current_reading');
            $units = $given('units');
            $typedPrevious = $given('previous_reading');
            foreach (['current_reading' => $current, 'units' => $units, 'previous_reading' => $typedPrevious] as $name => $v) {
                if ($v !== null && (! is_numeric($v) || (float) $v < 0)) {
                    $problems[] = "লাইন {$line}: ".$this->label($consumer).' — সংখ্যা ঠিক নয়।';

                    continue 2;
                }
            }
            if ($current === null && $units === null) {
                $skipped++;

                continue; // nothing typed for this one
            }

            $known = $this->gas->previousReadingFor($consumer, $probe);
            if ($known) {
                $previous = $this->num($known->current_reading);
                if ($typedPrevious !== null && bccomp($this->num($typedPrevious), $previous, 4) !== 0) {
                    $problems[] = "লাইন {$line}: ".$this->label($consumer)." — আগের রিডিং সিস্টেমে {$previous}, আপনার ফাইলে {$typedPrevious}। মিলছে না।";

                    continue;
                }
            } elseif ($typedPrevious !== null) {
                $previous = $this->num($typedPrevious);
            } elseif ($current !== null && $units !== null) {
                $previous = bcsub($this->num($current), $this->num($units), 4);
            } else {
                $problems[] = "লাইন {$line}: ".$this->label($consumer).' — প্রথম মাস, মিটারের আগের রিডিং দিন।';

                continue;
            }

            $current = $current !== null ? $this->num($current) : bcadd($previous, $this->num((string) $units), 4);
            if (bccomp($current, $previous, 4) < 0) {
                $problems[] = "লাইন {$line}: ".$this->label($consumer)." — বর্তমান রিডিং ({$current}) আগের রিডিং ({$previous}) থেকে কম।";

                continue;
            }

            $out[] = [
                'consumer_id' => $consumer->id, 'flat_no' => $consumer->flat?->flat_no, 'name' => $consumer->name,
                'is_office' => ! $consumer->flat_id, 'previous_known' => (bool) $known,
                'previous_reading' => $previous, 'current_reading' => $current, 'units_used' => bcsub($current, $previous, 4),
            ];
        }

        return ['rows' => $out, 'problems' => $problems, 'ready' => count($out), 'skipped' => $skipped];
    }

    /**
     * Saves every checked row for the month - all of them or none.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{saved: int, skipped: int, period_id: int}
     */
    public function save(int $year, int $month, array $rows, string $readingDate, int $by): array
    {
        $result = $this->check($year, $month, $rows);
        if ($result['problems']) {
            throw ValidationException::withMessages(['rows' => $result['problems']]);
        }
        if ($result['ready'] === 0) {
            throw ValidationException::withMessages(['rows' => ['কোনো রিডিং লেখা হয়নি।']]);
        }

        $period = DB::transaction(function () use ($year, $month, $result, $readingDate, $by) {
            $period = $this->period($year, $month, create: true);
            foreach ($result['rows'] as $r) {
                $this->gas->recordReading(GasConsumer::findOrFail($r['consumer_id']), $period->id, (float) $r['current_reading'], $readingDate, $by, null, $r['previous_known'] ? null : $r['previous_reading']);
            }

            return $period;
        });

        return ['saved' => $result['ready'], 'skipped' => $result['skipped'], 'period_id' => $period->id];
    }

    // ---- Excel

    /** The template: flat, name, last month's reading (filled in), this month's reading (to type), units used (worked out). */
    public function template(int $year, int $month): Spreadsheet
    {
        $book = new Spreadsheet;
        $ws = $book->getActiveSheet();
        $ws->setTitle('Gas readings');
        foreach (['Flat No', 'Name', 'Previous Reading', 'Current Reading', 'Units'] as $i => $h) {
            $ws->setCellValue([$i + 1, 1], $h);
        }
        $ws->getStyle('A1:E1')->getFont()->setBold(true);
        $ws->getStyle('A1:E1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE6F2EF');

        $r = 2;
        foreach ($this->sheet($year, $month) as $row) {
            $ws->setCellValueExplicit([1, $r], $row['is_office'] ? 'OFFICE' : (string) $row['flat_no'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValue([2, $r], $row['name']);
            if ($row['previous_reading'] !== null) {
                $ws->setCellValue([3, $r], (float) $row['previous_reading']);
            }
            if ($row['current_reading'] !== null) {
                $ws->setCellValue([4, $r], (float) $row['current_reading']);
            }
            $ws->setCellValue([5, $r], "=IF(OR(C{$r}=\"\",D{$r}=\"\"),\"\",D{$r}-C{$r})");
            $r++;
        }
        foreach (['A' => 10, 'B' => 28, 'C' => 18, 'D' => 18, 'E' => 10] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
        $ws->freezePane('A2');
        $ws->getStyle('D2:D'.($r - 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF8DC');

        return $book;
    }

    /**
     * Reads an uploaded sheet back into rows. The header names are matched loosely (Flat, Previous, Current, Units),
     * in any column order, so a sheet the office kept in its own layout still works.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rowsFromFile(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false);
        $cols = [];
        $headerAt = null;
        foreach ($data as $i => $line) {
            foreach ($line as $c => $cell) {
                $h = strtolower(trim((string) $cell));
                $key = match (true) {
                    str_contains($h, 'flat') => 'flat_no',
                    str_contains($h, 'prev') => 'previous_reading',
                    str_contains($h, 'curr') || str_contains($h, 'present') => 'current_reading',
                    $h === 'units' || str_contains($h, 'unit') => 'units',
                    default => null,
                };
                if ($key && ! isset($cols[$key])) {
                    $cols[$key] = $c;
                }
            }
            if (isset($cols['flat_no']) && (isset($cols['current_reading']) || isset($cols['units']))) {
                $headerAt = $i;
                break;
            }
            $cols = [];
        }
        if ($headerAt === null) {
            throw ValidationException::withMessages(['file' => 'ফাইলে "Flat No" ও "Current Reading" শিরোনামের কলাম পাওয়া যায়নি। টেমপ্লেট নামিয়ে সেটাতেই লিখুন।']);
        }

        $rows = [];
        foreach (array_slice($data, $headerAt + 1) as $line) {
            $row = [];
            foreach ($cols as $key => $c) {
                $v = $line[$c] ?? null;
                $row[$key] = is_string($v) ? trim($v) : $v;
            }
            if (($row['flat_no'] ?? '') === '' && ($row['current_reading'] ?? '') === '' && ($row['units'] ?? '') === '') {
                continue;
            }
            // formula results and numbers typed as text both arrive as numbers or numeric strings
            foreach (['previous_reading', 'current_reading', 'units'] as $k) {
                if (isset($row[$k]) && is_string($row[$k])) {
                    $row[$k] = str_replace(',', '', $row[$k]);
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    // ---- small helpers

    private function label(GasConsumer $c): string
    {
        return $c->flat_id ? 'ফ্ল্যাট '.$c->flat->flat_no : 'প্রজেক্ট অফিস';
    }

    private function num(mixed $v): string
    {
        return bcadd((string) $v, '0', 4);
    }
}
