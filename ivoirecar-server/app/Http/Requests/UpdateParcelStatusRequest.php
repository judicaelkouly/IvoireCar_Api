<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParcelStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,in_transit,arrived,delivered,cancelled',
            'payment_status' => 'nullable|in:pending,completed,failed,refunded',
            'trip_id' => 'nullable|uuid|exists:trips,id',
        ];
    }
}
