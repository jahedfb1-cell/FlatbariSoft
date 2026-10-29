<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\ManagedUserResource;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserManagementService $service) {}

    public function index(Request $request)
    {
        $users = User::with('person.flatParties.flat')
            ->when($request->filled('role'), fn ($q) => $q->role($request->role))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('mobile', 'like', '%'.$request->q.'%')
                ->orWhere('email', 'like', '%'.$request->q.'%')
                ->orWhereHas('person', fn ($p) => $p->where('name_bn', 'like', '%'.$request->q.'%')->orWhere('name_en', 'like', '%'.$request->q.'%'))))
            ->orderBy('id')
            ->paginate($request->integer('per_page', 25));

        return ManagedUserResource::collection($users);
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->service->create($request->validated(), $request->user());

        return (new ManagedUserResource($user->load('person.flatParties.flat')))->response()->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        return new ManagedUserResource($this->service->update($user, $request->validated(), $request->user())->load('person.flatParties.flat'));
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user)
    {
        $this->service->resetPassword($user, $request->validated('password'));

        return response()->json(['message' => 'পাসওয়ার্ড বদলানো হয়েছে।']);
    }
}
