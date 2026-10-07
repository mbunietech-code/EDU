<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['auto_renew' => $this->boolean('auto_renew')]);
    }

    public function rules(): array
    {
        return \App\Models\Account::planRules() + [
            'product_id' => ['required', 'exists:products,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'credentials' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['available', 'assigned', 'suspended', 'expired', 'maintenance', 'archived'])],
        ];
    }
}
