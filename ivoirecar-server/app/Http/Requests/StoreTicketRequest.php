<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trip_id' => 'required|uuid|exists:trips,id',
            'passenger_name' => 'required|string|max:255',
            'passenger_phone' => 'required|string|max:50',
            'seat_number' => 'nullable|integer|min:1',
            'pickup_station_id' => 'nullable|uuid|exists:stations,id',
            'dropoff_station_id' => 'nullable|uuid|exists:stations,id',
            'price_paid' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:cash,mobile_money,card',
        ];
    }
}
