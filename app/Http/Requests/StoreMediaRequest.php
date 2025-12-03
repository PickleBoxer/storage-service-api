<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by auth:sanctum middleware
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi',
                'max:102400', // 100MB max
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please upload a file.',
            'file.mimes' => 'File must be an image (jpg, png, gif, webp) or video (mp4, mov, avi).',
            'file.max' => 'File size must not exceed 100MB.',
            'title.required' => 'Title is required.',
            'title.max' => 'Title must not exceed 255 characters.',
        ];
    }
}
