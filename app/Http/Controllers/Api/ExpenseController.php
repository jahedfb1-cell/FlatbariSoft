<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $service) {}

    public function index(Request $request)
    {
        $expenses = Expense::when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return ExpenseResource::collection($expenses);
    }

    public function store(StoreExpenseRequest $request)
    {
        $expense = $this->service->create($request->validated(), Auth::id());

        return new ExpenseResource($expense);
    }

    public function approve(Expense $expense)
    {
        $expense = $this->service->approve($expense, Auth::id());

        return new ExpenseResource($expense);
    }
}
