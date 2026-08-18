<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadPaymentMethodQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->is_admin;
    }

    public function rules(): array
    {
        return [
            'qr_image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,bmp,webp,svg,avif,tiff,tif,ico,heic,heif', 'max:2048'],
        ];
    }
}