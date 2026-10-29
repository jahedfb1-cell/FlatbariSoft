<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisitorRequest;
use App\Models\FlatParty;
use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** The gate book. The gatekeeper writes people in and out; the office reads it; a resident sees only their own flat's. */
class VisitorController extends Controller
{
    private function shape(Visitor $v): array
    {
        return [
            'id' => $v->id, 'flat_id' => $v->flat_id, 'flat_no' => $v->flat?->flat_no, 'visitor_name' => $v->visitor_name, 'mobile' => $v->mobile,
            'purpose' => $v->purpose, 'vehicle_no' => $v->vehicle_no, 'note' => $v->note,
            'in_at' => $v->in_at?->timezone('Asia/Dhaka')->format('Y-m-d H:i'), 'out_at' => $v->out_at?->timezone('Asia/Dhaka')->format('Y-m-d H:i'),
            'inside' => $v->out_at === null,
        ];
    }

    /** ?inside=1 only those still in the building; ?date=YYYY-MM-DD one day; ?q= name, mobile, flat or vehicle. Newest first. */
    public function index(Request $request)
    {
        $v = $request->validate(['inside' => ['nullable', 'boolean'], 'date' => ['nullable', 'date'], 'q' => ['nullable', 'string', 'max:60']]);

        $rows = Visitor::with('flat')
            ->when($v['inside'] ?? false, fn ($q) => $q->whereNull('out_at'))
            ->when($v['date'] ?? null, fn ($q, $d) => $q->whereBetween('in_at', [$d.' 00:00:00', $d.' 23:59:59']))
            ->when($v['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('visitor_name', 'like', "%{$t}%")->orWhere('mobile', 'like', "%{$t}%")
                ->orWhere('vehicle_no', 'like', "%{$t}%")->orWhereHas('flat', fn ($f) => $f->where('flat_no', 'like', "%{$t}%"))))
            ->orderByDesc('in_at')->orderByDesc('id')->limit(200)->get();

        return response()->json([
            'inside_now' => Visitor::whereNull('out_at')->count(),
            'visitors' => $rows->map(fn ($r) => $this->shape($r)),
        ]);
    }

    public function store(StoreVisitorRequest $request)
    {
        $visitor = Visitor::create($request->validated() + ['in_at' => now(), 'logged_by' => Auth::id()]);

        return response()->json($this->shape($visitor->load('flat')), 201);
    }

    public function checkout(Visitor $visitor)
    {
        if ($visitor->out_at === null) {
            $visitor->update(['out_at' => now()]);
        }

        return response()->json($this->shape($visitor->load('flat')));
    }

    /** A resident's own flats' visitors, last 60 days. */
    public function mine()
    {
        $personId = Auth::user()->person_id;
        $flatIds = $personId ? FlatParty::where('person_id', $personId)->whereNull('to_date')->pluck('flat_id') : collect();

        return Visitor::with('flat')->whereIn('flat_id', $flatIds)->where('in_at', '>=', now()->subDays(60))
            ->orderByDesc('in_at')->limit(100)->get()->map(fn ($r) => $this->shape($r));
    }
}
