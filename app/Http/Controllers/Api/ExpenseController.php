<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveExpenseRequest;
use App\Http\Requests\StoreExpenseAttachmentsRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Services\ExpenseDetailService;
use App\Services\ExpenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $service, private readonly ExpenseDetailService $detail) {}

    public function index(Request $request)
    {
        // the attachments table arrives with the database upgrade; until then the list simply has no counts
        $expenses = Expense::when(Schema::hasTable('expense_attachments'), fn ($q) => $q->withCount('attachments'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return ExpenseResource::collection($expenses);
    }

    /** A new expense; the voucher / bill papers (photos or PDFs) can be sent along as attachments[]. */
    public function store(StoreExpenseRequest $request)
    {
        $expense = $this->service->create($request->validated(), Auth::id());
        if ($request->hasFile('attachments')) {
            $request->validate((new StoreExpenseAttachmentsRequest)->rules());
            $this->detail->attach($expense, $request->file('attachments'), Auth::id());
        }

        return new ExpenseResource($expense);
    }

    /** Everything the approver needs to see before deciding. */
    public function show(Expense $expense)
    {
        return response()->json($this->detail->present($expense));
    }

    public function approve(ApproveExpenseRequest $request, Expense $expense)
    {
        $expense = $this->service->approve($expense, Auth::id(), trim($request->validated('note')));

        return new ExpenseResource($expense);
    }

    public function attach(StoreExpenseAttachmentsRequest $request, Expense $expense)
    {
        $this->detail->attach($expense, $request->file('attachments'), Auth::id());

        return response()->json($this->detail->present($expense->fresh()));
    }

    /** The stored paper itself - never a public URL; only people who may view expenses can fetch it. */
    public function file(ExpenseAttachment $attachment)
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404, 'ফাইলটি পাওয়া যায়নি।');

        return Storage::disk('local')->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_name).'"',
        ]);
    }

    /** A wrong upload can be removed until the expense is approved; after that the papers are part of the record. */
    public function removeFile(ExpenseAttachment $attachment)
    {
        abort_if($attachment->expense->status === 'APPROVED', 422, 'অনুমোদিত খরচের কাগজ সরানো যাবে না।');
        $this->detail->remove($attachment);

        return response()->json(['ok' => true]);
    }
}
