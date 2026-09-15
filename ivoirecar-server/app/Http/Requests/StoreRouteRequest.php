<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'required|uuid|exists:companies,id',
            'departure_station_id' => 'required|uuid|exists:stations,id|different:arrival_station_id',
            'arrival_station_id' => 'required|uuid|exists:stations,id',
            'name' => 'nullable|string|max:255',
            'distance_km' => 'nullable|numeric|min:0',
            'estimated_duration_minutes' => 'nullable|integer|min:0',
            'price' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }
}
