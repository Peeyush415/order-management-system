<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\OrderNote;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderNotesActivityTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->create(['name' => 'Meera Manager'])->assignRole('manager');
        $this->staff = User::factory()->create(['name' => 'Staff One'])->assignRole('staff');
    }

    public function test_staff_adds_a_note_to_their_own_order(): void
    {
        $order = Order::factory()->assignedTo($this->staff)->create();

        $this->actingAs($this->staff)
            ->postJson("/api/v1/orders/{$order->id}/notes", ['body' => 'Customer asked for gift wrap.'])
            ->assertCreated()
            ->assertJsonPath('message', 'Note added successfully')
            ->assertJsonPath('data.body', 'Customer asked for gift wrap.')
            ->assertJsonPath('data.user.name', 'Staff One');

        $this->assertDatabaseHas('order_notes', ['order_id' => $order->id, 'user_id' => $this->staff->id]);
        $this->assertDatabaseHas('order_activities', ['order_id' => $order->id, 'type' => 'note_added', 'user_id' => $this->staff->id]);
    }

    public function test_staff_cannot_note_or_read_other_orders(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->staff)->postJson("/api/v1/orders/{$order->id}/notes", ['body' => 'x'])->assertForbidden();
        $this->actingAs($this->staff)->getJson("/api/v1/orders/{$order->id}/notes")->assertForbidden();
        $this->actingAs($this->staff)->getJson("/api/v1/orders/{$order->id}/activities")->assertForbidden();
    }

    public function test_note_body_is_required(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->manager)
            ->postJson("/api/v1/orders/{$order->id}/notes", ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.body.0', 'The note cannot be empty.');
    }

    public function test_notes_are_listed_newest_first_with_pagination(): void
    {
        $order = Order::factory()->create();
        OrderNote::factory()->for($order)->for($this->manager)->create(['body' => 'First', 'created_at' => now()->subHour()]);
        OrderNote::factory()->for($order)->for($this->manager)->create(['body' => 'Second']);

        $this->actingAs($this->manager)
            ->getJson("/api/v1/orders/{$order->id}/notes?per_page=1")
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Second')
            ->assertJsonPath('data.0.user.name', 'Meera Manager')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_activity_feed_shows_what_happened_newest_first(): void
    {
        $order = Order::factory()->create()->fresh();

        $this->actingAs($this->manager)->postJson("/api/v1/orders/{$order->id}/assign", ['assigned_to' => $this->staff->id])->assertOk();
        $this->actingAs($this->staff)->postJson("/api/v1/orders/{$order->id}/notes", ['body' => 'On it.'])->assertCreated();

        $this->actingAs($this->staff)
            ->getJson("/api/v1/orders/{$order->id}/activities")
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.type.value', 'note_added')
            ->assertJsonPath('data.0.user.name', 'Staff One')
            ->assertJsonPath('data.1.type.label', 'Assigned')
            ->assertJsonPath('data.1.description', 'Assigned to Staff One');
    }
}
