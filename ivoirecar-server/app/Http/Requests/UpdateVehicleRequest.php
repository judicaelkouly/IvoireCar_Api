<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'sometimes|uuid|exists:companies,id',
            'station_id' => 'nullable|uuid|exists:stations,id',
            'registration_number' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('vehicles', 'registration_number')->ignore($this->route('vehicle')),
            ],
            'model' => 'nullable|string|max:255',
            'total_seats' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:available,in_transit,maintenance,out_of_service',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
