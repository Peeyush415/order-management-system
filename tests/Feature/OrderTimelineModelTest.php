<?php

namespace Tests\Feature;

use App\Enums\OrderActivityType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTimelineModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_history_starts_with_a_null_from_status_and_lists_newest_first(): void
    {
        $order = Order::factory()->create();
        $user = User::factory()->create();

        $order->statusHistories()->create(['from_status' => null, 'to_status' => OrderStatus::Draft, 'changed_by' => $user->id]);
        $order->statusHistories()->create(['from_status' => OrderStatus::Draft, 'to_status' => OrderStatus::Pending, 'changed_by' => $user->id, 'remarks' => 'Ready']);

        $history = $order->statusHistories()->get();

        $this->assertSame(OrderStatus::Pending, $history->first()->to_status);
        $this->assertNull($history->last()->from_status);
        $this->assertTrue($history->first()->changedBy->is($user));
    }

    public function test_activity_is_append_only_and_casts_properties(): void
    {
        $order = Order::factory()->create();

        $activity = $order->activities()->create([
            'user_id' => null,
            'type' => OrderActivityType::StatusChanged,
            'description' => 'Status changed from Draft to Pending',
            'properties' => ['from' => 'draft', 'to' => 'pending'],
        ])->fresh();

        $this->assertSame(OrderActivityType::StatusChanged, $activity->type);
        $this->assertSame(['from' => 'draft', 'to' => 'pending'], $activity->properties);
        $this->assertNotNull($activity->created_at);
        $this->assertArrayNotHasKey('updated_at', $activity->getAttributes());
    }

    public function test_notes_belong_to_an_order_and_an_author(): void
    {
        $note = OrderNote::factory()->create();

        $this->assertTrue($note->order->notes->contains($note));
        $this->assertNotNull($note->user);
    }

    public function test_removing_an_order_removes_its_history_notes_and_activities(): void
    {
        $order = Order::factory()->create();
        $order->statusHistories()->create(['to_status' => OrderStatus::Pending, 'changed_by' => $order->created_by]);
        OrderNote::factory()->for($order)->create();
        $order->activities()->create(['type' => OrderActivityType::Created, 'description' => 'Order created']);

        $order->forceDelete();

        $this->assertDatabaseCount('order_status_histories', 0);
        $this->assertDatabaseCount('order_notes', 0);
        $this->assertDatabaseCount('order_activities', 0);
    }
}
