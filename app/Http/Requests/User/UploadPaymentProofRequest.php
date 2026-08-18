<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', Rule::exists('payment_methods', 'code')],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_proof' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
