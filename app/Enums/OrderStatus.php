<?php

namespace App\Enums;

/**
 * Order lifecycle states. The transition rules (state machine) are added in the status workflow step;
 * the helpers below are the edit/delete rules that depend only on the current state.
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
