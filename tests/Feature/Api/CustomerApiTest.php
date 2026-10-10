<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Asha Verma',
            'email' => 'asha@example.com',
            'phone' => '+91 98765 43210',
            'company' => 'Verma Traders',
            'address_line1' => '12 MG Road',
            'city' => 'Pune',
            'country' => 'India',
            ...$overrides,
        ];
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/v1/customers')->assertUnauthorized();
    }

    public function test_staff_can_list_customers_with_pagination_meta(): void
    {
        Customer::factory()->count(3)->create();
        Customer::factory()->inactive()->create();

        $this->actingAs($this->userWithRole('staff'))
            ->getJson('/api/v1/customers?status=active&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.status.value', 'active')
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'status' => ['value', 'label'], 'orders_count']]]);
    }

    public function test_list_sorts_by_a_whitelisted_column_only(): void
    {
        Customer::factory()->create(['name' => 'Zara']);
        Customer::factory()->create(['name' => 'Aman']);
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)
            ->getJson('/api/v1/customers?sort=name&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Aman');

        $this->actingAs($staff)
            ->getJson('/api/v1/customers?sort=password&per_page=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'per_page']);
    }

    public function test_short_search_uses_a_prefix_match(): void
    {
        Customer::factory()->create(['name' => 'Om Prakash']);
        Customer::factory()->create(['name' => 'Ravi Kumar']);

        $this->actingAs($this->userWithRole('staff'))
            ->getJson('/api/v1/customers?search=Om')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Om Prakash');
    }

    public function test_staff_cannot_create_a_customer(): void
    {
        $this->actingAs($this->userWithRole('staff'))
            ->postJson('/api/v1/customers', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_permission_is_checked_before_validation(): void
    {
        $customer = Customer::factory()->create();
        $staff = $this->userWithRole('staff');

        // Invalid payloads still get 403: no validation details for users who may not write.
        $this->actingAs($staff)->postJson('/api/v1/customers', ['name' => ''])->assertForbidden();
        $this->actingAs($staff)->putJson("/api/v1/customers/{$customer->id}", [])->assertForbidden();
        $this->actingAs($staff)->patchJson("/api/v1/customers/{$customer->id}/status", [])->assertForbidden();
    }

    public function test_manager_creates_a_customer(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->postJson('/api/v1/customers', $this->validPayload(['status' => 'inactive', 'created_by' => 999]))
            ->assertCreated()
            ->assertJsonPath('data.email', 'asha@example.com')
            ->assertJsonPath('data.status.value', 'active');

        // Client-sent status and created_by are ignored.
        $this->assertDatabaseHas('customers', ['email' => 'asha@example.com', 'status' => 'active', 'created_by' => $manager->id]);
    }

    public function test_create_validates_required_fields_and_unique_email(): void
    {
        Customer::factory()->create(['email' => 'asha@example.com']);

        $this->actingAs($this->userWithRole('manager'))
            ->postJson('/api/v1/customers', $this->validPayload(['name' => '', 'city' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'city', 'email'])
            ->assertJsonPath('errors.email.0', 'A customer with this email already exists.');
    }

    public function test_manager_updates_a_customer_keeping_its_own_email(): void
    {
        $customer = Customer::factory()->create(['email' => 'asha@example.com']);

        $this->actingAs($this->userWithRole('manager'))
            ->putJson("/api/v1/customers/{$customer->id}", $this->validPayload(['city' => 'Mumbai']))
            ->assertOk()
            ->assertJsonPath('data.city', 'Mumbai');

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'city' => 'Mumbai']);
    }

    public function test_manager_changes_status(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWithRole('manager'))
            ->patchJson("/api/v1/customers/{$customer->id}/status", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'inactive');

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'status' => 'inactive']);
    }

    public function test_manager_cannot_delete(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWithRole('manager'))
            ->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertForbidden();

        $this->assertNotSoftDeleted($customer);
    }

    public function test_admin_deletes_a_customer_without_orders(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($customer);
        $this->actingAs($this->userWithRole('admin'))
            ->getJson("/api/v1/customers/{$customer->id}")
            ->assertNotFound();
    }

    public function test_customer_with_orders_cannot_be_deleted(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->for($customer)->create();

        $this->actingAs($this->userWithRole('admin'))
            ->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This customer has orders and cannot be deleted. Deactivate the customer instead.');

        $this->assertNotSoftDeleted($customer);
    }

    public function test_show_includes_the_order_count(): void
    {
        $customer = Customer::factory()->create();
        Order::factory()->count(2)->for($customer)->create();

        $this->actingAs($this->userWithRole('staff'))
            ->getJson("/api/v1/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.orders_count', 2);
    }
}
