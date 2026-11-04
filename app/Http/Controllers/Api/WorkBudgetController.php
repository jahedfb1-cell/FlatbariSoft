<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkBudgetRequest;
use App\Models\Expense;
use App\Models\WorkBudget;
use App\Services\WorkBudgetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkBudgetController extends Controller
{
    public function __construct(private readonly WorkBudgetService $service) {}

    public function index()
    {
        if (! Schema::hasTable('work_budgets')) {
            return response()->json([]); // the table arrives with the database upgrade
        }

        $works = WorkBudget::with('book')->latest('id')->get()->map(function (WorkBudget $w) {
            $s = $this->service->summary($w);
            unset($s['rows']);

            return $s;
        });

        return response()->json($works->values());
    }

    public function preview(StoreWorkBudgetRequest $request)
    {
        $rows = $this->service->preview($request->validated('split_method'), (string) $request->validated('estimated_cost'), $request->validated('flat_ids'));

        return response()->json(['rows' => $rows]);
    }

    public function store(StoreWorkBudgetRequest $request)
    {
        if (! Schema::hasTable('work_budgets')) {
            throw ValidationException::withMessages(['title' => 'ডাটাবেস আপগ্রেড বাকি আছে — আপগ্রেড ফাইল ইমপোর্ট করার পর কাজের বাজেট তৈরি করা যাবে।']);
        }

        $work = $this->service->create($request->validated(), $request->validated('shares'), Auth::id());

        return response()->json($this->detail($work), 201);
    }

    public function show(WorkBudget $workBudget)
    {
        return response()->json($this->detail($workBudget));
    }

    public function update(Request $request, WorkBudget $workBudget)
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'estimated_cost' => ['sometimes', 'required', 'numeric', 'min:1', 'max:999999999'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(WorkBudget::STATUSES)],
            'resolution_id' => ['nullable', 'integer', 'exists:resolutions,id'],
        ]);
        $this->service->update($workBudget, $data);

        return response()->json($this->detail($workBudget->fresh()));
    }

    public function shares(Request $request, WorkBudget $workBudget)
    {
        $v = $request->validate([
            'shares' => ['required', 'array', 'min:1'],
            'shares.*.flat_id' => ['required', 'integer', 'distinct', 'exists:flats,id'],
            'shares.*.amount' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'shares.*.note' => ['nullable', 'string', 'max:255'],
        ], ['shares.required' => 'অন্তত একটি ফ্ল্যাট রাখুন।']);
        $this->service->setShares($workBudget, $v['shares']);

        return response()->json($this->detail($workBudget->fresh()));
    }

    /** The special collections one flat still has to pay - what the payment pop-up offers besides the monthly bill. */
    public function forFlat(\App\Models\Flat $flat)
    {
        return response()->json($this->service->forFlat($flat));
    }

    /** One payment for one work: its own receipt, its own ledger. */
    public function collect(Request $request, WorkBudget $workBudget)
    {
        $v = $request->validate([
            'flat_id' => ['required', 'integer', 'exists:flats,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'received_on' => ['required', 'date', 'before_or_equal:'.now('Asia/Dhaka')->toDateString()],
            'method' => ['required', Rule::in(\App\Http\Requests\StoreContributionRequest::methods())],
            'reference_no' => [Rule::requiredIf(fn () => $request->input('method') !== 'CASH'), 'nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['reference_no.required' => 'চেক / ট্রানজেকশন নম্বর আবশ্যক।', 'received_on.before_or_equal' => 'ভবিষ্যতের তারিখ দেওয়া যাবে না।']);
        $row = $this->service->collect($workBudget, \App\Models\Flat::findOrFail($v['flat_id']), $v, Auth::id());

        return response()->json(['receipt_no' => $row->receipt_no, 'amount' => (string) $row->amount, 'work' => $this->service->forFlat(\App\Models\Flat::find($v['flat_id']), all: true)], 201);
    }

    public function schedule(Request $request, WorkBudget $workBudget)
    {
        $v = $request->validate([
            'installments' => ['present', 'array', 'max:24'],
            'installments.*.due_date' => ['required', 'date'],
            'installments.*.percent' => ['required', 'numeric', 'gt:0', 'max:100'],
        ]);
        $this->service->setSchedule($workBudget, $v['installments']);

        return response()->json($this->detail($workBudget->fresh()));
    }

    /** @return array<string, mixed> */
    private function detail(WorkBudget $work): array
    {
        $work->loadMissing('book');

        return $this->service->summary($work) + [
            'expenses' => Expense::where('work_budget_id', $work->id)->orderByDesc('date')->orderByDesc('id')->get()
                ->map(fn ($e) => ['id' => $e->id, 'date' => $e->date?->toDateString(), 'payee' => $e->payee, 'amount' => (string) $e->amount, 'status' => $e->status, 'voucher_no' => $e->voucher_no])->values(),
        ];
    }
}
