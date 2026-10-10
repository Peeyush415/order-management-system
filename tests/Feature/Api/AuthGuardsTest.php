<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthGuardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Referer', 'http://localhost');
    }

    public function test_user_deactivated_mid_session_is_logged_out_on_the_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/v1/auth/me')->assertOk();

        $user->update(['is_active' => false]);

        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJson(['success' => false, 'message' => 'Your account has been deactivated.']);

        $this->assertGuest('web');
    }

    public function test_active_user_passes_the_check(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_sixth_login_attempt_in_a_minute_is_throttled(): void
    {
        User::factory()->create(['email' => 'target@oms.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'target@oms.test', 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'target@oms.test', 'password' => 'password'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('success', false);

        $this->assertGuest('web');
    }

    public function test_throttle_is_per_email_so_other_users_can_still_log_in(): void
    {
        User::factory()->create(['email' => 'other@oms.test']);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'target@oms.test', 'password' => 'wrong']);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'other@oms.test', 'password' => 'password'])
            ->assertOk();
    }
}
