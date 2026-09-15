<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('companies', 'name')->ignore($this->route('company')),
            ],
            'phone' => 'sometimes|string|max:20',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
