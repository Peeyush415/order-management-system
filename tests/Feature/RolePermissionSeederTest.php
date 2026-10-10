<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Written out by hand from the permission matrix, so a wrong seeder can't pass its own test.
     */
    public static function roleMatrix(): array
    {
        return [
            'admin' => ['admin', [
                'audit.view', 'customers.create', 'customers.delete', 'customers.update', 'customers.view',
                'dashboard.view', 'notes.create', 'orders.assign', 'orders.cancel', 'orders.change_status',
                'orders.create', 'orders.delete', 'orders.update', 'orders.view_any', 'orders.view_assigned',
                'products.manage', 'products.view', 'reports.view', 'roles.manage', 'users.manage',
            ]],
            'manager' => ['manager', [
                'customers.create', 'customers.update', 'customers.view',
                'dashboard.view', 'notes.create', 'orders.assign', 'orders.cancel', 'orders.change_status',
                'orders.create', 'orders.update', 'orders.view_any', 'orders.view_assigned',
                'products.view', 'reports.view',
            ]],
            'staff' => ['staff', [
                'customers.view', 'notes.create', 'orders.change_status', 'orders.view_assigned', 'products.view',
            ]],
        ];
    }

    #[DataProvider('roleMatrix')]
    public function test_role_has_exactly_the_matrix_permissions(string $role, array $expected): void
    {
        $this->seed(RolePermissionSeeder::class);

        $actual = Role::findByName($role)->permissions->pluck('name')->sort()->values()->all();

        $this->assertSame($expected, $actual);
    }

    public function test_seeder_can_run_twice_without_duplicates(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(20, Permission::count());
        $this->assertSame(3, Role::count());
    }

    public function test_demo_users_are_seeded_with_their_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(6, User::count());
        $this->assertTrue(User::where('email', 'admin@oms.test')->first()->hasRole('admin'));
        $this->assertSame(2, User::role('manager')->count());
        $this->assertSame(3, User::role('staff')->count());
        $this->assertDatabaseHas('users', ['email' => 'staff1@oms.test', 'is_active' => true]);
    }

    public function test_staff_cannot_delete_orders_but_admin_can(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(User::where('email', 'admin@oms.test')->first()->can('orders.delete'));
        $this->assertFalse(User::where('email', 'staff1@oms.test')->first()->can('orders.delete'));
    }

    public function test_deleted_user_is_soft_deleted(): void
    {
        $user = User::factory()->create();

        $user->delete();

        $this->assertSoftDeleted($user);
        $this->assertSame(0, User::count());
    }
}
