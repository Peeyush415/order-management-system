<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create()->assignRole('admin');
    }

    private function role(string $name): Role
    {
        return Role::findByName($name, 'web');
    }

    public function test_roles_list_shows_permissions_user_counts_and_the_locked_admin(): void
    {
        User::factory()->count(2)->create()->each->assignRole('staff');

        $roles = $this->actingAs($this->admin)->getJson('/api/v1/roles')->assertOk()->json('data');
        $byName = collect($roles)->keyBy('name');

        $this->assertTrue($byName['admin']['locked']);
        $this->assertFalse($byName['staff']['locked']);
        $this->assertSame(2, $byName['staff']['users_count']);
        $this->assertCount(count(RolePermissionSeeder::PERMISSIONS), $byName['admin']['permissions']);
        $this->assertContains('notes.create', $byName['staff']['permissions']);
    }

    public function test_permissions_are_grouped_by_module(): void
    {
        $groups = collect($this->actingAs($this->admin)->getJson('/api/v1/permissions')->assertOk()->json('data'))->keyBy('group');

        $this->assertSame('Orders', $groups['orders']['label']);
        $this->assertContains(['name' => 'orders.view_any', 'label' => 'View any'], $groups['orders']['permissions']);
        $this->assertSame(count(RolePermissionSeeder::PERMISSIONS), $groups->sum(fn ($g) => count($g['permissions'])));
    }

    public function test_a_change_applies_to_users_with_that_role_immediately(): void
    {
        $staff = User::factory()->create()->assignRole('staff');
        $this->actingAs($staff)->getJson('/api/v1/users')->assertForbidden();
        $this->assertFalse($staff->fresh()->can('customers.create'));

        $permissions = [...RolePermissionSeeder::ROLES['staff'], 'customers.create'];
        $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->role('staff')->id}/permissions", ['permissions' => $permissions])
            ->assertOk()
            ->assertJsonPath('data.users_count', 1);

        $this->assertTrue($staff->fresh()->can('customers.create'));

        $audit = AuditLog::where('event', 'permissions_changed')->firstOrFail();
        $this->assertSame($this->role('staff')->getMorphClass(), $audit->auditable_type);
        $this->assertNotContains('customers.create', $audit->old_values['permissions']);
        $this->assertContains('customers.create', $audit->new_values['permissions']);
    }

    public function test_saving_the_same_permissions_writes_no_audit_row(): void
    {
        $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->role('staff')->id}/permissions", ['permissions' => RolePermissionSeeder::ROLES['staff']])
            ->assertOk();

        $this->assertDatabaseMissing('audit_logs', ['event' => 'permissions_changed']);
    }

    public function test_admin_role_is_locked_and_input_is_validated(): void
    {
        $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->role('admin')->id}/permissions", ['permissions' => []])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The admin role always has every permission and cannot be changed.');

        $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->role('staff')->id}/permissions", ['permissions' => ['orders.fly']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions.0']);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/roles/{$this->role('staff')->id}/permissions", [])
            ->assertJsonValidationErrors(['permissions']);
    }

    public function test_only_roles_manage_can_change_permissions(): void
    {
        $manager = User::factory()->create()->assignRole('manager');
        $userManager = User::factory()->create()->assignRole('staff');
        $userManager->givePermissionTo('users.manage');

        $this->actingAs($manager)->getJson('/api/v1/roles')->assertForbidden();
        // The user form needs the role list, so users.manage may read it, but not change it.
        $this->actingAs($userManager)->getJson('/api/v1/roles')->assertOk();
        $this->actingAs($userManager)
            ->putJson("/api/v1/roles/{$this->role('staff')->id}/permissions", ['permissions' => []])
            ->assertForbidden();
    }
}
