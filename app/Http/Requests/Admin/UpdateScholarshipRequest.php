<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScholarshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->is_admin;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('scholarships', 'slug')->ignore($this->route('scholarship'))],
            'country' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
            'apply_url' => ['nullable', 'url', 'max:500'],
            'image' => ['nullable', 'mimes:jpg,jpeg,png,gif,bmp,webp,svg,avif,tiff,tif,ico,heic,heif', 'max:5120'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
