<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/**
 * InnoDB updates a FULLTEXT index only when a transaction commits, so RefreshDatabase
 * (one rolled-back transaction per test) would never find anything. These tests commit
 * for real and truncate the tables afterwards to leave a clean database for other tests.
 */
class CustomerSearchTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->actingAs(User::factory()->create()->assignRole('staff'));

        Customer::factory()->create(['name' => 'Ramesh Sharma', 'email' => 'ramesh@example.com', 'company' => 'Sharma Steel']);
        Customer::factory()->create(['name' => 'Priya Nair', 'email' => 'priya@example.com', 'company' => 'Nair Exports']);
        Customer::factory()->create(['name' => 'Rameshwar Gupta', 'email' => 'gupta@example.com', 'company' => null]);
    }

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    public function test_search_matches_word_prefixes_across_name_email_and_company(): void
    {
        $this->getJson('/api/v1/customers?search=rames&sort=name&direction=asc')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Ramesh Sharma')
            ->assertJsonPath('data.1.name', 'Rameshwar Gupta');

        $this->getJson('/api/v1/customers?search=exports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Priya Nair');
    }

    public function test_every_word_must_match(): void
    {
        $this->getJson('/api/v1/customers?search=ramesh sharma')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ramesh Sharma');
    }

    public function test_boolean_operators_in_input_are_ignored(): void
    {
        $this->getJson('/api/v1/customers?search='.urlencode('-priya +"nair'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Priya Nair');
    }
}
