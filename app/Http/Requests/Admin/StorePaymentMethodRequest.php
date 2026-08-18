<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->is_admin;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', Rule::unique('payment_methods', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'enabled' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'link_url' => ['nullable', 'string', 'max:1000'],
            'store_url' => ['nullable', 'url', 'max:1000'],
        ];
    }
}
