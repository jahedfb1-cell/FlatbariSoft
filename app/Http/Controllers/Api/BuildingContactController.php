<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BuildingContactRequest;
use App\Models\BuildingContact;

/** The office keeps the building's phone numbers (manager, guard, lift...) that every flat owner and tenant can read. */
class BuildingContactController extends Controller
{
    public function index()
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('building_contacts')) {
            return response()->json([]);
        }

        return response()->json(BuildingContact::orderBy('sort_order')->orderBy('id')->get());
    }

    public function store(BuildingContactRequest $request)
    {
        $data = $request->validated();
        $data['sort_order'] ??= (int) BuildingContact::max('sort_order') + 1;

        return response()->json(BuildingContact::create($data), 201);
    }

    public function update(BuildingContactRequest $request, BuildingContact $contact)
    {
        $contact->update($request->validated());

        return response()->json($contact->fresh());
    }

    public function destroy(BuildingContact $contact)
    {
        $contact->delete();

        return response()->json(['message' => 'নম্বরটি মুছে ফেলা হয়েছে।']);
    }
}
