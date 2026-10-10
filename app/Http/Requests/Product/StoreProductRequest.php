<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('sku'))) {
            $this->merge(['sku' => strtoupper(trim($this->input('sku')))]);
        }
    }

    /**
     * stock_quantity is never accepted: stock only changes through the ledger.
     * A new product can start with initial_stock, written as an opening ledger entry.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9-]+$/', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            // Integer cents: 12550 = 125.50.
            'price' => ['required', 'integer', 'min:1', 'max:100000000'],
            // Basis points: 1000 = 10%.
            'tax_rate_bps' => ['sometimes', 'integer', 'between:0,10000'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'initial_stock' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.unique' => 'A product with this SKU already exists.',
            'sku.regex' => 'The SKU may only contain letters, numbers and dashes.',
            'price.integer' => 'The price must be a whole number of cents.',
        ];
    }
}
