<?php

namespace App\Http\Requests\Product;

/**
 * Same rules as create, without initial_stock: existing stock only changes through stock adjustments.
 */
class UpdateProductRequest extends StoreProductRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return collect(parent::rules())->except('initial_stock')->all();
    }
}
