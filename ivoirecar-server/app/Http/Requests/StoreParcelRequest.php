<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreParcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sender_name' => 'required|string|max:255',
            'sender_phone' => 'required|string|max:50',
            'receiver_name' => 'required|string|max:255',
            'receiver_phone' => 'required|string|max:50',
            'departure_station_id' => 'required|uuid|exists:stations,id',
            'destination_station_id' => 'required|uuid|exists:stations,id|different:departure_station_id',
            'trip_id' => 'nullable|uuid|exists:trips,id',
            'weight_kg' => 'nullable|numeric|min:0.1',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'payment_method' => 'nullable|in:cash,mobile_money,card',
        ];
    }
}
