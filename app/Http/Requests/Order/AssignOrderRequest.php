<?php

namespace App\Http\Requests\Order;

use App\Rules\AssignableUser;
use Illuminate\Foundation\Http\FormRequest;

class AssignOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * assigned_to must be sent; null unassigns the order.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => ['present', 'nullable', 'integer', new AssignableUser],
        ];
    }
}
