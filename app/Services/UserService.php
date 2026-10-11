<?php

namespace App\Services;

use App\Exceptions\AccountLockoutException;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Each user has exactly one role. The rules here make sure at least one active
 * admin always remains, whatever order the requests arrive in.
 */
class UserService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create(Arr::only($data, ['name', 'email', 'password', 'is_active']));
            $user->assignRole($data['role']);
            $this->audit->record($user, 'role_changed', null, ['role' => $data['role']]);

            return $user;
        });
    }

    /**
     * A blank password keeps the current one.
     *
     * @param  array<string, mixed>  $data  validated input
     *
     * @throws AccountLockoutException
     */
    public function update(User $user, array $data, User $by): User
    {
        return DB::transaction(function () use ($user, $data, $by) {
            $oldRole = $user->getRoleNames()->first();
            $newRole = $data['role'];

            if ($oldRole === RoleService::ADMIN && $newRole !== RoleService::ADMIN) {
                if ($user->is($by)) {
                    throw new AccountLockoutException('You cannot remove your own admin role.');
                }
                $this->ensureAnotherActiveAdmin($user);
            }

            $passwordChanged = filled($data['password'] ?? null);
            $user->update(Arr::only($data, $passwordChanged ? ['name', 'email', 'password'] : ['name', 'email']));

            // The audit row says that the password changed, never what it is.
            if ($passwordChanged) {
                $this->audit->record($user, 'password_changed', null, null);
            }

            if ($oldRole !== $newRole) {
                $user->syncRoles([$newRole]);
                $this->audit->record($user, 'role_changed', ['role' => $oldRole], ['role' => $newRole]);
            }

            return $user;
        });
    }

    /**
     * A deactivated user can no longer log in, and the `active` middleware ends their current session.
     *
     * @throws AccountLockoutException
     */
    public function changeStatus(User $user, bool $active, User $by): User
    {
        return DB::transaction(function () use ($user, $active, $by) {
            if (! $active && $user->is_active) {
                if ($user->is($by)) {
                    throw new AccountLockoutException('You cannot deactivate your own account.');
                }
                if ($user->hasRole(RoleService::ADMIN)) {
                    $this->ensureAnotherActiveAdmin($user);
                }
            }

            $user->update(['is_active' => $active]);

            return $user;
        });
    }

    /**
     * Locks every active admin row (in id order, so two requests can't deadlock) before
     * counting. Two admins deactivating each other at the same time then run one after
     * the other, and the second one is refused.
     */
    private function ensureAnotherActiveAdmin(User $user): void
    {
        $activeAdminIds = User::role(RoleService::ADMIN)
            ->where('is_active', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        if ($activeAdminIds->reject(fn (int $id) => $id === $user->id)->isEmpty()) {
            throw new AccountLockoutException('At least one active admin must remain.');
        }
    }
}
