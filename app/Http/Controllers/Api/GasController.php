<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GasConsumerRequest;
use App\Http\Requests\GasReadingSheetRequest;
use App\Http\Requests\StoreGasReadingRequest;
use App\Http\Resources\GasConsumerResource;
use App\Models\GasConsumer;
use App\Services\GasBillingService;
use App\Services\GasReadingSheetService;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GasController extends Controller
{
    public function __construct(private readonly GasBillingService $service, private readonly GasReadingSheetService $sheets) {}

    /** The month's readings for every flat: last month's reading, this month's if already typed, and how many flats are still missing. */
    public function readingSheet(GasReadingSheetRequest $request)
    {
        $y = (int) $request->validated('year');
        $m = (int) $request->validated('month');
        $rows = $this->sheets->sheet($y, $m);

        return response()->json([
            'year' => $y, 'month' => $m, 'rows' => $rows,
            'missing_flats' => collect($rows)->where('is_office', false)->whereNull('current_reading')->count(),
        ]);
    }

    /** Saves the whole sheet at once - every row is checked first, and one wrong row saves nothing. */
    public function saveReadingSheet(GasReadingSheetRequest $request)
    {
        $v = $request->validated();
        $result = $this->sheets->save((int) $v['year'], (int) $v['month'], $v['rows'], $v['reading_date'] ?? now('Asia/Dhaka')->toDateString(), Auth::id());

        return response()->json($result + ['message' => 'রিডিং সংরক্ষিত হয়েছে।'], 201);
    }

    /** The Excel template with last month's readings filled in. */
    public function readingTemplate(GasReadingSheetRequest $request)
    {
        $y = (int) $request->validated('year');
        $m = (int) $request->validated('month');
        $book = $this->sheets->template($y, $m);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, sprintf('gas-readings-%04d-%02d.xlsx', $y, $m), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Reads an uploaded sheet and says what it would save (units, problems) - nothing is saved until the sheet is saved. */
    public function importReadings(GasReadingSheetRequest $request)
    {
        $rows = $this->sheets->rowsFromFile($request->file('file')->getRealPath());

        return response()->json($this->sheets->check((int) $request->validated('year'), (int) $request->validated('month'), $rows));
    }

    public function consumers(Request $request)
    {
        $this->service->syncFlatConsumers();

        // the project office first, then the flats in flat-number order
        return GasConsumerResource::collection(
            GasConsumer::with('flat')->when(! $request->boolean('all'), fn ($q) => $q->where('active', true))->get()
                ->sortBy(fn ($c) => [$c->flat_id ? 1 : 0, $c->flat?->floor ?? 0, $c->flat?->flat_no ?? ''])->values()
        );
    }

    /** Add a gas connection by hand: a flat's meter (flat_id) or any other consumer such as the project office. */
    public function storeConsumer(GasConsumerRequest $request)
    {
        $data = $request->validated();
        // a flat's consumer is never internal; an internal one never belongs to a flat
        $internal = empty($data['flat_id']) && ! empty($data['is_internal']);

        $consumer = GasConsumer::create([
            'flat_id' => $data['flat_id'] ?? null,
            'name' => $data['name'],
            'meter_no' => $data['meter_no'] ?? null,
            'is_internal' => $internal,
            'active' => $data['active'] ?? true,
        ]);

        return (new GasConsumerResource($consumer->load('flat')))->response()->setStatusCode(201);
    }

    /** Rename, change the meter number, or switch a consumer off (its readings are kept). */
    public function updateConsumer(GasConsumerRequest $request, GasConsumer $consumer)
    {
        $consumer->update($request->validated());

        return new GasConsumerResource($consumer->load('flat'));
    }

    public function storeReading(StoreGasReadingRequest $request)
    {
        $consumer = GasConsumer::findOrFail($request->validated('gas_consumer_id'));

        $reading = $this->service->recordReading(
            $consumer,
            $request->validated('billing_period_id'),
            $request->validated('current_reading'),
            $request->validated('reading_date'),
            Auth::id(),
        );

        return response()->json([
            'id' => $reading->id,
            'previous_reading' => $reading->previous_reading,
            'current_reading' => $reading->current_reading,
            'units_used' => $reading->units_used,
        ], 201);
    }

    public function applyToBills(int $billingPeriodId)
    {
        $this->service->applyToBills($billingPeriodId);

        return response()->json(['message' => 'গ্যাস বিল সব ফ্ল্যাটের বিলে যোগ হয়েছে।']);
    }

    /** Top-of-page numbers: total purchases and what the flats used this month (?year=&month=, default this month). */
    public function summary(Request $request)
    {
        $now = now('Asia/Dhaka');

        return response()->json($this->service->summary(
            (int) $request->query('year', $now->year),
            (int) $request->query('month', $now->month),
        ));
    }

    public function systemLoss(int $year, int $month, int $billingPeriodId)
    {
        return response()->json(['system_loss_percent' => $this->service->systemLossPercent($year, $month, $billingPeriodId)]);
    }
}
