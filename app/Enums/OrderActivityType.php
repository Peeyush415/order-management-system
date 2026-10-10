<?php

namespace App\Enums;

enum OrderActivityType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case StatusChanged = 'status_changed';
    case Assigned = 'assigned';
    case NoteAdded = 'note_added';
    case StockDeducted = 'stock_deducted';
    case Restocked = 'restocked';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::StatusChanged => 'Status changed',
            self::Assigned => 'Assigned',
            self::NoteAdded => 'Note added',
            self::StockDeducted => 'Stock deducted',
            self::Restocked => 'Restocked',
        };
    }
}
