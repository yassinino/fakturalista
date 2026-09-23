<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Team/user management for the current tenant (strictly isolated per
 * tenant - the `users` table lives in the tenant's own database, no
 * tenant_id column needed). Only admins may add/edit/remove users; the
 * seat limit is read from the existing Plan/PlanLimit ('users' resource)
 * via PlanService - never a second hardcoded limit.
 */
class UserController extends Controller
{
    public function __construct(private PlanService $planLimits) {}

    private function forbidden(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 403);
    }

    public function index(): JsonResponse
    {
        $users = User::orderBy('created_at')->get()->map(fn (User $user) => [
            'id'      => $user->id,
            'name'    => $user->name,
            'email'   => $user->email,
            'role'    => $user->role,
            'is_owner'=> $user->isOwner(),
            'is_self' => $user->id === auth()->id(),
        ]);

        $plan  = $this->planLimits->currentPlan();
        $limit = $this->planLimits->getLimit('users');

        return response()->json([
            'users' => $users,
            'usage' => [
                'used'      => $this->planLimits->totalUsers(),
                'limit'     => $limit, // null = unlimited
                'remaining' => $this->planLimits->remaining('users'),
            ],
            'can_manage' => auth()->user()?->isAdmin() ?? false,
        ]);
    }

    public function store(UserRequest $request): JsonResponse
    {
        if (!auth()->user()?->isAdmin()) {
            return $this->forbidden(__('team.not_authorized'));
        }

        if (!$this->planLimits->canInviteUser()) {
            $plan = $this->planLimits->currentPlan();

            return response()->json([
                'error'     => 'plan_limit_reached',
                'resource'  => 'users',
                'limit'     => $this->planLimits->getLimit('users'),
                'used'      => $this->planLimits->totalUsers(),
                'plan_name' => $plan ? $plan->translate('name') : 'Starter',
                'plan_slug' => $plan?->slug ?? 'starter',
            ], 402);
        }

        $validated = $request->validated();

        User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
        ]);

        return response()->json(['message' => __('team.created')], 201);
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        if (!auth()->user()?->isAdmin()) {
            return $this->forbidden(__('team.not_authorized'));
        }

        $validated = $request->validated();

        if ($user->isOwner() && $validated['role'] !== 'admin') {
            return $this->forbidden(__('team.owner_role_locked'));
        }

        $user->name  = $validated['name'];
        $user->email = $validated['email'];
        $user->role  = $validated['role'];
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        return response()->json(['message' => __('team.updated')]);
    }

    public function destroy(User $user): JsonResponse
    {
        if (!auth()->user()?->isAdmin()) {
            return $this->forbidden(__('team.not_authorized'));
        }

        if ($user->isOwner()) {
            return $this->forbidden(__('team.owner_cannot_be_removed'));
        }

        if ($user->id === auth()->id()) {
            return $this->forbidden(__('team.cannot_remove_self'));
        }

        $user->delete();

        return response()->json(['message' => __('team.deleted')]);
    }
}
