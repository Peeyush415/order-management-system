<?php

namespace App\Enums;

/**
 * Order lifecycle states and the state machine between them:
 *
 *   draft ⇄ pending → confirmed → processing → ready → completed
 *   every non-terminal state → cancelled; completed and cancelled are final.
 *
 * Who may make a transition is decided by OrderPolicy::changeStatus().
 */
enum OrderStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Processing => 'Processing',
            self::Ready => 'Ready',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending, self::Cancelled],
            self::Pending => [self::Draft, self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Processing, self::Cancelled],
            self::Processing => [self::Ready, self::Cancelled],
            self::Ready => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** Customer, items and pricing can change only before stock is deducted. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Pending], true);
    }

    /** No further changes of any kind. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** Orders that hold no deducted stock can be deleted; others must be cancelled first. */
    public function isDeletable(): bool
    {
        return in_array($this, [self::Draft, self::Pending, self::Cancelled], true);
    }
}
