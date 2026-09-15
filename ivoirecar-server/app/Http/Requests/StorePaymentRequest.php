<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'paymentable_type' => 'required|string|in:App\Models\Ticket,App\Models\Parcel',
            'paymentable_id' => 'required|uuid',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,mobile_money,card',
            'provider' => 'nullable|string|max:50', // Ex: Wave, Orange Money, MTN, Moov, Visa
            'transaction_reference' => 'nullable|string|max:100|unique:payments,transaction_reference',
        ];
    }
}
