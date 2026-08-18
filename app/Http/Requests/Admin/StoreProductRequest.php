<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->is_admin;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'features_list' => ['nullable', 'string'],
            'features' => ['nullable', 'array'],
            'price' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'mimes:jpg,jpeg,png,gif,bmp,webp,svg,avif,tiff,tif,ico,heic,heif', 'max:5120'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'is_featured' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
