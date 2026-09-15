<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'route_id' => 'sometimes|uuid|exists:routes,id',
            'vehicle_id' => 'sometimes|uuid|exists:vehicles,id',
            'driver_id' => 'nullable|uuid|exists:users,id',
            'departure_time' => 'sometimes|date',
            'arrival_time' => 'nullable|date|after:departure_time',
            'price' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:scheduled,in_transit,completed,cancelled',
        ];
    }
}
