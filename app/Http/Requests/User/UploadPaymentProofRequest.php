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
            'payment_proof' => ['required', 'file', 'mimes:jpeg,png,jpg,gif,webp,pdf', 'max:12288'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_proof.mimes' => 'Upload a JPG, PNG, GIF, WebP, or PDF payment proof.',
            'payment_proof.max' => 'The payment proof must not be larger than 12 MB.',
        ];
    }
}
