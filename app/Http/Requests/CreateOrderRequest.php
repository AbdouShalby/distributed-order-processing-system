<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // No auth in this project
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.min' => 'Order must contain at least one item.',
            'items.*.product_id.exists' => 'Product :input does not exist.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'idempotency_key.required' => 'Idempotency key is required for safe order creation.',
        ];
    }
}
