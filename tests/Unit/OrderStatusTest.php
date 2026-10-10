<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    /**
     * Written out by hand from the lifecycle diagram, independent of the enum's own map.
     */
    private const ALLOWED = [
        'draft' => ['pending', 'cancelled'],
        'pending' => ['draft', 'confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['ready', 'cancelled'],
        'ready' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    /** Every from → to pair: 7 × 7 = 49 cases. */
    public static function everyPair(): array
    {
        $cases = [];
        foreach (OrderStatus::cases() as $from) {
            foreach (OrderStatus::cases() as $to) {
                $cases["{$from->value} → {$to->value}"] = [$from, $to, in_array($to->value, self::ALLOWED[$from->value], true)];
            }
        }

        return $cases;
    }

    #[DataProvider('everyPair')]
    public function test_transition_matrix(OrderStatus $from, OrderStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public function test_assessment_examples_are_rejected(): void
    {
        $this->assertFalse(OrderStatus::Completed->canTransitionTo(OrderStatus::Draft));
        $this->assertFalse(OrderStatus::Cancelled->canTransitionTo(OrderStatus::Processing));
    }

    public function test_terminal_states_have_no_way_out(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $this->assertSame($status->isTerminal(), $status->allowedTransitions() === [], $status->value);
        }
    }
}
