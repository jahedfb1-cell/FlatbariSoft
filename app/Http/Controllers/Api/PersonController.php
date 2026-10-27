<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePersonRequest;
use App\Http\Requests\UpdatePersonRequest;
use App\Http\Resources\PersonResource;
use App\Models\Person;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function index(Request $request)
    {
        $persons = Person::when($request->filled('search'), function ($q) use ($request) {
            $term = $request->search;
            $q->where('mobile', 'like', "%{$term}%")
                ->orWhere('name_bn', 'like', "%{$term}%")
                ->orWhere('name_en', 'like', "%{$term}%");
        })->paginate($request->integer('per_page', 20));

        return PersonResource::collection($persons);
    }

    public function store(StorePersonRequest $request)
    {
        $person = Person::create(array_merge([
            'country_code' => '+880',
            'preferred_channel' => 'SMS',
        ], $request->validated()));

        return new PersonResource($person);
    }

    public function show(Person $person)
    {
        return new PersonResource($person);
    }

    public function update(UpdatePersonRequest $request, Person $person)
    {
        $person->update($request->validated());

        return new PersonResource($person);
    }

    public function destroy(Person $person)
    {
        $person->delete();

        return response()->json(['message' => 'ব্যক্তির তথ্য মুছে ফেলা হয়েছে।']);
    }
}
