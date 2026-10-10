<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/**
 * Customer and product search use FULLTEXT, which only sees committed rows,
 * so this test commits for real (see CustomerSearchTest) and truncates afterwards.
 */
class OrderSearchTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    public function test_search_by_customer_name_email_or_product(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->actingAs(User::factory()->create()->assignRole('manager'));

        $kavita = Order::factory()->for(Customer::factory()->create(['name' => 'Kavita Menon', 'email' => 'kavita@menon.test']))->create();
        $other = Order::factory()->for(Customer::factory()->create(['name' => 'Arjun Das', 'email' => 'arjun@das.test']))->create();
        OrderItem::factory()->for($other)->for(Product::factory()->create(['name' => 'Ergonomic Office Chair', 'sku' => 'CH-77']))->create();
        Order::factory()->create();

        $find = fn (string $term) => collect($this->getJson('/api/v1/orders?search='.urlencode($term))->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$kavita->id], $find('kavita'));
        $this->assertSame([$kavita->id], $find('kavita@menon'));
        $this->assertSame([$other->id], $find('office chair'));
        $this->assertSame([$other->id], $find('CH-77'));
    }
}
