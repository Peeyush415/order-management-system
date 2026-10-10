<?php

namespace Tests\Feature;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_an_active_customer_with_a_creator(): void
    {
        $customer = Customer::factory()->create();

        $this->assertSame(CustomerStatus::Active, $customer->fresh()->status);
        $this->assertNotNull($customer->createdBy);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'status' => 'active']);
    }

    public function test_inactive_state_is_cast_to_the_enum(): void
    {
        $customer = Customer::factory()->inactive()->create();

        $this->assertSame(CustomerStatus::Inactive, $customer->fresh()->status);
        $this->assertSame('Inactive', $customer->status->label());
    }

    public function test_duplicate_email_is_rejected_by_the_database(): void
    {
        Customer::factory()->create(['email' => 'same@example.com']);

        $this->expectException(QueryException::class);

        Customer::factory()->create(['email' => 'same@example.com']);
    }

    public function test_deleted_customer_is_soft_deleted(): void
    {
        $customer = Customer::factory()->create();

        $customer->delete();

        $this->assertSoftDeleted($customer);
    }
}
