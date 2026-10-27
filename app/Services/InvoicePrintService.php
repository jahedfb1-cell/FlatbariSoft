<?php

namespace App\Services;

use App\Models\Building;
use App\Models\ConstructionMaterialPurchase;
use App\Models\GasPurchase;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Black-and-white A4 print of purchase-invoice photos, for audits.
 *
 * One invoice per page: a small details table (so the page stands on its own
 * if it gets separated from the rest), then the photo converted to greyscale
 * and sized to fill the remaining page without distortion. The colour
 * original stays untouched on disk.
 *
 * Output is English-labelled for the same reason the report PDFs are (dompdf
 * cannot shape Bengali script - see PdfFontService); the invoice photo itself
 * carries whatever language the supplier printed.
 */
class InvoicePrintService
{
    // @page is A4 with 20mm/15mm margins -> 180 x 257 mm printable; the details
    // table above the photo takes roughly 65 mm of that height.
    private const BOX_WIDTH_MM = 180;

    private const BOX_HEIGHT_MM = 190;

    /** @var list<string> temp greyscale files to delete once the PDF is built */
    private array $tempFiles = [];

    public function __construct(private readonly InvoiceImageService $images) {}

    /** A single invoice, for the "Print A4" button on a purchase row. */
    public function single(string $type, Model $purchase): string
    {
        $title = ($type === 'gas' ? 'Gas Purchase' : 'Construction Material').' Invoice - Audit Copy (B&W)';

        return $this->render($title, [$this->describe($type, $purchase)], null);
    }

    /**
     * Every purchase in [from, to]: a cover page (counts, total, which
     * purchases have no invoice photo) followed by one page per invoice.
     */
    public function auditPack(string $from, string $to, string $type = 'all'): string
    {
        $purchases = collect();
        if ($type !== 'materials') {
            GasPurchase::with('expense')->whereBetween('date', [$from, $to])->get()
                ->each(fn ($p) => $purchases->push(['gas', $p]));
        }
        if ($type !== 'gas') {
            ConstructionMaterialPurchase::with('expense')->whereBetween('date', [$from, $to])->get()
                ->each(fn ($p) => $purchases->push(['materials', $p]));
        }
        $purchases = $purchases->sortBy(fn ($row) => $row[1]->date->format('Y-m-d').$row[0])->values();

        [$withImage, $without] = $purchases->partition(fn ($row) => $this->images->exists($row[1]->invoice_file));

        $max = (int) config('invoice_images.audit_pack_max');
        if ($withImage->count() > $max) {
            throw ValidationException::withMessages([
                'to' => "এক প্যাকে সর্বোচ্চ {$max}টা ইনভয়েস — তারিখের ব্যবধান ছোট করুন (এখন {$withImage->count()}টা)।",
            ]);
        }
        @set_time_limit(180);

        $cover = [
            'from' => date('d M Y', strtotime($from)),
            'to' => date('d M Y', strtotime($to)),
            'purchases' => $purchases->count(),
            'with_invoice' => $withImage->count(),
            'without_invoice' => $without->count(),
            'total' => $purchases->sum(fn ($row) => (float) $row[1]->total_cost),
            'missing' => $without->map(fn ($row) => $this->describe($row[0], $row[1], withImage: false))->all(),
        ];

        $items = $withImage->map(fn ($row) => $this->describe($row[0], $row[1]))->all();

        return $this->render('Purchase Invoice Audit Pack (B&W)', $items, $cover);
    }

    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function describe(string $type, Model $p, bool $withImage = true): array
    {
        $gas = $type === 'gas';

        return [
            'type_label' => $gas ? 'Gas purchase' : 'Construction material',
            'date' => $p->date->format('d M Y'),
            'supplier' => $p->supplier_name ?: '-',
            'detail' => $gas
                ? number_format((float) $p->quantity_kg, 2).' kg x '.number_format((float) $p->unit_price, 2)
                : $p->item_name.' - '.number_format((float) $p->quantity, 2).' '.$p->unit.' x '.number_format((float) $p->unit_rate, 2),
            'total' => (float) $p->total_cost,
            'voucher' => $p->expense?->voucher_no ?: '-',
            'status' => $p->expense?->status ?: '-',
            'image' => $withImage ? $this->preparedImage($p->invoice_file) : null,
        ];
    }

    /**
     * Greyscale JPEG written to a temp file inside the project (dompdf only
     * reads local files from within its chroot) and sized in millimetres to
     * fit the page box with the right aspect ratio.
     *
     * @return array{src:string, width_mm:float, height_mm:float}
     */
    private function preparedImage(string $path): array
    {
        $gray = $this->images->toGrayscaleJpeg($this->images->read($path));
        [$width, $height] = getimagesizefromstring($gray);

        $dir = storage_path('app/invoice-print-tmp');
        File::ensureDirectoryExists($dir);
        $file = $dir.DIRECTORY_SEPARATOR.Str::uuid().'.jpg';
        file_put_contents($file, $gray);
        $this->tempFiles[] = $file;

        $ratio = $height / $width;
        $widthMm = min(self::BOX_WIDTH_MM, self::BOX_HEIGHT_MM / $ratio);

        return [
            // A plain absolute path with forward slashes. NOT a file:/// URL: dompdf silently
            // fails to load those when the path has spaces or a drive letter, and prints a
            // broken-image box instead - which a layout-only test would never notice.
            'src' => str_replace('\\', '/', $file),
            'width_mm' => round($widthMm, 1),
            'height_mm' => round($widthMm * $ratio, 1),
        ];
    }

    /** @param  list<array<string, mixed>>  $items */
    private function render(string $title, array $items, ?array $cover): string
    {
        try {
            return Pdf::loadView('reports.invoice-pages', [
                'building' => Building::first(),
                'title' => $title,
                'items' => $items,
                'cover' => $cover,
            ])->setPaper('a4', 'portrait')->output();
        } finally {
            foreach ($this->tempFiles as $file) {
                @unlink($file);
            }
            $this->tempFiles = [];
        }
    }
}
