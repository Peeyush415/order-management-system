<?php

namespace App\Services;

use App\Exceptions\RoleLockedException;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Roles are bundles of permissions that an admin can change without a code change.
 */
class RoleService
{
    /** Always has every permission, so someone can always manage users and roles. */
    public const ADMIN = 'admin';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Every role with its permissions and a users_count, in two queries.
     *
     * The count is read from model_has_roles directly: spatie's users() relation picks the
     * user model from the current guard, and inside an API request that guard is sanctum.
     *
     * @return Collection<int, Role>
     */
    public function all(): Collection
    {
        $counts = DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', (new User)->getMorphClass())
            ->selectRaw('role_id, COUNT(*) as aggregate')
            ->groupBy('role_id')
            ->pluck('aggregate', 'role_id');

        return Role::query()->with('permissions:id,name')->orderBy('id')->get()
            ->each(fn (Role $role) => $role->setAttribute('users_count', (int) ($counts[$role->id] ?? 0)));
    }

    /**
     * Replaces the role's permissions. spatie clears its permission cache on sync,
     * so the change applies to every user with this role on their next request.
     *
     * @param  list<string>  $permissions
     *
     * @throws RoleLockedException
     */
    public function syncPermissions(Role $role, array $permissions): Role
    {
        if ($role->name === self::ADMIN) {
            throw new RoleLockedException;
        }

        return DB::transaction(function () use ($role, $permissions) {
            $old = $role->permissions()->pluck('name')->sort()->values()->all();
            $role->syncPermissions($permissions);
            $new = $role->permissions()->pluck('name')->sort()->values()->all();

            if ($old !== $new) {
                $this->audit->record($role, 'permissions_changed', ['permissions' => $old], ['permissions' => $new]);
            }

            return $role;
        });
    }
}
