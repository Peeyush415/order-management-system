<?php

namespace App\Http\Requests\Order;

use App\Enums\DiscountType;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Rules\AssignableUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Only what the user chooses. Prices, totals and order numbers are never accepted:
 * fields like grand_total or unit_price are simply not in the rules, so validated() drops them.
 */
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('status', 'active')->whereNull('deleted_at')],
            // A new order is saved as a draft or submitted as pending; later states come from the workflow.
            'status' => ['sometimes', Rule::in([OrderStatus::Draft->value, OrderStatus::Pending->value])],
            'priority' => ['sometimes', Rule::enum(OrderPriority::class)],
            'order_date' => ['sometimes', 'date_format:Y-m-d'],
            'assigned_to' => ['nullable', 'integer', new AssignableUser],
            'discount_type' => ['nullable', Rule::enum(DiscountType::class)],
            'discount_value' => [
                'nullable', 'required_with:discount_type', 'integer', 'min:0',
                // Percent discounts are basis points: 10000 = 100%.
                Rule::when($this->input('discount_type') === DiscountType::Percent->value, ['max:10000']),
            ],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('status', 'active')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer is required.',
            'customer_id.exists' => 'Customer must exist and be active.',
            'items.required' => 'Order must contain at least one product.',
            'items.min' => 'Order must contain at least one product.',
            'items.*.product_id.required' => 'Product is required.',
            'items.*.product_id.exists' => 'Product must exist and be active.',
            'items.*.product_id.distinct' => 'Each product can only be added once.',
            'items.*.quantity.required' => 'Quantity must be greater than 0.',
            'items.*.quantity.integer' => 'Quantity must be greater than 0.',
            'items.*.quantity.min' => 'Quantity must be greater than 0.',
            'discount_value.max' => 'A percentage discount cannot exceed 100%.',
        ];
    }
}
