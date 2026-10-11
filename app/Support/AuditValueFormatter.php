<?php

namespace App\Support;

use App\Enums\CustomerStatus;
use App\Enums\DiscountType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns raw audit values into what a person reads: "Priority: Normal → High",
 * "Assigned to: John → David". The raw values stay in the database untouched.
 *
 * Names behind ids are loaded once per page (one query each for users and customers),
 * not once per row.
 */
class AuditValueFormatter
{
    private const MONEY = ['subtotal', 'discount_total', 'tax_total', 'grand_total', 'price'];

    private const USER_IDS = ['assigned_to', 'created_by'];

    private const LABELS = [
        'assigned_to' => 'Assigned to',
        'created_by' => 'Created by',
        'customer_id' => 'Customer',
        'is_active' => 'Status',
        'tax_rate_bps' => 'Tax rate',
        'stock_quantity' => 'Stock',
        'sku' => 'SKU',
    ];

    /**
     * @param  array<int, string>  $userNames
     * @param  array<int, string>  $customerNames
     */
    private function __construct(private readonly array $userNames, private readonly array $customerNames) {}

    /**
     * @param  Collection<int, AuditLog>  $logs
     */
    public static function for(Collection $logs): self
    {
        $ids = fn (array $fields) => $logs
            ->flatMap(fn (AuditLog $log) => [
                ...array_values(array_intersect_key($log->old_values ?? [], array_flip($fields))),
                ...array_values(array_intersect_key($log->new_values ?? [], array_flip($fields))),
            ])
            ->filter()
            ->unique()
            ->values();

        $userIds = $ids(self::USER_IDS);
        $customerIds = $ids(['customer_id']);

        return new self(
            $userIds->isEmpty() ? [] : User::withTrashed()->whereIn('id', $userIds)->pluck('name', 'id')->all(),
            $customerIds->isEmpty() ? [] : Customer::withTrashed()->whereIn('id', $customerIds)->pluck('name', 'id')->all(),
        );
    }

    /**
     * @return list<array{field: string, label: string, old: ?string, new: ?string}>
     */
    public function changes(AuditLog $log): array
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];
        $type = $log->subjectType();

        if ($type === 'role' && array_key_exists('permissions', $old + $new)) {
            return $this->permissionChanges($old['permissions'] ?? [], $new['permissions'] ?? []);
        }

        return array_map(fn (string $field) => [
            'field' => $field,
            'label' => self::LABELS[$field] ?? Str::of($field)->replace('_', ' ')->ucfirst()->toString(),
            'old' => $this->display($type, $field, $old[$field] ?? null),
            'new' => $this->display($type, $field, $new[$field] ?? null),
        ], array_keys($old + $new));
    }

    private function display(string $type, string $field, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (true) {
            $field === 'status' => $this->statusLabel($type, (string) $value),
            $field === 'priority' => OrderPriority::tryFrom((int) $value)?->label() ?? (string) $value,
            $field === 'discount_type' => DiscountType::tryFrom((string) $value)?->label() ?? (string) $value,
            $field === 'is_active' => $value ? 'Active' : 'Inactive',
            $field === 'tax_rate_bps' => sprintf('%d.%02d%%', intdiv((int) $value, 100), (int) $value % 100),
            in_array($field, self::MONEY, true) => Money::format((int) $value),
            in_array($field, self::USER_IDS, true) => $this->userNames[$value] ?? "#{$value}",
            $field === 'customer_id' => $this->customerNames[$value] ?? "#{$value}",
            is_array($value) => implode(', ', $value),
            is_bool($value) => $value ? 'Yes' : 'No',
            default => (string) $value,
        };
    }

    private function statusLabel(string $type, string $value): string
    {
        $enum = match ($type) {
            'order' => OrderStatus::tryFrom($value),
            'customer' => CustomerStatus::tryFrom($value),
            'product' => ProductStatus::tryFrom($value),
            default => null,
        };

        return $enum?->label() ?? $value;
    }

    /**
     * Full before/after lists are stored; people want to see what was added and removed.
     *
     * @param  list<string>  $old
     * @param  list<string>  $new
     * @return list<array{field: string, label: string, old: ?string, new: ?string}>
     */
    private function permissionChanges(array $old, array $new): array
    {
        $rows = [];
        if ($added = array_values(array_diff($new, $old))) {
            $rows[] = ['field' => 'permissions_added', 'label' => 'Permissions added', 'old' => null, 'new' => implode(', ', $added)];
        }
        if ($removed = array_values(array_diff($old, $new))) {
            $rows[] = ['field' => 'permissions_removed', 'label' => 'Permissions removed', 'old' => implode(', ', $removed), 'new' => null];
        }

        return $rows;
    }
}
