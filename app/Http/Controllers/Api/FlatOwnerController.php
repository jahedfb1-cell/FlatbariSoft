<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignOwnerRequest;
use App\Http\Requests\EndOwnershipRequest;
use App\Http\Resources\FlatPartyResource;
use App\Models\Flat;
use App\Models\FlatParty;
use App\Services\FlatOccupancyService;

class FlatOwnerController extends Controller
{
    public function __construct(private readonly FlatOccupancyService $service) {}

    public function store(AssignOwnerRequest $request, Flat $flat)
    {
        $party = $this->service->assignOwner(
            $flat,
            $request->validated('person'),
            $request->validated('from_date'),
            (bool) $request->validated('is_billing_contact', false),
        );

        return new FlatPartyResource($party->load('person'));
    }

    public function destroy(EndOwnershipRequest $request, Flat $flat, FlatParty $flatParty)
    {
        abort_unless($flatParty->flat_id === $flat->id, 404);

        $party = $this->service->endOwnership($flatParty, $request->validated('to_date'));

        return new FlatPartyResource($party->load('person'));
    }
}
