<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'route_id' => 'required|uuid|exists:routes,id',
            'vehicle_id' => 'required|uuid|exists:vehicles,id',
            'driver_id' => 'nullable|uuid|exists:users,id',
            'departure_time' => 'required|date|after:now',
            'arrival_time' => 'nullable|date|after:departure_time',
            'price' => 'nullable|numeric|min:0', // Prix spécifique ou hérité de la ligne
            'status' => 'nullable|in:scheduled,in_transit,completed,cancelled',
        ];
    }
}
