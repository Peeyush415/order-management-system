<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * quantity is signed: +20 goods received, −3 damaged or a count correction.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.not_in' => 'The adjustment cannot be zero.',
            'reason.required' => 'Please give a reason for the stock adjustment.',
        ];
    }
}
