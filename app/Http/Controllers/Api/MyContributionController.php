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
 * An owner's own ledgers - one per book (construction cost, flat, parking sale) for each flat they currently
 * OWN. A tenant has no share in these, and nobody can ask for a flat that is not theirs.
 */
class MyContributionController extends Controller
{
    public function __construct(private readonly ContributionService $service) {}

    /** @return list<array<string, mixed>> */
    private function ledgers(): array
    {
        $personId = Auth::user()->person_id;
        $ids = $personId ? FlatParty::where('person_id', $personId)->where('role', 'OWNER')->whereNull('to_date')->pluck('flat_id') : collect();

        return Flat::whereIn('id', $ids)->orderBy('flat_no')->get()
            ->flatMap(fn (Flat $f) => $this->service->flatLedgers($f))->values()->all();
    }

    public function index()
    {
        return response()->json($this->ledgers());
    }

    public function pdf()
    {
        $ledgers = $this->ledgers();
        abort_if($ledgers === [], 404);

        return Pdf::loadView('reports.contribution-ledger', ['building' => Building::first(), 'ledgers' => $ledgers])
            ->setPaper('a4', 'portrait')->stream('my-ledger.pdf');
    }
}
