<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['name' => 'Admin One'])->assignRole('admin');
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Ravi Kumar',
            'email' => 'ravi@oms.test',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role' => 'staff',
            ...$overrides,
        ];
    }

    public function test_only_users_manage_can_reach_the_user_api(): void
    {
        $manager = User::factory()->create()->assignRole('manager');

        $this->getJson('/api/v1/users')->assertUnauthorized();
        $this->actingAs($manager)->getJson('/api/v1/users')->assertForbidden();
        // 403 before validation: an empty body still gets "forbidden", not field errors.
        $this->actingAs($manager)->postJson('/api/v1/users', [])->assertForbidden();
        $this->actingAs($manager)->patchJson("/api/v1/users/{$this->admin->id}/status", ['is_active' => false])->assertForbidden();
    }

    public function test_admin_lists_users_with_filters(): void
    {
        User::factory()->create(['name' => 'Sita Staff', 'email' => 'sita@oms.test'])->assignRole('staff');
        User::factory()->create(['name' => 'Old Staff', 'is_active' => false])->assignRole('staff');
        User::factory()->create(['name' => 'Mona Manager'])->assignRole('manager');

        $this->actingAs($this->admin)
            ->getJson('/api/v1/users?role=staff&status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sita Staff')
            ->assertJsonPath('data.0.role', 'staff')
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.permissions');

        $this->actingAs($this->admin)->getJson('/api/v1/users?search=sita@')->assertJsonCount(1, 'data');
        $this->actingAs($this->admin)->getJson('/api/v1/users?role=ghost')->assertUnprocessable();
    }

    public function test_admin_creates_a_user_with_a_hashed_password_and_one_role(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/users', $this->payload())->assertCreated();

        $user = User::findOrFail($response->json('data.id'));
        $this->assertSame(['staff'], $user->getRoleNames()->all());
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('secret-pass', $user->password));
        $response->assertJsonMissingPath('data.password');

        // The audit trail has the account and its role, never the password.
        $created = AuditLog::where('auditable_id', $user->id)->where('event', 'created')->firstOrFail();
        $this->assertArrayNotHasKey('password', $created->new_values);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $user->id, 'event' => 'role_changed']);
    }

    public function test_create_validates_input(): void
    {
        User::factory()->create(['email' => 'taken@oms.test']);

        $this->actingAs($this->admin)
            ->postJson('/api/v1/users', $this->payload([
                'email' => 'taken@oms.test',
                'password' => 'short',
                'password_confirmation' => 'different',
                'role' => 'superuser',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'role'])
            ->assertJsonPath('errors.email.0', 'A user with this email already exists.');
    }

    public function test_update_changes_role_and_keeps_the_password_when_blank(): void
    {
        $user = User::factory()->create(['email' => 'ravi@oms.test'])->assignRole('staff');
        $oldHash = $user->password;

        $this->actingAs($this->admin)
            ->putJson("/api/v1/users/{$user->id}", $this->payload(['name' => 'Ravi K', 'role' => 'manager', 'password' => '', 'password_confirmation' => '']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Ravi K')
            ->assertJsonPath('data.role', 'manager');

        $user->refresh();
        $this->assertSame($oldHash, $user->password);
        $this->assertSame(['manager'], $user->getRoleNames()->all());
        $audit = AuditLog::where('auditable_id', $user->id)->where('event', 'role_changed')->latest('id')->firstOrFail();
        $this->assertSame(['role' => 'staff'], $audit->old_values);
        $this->assertSame(['role' => 'manager'], $audit->new_values);
        $this->assertDatabaseMissing('audit_logs', ['auditable_id' => $user->id, 'event' => 'password_changed']);
    }

    public function test_update_with_a_password_records_only_that_it_changed(): void
    {
        $user = User::factory()->create(['email' => 'ravi@oms.test'])->assignRole('staff');

        $this->actingAs($this->admin)->putJson("/api/v1/users/{$user->id}", $this->payload(['password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1']))->assertOk();

        $this->assertTrue(Hash::check('new-secret-1', $user->fresh()->password));
        $audit = AuditLog::where('auditable_id', $user->id)->where('event', 'password_changed')->firstOrFail();
        $this->assertNull($audit->old_values);
        $this->assertNull($audit->new_values);
    }

    public function test_deactivated_user_is_logged_out_on_the_next_request(): void
    {
        $staff = User::factory()->create()->assignRole('staff');

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$staff->id}/status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $staff->id, 'event' => 'status_changed']);
        $this->actingAs($staff->fresh())->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $this->actingAs($this->admin)
            ->patchJson("/api/v1/users/{$this->admin->id}/status", ['is_active' => false])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You cannot deactivate your own account.');

        $this->actingAs($this->admin)
            ->putJson("/api/v1/users/{$this->admin->id}", $this->payload(['email' => $this->admin->email, 'role' => 'manager']))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You cannot remove your own admin role.');

        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
    }

    public function test_the_last_active_admin_cannot_be_deactivated_or_demoted(): void
    {
        // A second admin account exists but is already inactive.
        $other = User::factory()->create(['is_active' => false])->assignRole('admin');
        $userManager = User::factory()->create()->assignRole('staff');
        $userManager->givePermissionTo('users.manage');

        $this->actingAs($userManager)
            ->patchJson("/api/v1/users/{$this->admin->id}/status", ['is_active' => false])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'At least one active admin must remain.');

        $this->actingAs($userManager)
            ->putJson("/api/v1/users/{$this->admin->id}", $this->payload(['email' => $this->admin->email, 'role' => 'staff']))
            ->assertUnprocessable();

        // With another active admin, the change is allowed.
        $other->update(['is_active' => true]);
        $this->actingAs($userManager)
            ->patchJson("/api/v1/users/{$this->admin->id}/status", ['is_active' => false])
            ->assertOk();
    }
}
