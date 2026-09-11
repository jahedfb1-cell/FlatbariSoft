<?php

namespace Tests\Feature;

use App\Models\Flat;
use App\Services\ConstructionDueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class Phase21ConstructionDuesTest extends TestCase
{
    use RefreshDatabase;

    private ConstructionDueService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ConstructionDueService::class);
    }

    public function test_creating_a_due_creates_its_installments(): void
    {
        $flat = Flat::factory()->create();

        $due = $this->service->create(
            ['flat_id' => $flat->id, 'category' => 'Initial construction cost', 'source_type' => 'INITIAL_CONTRACT', 'total_amount' => 30000],
            [
                ['due_date' => '2026-01-01', 'amount' => 10000],
                ['due_date' => '2026-02-01', 'amount' => 10000],
                ['due_date' => '2026-03-01', 'amount' => 10000],
            ],
        );

        $this->assertSame(3, $due->installments()->count());
        $this->assertSame('30000.0000', $due->balance);
    }

    public function test_recording_an_installment_payment_reduces_balance(): void
    {
        $flat = Flat::factory()->create();
        $due = $this->service->create(
            ['flat_id' => $flat->id, 'category' => 'Levy', 'source_type' => 'LEVY_RESOLUTION', 'total_amount' => 10000],
            [['due_date' => '2026-01-01', 'amount' => 10000]],
        );

        $installment = $due->installments()->first();
        $this->service->recordInstallmentPayment($installment, '6000');

        $this->assertSame('PARTIAL', $installment->fresh()->status);
        $this->assertSame('4000.0000', $due->fresh()->balance);
    }

    public function test_aging_report_buckets_overdue_installments_correctly(): void
    {
        Carbon::setTestNow('2026-06-01');
        $flat = Flat::factory()->create();
        $due = $this->service->create(
            ['flat_id' => $flat->id, 'category' => 'Levy', 'source_type' => 'LEVY_RESOLUTION', 'total_amount' => 30000],
            [
                ['due_date' => '2026-05-20', 'amount' => 5000],  // 12 days overdue -> 0-30
                ['due_date' => '2026-03-15', 'amount' => 8000],  // 78 days overdue -> 31-90
                ['due_date' => '2026-01-01', 'amount' => 12000], // 151 days overdue -> 90+
                ['due_date' => '2026-06-15', 'amount' => 5000],  // not due yet -> excluded
            ],
        );

        $report = $this->service->agingReport();

        $this->assertSame(5000.0, $report['0-30']);
        $this->assertSame(8000.0, $report['31-90']);
        $this->assertSame(12000.0, $report['90+']);

        Carbon::setTestNow();
    }
}
