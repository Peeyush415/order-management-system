<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/v1/lookups')->assertUnauthorized();
    }

    public function test_returns_statuses_priorities_and_assignable_users(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $staff = User::factory()->create(['name' => 'Bina Staff'])->assignRole('staff');
        $manager = User::factory()->create(['name' => 'Arun Manager'])->assignRole('manager');
        User::factory()->create(['name' => 'Ada Admin'])->assignRole('admin');
        User::factory()->inactive()->create(['name' => 'Old Staff'])->assignRole('staff');

        $this->actingAs($staff)
            ->getJson('/api/v1/lookups')
            ->assertOk()
            ->assertJsonCount(7, 'data.order_statuses')
            ->assertJsonPath('data.order_statuses.0', ['value' => 'draft', 'label' => 'Draft'])
            ->assertJsonPath('data.order_priorities.3', ['value' => 4, 'label' => 'Urgent'])
            // Active managers and staff only, sorted by name, without emails.
            ->assertJsonPath('data.assignable_users', [
                ['id' => $manager->id, 'name' => 'Arun Manager'],
                ['id' => $staff->id, 'name' => 'Bina Staff'],
            ]);
    }
}
