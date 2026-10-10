<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum only starts a session for requests coming from the SPA's own domain.
        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Referer', 'http://localhost');
    }

    public function test_user_can_log_in_and_gets_roles_and_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['email' => 'staff@oms.test'])->assignRole('staff');

        $this->postJson('/api/v1/auth/login', ['email' => 'staff@oms.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'staff@oms.test')
            ->assertJsonPath('data.roles', ['staff'])
            ->assertJsonPath('data.permissions', ['customers.view', 'notes.create', 'orders.change_status', 'orders.view_assigned', 'products.view'])
            ->assertJsonMissingPath('data.password');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_is_rejected_with_a_generic_message(): void
    {
        User::factory()->create(['email' => 'admin@oms.test']);

        $this->postJson('/api/v1/auth/login', ['email' => 'admin@oms.test', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');

        $this->assertGuest('web');
    }

    public function test_inactive_user_gets_the_same_generic_message(): void
    {
        $user = User::factory()->inactive()->create(['email' => 'old@oms.test']);

        $this->postJson('/api/v1/auth/login', ['email' => 'old@oms.test', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');

        $this->assertGuest('web');
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_deleted_user_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'gone@oms.test'])->delete();

        $this->postJson('/api/v1/auth/login', ['email' => 'gone@oms.test', 'password' => 'password'])
            ->assertUnprocessable();

        $this->assertGuest('web');
    }

    public function test_login_validates_its_input(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_from_outside_the_spa_gets_a_clear_400_not_a_500(): void
    {
        User::factory()->create(['email' => 'admin@oms.test']);

        $this->withHeader('Referer', 'http://evil.test')
            ->postJson('/api/v1/auth/login', ['email' => 'admin@oms.test', 'password' => 'password'])
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertGuest('web');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJson(['success' => false, 'message' => 'Unauthenticated.']);
    }

    public function test_me_returns_the_logged_in_user(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.roles', ['admin'])
            ->assertJsonCount(20, 'data.permissions');
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully');

        $this->assertGuest('web');
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }
}
