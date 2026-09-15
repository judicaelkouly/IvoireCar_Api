<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'required|uuid|exists:companies,id',
            'station_id' => 'nullable|uuid|exists:stations,id',
            'registration_number' => 'required|string|max:50|unique:vehicles,registration_number',
            'model' => 'nullable|string|max:255',
            'total_seats' => 'required|integer|min:1',
            'status' => 'nullable|in:available,in_transit,maintenance,out_of_service',
            'is_active' => 'nullable|boolean',
        ];
    }
}
