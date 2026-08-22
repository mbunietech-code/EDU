<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreToolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->is_admin;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tools,slug'],
            'description' => ['nullable', 'string'],
            'version' => ['nullable', 'string', 'max:255'],
            'license_key' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'mimes:jpg,jpeg,png,gif,bmp,webp,svg,avif,tiff,tif,ico,heic,heif', 'max:5120'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
            'tool_file' => ['nullable', 'file', 'mimes:exe,zip,msi,rar,apk', 'max:153600'],
            'tool_file_path' => ['nullable', 'string', 'max:500'],
            'tool_filename' => ['nullable', 'string', 'max:255'],
        ];
    }
}
