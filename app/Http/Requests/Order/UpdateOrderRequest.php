<?php

namespace App\Http\Requests\Order;

use App\Enums\OrderPriority;
use App\Models\Order;
use Illuminate\Validation\Rule;

/**
 * Draft/pending orders accept the same fields as create; once stock is deducted
 * (confirmed and later) only the priority may change. Status, notes and assignment
 * have their own endpoints. The version the client loaded is always required.
 */
class UpdateOrderRequest extends StoreOrderRequest
{
    /** Fields frozen once the order leaves draft/pending. */
    private const LOCKED_FIELDS = ['customer_id', 'items', 'discount_type', 'discount_value', 'order_date'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Order $order */
        $order = $this->route('order');

        if ($order->status->isEditable()) {
            $rules = collect(parent::rules())->except(['status', 'note', 'assigned_to'])->all();
        } else {
            $rules = ['priority' => ['required', Rule::enum(OrderPriority::class)]];
            foreach (self::LOCKED_FIELDS as $field) {
                $rules[$field] = ['prohibited'];
            }
        }

        return [
            ...$rules,
            'status' => ['prohibited'],
            'assigned_to' => ['prohibited'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $locked = array_fill_keys(
            array_map(fn (string $field) => "{$field}.prohibited", self::LOCKED_FIELDS),
            'Only the priority can be changed once the order is confirmed.',
        );

        return [
            ...parent::messages(),
            ...$locked,
            'status.prohibited' => 'Use the status action to change the status.',
            'assigned_to.prohibited' => 'Use the assign action to change the assignee.',
            'version.required' => 'The order version is required. Reload the order and try again.',
        ];
    }
}
