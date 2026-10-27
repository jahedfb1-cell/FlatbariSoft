<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The roles screen: what each role may do. Rules that protect the system:
 *  - super_admin is untouchable (it bypasses every check anyway);
 *  - flat_owner / tenant have no office permissions on purpose - their portal is scoped to their own flat
 *    by the server, and a permission here would open the whole building's data, so they are not editable;
 *  - admin must always keep user/role management, or one wrong tick could lock everyone out for good.
 */
class RoleManagementService
{
    public function isLocked(string $role): bool
    {
        return in_array($role, config('roles.locked_roles'), true) || in_array($role, config('roles.resident_roles'), true);
    }

    /** @return array<int, array{name:string, label_bn:string, label_en:string, permissions:array, users_count:int, locked:bool, system:bool}> */
    public function list(): array
    {
        $labels = config('roles.labels');
        $counts = $this->userCounts();

        return Role::with('permissions')->orderBy('id')->get()->map(fn (Role $r) => [
            'name' => $r->name,
            'label_bn' => $labels[$r->name]['bn'] ?? $r->name,
            'label_en' => $labels[$r->name]['en'] ?? $r->name,
            'permissions' => $r->permissions->pluck('name')->sort()->values()->all(),
            'users_count' => (int) ($counts[$r->id] ?? 0),
            'locked' => $this->isLocked($r->name),
            'system' => isset($labels[$r->name]),
        ])->all();
    }

    /**
     * role id => number of users holding it. Read from the pivot table directly: spatie's own users()
     * relation looks up the user model through the *current* auth guard, which under Sanctum has none.
     *
     * @return array<int, int>
     */
    private function userCounts(): array
    {
        return DB::table('model_has_roles')->where('model_type', User::class)
            ->selectRaw('role_id, count(*) as c')->groupBy('role_id')->pluck('c', 'role_id')->all();
    }

    /** Every permission, grouped by module, with readable labels - what the tick-box grid is built from. */
    public function catalog(): array
    {
        $groups = config('roles.groups');
        $actions = config('roles.actions');
        $out = [];

        foreach (Permission::orderBy('name')->pluck('name') as $name) {
            [$module, $action] = array_pad(explode('.', $name, 2), 2, '');
            $out[$module]['module'] = $module;
            $out[$module]['label_bn'] = $groups[$module]['bn'] ?? $module;
            $out[$module]['label_en'] = $groups[$module]['en'] ?? $module;
            $out[$module]['permissions'][] = [
                'name' => $name,
                'label_bn' => $actions[$action]['bn'] ?? $action,
                'label_en' => $actions[$action]['en'] ?? $action,
            ];
        }

        return array_values($out);
    }

    public function syncPermissions(string $roleName, array $permissions): Role
    {
        $role = Role::where('name', $roleName)->firstOrFail();

        if ($this->isLocked($role->name)) {
            throw ValidationException::withMessages(['role' => 'এই রোলের অনুমতি বদলানো যায় না।']);
        }

        $permissions = array_values(array_unique($permissions));
        $known = Permission::whereIn('name', $permissions)->pluck('name')->all();
        if (count($known) !== count($permissions)) {
            throw ValidationException::withMessages(['permissions' => 'অজানা অনুমতি।']);
        }

        if ($role->name === 'admin') {
            $missing = array_diff(config('roles.admin_must_keep'), $permissions);
            if ($missing) {
                throw ValidationException::withMessages(['permissions' => 'অ্যাডমিনের ইউজার ও রোল ম্যানেজমেন্টের অনুমতি সরানো যাবে না।']);
            }
        }

        return DB::transaction(function () use ($role, $permissions) {
            $role->syncPermissions($permissions);
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            return $role->fresh('permissions');
        });
    }

    public function create(string $name, array $permissions): Role
    {
        if (Role::where('name', $name)->exists()) {
            throw ValidationException::withMessages(['name' => 'এই নামে রোল আগে থেকেই আছে।']);
        }

        return DB::transaction(function () use ($name, $permissions) {
            $role = Role::create(['name' => $name, 'guard_name' => 'web']);

            return $this->syncPermissions($role->name, $permissions);
        });
    }

    public function delete(string $roleName): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();

        if (isset(config('roles.labels')[$role->name])) {
            throw ValidationException::withMessages(['role' => 'সিস্টেমের নিজস্ব রোল মোছা যায় না।']);
        }
        if (($this->userCounts()[$role->id] ?? 0) > 0) {
            throw ValidationException::withMessages(['role' => 'এই রোলে ইউজার আছে — আগে তাদের রোল বদলান।']);
        }

        $role->delete();
    }
}
