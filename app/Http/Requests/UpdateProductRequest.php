<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'name' => 'nullable|string|max:255',
            'sku' => "nullable|string|unique:products,sku,{$productId}",
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0.01',
            'cost' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:0',
            'variants' => 'nullable|array',
            'variants.*.id' => 'nullable|integer|exists:product_variants,id',
            'variants.*.sku' => 'nullable|string',
            'variants.*.name' => 'nullable|string',
            'variants.*.attributes' => 'nullable|array',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.quantity' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }
}
