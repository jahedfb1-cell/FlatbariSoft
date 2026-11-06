<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BuildingContact;
use App\Models\Building;
use App\Services\BillPrintService;
use App\Services\ResidentPortalService;
use App\Support\PdfName;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/** The flat owner's / tenant's own home: the summary card, the bills with their breakdown, the committee's decisions, the building's phone numbers. */
class MyPortalController extends Controller
{
    public function __construct(private readonly ResidentPortalService $portal, private readonly BillPrintService $slips) {}

    public function summary(Request $request)
    {
        return response()->json($this->portal->summary($request->user()));
    }

    public function bills(Request $request)
    {
        return response()->json($this->portal->bills($request->user()));
    }

    /** The resident's own copy of a bill, the same slip the manager prints. Another flat's bill does not exist for them. */
    public function billPdf(Request $request, Bill $bill)
    {
        abort_unless($this->portal->ownsBill($request->user(), $bill), 404);
        $slip = $this->slips->slipFor($bill);
        $name = PdfName::forOwner($bill->flat, 'Bill '.$slip['month_label']);

        return PdfName::titled(Pdf::loadView('reports.bill-slips', ['building' => Building::first(), 'slips' => collect([$slip])])->setPaper('a4', 'portrait'), $name)->stream($name);
    }

    public function decisions()
    {
        return response()->json($this->portal->decisions());
    }

    /** The building's own numbers (manager, guard, lift...) - every logged-in person may read them. */
    public function contacts()
    {
        // the table arrives with a database upgrade: until then there simply are no numbers
        if (! \Illuminate\Support\Facades\Schema::hasTable('building_contacts')) {
            return response()->json([]);
        }

        return response()->json(BuildingContact::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'role', 'phone']));
    }
}
