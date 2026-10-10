<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles are bundles of permissions. Code checks permissions (can('orders.delete')),
 * never role names, so the bundles can change without a code change.
 * Safe to run again: it creates what is missing and re-syncs each role.
 */
class RolePermissionSeeder extends Seeder
{
    public const PERMISSIONS = [
        'dashboard.view', 'reports.view', 'audit.view',
        'orders.view_any', 'orders.view_assigned', 'orders.create', 'orders.update',
        'orders.delete', 'orders.assign', 'orders.change_status', 'orders.cancel',
        'notes.create',
        'customers.view', 'customers.create', 'customers.update', 'customers.delete',
        'products.view', 'products.manage',
        'users.manage', 'roles.manage',
    ];

    public const ROLES = [
        'admin' => self::PERMISSIONS,
        'manager' => [
            'dashboard.view', 'reports.view',
            'orders.view_any', 'orders.view_assigned', 'orders.create', 'orders.update',
            'orders.assign', 'orders.change_status', 'orders.cancel',
            'notes.create',
            'customers.view', 'customers.create', 'customers.update',
            'products.view',
        ],
        'staff' => [
            'orders.view_assigned', 'orders.change_status',
            'notes.create',
            'customers.view',
            'products.view',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (self::ROLES as $role => $permissions) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
                ->syncPermissions($permissions);
        }
    }
}
