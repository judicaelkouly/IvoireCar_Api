<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => 'required|string', // Permet de se connecter soit par email soit par téléphone
            'password' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Veuillez saisir votre adresse e-mail ou numéro de téléphone.',
            'password.required' => 'Veuillez saisir votre mot de passe.',
        ];
    }
}
