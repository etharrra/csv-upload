<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PresignedUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:52428800'],
            'type' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $extension = strtolower(pathinfo((string) $this->input('name'), PATHINFO_EXTENSION));

            if (! in_array($extension, ['csv'], true)) {
                $validator->errors()->add('name', 'The file must be a CSV file.');
            }
        });
    }
}
