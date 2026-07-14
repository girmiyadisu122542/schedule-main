<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest {

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        $photoMimeTypes = 'mimes:' . implode(',', PROFILE_PHOTO_ALLOWED_EXTENSIONS);

        return [
            'bio' => ['nullable', 'string',],
            'photo' => ['nullable', 'image', $photoMimeTypes,],
            'cover_photo' => ['nullable', 'image', $photoMimeTypes,],
        ];
    }
}
