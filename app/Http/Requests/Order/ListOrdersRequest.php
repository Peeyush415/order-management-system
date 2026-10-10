<?php

namespace App\Http\Requests\Order;

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Filters\OrderFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string: ?search=&status[]=pending&priority[]=4&customer_id=&assigned_to=
 * &date_from=2026-01-01&date_to=2026-01-31&sort=grand_total&direction=asc&page=2&per_page=25
 */
class ListOrdersRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(OrderStatus::class)],
            'priority' => ['nullable', 'array'],
            'priority.*' => ['integer', Rule::in(array_map(fn (OrderPriority $p) => $p->value, OrderPriority::cases()))],
            'customer_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(OrderFilter::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
