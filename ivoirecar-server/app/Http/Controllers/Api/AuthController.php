<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Inscription d'un nouveau voyageur (Client)
     */
    public function register(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'name'     => 'required|string|max:255',
            'phone'    => 'required|string|unique:users,phone',
            'email'    => 'nullable|string|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name'     => $fields['name'],
            'phone'    => $fields['phone'],
            'email'    => $fields['email'] ?? null,
            'password' => Hash::make($fields['password']),
            'role'     => 'passenger',
        ]);

        // Assignation du rôle Spatie
        $user->assignRole('passenger');

        $token = $user->createToken('ivoirecar_token')->plainTextToken;

        return response()->json([
            'message' => 'Compte créé avec succès',
            'user'    => $user->load(['roles', 'company', 'station']),
            'token'   => $token,
        ], 201);
    }

    /**
     * Connexion classique (Téléphone + Mot de passe)
     */
    public function login(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'phone'    => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('phone', $fields['phone'])->first();

        if (!$user || !Hash::check($fields['password'], $user->password)) {
            return response()->json([
                'message' => 'Numéro de téléphone ou mot de passe incorrect'
            ], 401);
        }

        $token = $user->createToken('ivoirecar_token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'user'    => $user->load(['roles', 'company', 'station']),
            'token'   => $token,
        ], 200);
    }

    /**
     * Demande d'un code OTP par SMS pour connexion rapide sans mot de passe
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        // Code OTP simulé en dev (Ex: 123456)
        $otp = "123456";

        // TODO: En Production, connecter le service SMS (Termii / Orange SMS) ici

        return response()->json([
            'message' => 'Code OTP envoyé par SMS (Utilisez 123456 en dev)',
            'phone'   => $request->phone,
        ], 200);
    }

    /**
     * Verification de l'OTP et création de Token
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'phone' => 'required|string',
            'otp'   => 'required|string',
        ]);

        // Vérification de l'OTP de dev
        if ($fields['otp'] !== '123456') {
            return response()->json(['message' => 'Code OTP invalide'], 400);
        }

        // Trouver ou créer l'utilisateur à la volée
        $user = User::firstOrCreate(
            ['phone' => $fields['phone']],
            [
                'name'     => 'Voyageur ' . substr($fields['phone'], -4),
                'password' => Hash::make($fields['phone']),
                'role'     => 'passenger',
            ]
        );

        if (!$user->hasRole('passenger')) {
            $user->assignRole('passenger');
        }

        $token = $user->createToken('ivoirecar_token')->plainTextToken;

        return response()->json([
            'message' => 'Authentification OTP réussie',
            'user'    => $user->load(['roles', 'company', 'station']),
            'token'   => $token,
        ], 200);
    }

    /**
     * Obtenir le profil de l'utilisateur connecté
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->load(['roles', 'company', 'station'])
        );
    }

    /**
     * Mise à jour des informations de profil
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users')->ignore($user->id),
            ],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profil mis à jour avec succès',
            'user'    => $user->fresh()->load(['roles', 'company', 'station']),
        ]);
    }

    /**
     * Modification du mot de passe
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        // 1. Vérification de l'ancien mot de passe
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect.'
            ], 422);
        }

        // 2. Mise à jour du nouveau mot de passe
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Mot de passe modifié avec succès.',
        ]);
    }

    /**
     * Déconnexion (Révocation du Token)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie']);
    }
}
