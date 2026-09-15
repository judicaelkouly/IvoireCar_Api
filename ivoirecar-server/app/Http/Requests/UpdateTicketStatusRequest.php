<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:reserved,paid,boarded,cancelled,refunded',
            'payment_status' => 'nullable|in:pending,completed,failed,refunded',
        ];
    }
}
