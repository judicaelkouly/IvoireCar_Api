<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRouteStopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'route_id' => 'required|uuid|exists:routes,id',
            'station_id' => 'required|uuid|exists:stations,id',
            'stop_order' => 'required|integer|min:1',
            'price_from_departure' => 'nullable|numeric|min:0',
            'duration_from_departure_minutes' => 'nullable|integer|min:0',
        ];
    }
}
