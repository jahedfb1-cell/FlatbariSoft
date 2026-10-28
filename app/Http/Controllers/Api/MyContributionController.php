<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Flat;
use App\Models\FlatParty;
use App\Services\ContributionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

/**
 * An owner's own construction-cost ledger. Only the flats the logged-in person currently OWNS: a tenant
 * has no share in the construction cost, and nobody can ask for a flat that is not theirs.
 */
class MyContributionController extends Controller
{
    public function __construct(private readonly ContributionService $service) {}

    private function ownedFlats()
    {
        $personId = Auth::user()->person_id;
        $ids = $personId ? FlatParty::where('person_id', $personId)->where('role', 'OWNER')->whereNull('to_date')->pluck('flat_id') : collect();

        return Flat::whereIn('id', $ids)->orderBy('flat_no')->get();
    }

    public function index()
    {
        return response()->json($this->ownedFlats()->map(fn (Flat $f) => $this->service->ledger($f))->values());
    }

    public function pdf()
    {
        $ledgers = $this->ownedFlats()->map(fn (Flat $f) => $this->service->ledger($f))->values()->all();
        abort_if($ledgers === [], 404);

        return Pdf::loadView('reports.contribution-ledger', ['building' => Building::first(), 'ledgers' => $ledgers])
            ->setPaper('a4', 'portrait')->stream('my-ledger.pdf');
    }
}
